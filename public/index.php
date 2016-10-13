<?php

namespace App;

use Zend\Expressive\Helper;
use App\Action;
use Zend\Expressive\Application;

// Delegate static file requests back to the PHP built-in webserver
if (php_sapi_name() === 'cli-server'
    && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))
) {
    return false;
}

chdir(dirname(__DIR__));
require __DIR__ . '/../vendor/autoload.php';

/** @var \Interop\Container\ContainerInterface $container */
$container = require __DIR__ . '/../config/container.php';

/** @var \Zend\Expressive\Application $app */
$app = $container->get(Application::class);

$app->pipe(Helper\ServerUrlMiddleware::class);
$app->pipeRoutingMiddleware();
$app->pipe(Helper\UrlHelperMiddleware::class);
$app->pipeDispatchMiddleware();

// webhooks
$app->post('/twilio', Action\TwilioAction::class, 'Twilio');
$app->get('/nexmo', Action\NexmoAction::class, 'Nexmo');
// normal site
$app->get('/', Action\HomeAction::class, 'Home');
$app->post('/', Action\CreateAction::class, 'Create');
$app->get('/{id}', Action\GetAction::class, 'Get');
$app->post('/{id}', Action\CompleteAction::class, 'Complete');

$app->run();
