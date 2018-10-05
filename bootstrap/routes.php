<?php

use Amp\Http\Server\FormParser\Form;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler\CallableRequestHandler;
use Amp\Http\Server\Response;
use function Amp\Http\Server\FormParser\parseForm;

return function (\Pimple\Container $c, \Amp\Http\Server\Router $app) {
    // incoming SMS webhooks

    $app->addRoute('POST', '/twilio', new CallableRequestHandler(function (Request $req, Response $res) use ($c) {
        /** @var RaffleService $rs */
        $rs = $c['raffleService'];

        /** @var Form $form */
        $form = yield parseForm($req);

        if (yield from $rs->recordEntry($form->getValue('Body'), $form->getValue('From'))) {
            $res->setBody("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Response>
            <Message>Your entry into " . (yield from $rs->getNameByCode($form->getValue('Body'))) .
                " has been received!</Message></Response>");
        } else {
            $res->setBody("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Response />");
        }
        return $res;
    }));

    $app->addRoute('GET', '/nexmo', new CallableRequestHandler(functioN (Request $req, Response $res) use ($c) {
        /** @var RaffleService $rs */
        $rs = $c['raffleService'];

        parse_str($req->getUri()->getQuery(), $query);

        $from = $query['msisdn'];
        $code = $query['text'];

        if ($code && yield from $rs->recordEntry($code, $from)) {
            $c['sms']->send($from, 'Your entry into ' . (yield from $rs->getNameByCode($code)) . ' has been received!');
        }
        $res->setBody('Message received.');

        return $res;
    }));

    // end of webhooks

    $app->addRoute('GET', '/', new CallableRequestHandler(function (Request $req, Response $res) use ($c) {
        return $c['view']->render($res, 'home.php');
    }));

    $app->addRoute('POST', '/', new CallableRequestHandler(function (Request $req, Response $res) use ($c) {
        /** @var Form $form */
        $form = yield parseForm($req);
        $items = trim($form->getValue('raffle_items'));
        $name = trim($form->getValue('raffle_name'));

        $errors = [];

        if (!strlen($items)) {
            $errors['raffle_name'] = true;
        }
        if (!strlen($name)) {
            $errors['raffle_items'] = true;
        }

        if (count($errors)) {
            return $c['view']->render($res, 'home.php',
                ['raffleItems' => $items, 'raffleName' => $name, 'errors' => $errors]);
        }

        $id = yield from $c['raffleService']->create($name, explode("\n", trim($items)));

        $res->addHeader('Location', '/' . $id);
        $res->setCookie(new \Amp\Http\Cookie\ResponseCookie('sid' . $id, yield from $c['raffleService']->getSid($id)));
        $res->setStatus(302);
        return $res;
    }));

    $app->addRoute('GET', '/{id}', new CallableRequestHandler(function (Request $req, Response $res, array $args) use ($c) {
        $id = $args['id'];
        /** @var RaffleService $rs */
        $rs = $c['raffleService'];

        if (!(yield from $rs->raffleExists($id))) {
            return $c['view']->renderNotFound($res);
        }

        $numbers = yield from $rs->getEntrantPhoneNumbers($id);

        parse_str($req->getUri()->getQuery(), $query);

        if ($query['show'] === 'entrants') {
            $output = ['is_complete' => yield from $rs->isComplete($id)];

            $output['count'] = count($numbers);

            if (yield from $c['auth']->isAuthorized($req, $id)) {
                $output['numbers'] = array_map(function ($number) {
                    return 'xxx-xxx-' . substr($number, -4);
                }, $numbers);
            }

            $res->setBody(json_encode($output));
            return $res;
        }

        if (yield from $rs->isComplete($id)) {
            return $c['view']->render($res, 'finished.php', ['raffleName' => yield from $rs->getName($id)]);
        }
        if (!(yield from $c['auth']->isAuthorized($req, $id))) {
            $numbers = null;
        }

        return $c['view']->render($res, 'waiting.php', [
            'phoneNumber' => $rs->getPhoneNumber($id),
            'code' => $rs->getCode($id),
            'entrantNumbers' => $numbers,
            'entrantCount' => yield from $rs->getEntrantCount($id)
        ]);
    }));

    $app->addRoute('POST', '/{id}', new CallableRequestHandler(function (Request $req, Response $res, array $args) use ($c) {
        $id = $args['id'];
        /** @var RaffleService $rs */
        $rs = $c['raffleService'];

        if (!(yield from $rs->raffleExists($id))) {
            return $c['view']->renderNotFound($res);
        }

        if (!(yield from $c['auth']->isAuthorized($req, $id))) {
            $res->addHeader('Location', '/');
            $res->setStatus(302);
            return $res;
        }

        $data = ['raffleName' => yield from $rs->getName($id)];

        if (!(yield from $rs->isComplete($id))) {
            $data['winnerNumbers'] = yield from $rs->complete($id);
        }

        return $c['view']->render($res, 'finished.php', $data);
    }));

    return $app;
};
