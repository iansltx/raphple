<?php

use Aerys\Request;
use Aerys\Response;

return function(\Pimple\Container $c, \Aerys\Router $app) {
    // incoming SMS webhooks

    $app->route('POST', '/twilio', function (Request $req, Response $res) use ($c) {
        /** @var RaffleService $rs */
        $rs = $c['raffleService'];
        $body = yield Aerys\parseBody($req, 4096);

        if ($rs->recordEntry($body->get('Body'), $body->get('From')))
            return $res->end("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Response>
            <Message>Your entry into " . $rs->getNameByCode($body->get('Body')) . " has been received!</Message>
        </Response>");

        return $res->end("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Response />");
    });

    $app->route('GET', '/nexmo', functioN(Request $req, Response $res) use ($c) {
        /** @var RaffleService $rs */
        $rs = $c['raffleService'];
        $from = $req->getParam('msisdn');
        $code = $req->getParam('text');

        if ($rs->recordEntry($code, $from)) {
            $c['sms']->send($from, 'Your entry into ' . $rs->getNameByCode($code) . ' has been received!');
        }
        return $res->setStatus(200)->end('Message received.');
    });

    // end of webhooks

    $app->route('GET', '/', function (Request $req, Response $res) use ($c) {
        return $c['view']->render($res, 'home.php');
    });

    $app->route('POST', '/', function (Request $req, Response $res) use ($c) {
        $body = yield Aerys\parseBody($req, 4096);
        $items = trim($body->get('raffle_items'));
        $name = trim($body->get('raffle_name'));

        $errors = [];

        if (!strlen($items))
            $errors['raffle_name'] = true;
        if (!strlen($name))
            $errors['raffle_items'] = true;

        if (count($errors))
            return $c['view']->render($res, 'home.php',
                ['raffleItems' => $items, 'raffleName' => $name, 'errors' => $errors]);

        $id = $c['raffleService']->create($name, explode("\n", trim($items)));

        $res->addHeader('Location', '/' . $id)->setCookie('sid' . $id, $c['raffleService']->getSid($id))
            ->setStatus(302)->end();
    });

    $app->route('GET', '/{id}', function (Request $req, Response $res, array $args) use ($c) {
        $id = $args['id'];
        /** @var RaffleService $rs */
        $rs = $c['raffleService'];

        if (!$rs->raffleExists($id))
            return $c['view']->renderNotFound($res);

        if ($req->getParam('show') === 'entrants') {
            $output = ['is_complete' => $rs->isComplete($id)];

            $numbers = $rs->getEntrantPhoneNumbers($id);
            $output['count'] = count($numbers);

            if ($c['auth']->isAuthorized($req, $id))
                $output['numbers'] = array_map(function ($number) {
                    return 'xxx-xxx-' . substr($number, -4);
                }, $numbers);

            return $res->end(json_encode($output));
        }

        if ($rs->isComplete($id))
            return $c['view']->render($res, 'finished.php', ['raffleName' => $rs->getName($id)]);

        return $c['view']->render($res, 'waiting.php', [
            'phoneNumber' => $rs->getPhoneNumber($id),
            'code' => $rs->getCode($id),
            'entrantNumbers' => $c['auth']->isAuthorized($req, $id) ? $rs->getEntrantPhoneNumbers($id) : null,
            'entrantCount' => $rs->getEntrantCount($id)
        ]);
    });

    $app->route('POST', '/{id}', function (Request $req, Response $res, array $args) use ($c) {
        $id = $args['id'];
        /** @var RaffleService $rs */
        $rs = $c['raffleService'];

        if (!$rs->raffleExists($id))
            return $c['view']->renderNotFound($res);

        if (!$c['auth']->isAuthorized($req, $id))
            return $res->addHeader('Location', '/')->setStatus(302)->end();

        $data = ['raffleName' => $rs->getName($id)];

        if (!$rs->isComplete($id))
            $data['winnerNumbers'] = $rs->complete($id);

        return $c['view']->render($res, 'finished.php', $data);
    });

    return $app;
};
