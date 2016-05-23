<?php

require __DIR__ . '/../vendor/autoload.php';

$app = new \Slim\App();
call_user_func(require __DIR__ . '/../bootstrap/services.php', $app->getContainer(), $_SERVER + $_ENV);
call_user_func(require __DIR__ . '/../bootstrap/routes.php', $app);

$app->run();
