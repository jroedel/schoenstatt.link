<?php

namespace RestApi;

use Laminas\Router\Http\Regex;
use Laminas\ServiceManager\Factory\InvokableFactory;

return [

    /**
     * JSON 404 for unknown API paths only. The multidots original was a
     * global '/:*' catch-all named '404' (an INTEGER array key): it swallowed
     * every unmatched web URL, the default-deny route guard then refused it,
     * and JUser's RedirectionStrategy fataled assembling the int route name —
     * a fatal-under-HTTP-200 on every unknown URL. Unmatched web URLs must
     * NOT route-match at all, so Laminas renders the normal error/404 page.
     */
    'router' => [
        'routes' => [
            'api-route-not-found' => [
                'type' => Regex::class,
                'options' => [
                    'regex' => '/api(/.*)?',
                    'spec' => '/api',
                    'defaults' => [
                        'controller' => Controller\RouteNotFoundController::class,
                        'action' => 'routenotfound',
                    ],
                ],
                'priority' => -1000,
            ],
        ],
    ],
    'bjyauthorize' => [
        'guards' => [
            \BjyAuthorize\Guard\Route::class => [
                ['route' => 'api-route-not-found', 'roles' => ['guest', 'user']],
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
