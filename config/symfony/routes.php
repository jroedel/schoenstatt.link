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

use App\Controller\CacheStatusController;
use App\Controller\ClearPersistentCacheController;
use App\Controller\HealthController;
use App\Http\LegacyBridge;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

$routes = new RouteCollection();

$routes->add('health', new Route('/_health', ['_controller' => HealthController::class]));

/**
 * Every ported path also has to answer under a locale prefix, because every
 * caller uses that form: `/en/sm/cache-status` is what tools/smoke-prod.sh, the
 * smoke suite and the phploy hooks ask for. Under laminas the prefix never
 * reaches the router — SlmLocale\Strategy\UriPathStrategy strips it first — and
 * Symfony has no such listener, so the literal path has to be matched here.
 *
 * The five values are the aliases configured in config/autoload/juser.global.php
 * (`slm_locale`), and they are constrained rather than left open so that a path
 * with some *other* first segment keeps falling through to `legacy` instead of
 * being swallowed by a two-segment pattern. The value is not passed on and not
 * used: these endpoints emit no localized text.
 */
$locales = 'en|es|de|pt|it';
$ported  = static function (string $name, string $path, string $controller) use ($routes, $locales): void {
    $routes->add($name, new Route($path, ['_controller' => $controller]));
    $routes->add($name . '.locale', new Route(
        '/{_locale}' . $path,
        ['_controller' => $controller],
        ['_locale' => $locales]
    ));
};

// SionModel's maintenance endpoints, ported 2026-08-05. Both are machine
// endpoints gated by a maintenance key the controller checks itself, so they need
// nothing the laminas MVC listeners provide — no session, no ACL guard, no view
// layer. See docs/strangler.md.
$ported('sm-cache-status', '/sm/cache-status', CacheStatusController::class);
$ported('sm-clear-persistent-cache', '/sm/clear-persistent-cache', ClearPersistentCacheController::class);

// The catch-all, and last for that reason. `.*` rather than `.+` so that "/"
// matches too, with an empty `path`.
$routes->add('legacy', new Route(
    '/{path}',
    ['_controller' => LegacyBridge::class, 'path' => ''],
    ['path' => '.*']
));

return $routes;
