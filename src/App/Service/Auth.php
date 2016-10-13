<?php

namespace App\Service;

use Psr\Http\Message\ServerRequestInterface;

class Auth
{
    protected $rs;

    public function __construct(RaffleService $rs) {
        $this->rs = $rs;
    }

    public function isAuthorized(ServerRequestInterface $req, $id) {
        $sid = $this->rs->getSid($id);

        $cookieValues = [];
        if ($req->hasHeader('Cookie')) {
            foreach (explode(';', $req->getHeader('Cookie')[0]) as $cookie) {
                list($cookieKey, $cookieValue) = explode('=', $cookie, 2);
                $cookieValues[trim($cookieKey)] = trim($cookieValue);
            }
        }
        foreach ($req->getHeader('Cookie') as $cookieString) {
            parse_str($cookieString, $cookie);
            $cookieValues = array_merge($cookieValues, $cookie);
        }
        parse_str($req->getUri()->getQuery(), $qs);

        return $sid === @$cookieValues['sid' . $id] || $sid === @$qs['sid'];
    }
}
