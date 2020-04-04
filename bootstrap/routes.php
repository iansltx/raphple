<?php

use Slim\Http\ServerRequest as Request;
use Slim\Http\Response;

return function(\Slim\App $app) {
    // incoming SMS webhooks

    $app->post('/twilio', function (Request $req, Response $res) {
        /** @var RaffleService $rs */
        $rs = $this->get('raffleService');
        $body = $req->getParsedBody();

        if ($rs->recordEntry($body['Body'], $body['From']))
            return $res->write("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Response>
            <Message>Your entry into " . $rs->getNameByCode($body['Body']) . " has been received!</Message>
        </Response>");

        return $res->write("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Response />");
    });

    $app->get('/nexmo', functioN(Request $req, Response $res) {
        /** @var RaffleService $rs */
        $rs = $this->get('raffleService');
        $qs = $req->getQueryParams();
        $code = $qs['text'];

        if ($code && $rs->recordEntry($code, $qs['msisdn'])) {
            $this->get('sms')->send($qs['msisdn'], 'Your entry into ' . $rs->getNameByCode($code) . ' has been received!');
        }
        return $res->withStatus(200, "OK")->write('Message received.');
    });

    // end of webhooks

    $app->get('/', function (Request $req, Response $res) {
        return $this->get('view')->render($res, 'home.php');
    });

    $app->post('/', function (Request $req, Response $res) {
        $body = $req->getParsedBody();

        $items = trim($body['raffle_items']);
        $name = trim($body['raffle_name']);

        $errors = [];

        if (!strlen($items))
            $errors['raffle_name'] = true;
        if (!strlen($name))
            $errors['raffle_items'] = true;

        if (count($errors))
            return $this->get('view')->render($res, 'home.php',
                ['raffleItems' => $items, 'raffleName' => $name, 'errors' => $errors]);

        $id = $this->get('raffleService')->create($name, explode("\n", trim($items)));

        return $this->get('addCookieToResponse')
            ->__invoke($res, 'sid' . $id, $this->get('raffleService')->getSid($id))->withRedirect('/' . $id);
    });

    $app->get('/{id}', function (Request $req, Response $res, array $args) {
        $id = $args['id'];
        /** @var RaffleService $rs */
        $rs = $this->get('raffleService');

        if (!$rs->raffleExists($id))
            return $this->view->renderNotFound($res);

        if ($req->getQueryParams()['show'] === 'entrants') {
            $output = ['is_complete' => $rs->isComplete($id)];

            $numbers = $rs->getEntrantPhoneNumbers($id);
            $output['count'] = count($numbers);

            if ($this->get('auth')->isAuthorized($req, $id))
                $output['numbers'] = array_map(function ($number) {
                    if (strlen($number) != 10 && substr($number, 0, 2) != '+1') {
                        return substr($number, 0, 3) . '...' . substr($number, -4);
                    }

                    return 'xxx-xxx-' . substr($number, -4);
                }, $numbers);

            return $res->withJson($output);
        }

        if ($rs->isComplete($id))
            return $this->get('view')->render($res, 'finished.php', ['raffleName' => $rs->getName($id)]);

        return $this->get('view')->render($res, 'waiting.php', [
            'phoneNumber' => $rs->getPhoneNumber($id),
            'code' => $rs->getCode($id),
            'entrantNumbers' => $this->get('auth')->isAuthorized($req, $id) ? $rs->getEntrantPhoneNumbers($id) : null,
            'entrantCount' => $rs->getEntrantCount($id)
        ]);
    });

    $app->post('/{id}', function (Request $req, Response $res, array $args) {
        $id = $args['id'];
        /** @var RaffleService $rs */
        $rs = $this->get('raffleService');

        if (!$rs->raffleExists($id))
            return $this->get('view')->renderNotFound($res);

        if (!$this->get('auth')->isAuthorized($req, $id))
            return $res->withRedirect('/');

        $data = ['raffleName' => $rs->getName($id)];

        if (!$rs->isComplete($id))
            $data['winnerNumbers'] = $rs->complete($id);

        return $this->get('view')->render($res, 'finished.php', $data);
    });
};
