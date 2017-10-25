<?php

const AERYS_OPTIONS = ["user" => "www-data"];

return (new Aerys\Host)->expose("*", $_ENV['APP_PORT'])
    ->use(call_user_func(require __DIR__ . '/../bootstrap/routes.php',
            call_user_func(require __DIR__ . '/../bootstrap/services.php', new Pimple\Container(), $_SERVER + $_ENV),
        Aerys\router())) // routes
    ->use(Aerys\root(__DIR__)); // static file serving
