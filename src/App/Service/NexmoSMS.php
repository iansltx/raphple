<?php

namespace App\Service;

class NexmoSMS implements SMS
{
    protected $key;
    protected $secret;
    protected $fromNumber;

    public function __construct($key, $secret, $fromNumber)
    {
        $this->key = $key;
        $this->secret = $secret;
        $this->fromNumber = $fromNumber;
    }

    public function send($to, $text)
    {
        error_log(file_get_contents('https://rest.nexmo.com/sms/json?' . http_build_query([
                'api_key' => $this->key,
                'api_secret' => $this->secret,
                'to' => $to,
                'from' => $this->fromNumber,
                'text' => $text
            ])));
    }
}
