<?php

use Slim\Http\Request;
use Slim\Http\Response;

require __DIR__ . '/../vendor/autoload.php';

/** SERVICES **/

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
    protected $twilio;
    protected $phoneNumber;

    public function __construct(\Aura\Sql\ExtendedPdoInterface $db, Services_Twilio $twilio, $phone_number) {
        $this->db = $db;
        $this->twilio = $twilio;
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

            $this->twilio->account->messages->sendMessage($this->phoneNumber, $phone_number, $message);
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

$container = new \Slim\Container();

$container['view'] = function() {return new View(__DIR__ . '/../templates');};
$container['raffleService'] = function() {return new RaffleService(
    new \Aura\Sql\ExtendedPdo('mysql:host=127.0.0.1;dbname=raphple', 'raphple', 'raphple'),
    new Services_Twilio($_SERVER['TWILIO_SID'], $_SERVER['TWILIO_TOKEN']), $_SERVER['TWILIO_NUMBER']
);};
$container['auth'] = function($c) {return new Auth($c->get('raffleService'));};

// Slim3 doesn't currently do response cookies
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

$app = new \Slim\App($container);

/** ROUTES **/

$app->get('/', function(Request $req, Response $res) {
    return $this->view->render($res, 'home.php');
});

$app->post('/', function(Request $req, Response $res) {
    $body = $req->getParsedBody();

    $items = trim($body['raffle_items']);
    $name = trim($body['raffle_name']);

    $errors = [];

    if (!strlen($items))
        $errors['raffle_name'] = true;
    if (!strlen($name))
        $errors['raffle_items'] = true;

    if (count($errors))
        return $this->view->render($res, 'home.php',
            ['raffleItems' => $items, 'raffleName' => $name, 'errors' => $errors]);

    $id = $this->raffleService->create($name, explode("\n", trim($items)));

    return $this->addCookieToResponse
        ->__invoke($res, 'sid' . $id, $this->raffleService->getSid($id))->withRedirect('/' . $id);
});

$app->get('/{id}', function(Request $req, Response $res, array $args) {
    $id = $args['id'];
    /** @var RaffleService $rs */
    $rs = $this->raffleService;

    if (!$rs->raffleExists($id))
        return $this->view->renderNotFound($res);

    if ($req->getQueryParams()['show'] === 'entrants') {
        $output = ['is_complete' => $rs->isComplete($id)];

        $numbers = $rs->getEntrantPhoneNumbers($id);
        $output['count'] = count($numbers);

        if ($this->auth->isAuthorized($req, $id))
            $output['numbers'] = array_map(function($number) {return 'xxx-xxx-' . substr($number, -4);}, $numbers);

        return $res->withJson($output);
    }

    if ($rs->isComplete($id))
        return $this->view->render($res, 'finished.php', ['raffleName' => $rs->getName($id)]);

    return $this->view->render($res, 'waiting.php', [
        'phoneNumber' => $rs->getPhoneNumber($id),
        'code' => $rs->getCode($id),
        'entrantNumbers' => $this->auth->isAuthorized($req, $id) ? $rs->getEntrantPhoneNumbers($id) : null,
        'entrantCount' => $rs->getEntrantCount($id)
    ]);
});

$app->post('/twilio', function(Request $req, Response $res) {
    /** @var RaffleService $rs */
    $rs = $this->raffleService;
    $body = $req->getParsedBody();

    if ($rs->recordEntry($body['Body'], $body['From']))
        return $res->write("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Response>
            <Message>Your entry into " . $rs->getNameByCode($body['Body']) . " has been received!</Message>
        </Response>");

    return $res->write("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Response />");
});

$app->post('/{id}', function(Request $req, Response $res, array $args) {
    $id = $args['id'];
    /** @var RaffleService $rs */
    $rs = $this->raffleService;

    if (!$rs->raffleExists($id))
        return $this->view->renderNotFound($res);

    if (!$this->auth->isAuthorized($req, $id))
        return $res->withRedirect('/');

    $data = ['raffleName' => $rs->getName($id)];

    if (!$rs->isComplete($id))
        $data['winnerNumbers'] = $rs->complete($id);

    return $this->view->render($res, 'finished.php', $data);
});

$app->run();
