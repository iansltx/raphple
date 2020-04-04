<?php

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

class SignalWireSMS implements SMS
{
    protected string $space;
    protected string $projectId;
    protected string $authToken;
    protected string $fromNumber;

    public function __construct(string $space, string $projectId, string $authToken, string $fromNumber)
    {
        $this->space = $space;
        $this->projectId = $projectId;
        $this->authToken = $authToken;
        $this->fromNumber = $fromNumber;
    }

    public function send($to, $text)
    {
        file_get_contents('https://' . $this->space . '.signalwire.com/api/laml/2010-04-01/Accounts/' .
                $this->projectId . '/Messages.json', false,
            stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => [
                        'Content-type: application/x-www-form-urlencoded',
                        'Authorization: Basic ' . base64_encode($this->projectId . ':' . $this->authToken)
                    ],
                    'content' => http_build_query([
                        'To' => $to,
                        'From' => $this->fromNumber,
                        'Body' => $text
                    ])
                ]]));
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
