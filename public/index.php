<?php

const AERYS_OPTIONS = [
    "user" => "www-data",
];

$container = call_user_func(require __DIR__ . '/../bootstrap/services.php', new Pimple\Container(), $_SERVER + $_ENV);
(new Aerys\Host)->expose("*", 80)->name('90576ab5.ngrok.io')
    ->use(call_user_func(require __DIR__ . '/../bootstrap/routes.php', $container, Aerys\router())) // routes
    ->use(Aerys\root(__DIR__)); // static file serving
