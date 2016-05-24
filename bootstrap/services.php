<?php

use Aerys\Request;
use Aerys\Response;

require __DIR__ . '/smsServices.php';

class View
{
    protected $templateDir;

    public function __construct($template_dir) {
        $this->templateDir = $template_dir;
    }

    public function render(Response $res, $filename, $data = []) {
        extract($data, EXTR_SKIP);
        ob_start();
        require $this->templateDir . '/' . $filename;
        return $res->end(ob_get_clean());
    }

    public function renderNotFound(Response $res) {
        return $this->render($res->setStatus(404), 'not_found.php');
    }
}

class RaffleService {
    protected $db;
    protected $sms;
    protected $phoneNumber;

    public function __construct(\Amp\Mysql\Pool $db, SMS $sms, $phone_number) {
        $this->db = $db;
        $this->sms = $sms;
        $this->phoneNumber = $phone_number;
    }

    public function create($name, array $items) {
        $sid = uniqid();
        /** @var \Amp\Mysql\Connection $conn */
        $conn = yield $this->db->getConnection(); // need a stateful connection for this call
        yield $conn->prepare('INSERT INTO raffle (raffle_name, sid) VALUES(?, ?)', [$name, $sid]);
        $id = $conn->getConnInfo()->insertId;
        /** @var \Amp\Mysql\Stmt $itemsStmt */
        $itemsStmt = yield $conn->prepare('INSERT INTO raffle_item (raffle_id, item) VALUES(?, ?)');
        foreach ($items as $item)
            yield $itemsStmt->execute([$id, $item]);
        return $id;
    }

    public function getEntrantCount($id) {
        /** @var \Amp\Mysql\Stmt $stmt */
        $stmt = yield $this->db->prepare('SELECT COUNT(*) FROM entrant WHERE raffle_id = ?');
        /** @var \Amp\Mysql\ResultSet $resultSet */
        $resultSet = yield $stmt->execute([$id]);
        $row = yield $resultSet->fetchRow();
        return $row[0];
    }

    public function getPhoneNumber($id) {
        return $this->phoneNumber;
    }

    public function getCode($id) {
        return $id;
    }

    public function isComplete($id) {
        /** @var \Amp\Mysql\ResultSet $resultSet */
        $resultSet = yield $this->db->prepare('SELECT COUNT(*) FROM raffle WHERE is_complete = 1 && id = ?', [$id]);
        $row = yield $resultSet->fetchRow();
        return $row[0] > 0;
    }

    public function getName($id) {
        /** @var \Amp\Mysql\ResultSet $resultSet */
        $resultSet = yield $this->db->prepare('SELECT raffle_name FROM raffle WHERE id = ?', [$id]);
        $row = yield $resultSet->fetchRow();
        return $row[0];
    }

    public function getNameByCode($code) {
        return $this->getName($code);
    }

    public function getSid($id) {
        /** @var \Amp\Mysql\ResultSet $resultSet */
        $resultSet = yield $this->db->prepare('SELECT sid FROM raffle WHERE id = ?', [$id]);
        $row = yield $resultSet->fetchRow();
        return $row[0];
    }

    public function getEntrantPhoneNumbers($id) {
        /** @var \Amp\Mysql\ResultSet $resultSet */
        $resultSet = yield $this->db->prepare('SELECT phone_number FROM entrant WHERE raffle_id = ?', [$id]);
        $rows = yield $resultSet->fetchRows();
        return array_column($rows, 0);
    }

    public function complete($id) {
        yield $this->db->prepare('UPDATE raffle SET is_complete = 1 WHERE id = ?', [$id]);

        /** @var \Amp\Mysql\ResultSet[] $resultSets */
        $resultSets = yield Amp\all([
            $this->db->prepare('SELECT item FROM raffle_item WHERE raffle_id = ? ORDER BY id ASC', [$id]),
            $this->db->prepare('SELECT phone_number FROM entrant WHERE raffle_id = ?', [$id])
        ]);

        $rowSets = yield Amp\all([
            $resultSets[0]->fetchRows(),
            $resultSets[1]->fetchRows()
        ]);

        $items = array_column($rowSets[0], 0);
        $entrants = array_column($rowSets[1], 0);

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

    public function recordEntry($code, $phone_number) {
        if (!(yield from $this->raffleExists($code)))
            return false;

        /** @var \Amp\Mysql\ResultSet $entrantCtRs */
        $entrantCtRs = yield $this->db->prepare('SELECT COUNT(*) FROM entrant WHERE raffle_id = ? && phone_number = ?',
            [$code, $phone_number]);
        $entrantCtRow = yield $entrantCtRs->fetchRow();

        if ($entrantCtRow[0]) {
            return false;
        }

        yield $this->db->prepare('INSERT INTO entrant (raffle_id, phone_number) VALUES (?, ?)', [$code, $phone_number]);
        return true;
    }

    public function raffleExists($id) {
        /** @var \Amp\Mysql\ResultSet $existenceRs */
        $existenceRs = yield $this->db->prepare('SELECT COUNT(*) FROM raffle WHERE id = ?', [$id]);
        return (yield $existenceRs->fetchRow()) > 0;
    }
}

class Auth
{
    protected $rs;

    public function __construct(RaffleService $rs) {
        $this->rs = $rs;
    }

    public function isAuthorized(Request $req, $id) {
        $sid = yield from $this->rs->getSid($id);
        return $sid === $req->getCookie('sid' . $id) || $sid === $req->getParam('sid');
    }
}

return function(\Pimple\Container $container, $env) {
    $container['view'] = function() {return new View(__DIR__ . '/../templates');};
    $container['raffleService'] = function($c) use ($env) {
        return new RaffleService(new \Amp\Mysql\Pool('host=' . $env['DB_HOST'] . ';db=' . $env['DB_NAME'] .
            ";user=" . $env['DB_USER'] . ";pass=" . $env['DB_PASSWORD']), $c['sms'], $env['PHONE_NUMBER']);
    };
    $container['auth'] = function(\Pimple\Container $c) {return new Auth($c['raffleService']);};

    $container['sms'] = function() use ($env) {
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
