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
use App\Controller\ShrinesController;
use App\Http\LegacyBridge;
use App\Locale\Locales;
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
 * The five values come from App\Locale\Locales, which mirrors the aliases in
 * config/autoload/juser.global.php (`slm_locale`) — the class rather than the config
 * because reading the merged config here would mean loading every laminas module
 * before the first route is declared, and test/Integration guards the two against
 * drift. They are constrained rather than left open so that a path with some *other*
 * first segment keeps falling through to `legacy` instead of being swallowed by a
 * two-segment pattern.
 *
 * The maintenance endpoints ignore the matched value — they emit no localized text.
 * An HTML route does not: App\Http\LocaleListener turns `_locale` into
 * \Locale::setDefault() before the controller runs, and the *absence* of `_locale`
 * is how a controller knows to redirect to the prefixed form the way SlmLocale would.
 *
 * The `.locale` suffix on the second name is load-bearing: App\Http\SymfonyRoute
 * strips it to recover the laminas route name a ported route shadows, which is what
 * the Twig layout compares against to mark a navigation item active.
 */
$locales = Locales::pattern();
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

// The first HTML route, ported 2026-08-05, and the reason templates/ and the Twig
// layer exist. Portable only because it is public: `shrines` is guarded
// ['null','guest','user'] and a null role means everyone, so nothing is lost by
// arriving without the BjyAuthorize route guard. The laminas route it shadows stays
// exactly as it was — production still serves it, SYMFONY_KERNEL being unset there.
$ported('shrines', '/shrines', ShrinesController::class);

// The catch-all, and last for that reason. `.*` rather than `.+` so that "/"
// matches too, with an empty `path`.
$routes->add('legacy', new Route(
    '/{path}',
    ['_controller' => LegacyBridge::class, 'path' => ''],
    ['path' => '.*']
));

return $routes;
