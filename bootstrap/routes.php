<?php

use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

return function(\Slim\App $app) {
    // incoming SMS webhooks

    $app->post('/twilio', function (Request $req, Response $res) {
        /** @var RaffleService $rs */
        $rs = $this->raffleService;
        parse_str($req->getBody()->getContents(), $body);

        if ($rs->recordEntry($body['Body'], $body['From'])) {
            $res->getBody()->write("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Response>
                <Message>Your entry into " . $rs->getNameByCode($body['Body']) . " has been received!</Message>
            </Response>");
            return $res;
        }

        $res->getBody()->write("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Response />");
        return $res;
    });

    $app->get('/nexmo', functioN(Request $req, Response $res) {
        /** @var RaffleService $rs */
        $rs = $this->raffleService;
        parse_str($req->getUri()->getQuery(), $qs);
        $code = $qs['text'];

        if ($rs->recordEntry($code, $qs['msisdn'])) {
            $this->sms->send($qs['msisdn'], 'Your entry into ' . $rs->getNameByCode($code) . ' has been received!');
        }
        $res->getBody()->write('Message received.');
        return $res->withStatus(200, "OK");
    });

    // end of webhooks

    $app->get('/', function (Request $req, Response $res) {
        return $this->view->render($res, 'home.php');
    });

    $app->post('/', function (Request $req, Response $res) {
        parse_str($req->getBody(), $body);

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

    $app->get('/{id}', function (Request $req, Response $res, array $args) {
        $id = $args['id'];
        /** @var RaffleService $rs */
        $rs = $this->raffleService;

        if (!$rs->raffleExists($id))
            return $this->view->renderNotFound($res);

        parse_str($req->getUri()->getQuery(), $qs);

        if ($qs['show'] === 'entrants') {
            $output = ['is_complete' => $rs->isComplete($id)];

            $numbers = $rs->getEntrantPhoneNumbers($id);
            $output['count'] = count($numbers);

            if ($this->auth->isAuthorized($req, $id))
                $output['numbers'] = array_map(function ($number) {
                    return 'xxx-xxx-' . substr($number, -4);
                }, $numbers);

            $res->getBody()->write(json_encode($output));
            return $res->withHeader('Content-type', 'application/json');
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

    $app->post('/{id}', function (Request $req, Response $res, array $args) {
        $id = $args['id'];
        /** @var RaffleService $rs */
        $rs = $this->raffleService;

        if (!$rs->raffleExists($id))
            return $this->view->renderNotFound($res);

        if (!$this->auth->isAuthorized($req, $id))
            return $res->withHeader('Location', '/')->withStatus('302', 'Found');

        $data = ['raffleName' => $rs->getName($id)];

        if (!$rs->isComplete($id))
            $data['winnerNumbers'] = $rs->complete($id);

        return $this->view->render($res, 'finished.php', $data);
    });
};
