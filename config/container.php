<?php

use Xtreamwayz\Pimple\Container as Pimple;
use Zend\Expressive\Container;

$env = $_SERVER + $_ENV;

// Load configuration
$config = require __DIR__ . '/config.php';

// Build container
$container = new Pimple();

// Inject config
$container['config'] = $config;

// Inject factories
foreach ($config['dependencies']['factories'] as $name => $object) {
    $container[$name] = function (Pimple $c) use ($object, $name) {
        if ($c->has($object)) {
            $factory = $c->get($object);
        } else {
            $factory = new $object();
            $c[$object] = $c->protect($factory);
        }

        return $factory($c, $name);
    };
}
// Inject invokables
foreach ($config['dependencies']['invokables'] as $name => $object) {
    $container[$name] = function ($c) use ($object) {
        return new $object();
    };
}
// Inject "pimple extend-style" factories
if (! empty($config['dependencies']['extensions'])
    && is_array($config['dependencies']['extensions'])
) {
    foreach ($config['dependencies']['extensions'] as $name => $extensions) {
        foreach ($extensions as $extension) {
            $container->extend($name, function ($service, $c) use ($extension, $name) {
                $factory = new $extension();
                return $factory($service, $c, $name); // passing extra parameter $name
            });
        }
    }
}
// Inject "zend-servicemanager3 style" delegators as Pimple anonymous "extend" functions
if (! empty($config['dependencies']['delegators'])
    && is_array($config['dependencies']['delegators'])
) {
    foreach ($config['dependencies']['delegators'] as $name => $delegators) {
        foreach ($delegators as $delegator) {
            $container->extend($name, function ($service, $c) use ($delegator, $name) {
                $factory  = new $delegator();
                $callback = function () use ($service) {
                    return $service;
                };

                return $factory($c, $name, $callback);
            });
        }
    }
}

$container['Zend\Expressive\Whoops'] = new Container\WhoopsFactory();
$container['Zend\Expressive\WhoopsPageHandler'] = new Container\WhoopsPageHandlerFactory();

$container['Zend\Expressive\FinalHandler'] = new Container\WhoopsErrorHandlerFactory();

$container['raffleService'] = function($c) use ($env) {return new \App\Service\RaffleService(
    new \Aura\Sql\ExtendedPdo('mysql:host=' . $env['DB_HOST'] . ';dbname=' . $env['DB_NAME'],
        $env['DB_USER'], $env['DB_PASSWORD']), $c['sms'], $env['PHONE_NUMBER']
);};
$container['auth'] = function(Pimple $c) {return new \App\Service\Auth($c->get('raffleService'));};

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

return $container;
