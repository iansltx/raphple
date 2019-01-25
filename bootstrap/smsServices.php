<?php

use Amp\Artax\Request;

interface SMS
{
    public function send($to, $text);
}

class TwilioSMS implements SMS
{
    protected $sid;
    protected $token;
    protected $fromNumber;

    public function __construct($sid, $token, $fromNumber)
    {
        $this->sid = $sid;
        $this->token = $token;
        $this->fromNumber = str_replace(['-', ' '], '', $fromNumber);
    }

    public function send($to, $text)
    {
        return (new \Amp\Artax\DefaultClient)->request(
            (new Request('https://api.twilio.com/2010-04-01/Accounts/' . $this->sid . '/Messages.json', 'POST'))
            ->withHeader('Authorization', 'Basic ' . base64_encode($this->sid . ':' . $this->token))
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody(http_build_query([
                'To' => $to,
                'From' => $this->fromNumber,
                'Body' => $text
            ]))
        );
    }
}

class NexmoSMS implements SMS
{
    protected $key;
    protected $secret;
    protected $fromNumber;

    public function __construct($key, $secret, $fromNumber)
    {
        $this->key = $key;
        $this->secret = $secret;
        $this->fromNumber = str_replace(['-', ' '], '', $fromNumber);
    }

    /**
     * @param $to
     * @param $text
     * @return \Amp\Promise
     */
    public function send($to, $text)
    {
        return (new \Amp\Artax\DefaultClient)->request('https://rest.nexmo.com/sms/json?' . http_build_query([
                'api_key' => $this->key,
                'api_secret' => $this->secret,
                'to' => $to,
                'from' => $this->fromNumber,
                'text' => $text
            ]));
    }
}

class DummySMS implements SMS
{
    protected $waitMs;

    public function __construct($waitMs)
    {
        $this->waitMs = $waitMs;
    }

    public function send($to, $message)
    {
        usleep($this->waitMs * 1000);
        error_log("Dummy SMS: " . $to . " <- " . $message);
    }
}
