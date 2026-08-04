<?php

/**
 * Routes served by the Symfony kernel.
 *
 * Read top to bottom: UrlMatcher takes the *first* route that matches, so the
 * order in this file is the migration status of the site. Everything declared
 * above the catch-all is Symfony's; everything else still belongs to
 * laminas-mvc. Porting a route means moving one line up past `legacy`.
 *
 * Deliberately outside config/autoload/, which laminas-mvc glob-loads: this file
 * is not application config, and returning a RouteCollection into that merge
 * would break it.
 */

declare(strict_types=1);

use App\Controller\HealthController;
use App\Http\LegacyBridge;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

$routes = new RouteCollection();

$routes->add('health', new Route('/_health', ['_controller' => HealthController::class]));

// The catch-all, and last for that reason. `.*` rather than `.+` so that "/"
// matches too, with an empty `path`.
$routes->add('legacy', new Route(
    '/{path}',
    ['_controller' => LegacyBridge::class, 'path' => ''],
    ['path' => '.*']
));

return $routes;
