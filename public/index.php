<?php

use Amp\Cluster\Cluster;
use Amp\Http\Server\Server;
use Amp\Http\Server\StaticContent\DocumentRoot;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Amp\ByteStream;
use Amp\Promise;
use Monolog\Logger;

require __DIR__ . '/../vendor/autoload.php';

Amp\Loop::run(function() {
    $router = new Amp\Http\Server\Router;

    call_user_func(require __DIR__ . '/../bootstrap/routes.php',
        call_user_func(require __DIR__ . '/../bootstrap/services.php', new Pimple\Container(), $_SERVER + $_ENV),
        $router);

    $router->setFallback(new DocumentRoot(__DIR__));

    $sockets = yield [
        Cluster::listen("0.0.0.0:" . ($_ENV['APP_PORT'] ?? 80)),
//        Cluster::listen("[::]:" . ($_ENV['APP_PORT'] ?? 80)),
    ];

    // Creating a log handler in this way allows the script to be run in a cluster or standalone.
    if (Cluster::isWorker()) {
        $handler = Cluster::createLogHandler();
    } else {
        $handler = new StreamHandler(ByteStream\getStdout());
        $handler->setFormatter(new ConsoleFormatter());
    }

    $logger = new Logger('worker-' . Cluster::getId());
    $logger->pushHandler($handler);

    $server = new Server($sockets, $router, $logger);

    yield $server->start();

    Cluster::onTerminate(function () use ($server): Promise {
        return $server->stop();
    });
});
