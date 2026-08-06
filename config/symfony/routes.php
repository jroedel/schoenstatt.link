<?php

/**
 * Routes served by the Symfony kernel.
 *
 * Read top to bottom: UrlMatcher takes the *first* route that matches, so the
 * order in this file is the migration status of the site. Everything declared
 * above the catch-all is Symfony's; everything else still belongs to
 * laminas-mvc. Porting a route means moving one line up past `legacy`.
 *
 * Every route here also declares **who may reach it**, as an
 * App\Authorization\RouteAccess in its defaults. That is not decoration: a
 * Symfony-served route never boots laminas-mvc, so BjyAuthorize\Guard\Route never
 * runs for it, and before App\Authorization\RouteGuard existed a ported guarded
 * route admitted everyone while continuing to look perfectly healthy. The
 * declaration names an ACL resource the laminas guards already define, so one guard
 * entry governs both front controllers — and a route that declares nothing raises
 * App\Authorization\UndeclaredRouteAccess rather than being assumed either way.
 * `tools/acl-table.php` prints the whole picture and warns on a gap.
 *
 * Deliberately outside config/autoload/, which laminas-mvc glob-loads: this file
 * is not application config, and returning a RouteCollection into that merge
 * would break it.
 */

declare(strict_types=1);

use App\Authorization\RouteAccess;
use App\Controller\AdminController;
use App\Controller\CacheStatusController;
use App\Controller\ClearPersistentCacheController;
use App\Controller\HealthController;
use App\Controller\ShrinesController;
use App\Controller\WaysideShrinesController;
use App\Http\LegacyBridge;
use App\Locale\Locales;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

$routes = new RouteCollection();

// The one route with no laminas twin at all: it predates the strangler and exists to
// answer a load balancer. There is no `route/…` resource to consult, so openness is
// stated rather than inferred.
$routes->add('health', new Route('/_health', [
    '_controller'          => HealthController::class,
    RouteAccess::ATTRIBUTE => RouteAccess::openToEveryone(
        'shadows no laminas route, so there is no guard entry to consult; it reports liveness only, '
        . 'which is nothing a visitor could not learn from the site answering at all'
    ),
]));

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
 *
 * $access is a required argument rather than a defaulted one, and both twins get the
 * same instance: they are one page reached two ways, and a check that applied to only
 * one of them would be a hole shaped exactly like the locale prefix.
 */
$locales = Locales::pattern();
$ported  = static function (
    string $name,
    string $path,
    string $controller,
    RouteAccess $access
) use (
    $routes,
    $locales
): void {
    $defaults = ['_controller' => $controller, RouteAccess::ATTRIBUTE => $access];
    $routes->add($name, new Route($path, $defaults));
    $routes->add($name . '.locale', new Route('/{_locale}' . $path, $defaults, ['_locale' => $locales]));
};

// SionModel's maintenance endpoints, ported 2026-08-05. Both are machine
// endpoints gated by a maintenance key the controller checks itself, so they need
// nothing the laminas MVC listeners provide — no session, no ACL guard, no view
// layer. See docs/strangler.md.
//
// Declared open, and the reason is worth stating precisely because `guardedBy` would
// also have "worked": the laminas guards they shadow
// (`route/sion-model/cache-status`, `route/sion-model/clear-persistent-cache`) carry
// `null` in their roles, i.e. they admit everyone, so consulting them could only ever
// answer yes. What it would cost is a session start plus the ACL build — several
// database queries, some through Books\Model\LibraryTable — on the two endpoints a
// deploy hook and the production monitor call. tools/acl-table.php cross-checks the
// claim: should either guard stop being public, it warns that this declaration now
// diverges from it.
$maintenance = RouteAccess::openToEveryone(
    'the laminas guard it shadows is public (a null role admits everyone), and its real gate is the '
    . 'maintenance key App\Http\MaintenanceKey checks inside the controller. Consulting the ACL here '
    . 'would put a session and the role/resource queries behind it on an endpoint the deploy hooks '
    . 'call, for a foregone answer'
);
$ported('sm-cache-status', '/sm/cache-status', CacheStatusController::class, $maintenance);
$ported(
    'sm-clear-persistent-cache',
    '/sm/clear-persistent-cache',
    ClearPersistentCacheController::class,
    $maintenance
);

// The first HTML route, ported 2026-08-05, and the reason templates/ and the Twig
// layer exist. Checked against its own laminas resource rather than declared open,
// even though `route/shrines` is guarded ['null','guest','user'] and a null role
// means everyone: the page already builds the ACL for its moderator markup, so the
// check costs nothing extra, and if that guard is ever tightened both front
// controllers tighten together. The laminas route it shadows stays exactly as it was
// — production still serves it, SYMFONY_KERNEL being unset there.
$ported('shrines', '/shrines', ShrinesController::class, RouteAccess::guardedBy('route/shrines'));

// The same index over the wayside-shrine associations, ported 2026-08-07. Guarded
// `['user', 'guest', null]` exactly as `shrines` is, and checked against its own
// resource for the same reason: the page builds the ACL anyway for its moderator
// markup, and one guard entry then governs both front controllers.
//
// Only `/wayside-shrines` itself. It has no laminas child routes — the "submitting
// photos" page it links to is a child of `shrines` and still falls through to
// `legacy`.
$ported(
    'wayside-shrines',
    '/wayside-shrines',
    WaysideShrinesController::class,
    RouteAccess::guardedBy('route/wayside-shrines')
);

// The first *restricted* route, ported 2026-08-06 — the proof that
// App\Authorization\RouteGuard works, and so the reason the other 148 restricted
// routes became portable at all. `route/admin` admits sch_moderator and translator
// (plus their descendants): an anonymous visitor gets 302 to the sign-in page, a
// signed-in visitor holding neither gets 403. Measured in
// test/Smoke/AdminAuthorizationSmokeTest.
//
// Only `/admin` itself. Its laminas children — /admin/import-father,
// /admin/maintenance, /admin/literature-maintenance, /admin/translations — are
// separate routes with their own guards, and a literal path with no trailing-slash
// variant is what leaves every one of them falling through to `legacy`.
$ported('admin', '/admin', AdminController::class, RouteAccess::guardedBy('route/admin'));

// The catch-all, and last for that reason. `.*` rather than `.+` so that "/"
// matches too, with an empty `path`.
//
// It declares no RouteAccess and must not: this is not a ported route but the door
// back into laminas-mvc, which runs BjyAuthorize\Guard\Route itself for whatever it
// matches. App\Authorization\RouteGuard skips it on exactly that ground
// (App\Http\SymfonyRoute::isPorted), and tools/acl-table.php excludes it from the
// ported-route table for the same reason.
$routes->add('legacy', new Route(
    '/{path}',
    ['_controller' => LegacyBridge::class, 'path' => ''],
    ['path' => '.*']
));

return $routes;
