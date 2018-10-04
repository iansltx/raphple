<?php

use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler\CallableRequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\Server;
use Amp\Http\Status;

Amp\Loop::run(function() {
    $logHandler = new \Amp\Log\StreamHandler(new \Amp\ByteStream\ResourceOutputStream(\STDOUT));
    $logHandler->setFormatter(new \Amp\Log\ConsoleFormatter());
    $logger = new \Monolog\Logger('server');
    $logger->pushHandler($logHandler);

    $server = new Server([
        \Amp\Socket\listen('0.0.0.0:' . $_ENV['APP_PORT']),
        \Amp\Socket\listen('[::]:' . $_ENV['APP_PORT'])
    ], new CallableRequestHandler(function (Request $request) {
        return new Response(Status::OK, [
            "content-type" => "text/plain; charset=utf-8"
        ], "Hello, World!");
    }), $logger);
    yield $server->start();

    // TODO add routes

    // Stop the server when SIGINT is received (this is technically optional, but it is best to call Server::stop()).
    Amp\Loop::onSignal(SIGINT, function (string $watcherId) use ($server) {
        Amp\Loop::cancel($watcherId);
        yield $server->stop();
    });
});
/*
return (new Aerys\Host)->expose("*", $_ENV['APP_PORT'])
    ->use(call_user_func(require __DIR__ . '/../bootstrap/routes.php',
            call_user_func(require __DIR__ . '/../bootstrap/services.php', new Pimple\Container(), $_SERVER + $_ENV),
        Aerys\router())) // routes
    ->use(Aerys\root(__DIR__)); // static file serving
*/
