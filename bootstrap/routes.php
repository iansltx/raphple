<?php

use Aerys\Request;
use Aerys\Response;

return function(\Pimple\Container $c, \Aerys\Router $app) {
    // incoming SMS webhooks

    $app->route('POST', '/twilio', function (Request $req, Response $res) use ($c) {
        /** @var RaffleService $rs */
        $rs = $c['raffleService'];
        /** @var \Aerys\ParsedBody $body */
        $body = yield Aerys\parseBody($req, 4096);

        if (yield from $rs->recordEntry($body->get('Body'), $body->get('From')))
            return $res->end("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Response>
            <Message>Your entry into " . yield from $rs->getNameByCode($body->get('Body')) . " has been received!</Message>
            </Response>");

        return $res->end("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Response />");
    });

    $app->route('GET', '/nexmo', functioN(Request $req, Response $res) use ($c) {
        /** @var RaffleService $rs */
        $rs = $c['raffleService'];
        $from = $req->getParam('msisdn');
        $code = $req->getParam('text');

        if (yield from $rs->recordEntry($code, $from)) {
            $c['sms']->send($from, 'Your entry into ' . (yield from $rs->getNameByCode($code)) . ' has been received!');
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

        $id = yield from $c['raffleService']->create($name, explode("\n", trim($items)));

        $res->addHeader('Location', '/' . $id)->setCookie('sid' . $id, yield from $c['raffleService']->getSid($id))
            ->setStatus(302)->end();
    });

    $app->route('GET', '/{id}', function (Request $req, Response $res, array $args) use ($c) {
        $id = $args['id'];
        /** @var RaffleService $rs */
        $rs = $c['raffleService'];

        if (!(yield from $rs->raffleExists($id)))
            return $c['view']->renderNotFound($res);

        if ($req->getParam('show') === 'entrants') {
            $output = ['is_complete' => yield from $rs->isComplete($id)];

            $numbers = yield from $rs->getEntrantPhoneNumbers($id);
            $output['count'] = count($numbers);

            if ($c['auth']->isAuthorized($req, $id))
                $output['numbers'] = array_map(function ($number) {
                    return 'xxx-xxx-' . substr($number, -4);
                }, $numbers);

            return $res->end(json_encode($output));
        }

        if (yield from $rs->isComplete($id))
            return $c['view']->render($res, 'finished.php', ['raffleName' => yield from $rs->getName($id)]);

        return $c['view']->render($res, 'waiting.php', [
            'phoneNumber' => $rs->getPhoneNumber($id),
            'code' => $rs->getCode($id),
            'entrantNumbers' => $c['auth']->isAuthorized($req, $id) ?
                yield from $rs->getEntrantPhoneNumbers($id) : null,
            'entrantCount' => yield from $rs->getEntrantCount($id)
        ]);
    });

    $app->route('POST', '/{id}', function (Request $req, Response $res, array $args) use ($c) {
        $id = $args['id'];
        /** @var RaffleService $rs */
        $rs = $c['raffleService'];

        if (!(yield from $rs->raffleExists($id)))
            return $c['view']->renderNotFound($res);

        if (!$c['auth']->isAuthorized($req, $id))
            return $res->addHeader('Location', '/')->setStatus(302)->end();

        $data = ['raffleName' => yield from $rs->getName($id)];

        if (!(yield from $rs->isComplete($id)))
            $data['winnerNumbers'] = yield from $rs->complete($id);

        return $c['view']->render($res, 'finished.php', $data);
    });

    return $app;
};
