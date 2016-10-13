<?php

namespace App\Action;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use App\Service\RaffleService;
use App\Service\SMS;

class NexmoAction
{
    protected $raffleService;
    protected $sms;

    public function __construct(RaffleService $raffleService, SMS $sms)
    {
        $this->raffleService = $raffleService;
        $this->sms = $sms;
    }

    public function __invoke(Request $req, Response $res, callable $next) {
        $rs = $this->raffleService;
        parse_str($req->getUri()->getQuery(), $qs);
        $code = $qs['text'];

        if ($rs->recordEntry($code, $qs['msisdn'])) {
            $this->sms->send($qs['msisdn'], 'Your entry into ' . $rs->getNameByCode($code) . ' has been received!');
        }
        $res->getBody()->write('Message received.');
        return $res->withStatus(200, "OK");
    }
}
