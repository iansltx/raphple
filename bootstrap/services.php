<?php

use Interop\Container\ContainerInterface as Container;

return function(Container $container, $env) {
    $container['raffleService'] = function($c) use ($env) {return new \App\Service\RaffleService(
        new \Aura\Sql\ExtendedPdo('mysql:host=' . $env['DB_HOST'] . ';dbname=' . $env['DB_NAME'],
            $env['DB_USER'], $env['DB_PASSWORD']), $c['sms'], $env['PHONE_NUMBER']
    );};
    $container['auth'] = function(Container $c) {return new \App\Service\Auth($c->get('raffleService'));};

    $container['sms'] = function() use ($env) {
        if (isset($env['TWILIO_SID'])) {
            return new \App\Service\TwilioSMS($env['TWILIO_SID'], $env['TWILIO_TOKEN'], $env['PHONE_NUMBER']);
        }
        if (isset($env['NEXMO_KEY'])) {
            return new \App\Service\NexmoSMS($env['NEXMO_KEY'], $env['NEXMO_SECRET'], $env['PHONE_NUMBER']);
        }
        if (isset($env['DUMMY_SMS_WAIT_MS'])) {
            return new \App\Service\DummySMS($env['DUMMY_SMS_WAIT_MS']);
        }
        throw new InvalidArgumentException('Could not find SMS service creds, and a dummy timeout was not supplied.');
    };
};
