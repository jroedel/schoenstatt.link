<?php

namespace RestApi;

use Laminas\Router\Http\Segment;
use Laminas\ServiceManager\Factory\InvokableFactory;

return [

    /**
     * Define route not found routes
     */
    'router' => [
        'routes' => [
            '404' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/:*',
                    'defaults' => [
                        'controller' => Controller\RouteNotFoundController::class,
                        'action' => 'routenotfound',
                    ],
                ],
                'priority' => -1000,
            ],
        ],
    ],
    'controllers' => [
        'factories' => [
            Controller\RouteNotFoundController::class => InvokableFactory::class,
        ],
    ],
    'view_manager' => [
        'strategies' => array(
            'ViewJsonStrategy',
        ),
    ],
];
