<?php

use Amp\Http\Server\Response;
use Amp\Mysql\ResultSet;
use Amp\Mysql\ConnectionConfig;

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
    protected $prettyPhoneNumber;

    public function __construct(\Amp\Mysql\Pool $db, SMS $sms, ?string $phoneNumber, ?string $prettyPhoneNumber)
    {
        $this->db = $db;
        $this->sms = $sms;
        $this->phoneNumber = $phoneNumber;
        $this->prettyPhoneNumber = $prettyPhoneNumber;
    }

    /**
     * @param $name
     * @param array $items
     * @return Generator
     * @throws \Amp\Sql\ConnectionException
     * @throws \Amp\Sql\FailureException
     */
    public function create($name, array $items)
    {
        $sid = uniqid();

        /** @var \Amp\Mysql\Connection $conn */
        $conn = yield $this->db->extractConnection();

        /** @var \Amp\Mysql\Statement $statement */
        $statement = yield $conn->prepare('INSERT INTO raffle (raffle_name, sid) VALUES(?, ?)');
        /** @var \Amp\Mysql\CommandResult $result */
        $result = yield $statement->execute([$name, $sid]);

        $id = $result->getLastInsertId();

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
     * @throws \Amp\Sql\ConnectionException
     * @throws \Amp\Sql\FailureException
     * @throws Throwable
     */
    public function getEntrantCount($id)
    {
        /** @var ResultSet $resultSet */
        $resultSet = yield $this->db->execute('SELECT COUNT(*) ct FROM entrant WHERE raffle_id = ?', [$id]);
        yield $resultSet->advance();
        return $resultSet->getCurrent()['ct'];
    }

    public function getPhoneNumber($id)
    {
        return $this->phoneNumber;
    }

    public function getPrettyPhoneNumber($id)
    {
        return $this->prettyPhoneNumber;
    }

    public function getCode($id)
    {
        return $id;
    }

    /**
     * @param $id
     * @return bool
     * @throws \Amp\Sql\ConnectionException
     * @throws \Amp\Sql\FailureException
     * @throws Throwable
     */
    public function isComplete($id)
    {
        /** @var ResultSet $resultSet */
        $resultSet = yield $this->db->execute('SELECT COUNT(*) ct FROM raffle WHERE is_complete = 1 && id = ?', [$id]);
        yield $resultSet->advance();
        return $resultSet->getCurrent()['ct'];
    }

    /**
     * @param $id
     * @return Generator
     * @throws Throwable
     * @throws \Amp\Sql\ConnectionException
     * @throws \Amp\Sql\FailureException
     */
    public function getName($id)
    {
        /** @var ResultSet $resultSet */
        $resultSet = yield $this->db->execute('SELECT raffle_name FROM raffle WHERE id = ?', [$id]);
        yield $resultSet->advance();
        return $resultSet->getCurrent()['raffle_name'];
    }

    /**
     * @param $code
     * @return Generator
     * @throws Throwable
     * @throws \Amp\Sql\ConnectionException
     * @throws \Amp\Sql\FailureException
     */
    public function getNameByCode($code)
    {
        return $this->getName($code);
    }

    /**
     * @param $id
     * @return mixed
     * @throws \Amp\Sql\ConnectionException
     * @throws \Amp\Sql\FailureException
     * @throws Throwable
     */
    public function getSid($id)
    {
        /** @var ResultSet $resultSet */
        $resultSet = yield $this->db->execute('SELECT sid FROM raffle WHERE id = ?', [$id]);
        $exists = yield $resultSet->advance();
        return $exists ? $resultSet->getCurrent()['sid'] : null;
    }

    /**
     * @param $id
     * @return array
     * @throws \Amp\Sql\ConnectionException
     * @throws \Amp\Sql\FailureException
     * @throws Throwable
     */
    public function getEntrantPhoneNumbers($id)
    {
        /** @var ResultSet $resultSet */
        $resultSet = yield $this->db->execute('SELECT phone_number FROM entrant WHERE raffle_id = ?', [$id]);
        $phoneNumbers = [];
        while (yield $resultSet->advance()) {
            $phoneNumbers[] = $resultSet->getCurrent()['phone_number'];
        }

        return $phoneNumbers;
    }

    /**
     * @param $id
     * @return array|Generator
     * @throws \Amp\Sql\ConnectionException
     * @throws \Amp\Sql\FailureException
     * @throws Throwable
     */
    public function complete($id)
    {
        yield $this->db->execute('UPDATE raffle SET is_complete = 1 WHERE id = ?', [$id]);

        /** @var ResultSet[] $resultSets */
        $resultSets = yield [
            $this->db->execute('SELECT item FROM raffle_item WHERE raffle_id = ? ORDER BY id ASC', [$id]),
            $this->db->execute('SELECT phone_number FROM entrant WHERE raffle_id = ?', [$id])
        ];

        $items = [];
        while (yield $resultSets[0]->advance()) {
            $items[] = $resultSets[0]->getCurrent()['item'];
        }

        $entrants = [];
        while (yield $resultSets[1]->advance()) {
            $entrants[] = $resultSets[1]->getCurrent()['phone_mumber'];
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
     * @throws \Amp\Sql\ConnectionException
     * @throws \Amp\Sql\FailureException
     * @throws Throwable
     */
    public function recordEntry($code, $phone_number)
    {
        if (!(yield from $this->raffleExists($code))) {
            return false;
        }

        /** @var ResultSet $entrants */
        $entrants = yield $this->db->execute('SELECT COUNT(*) ct FROM entrant WHERE raffle_id = ? && phone_number = ?',
            [$code, $phone_number]);
        yield $entrants->advance();
        if ($entrants->getCurrent()['ct']) {
            return false;
        }

        $this->db->execute('INSERT INTO entrant (raffle_id, phone_number) VALUES (?, ?)', [$code, $phone_number]);
        return true;
    }

    /**
     * @param $id
     * @return bool
     * @throws \Amp\Sql\ConnectionException
     * @throws \Amp\Sql\FailureException
     * @throws Throwable
     */
    public function raffleExists($id)
    {
        /** @var ResultSet $existenceRs */
        $existenceRs = yield $this->db->execute('SELECT COUNT(*) ct FROM raffle WHERE id = ?', [$id]);
        yield $existenceRs->advance();
        return $existenceRs->getCurrent()['ct'] > 0;
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
     * @throws \Amp\Sql\ConnectionException
     * @throws \Amp\Sql\FailureException
     */
    public function isAuthorized(\Amp\Http\Server\Request $req, $id)
    {
        $sid = yield from $this->rs->getSid($id);
        parse_str($req->getUri()->getQuery(), $query);
        if (($cookie = $req->getCookie('sid' . $id)) && $sid === $cookie->getValue()) {
            return true;
        }

        return $sid === ($query['sid'] ?? false);
    }
}

return function (\Pimple\Container $container, $env) {
    $container['view'] = function () {
        return new View(__DIR__ . '/../templates');
    };
    $container['raffleService'] = function ($c) use ($env) {
        return new RaffleService(Amp\Mysql\pool(new ConnectionConfig(
            $env['DB_HOST'], ConnectionConfig::DEFAULT_PORT, $env['DB_USER'], $env['DB_PASSWORD'], $env['DB_NAME']
        )), $c['sms'], $env['PHONE_NUMBER'], $env['PRETTY_PHONE_NUMBER'] ?? $env['PHONE_NUMBER']);
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
