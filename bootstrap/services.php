<?php

use Amp\Http\Server\Response;
use Amp\Mysql\ResultSet;

require __DIR__ . '/smsServices.php';

class View
{
    protected $templateDir;

    public function __construct($template_dir)
    {
        $this->templateDir = $template_dir;
    }

    public function render(Response $res, $filename, $data = [])
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require $this->templateDir . '/' . $filename;
        $res->setBody(ob_get_clean());
        return $res;
    }

    public function renderNotFound(Response $res)
    {
        $res->setStatus(404);
        return $this->render($res, 'not_found.php');
    }
}

class RaffleService
{
    protected $db;
    protected $sms;
    protected $phoneNumber;

    public function __construct(\Amp\Mysql\Pool $db, SMS $sms, $phone_number)
    {
        $this->db = $db;
        $this->sms = $sms;
        $this->phoneNumber = $phone_number;
    }

    /**
     * @param $name
     * @param array $items
     * @return Generator
     * @throws \Amp\Mysql\ConnectionException
     * @throws \Amp\Mysql\FailureException
     */
    public function create($name, array $items)
    {
        $sid = uniqid();

        /** @var \Amp\Mysql\Connection $conn */
        $conn = $this->db->extractConnection();

        yield $conn->execute('INSERT INTO raffle (raffle_name, sid) VALUES(?, ?)', [$name, $sid]);

        $id = $conn->getConnInfo()->insertId;

        $conn->close();

        /** @var \Amp\Mysql\Statement $itemsStmt */
        $itemsStmt = yield $this->db->prepare('INSERT INTO raffle_item (raffle_id, item) VALUES(?, ?)');

        foreach ($items as $item) { // queue up inserts, but we don't care when they happen
            $itemsStmt->execute([$id, $item]);
        }

        return $id;
    }

    /**
     * @param $id
     * @return mixed
     * @throws \Amp\Mysql\ConnectionException
     * @throws \Amp\Mysql\FailureException
     * @throws Throwable
     */
    public function getEntrantCount($id)
    {
        /** @var ResultSet $resultSet */
        $resultSet = yield $this->db->execute('SELECT COUNT(*) FROM entrant WHERE raffle_id = ?', [$id]);
        yield $resultSet->advance(ResultSet::FETCH_ARRAY);
        return $resultSet->getCurrent()[0];
    }

    public function getPhoneNumber($id)
    {
        return $this->phoneNumber;
    }

    public function getCode($id)
    {
        return $id;
    }

    /**
     * @param $id
     * @return bool
     * @throws \Amp\Mysql\ConnectionException
     * @throws \Amp\Mysql\FailureException
     * @throws Throwable
     */
    public function isComplete($id)
    {
        /** @var ResultSet $resultSet */
        $resultSet = yield $this->db->execute('SELECT COUNT(*) FROM raffle WHERE is_complete = 1 && id = ?', [$id]);
        yield $resultSet->advance(ResultSet::FETCH_ARRAY);
        return $resultSet->getCurrent()[0];
    }

    /**
     * @param $id
     * @return Generator
     * @throws Throwable
     * @throws \Amp\Mysql\ConnectionException
     * @throws \Amp\Mysql\FailureException
     */
    public function getName($id)
    {
        /** @var ResultSet $resultSet */
        $resultSet = yield $this->db->execute('SELECT raffle_name FROM raffle WHERE id = ?', [$id]);
        yield $resultSet->advance(ResultSet::FETCH_ARRAY);
        return $resultSet->getCurrent()[0];
    }

    /**
     * @param $code
     * @return Generator
     * @throws Throwable
     * @throws \Amp\Mysql\ConnectionException
     * @throws \Amp\Mysql\FailureException
     */
    public function getNameByCode($code)
    {
        return $this->getName($code);
    }

    /**
     * @param $id
     * @return mixed
     * @throws \Amp\Mysql\ConnectionException
     * @throws \Amp\Mysql\FailureException
     * @throws Throwable
     */
    public function getSid($id)
    {
        /** @var ResultSet $resultSet */
        $resultSet = yield $this->db->execute('SELECT sid FROM raffle WHERE id = ?', [$id]);

        return yield $resultSet->advance(ResultSet::FETCH_ARRAY) ? $resultSet->getCurrent()[0] : null;
    }

    /**
     * @param $id
     * @return array
     * @throws \Amp\Mysql\ConnectionException
     * @throws \Amp\Mysql\FailureException
     * @throws Throwable
     */
    public function getEntrantPhoneNumbers($id)
    {
        /** @var ResultSet $resultSet */
        $resultSet = yield $this->db->execute('SELECT phone_number FROM entrant WHERE raffle_id = ?', [$id]);
        $phoneNumbers = [];
        while (yield $resultSet->advance(ResultSet::FETCH_ARRAY)) {
            $phoneNumbers[] = $resultSet->getCurrent()[0];
        }

        return $phoneNumbers;
    }

