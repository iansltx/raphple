<?php

namespace App\Service;

class TwilioSMS implements SMS
{
    protected $sid;
    protected $token;
    protected $fromNumber;

    public function __construct($sid, $token, $fromNumber)
    {
        $this->sid = $sid;
        $this->token = $token;
        $this->fromNumber = $fromNumber;
    }

    public function send($to, $text)
    {
        file_get_contents('https://api.twilio.com/2010-04-01/Accounts/' . $this->sid . '/Messages.json', false,
            stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => [
                        'Content-type: application/x-www-form-urlencoded',
                        'Authorization: Basic ' . base64_encode($this->sid . ':' . $this->token)
                    ],
                    'content' => http_build_query([
                        'To' => $to,
                        'From' => $this->fromNumber,
                        'Body' => $text
                    ])
                ]]));
    }
}
