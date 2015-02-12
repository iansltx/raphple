<?php

require __DIR__ . '/../vendor/autoload.php';

$app = new \Slim\Slim(['templates.path' => '../templates']);

$app->get('/', function() use ($app) {
    $app->render('home.php');
});

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

$rs = new RaffleService(
    new \Aura\Sql\ExtendedPdo('mysql:host=127.0.0.1;dbname=raphple', 'raphple', 'raphple'),
    new Services_Twilio($_SERVER['TWILIO_SID'], $_SERVER['TWILIO_TOKEN']), $_SERVER['TWILIO_NUMBER']
);

$isAuthorized = function($id) use ($app, $rs) {
    $sid = $rs->getSid($id);
    return $sid === $app->getCookie('sid' . $id) || $sid === $app->request()->get('sid');
};

$app->post('/', function() use ($app, $rs) {
    $items = trim($app->request()->post('raffle_items'));
    $name = trim($app->request()->post('raffle_name'));

    $errors = [];

    if (!strlen($items))
        $errors['raffle_name'] = true;
    if (!strlen($name))
        $errors['raffle_items'] = true;

    if (count($errors)) {
        $app->render('home.php', ['raffleItems' => $items, 'raffleName' => $name, 'errors' => $errors]);
        return;
    }

    $id = $rs->create($name, explode("\n", trim($items)));

    $app->setCookie('sid' . $id, $rs->getSid($id));
    $app->redirect('/' . $id);
});

$app->get('/:id', function($id) use ($app, $rs, $isAuthorized) {
    if (!$rs->raffleExists($id)) {
        $app->response()->setStatus(404);
        $app->render('not_found.php');
        return;
    }

    if ($app->request()->get('show') === 'entrants') {
        $output = ['is_complete' => $rs->isComplete($id)];

        $numbers = $rs->getEntrantPhoneNumbers($id);
        $output['count'] = count($numbers);

        if ($isAuthorized($id))
            $output['numbers'] = array_map(function($number) {return 'xxx-xxx-' . substr($number, -4);}, $numbers);

        echo json_encode($output);
        return;
    }

    if ($rs->isComplete($id)) {
        $app->render('finished.php', ['raffleName' => $rs->getName($id)]);
        return;
    }

    $app->render('waiting.php', [
        'phoneNumber' => $rs->getPhoneNumber($id),
        'code' => $rs->getCode($id),
        'entrantNumbers' => $isAuthorized($id) ? $rs->getEntrantPhoneNumbers($id) : null,
        'entrantCount' => $rs->getEntrantCount($id)
    ]);
});

$app->post('/twilio', function() use ($app, $rs) {
    if ($rs->recordEntry($app->request()->post('Body'), $app->request->post('From'))) {
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Response>
            <Message>Your entry into " . $rs->getNameByCode($app->request->post('Body')) . " has been received!</Message>
        </Response>";
    } else {
        echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Response />";
    }
});

$app->post('/:id', function($id) use ($app, $rs, $isAuthorized) {
    if (!$rs->raffleExists($id)) {
        $app->response()->setStatus(404);
        $app->render('not_found.php');
        return;
    }

    if (!$isAuthorized) {
        $app->redirect('/' . $id);
        return;
    }

    $data = ['raffleName' => $rs->getName($id)];

    if (!$rs->isComplete($id))
        $data['winnerNumbers'] = $rs->complete($id);

    $app->render('finished.php', $data);
});

$app->run();
