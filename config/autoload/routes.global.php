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
];
