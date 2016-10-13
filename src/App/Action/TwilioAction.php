<?php

namespace App\Action;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use App\Service\RaffleService;

class TwilioAction
{
    protected $raffleService;

    public function __construct(RaffleService $raffleService)
    {
        $this->raffleService = $raffleService;
    }

    public function __invoke(Request $req, Response $res, callable $next) {
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
    }
}