    /**
     * @param $id
     * @return array|Generator
     * @throws \Amp\Mysql\ConnectionException
     * @throws \Amp\Mysql\FailureException
     * @throws Throwable
     */
    public function complete($id)
    {
        yield $this->db->execute('UPDATE raffle SET is_complete = 1 WHERE id = ?', [$id]);

        /** @var ResultSet $resultSets */
        $resultSets = yield $this->db->execute('SELECT item FROM raffle_item WHERE raffle_id = ? ORDER BY id ASC;'.
                'SELECT phone_number FROM entrant WHERE raffle_id = ?;', [$id, $id]);

        $items = [];
        while (yield $resultSets->advance(ResultSet::FETCH_ARRAY)) {
            $items[] = $resultSets->getCurrent()[0];
        }

        yield $resultSets->nextResultSet();
        $entrants = [];
        while (yield $resultSets->advance(ResultSet::FETCH_ARRAY)) {
            $entrants[] = $resultSets->getCurrent()[0];
        }

        shuffle($entrants);

        $winnerNumbers = [];

        foreach ($entrants as $k => $phone_number) {
            if (isset($items[$k])) {
                $message = 'You won! Your prize: ' . $items[$k];
                $winnerNumbers[] = $phone_number;
            } else {
                $message = 'Sorry, you didn\'t win this time. Maybe next time!';
            }

            $this->sms->send($phone_number, $message);
        }

        return $winnerNumbers;
    }

    /**
     * @param $code
     * @param $phone_number
     * @return bool|Generator
     * @throws \Amp\Mysql\ConnectionException
     * @throws \Amp\Mysql\FailureException
     * @throws Throwable
     */
    public function recordEntry($code, $phone_number)
    {
        if (!(yield from $this->raffleExists($code))) {
            return false;
        }

        /** @var ResultSet $entrantCtRs */
        $entrantCtRs = yield $this->db->execute('SELECT COUNT(*) FROM entrant WHERE raffle_id = ? && phone_number = ?',
            [$code, $phone_number]);
        yield $entrantCtRs->advance(ResultSet::FETCH_ARRAY);
        if ($entrantCtRs->getCurrent()[0]) {
            return false;
        }

        $this->db->execute('INSERT INTO entrant (raffle_id, phone_number) VALUES (?, ?)', [$code, $phone_number]);
        return true;
    }

    /**
     * @param $id
     * @return bool
     * @throws \Amp\Mysql\ConnectionException
     * @throws \Amp\Mysql\FailureException
     * @throws Throwable
     */
    public function raffleExists($id)
    {
        /** @var ResultSet $existenceRs */
        $existenceRs = yield $this->db->execute('SELECT COUNT(*) FROM raffle WHERE id = ?', [$id]);
        yield $existenceRs->advance(ResultSet::FETCH_ARRAY);
        return $existenceRs->getCurrent()[0] > 0;
    }
}

class Auth
{
    protected $rs;

    public function __construct(RaffleService $rs)
    {
        $this->rs = $rs;
    }

    /**
     * @param \Amp\Http\Server\Request $req
     * @param $id
     * @return bool
     * @throws Throwable
     * @throws \Amp\Mysql\ConnectionException
     * @throws \Amp\Mysql\FailureException
     */
    public function isAuthorized(\Amp\Http\Server\Request $req, $id)
    {
        $sid = yield from $this->rs->getSid($id);
        parse_str($req->getUri()->getQuery(), $query);
        return $sid === $req->getCookie('sid' . $id) || $sid === ($query['sid'] ?? false);
    }
}

return function (\Pimple\Container $container, $env) {
    $container['view'] = function () {
        return new View(__DIR__ . '/../templates');
    };
    $container['raffleService'] = function ($c) use ($env) {
        return new RaffleService(Amp\Mysql\pool('host=' . $env['DB_HOST'] . ';db=' . $env['DB_NAME'] .
            ";user=" . $env['DB_USER'] . ";pass=" . $env['DB_PASSWORD']), $c['sms'], $env['PHONE_NUMBER']);
    };
    $container['auth'] = function (\Pimple\Container $c) {
        return new Auth($c['raffleService']);
    };

    $container['sms'] = function () use ($env) {
        if (isset($env['TWILIO_SID'])) {
            return new TwilioSMS($env['TWILIO_SID'], $env['TWILIO_TOKEN'], $env['PHONE_NUMBER']);
        }
        if (isset($env['NEXMO_KEY'])) {
            return new NexmoSMS($env['NEXMO_KEY'], $env['NEXMO_SECRET'], $env['PHONE_NUMBER']);
        }
        if (isset($env['DUMMY_SMS_WAIT_MS'])) {
            return new DummySMS($env['DUMMY_SMS_WAIT_MS']);
        }
        throw new InvalidArgumentException('Could not find SMS service creds, and a dummy timeout was not supplied.');
    };

    return $container;
};
