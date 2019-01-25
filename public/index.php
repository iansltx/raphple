<?php

use Amp\Cluster\Cluster;
use Amp\Http\Server\Server;
use Amp\Http\Server\StaticContent\DocumentRoot;

require __DIR__ . '/../vendor/autoload.php';

Amp\Loop::run(function() {
    $logHandler = new \Amp\Log\StreamHandler(new \Amp\ByteStream\ResourceOutputStream(\STDOUT));
    $logHandler->setFormatter(new \Amp\Log\ConsoleFormatter());
    $logger = new \Monolog\Logger('server');
    $logger->pushHandler($logHandler);

    $router = new Amp\Http\Server\Router;

    call_user_func(require __DIR__ . '/../bootstrap/routes.php',
        call_user_func(require __DIR__ . '/../bootstrap/services.php', new Pimple\Container(), $_SERVER + $_ENV),
        $router);

    $router->setFallback(new DocumentRoot(__DIR__));

    $sockets = Cluster::isWorker() ? [yield Cluster::listen('0.0.0.0:' . ($_ENV['APP_PORT'] ?? 80))] : [
        \Amp\Socket\listen('0.0.0.0:' . ($_ENV['APP_PORT'] ?? 80)),
        \Amp\Socket\listen('[::]:' . ($_ENV['APP_PORT'] ?? 80))
    ];

    $server = new Server($sockets, $router, $logger);

    yield $server->start();

    if (Cluster::isWorker()) {
        Cluster::onTerminate(function () use ($server, $logger) {
            $logger->info("Received termination request");
            $server->stop();
        });
    } else {
        // Stop the server when SIGINT is received (this is technically optional, but it is best to call Server::stop()).
        Amp\Loop::onSignal(SIGINT, function (string $watcherId) use ($server) {
            Amp\Loop::cancel($watcherId);
            yield $server->stop();
        });
    }
});
