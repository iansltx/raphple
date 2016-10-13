<?php

return [
    'dependencies' => [
        'invokables' => [
            Zend\Expressive\Router\RouterInterface::class => Zend\Expressive\Router\FastRouteRouter::class,
            App\Action\TwilioAction::class => App\Action\TwilioAction::class,
            App\Action\NexmoAction::class => App\Action\NexmoAction::class,
            App\Action\HomeAction::class => App\Action\HomeAction::class,
            App\Action\CreateAction::class => App\Action\CreateAction::class,
            App\Action\GetAction::class => App\Action\GetAction::class,
            App\Action\CompleteAction::class => App\Action\CompleteAction::class,
        ]
    ],

    'routes' => [
        // webhooks
        [
            'name' => 'Twilio',
            'path' => '/twilio',
            'middleware' => App\Action\TwilioAction::class,
            'allowed_methods' => ['POST'],
        ],
        [
            'name' => 'Nexmo',
            'path' => '/nexmo',
            'middleware' => App\Action\NexmoAction::class,
            'allowed_methods' => ['GET'],
        ],
        // main site
        [
            'name' => 'Home',
            'path' => '/',
            'middleware' => App\Action\HomeAction::class,
            'allowed_methods' => ['GET'],
        ],
        [
            'name' => 'Create',
            'path' => '/',
            'middleware' => App\Action\CreateAction::class,
            'allowed_methods' => ['POST'],
        ],
        [
            'name' => 'Get',
            'path' => '/:id',
            'middleware' => App\Action\GetAction::class,
            'allowed_methods' => ['GET'],
        ],
        [
            'name' => 'Complete',
            'path' => '/:id',
            'middleware' => App\Action\CompleteAction::class,
            'allowed_methods' => ['POST'],
        ],
    ],
];
