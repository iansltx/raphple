<?php

use Pimple\Container;
use Pimple\Psr11\Container as PsrContainer;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

call_user_func(require __DIR__ . '/../bootstrap/services.php', $container = new Container(), $_SERVER + $_ENV);
AppFactory::setContainer(new PsrContainer($container));
call_user_func(require __DIR__ . '/../bootstrap/routes.php', $app = AppFactory::create());

$app->addErrorMiddleware(true, true, true);

$app->run();
