<?php

interface SMS
{
    public function send($to, $text);
}

class DeferredSMS implements SMS
{
    protected $actual;
    protected $queue;

    public function __construct(SMS $actual, TaskQueue $queue)
    {
        $this->actual = $actual;
        $this->queue = $queue;
    }

    public function send($to, $text)
    {
        $this->queue->push(function() use ($to, $text) {
            $this->actual->send($to, $text);
        });
    }
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
