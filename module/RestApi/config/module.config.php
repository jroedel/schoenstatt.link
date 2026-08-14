<?php

namespace RestApi;

use Laminas\Router\Http\Regex;

return [

    /**
     * The JSON refusal for unknown API paths — which, since /api/v1 and /api/v2 were
     * retired, means every one of their 26 former URLs too. A withdrawn API should
     * answer a machine-readable refusal rather than the site's HTML error page, and
     * this route is the whole mechanism: it matches at priority -1000, after every
     * real route has had its chance, so it never shadows /api/v3.
     *
     * The controller decides between 410 Gone for the two retired versions and 404 for
     * anything else; see its docblock for why that distinction is load-bearing.
     *
     * The multidots original was a
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
            Controller\RouteNotFoundController::class => Controller\RouteNotFoundControllerFactory::class,
        ],
    ],
    'view_manager' => [
        //what turns RouteNotFoundController's JsonModel into a JSON body rather than a
        //rendered .phtml — the refusals stop being machine-readable without it
        'strategies' => [
            'ViewJsonStrategy',
        ],
    ],
];
