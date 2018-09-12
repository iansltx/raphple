<?php

require __DIR__ . '/../vendor/autoload.php';

$app = new \Slim\App();
call_user_func(require __DIR__ . '/../bootstrap/services.php', $container = $app->getContainer(), $_SERVER + $_ENV);
call_user_func(require __DIR__ . '/../bootstrap/routes.php', $app);

$app->run();

if (count($queuedTasks = $container->get('taskQueue'))) {
    fastcgi_finish_request();
    $queuedTasks();
}
