<?php

use Pimple\Container;
use Slim\Http\ServerRequest as Request;
use Slim\Http\Response;

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
        return $res->write(ob_get_clean());
    }

    public function renderNotFound(Response $res) {
        return $this->render($res->withStatus(404), 'not_found.php');
    }
}

class RaffleService {
    protected $db;
    protected $sms;
    protected $phoneNumber;

    public function __construct(\Aura\Sql\ExtendedPdoInterface $db, SMS $sms, $phone_number) {
        $this->db = $db;
        $this->sms = $sms;
        $this->phoneNumber = $phone_number;
    }

    public function create($name, array $items) {
        $sid = uniqid();
        $this->db->perform('INSERT INTO raffle (raffle_name, sid) VALUES(?, ?)', [$name, $sid]);
        $id = $this->db->lastInsertId();
        foreach ($items as $item)
            $this->db->perform('INSERT INTO raffle_item (raffle_id, item) VALUES(?, ?)', [$id, $item]);
        return $id;
    }

    public function getEntrantCount($id) {
        return $this->db->fetchValue('SELECT COUNT(*) FROM entrant WHERE raffle_id = ?', [$id]);
    }

    public function getPhoneNumber($id) {
        return $this->phoneNumber;
    }

    public function getCode($id) {
        return $id;
    }

    public function isComplete($id) {
        return $this->db->fetchValue('SELECT COUNT(*) FROM raffle WHERE is_complete = 1 && id = ?', [$id]);
    }

    public function getName($id) {
        return $this->db->fetchValue('SELECT raffle_name FROM raffle WHERE id = ?', [$id]);
    }

    public function getNameByCode($code) {
        return $this->getName($code);
    }

    public function getSid($id) {
        return $this->db->fetchValue('SELECT sid FROM raffle WHERE id = ?', [$id]);
    }

    public function getEntrantPhoneNumbers($id) {
        return $this->db->fetchCol('SELECT phone_number FROM entrant WHERE raffle_id = ?', [$id]);
    }

    public function complete($id) {
        $this->db->perform('UPDATE raffle SET is_complete = 1 WHERE id = ?', [$id]);

        $items = $this->db->fetchCol('SELECT item FROM raffle_item WHERE raffle_id = ? ORDER BY id ASC', [$id]);
        $entrants = $this->db->fetchCol('SELECT phone_number FROM entrant WHERE raffle_id = ?', [$id]);

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
        if (!$this->raffleExists($code))
            return false;
        if ($this->db->fetchValue('SELECT COUNT(*) FROM entrant WHERE raffle_id = ? && phone_number = ?',
            [$code, $phone_number]))
            return false;

        $this->db->perform('INSERT INTO entrant (raffle_id, phone_number) VALUES (?, ?)', [$code, $phone_number]);
        return true;
    }

    public function raffleExists($id) {
        return $this->db->fetchValue('SELECT COUNT(*) FROM raffle WHERE id = ?', [$id]) > 0;
    }
}

class Auth
{
    protected $rs;

    public function __construct(RaffleService $rs) {
        $this->rs = $rs;
    }

    public function isAuthorized(Request $req, $id) {
        $sid = $this->rs->getSid($id);
        return $sid === $req->getCookieParams()['sid' . $id] || $sid === $req->getQueryParams()['sid'];
    }
}

return function(Container $container, $env) {
    $container['view'] = function() {return new View(__DIR__ . '/../templates');};
    $container['raffleService'] = function($c) use ($env) {return new RaffleService(
        new \Aura\Sql\ExtendedPdo('mysql:host=' . $env['DB_HOST'] . ';dbname=' . $env['DB_NAME'],
            $env['DB_USER'], $env['DB_PASSWORD']), $c['sms'], $env['PHONE_NUMBER']
    );};
    $container['auth'] = function($c) {return new Auth($c['raffleService']);};

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

    $container['addCookieToResponse'] = $container->protect(function(Response $res, $name, $properties) {
        if (is_string($properties)) {
            $properties = ['value' => $properties];
        }

        $result = urlencode($name) . '=' . urlencode($properties['value']);

        if (isset($properties['domain'])) {
            $result .= '; domain=' . $properties['domain'];
        }

        if (isset($properties['path'])) {
            $result .= '; path=' . $properties['path'];
        }

        if (isset($properties['expires'])) {
            if (is_string($properties['expires'])) {
                $timestamp = strtotime($properties['expires']);
            } else {
                $timestamp = (int)$properties['expires'];
            }
            if ($timestamp !== 0) {
                $result .= '; expires=' . gmdate('D, d-M-Y H:i:s e', $timestamp);
            }
        }

        if (isset($properties['secure']) && $properties['secure']) {
            $result .= '; secure';
        }

        if (isset($properties['httponly']) && $properties['httponly']) {
            $result .= '; HttpOnly';
        }

        return $res->withAddedHeader('Set-Cookie', $result);
    });
};
