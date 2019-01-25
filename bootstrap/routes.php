<?php

use Amp\Http\Cookie\ResponseCookie;
use Amp\Http\Server\FormParser\Form;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler\CallableRequestHandler;
use Amp\Http\Server\Response;
use function Amp\Http\Server\FormParser\parseForm;
use Amp\Http\Server\Router;
use Amp\Http\Status;

return function (\Pimple\Container $c, Router $app) {
    // incoming SMS webhooks

    $app->addRoute('POST', '/twilio', new CallableRequestHandler(function (Request $req) use ($c) {
        /** @var RaffleService $rs */
        $rs = $c['raffleService'];

        /** @var Form $form */
        $form = yield parseForm($req);

        $res = new Response(Status::OK, ['Content-Type' => 'text/xml']);

        if (yield from $rs->recordEntry($form->getValue('Body'), $form->getValue('From'))) {
            $res->setBody("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Response>
            <Message>Your entry into " . (yield from $rs->getNameByCode($form->getValue('Body'))) .
                " has been received!</Message></Response>");
        } else {
            $res->setBody("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<Response />");
        }
        return $res;
    }));

    $app->addRoute('GET', '/nexmo', new CallableRequestHandler(functioN (Request $req) use ($c) {
        /** @var RaffleService $rs */
        $rs = $c['raffleService'];

        parse_str($req->getUri()->getQuery(), $query);

        $from = $query['msisdn'];
        $code = $query['text'];

        if ($code && yield from $rs->recordEntry($code, $from)) {
            $c['sms']->send($from, 'Your entry into ' . (yield from $rs->getNameByCode($code)) . ' has been received!');
        }

        return new Response(Status::OK, [], 'Message received.');
    }));

    // end of webhooks

    $app->addRoute('GET', '/', new CallableRequestHandler(function (Request $req) use ($c) {
        return $c['view']->render(new Response(), 'home.php');
    }));

    $app->addRoute('POST', '/', new CallableRequestHandler(function (Request $req) use ($c) {
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
            return $c['view']->render(new Response(), 'home.php',
                ['raffleItems' => $items, 'raffleName' => $name, 'errors' => $errors]);
        }

        $id = yield from $c['raffleService']->create($name, explode("\n", trim($items)));

        $res = new Response(302, ['Location' => '/' . $id]);
        $res->setCookie(new ResponseCookie('sid' . $id, yield from $c['raffleService']->getSid($id)));
        return $res;
    }));

    $app->addRoute('GET', '/{id}', new CallableRequestHandler(function (Request $req) use ($c) {
        $id = $req->getAttribute(Router::class)['id'];
        /** @var RaffleService $rs */
        $rs = $c['raffleService'];

        if (!(yield from $rs->raffleExists($id))) {
            return $c['view']->renderNotFound(new Response());
        }

        $numbers = yield from $rs->getEntrantPhoneNumbers($id);

        parse_str($req->getUri()->getQuery(), $query);

        if ($query['show'] === 'entrants') {
            $output = ['is_complete' => yield from $rs->isComplete($id)];

            $output['count'] = count($numbers);

            if (yield from $c['auth']->isAuthorized($req, $id)) {
                $output['numbers'] = array_map(function ($number) {
                    if (strlen($number) != 10 && substr($number, 0, 2) != '+1') {
                        return substr($number, 0, 3) . '...' . substr($number, -4);
                    }

                    return 'xxx-xxx-' . substr($number, -4);
                }, $numbers);
            }

            return new Response(Status::OK, ['Content-Type' => 'applications/json'], json_encode($output));
        }

        if (yield from $rs->isComplete($id)) {
            return $c['view']->render(new Response(), 'finished.php', ['raffleName' => yield from $rs->getName($id)]);
        }
        if (!(yield from $c['auth']->isAuthorized($req, $id))) {
            $numbers = null;
        }

        return $c['view']->render(new Response(), 'waiting.php', [
            'phoneNumber' => $rs->getPrettyPhoneNumber($id),
            'code' => $rs->getCode($id),
            'entrantNumbers' => $numbers,
            'entrantCount' => yield from $rs->getEntrantCount($id)
        ]);
    }));

    $app->addRoute('POST', '/{id}', new CallableRequestHandler(function (Request $req) use ($c) {
        $id = $req->getAttribute(Router::class)['id'];
        /** @var RaffleService $rs */
        $rs = $c['raffleService'];

        if (!(yield from $rs->raffleExists($id))) {
            return $c['view']->renderNotFound(new Response());
        }

        if (!(yield from $c['auth']->isAuthorized($req, $id))) {
            return new Response(302, ['Location' => '/']);
        }

        $data = ['raffleName' => yield from $rs->getName($id)];

        if (!(yield from $rs->isComplete($id))) {
            $data['winnerNumbers'] = yield from $rs->complete($id);
        }

        return $c['view']->render(new Response(), 'finished.php', $data);
    }));

    return $app;
};
