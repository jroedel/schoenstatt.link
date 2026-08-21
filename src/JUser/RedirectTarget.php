<?php

declare(strict_types=1);

namespace App\JUser;

use App\Laminas\ServiceBridge;
use App\Locale\Locales;
use BjyAuthorize\Service\Authorize;
use JUser\Model\User;
use JUser\Model\UserTable;
use Laminas\Http\Request as LaminasRequest;
use Laminas\Permissions\Acl\Acl;
use Laminas\Router\RouteMatch;
use Laminas\Router\Http\RouteInterface as HttpRouteInterface;
use Laminas\Router\Http\TreeRouteStack;
use Laminas\Router\RouteStackInterface;
use Throwable;

use function explode;
use function implode;
use function is_array;
use function is_string;
use function ltrim;
use function parse_url;
use function str_starts_with;

use const PHP_URL_PATH;
use const PHP_URL_QUERY;

/**
 * Where a visitor was heading before they were asked to sign in, and whether they may
 * actually go there.
 *
 * `JUser\Controller\LoginController::validRedirect()`, `matchUrl()`, `refusedRouteFor()`,
 * `aclRolesOf()` and `pathOf()`, ported. The reasoning inside each is the laminas class's
 * and is not repeated here — read it there — with one exception, which is the whole reason
 * this is a class of its own rather than four private methods on a controller.
 *
 * ## The locale prefix, which is why a straight port of matchUrl() would have been broken
 *
 * `matchUrl()` asks the **laminas** router whether a candidate resolves to a route. Under
 * a laminas dispatch that works because SlmLocale has already run: its listener strips the
 * locale segment off the request and sets the router's base, so the router only ever sees
 * `/shrines`. Under a Symfony dispatch no laminas MVC listener runs at all, so the router
 * sees exactly what it is handed — and measured through `App\Laminas\ServiceBridge` on
 * 2026-08-21:
 *
 *     /en/shrines  => NO MATCH          /shrines => shrines
 *     /en/users    => NO MATCH          /        => welcome
 *     /en/         => NO MATCH
 *
 * Every `?redirect=` the route guards emit carries the locale prefix. So a faithful
 * transcription of `matchUrl()` would have refused **every** destination on the site, and
 * the visitor would have signed in and landed on the home page — silently, and looking
 * exactly like the "three redirects, no explanation" defect that was fixed on 2026-08-20.
 * Nothing would have failed: `validRedirect()` returning null is a legitimate answer.
 *
 * So the prefix is stripped here before the router is asked. `Locales::isAlias()` decides
 * what counts as one, which is the same list the routes are built from.
 *
 * ## Asking the laminas router is still the right question
 *
 * Not the Symfony one, and not both. Every ported route keeps a laminas route declared —
 * that is the `$ported()` contract in config/symfony/routes.php, and it is what lets
 * `laminas_path()` and the BjyAuthorize guards go on naming them — so the laminas router
 * knows the whole site and the Symfony router knows only the ported part. The day a
 * Symfony route ships with no laminas twin, this has to consult both, and the symptom
 * will be that signing in towards that page lands home instead.
 */
final class RedirectTarget
{
    public function __construct(private readonly ServiceBridge $laminas)
    {
    }

    /**
     * A destination safe to send a browser to, or null.
     *
     * Three rules, and the **order matters**: the two string checks run before the router
     * is asked. That is not tidiness. `LaminasRequest::setUri()` parses a URL and the
     * router matches its *path*, discarding the host — measured 2026-08-21, both
     * `//evil.example.com/` and `https://evil.example.com/` match the route `welcome`. So
     * the router is no defence against an off-site destination and never was; the leading
     * slash and the rejection of a protocol-relative `//host` are the entire defence.
     * `test/Smoke/UserSmokeTest::testAHostileRedirectIsNotCarriedIntoTheForm` drives all
     * three rules and would catch a reordering.
     */
    public function valid(mixed $redirect): ?string
    {
        if (! is_string($redirect) || '' === $redirect) {
            return null;
        }
        //`//host/path` is a protocol-relative URL, i.e. an absolute one, and it starts
        //with a slash — so the first test does not imply the second.
        if (! str_starts_with($redirect, '/') || str_starts_with($redirect, '//')) {
            return null;
        }

        return $this->match($redirect) instanceof RouteMatch ? $redirect : null;
    }

    /**
     * The laminas route a candidate resolves to, or null.
     *
     * Locale prefix stripped first — see the class docblock. The query string is kept,
     * because a route can match on it and because dropping it here would silently answer
     * a different question from the one the caller asked.
     */
    public function match(string $url): ?RouteMatch
    {
        try {
            $request = new LaminasRequest();
            $request->setUri($this->withoutLocalePrefix($url));
            $match = $this->router()->match($request);

            return $match instanceof RouteMatch ? $match : null;
        } catch (Throwable) {
            //the laminas router throws for a malformed URI and for a part route that may
            //not terminate; either way the answer is "not a destination"
            return null;
        }
    }

    /**
     * `/en/shrines` → `/shrines`; anything else unchanged.
     *
     * Only a *leading* segment that is a known alias, and only when it is the whole
     * segment: `/entity/1` must not lose its first four characters. `/en` alone becomes
     * `/`, because the empty path is not something the router can match and the locale
     * home page is `welcome`.
     */
    private function withoutLocalePrefix(string $url): string
    {
        $path  = (string) parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);
        if ('' === $path || ! str_starts_with($path, '/')) {
            return $url;
        }

        $segments = explode('/', $path);
        //$segments[0] is the empty string before the leading slash
        if (! isset($segments[1]) || ! Locales::isAlias($segments[1])) {
            return $url;
        }

        unset($segments[1]);
        $stripped = '/' . ltrim(implode('/', $segments), '/');

        return is_string($query) && '' !== $query ? $stripped . '?' . $query : $stripped;
    }

    /**
     * The route name behind $url when $user may **not** reach it, or null when they may.
     *
     * Transcribed from `LoginController::refusedRouteFor()`, including why it asks about
     * the account's own roles rather than calling `Authorize::isAllowed()`: `load()` runs
     * once per request and bakes the identity's roles into the ACL as it goes, and on this
     * request the guard already triggered that load while the visitor was anonymous. The
     * Symfony side has the same problem for the same reason — `App\Http\AuthorizationListener`
     * consults the same `Authorize` — so nothing about the move makes the shortcut safe.
     */
    public function refusedRoute(string $url, User $user): ?string
    {
        $match = $this->match($url);
        if (! $match instanceof RouteMatch) {
            return null;
        }

        $route = $match->getMatchedRouteName();
        if (! is_string($route) || '' === $route) {
            return null;
        }

        $acl      = $this->authorize()->getAcl();
        $resource = 'route/' . $route;

        //default deny: an unguarded route is reachable by nobody, which is how
        //BjyAuthorize\Guard\Route treats a missing entry
        if (! $acl->hasResource($resource)) {
            return $route;
        }

        //a rule naming the null role means genuinely public, and is asked first so a
        //public route does not depend on the account holding a role the ACL knows
        if ($this->allows($acl, null, $resource)) {
            return null;
        }

        foreach ($this->aclRoles($user) as $role) {
            if ($acl->hasRole($role) && $this->allows($acl, $role, $resource)) {
                return null;
            }
        }

        return $route;
    }

    /**
     * The path and query of a URL, for telling a visitor where they were refused.
     *
     * Never the whole URL: `force_canonical` is on for the emailed link, so this may
     * carry a scheme and host, and a message quoting those reads like a phishing warning
     * rather than a place on this site.
     */
    public function pathOf(string $url): string
    {
        $path  = parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);
        if (! is_string($path) || '' === $path) {
            return $url;
        }

        return is_string($query) && '' !== $query ? $path . '?' . $query : $path;
    }

    /**
     * isAllowed() without the exceptions — laminas throws for an unknown role or
     * resource, and either one means "no rule says yes", which is the same answer as
     * false.
     */
    private function allows(Acl $acl, ?string $role, string $resource): bool
    {
        try {
            return (bool) $acl->isAllowed($role, $resource);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Every role *name* the ACL might hold a rule for, for this account.
     *
     * Two traps, both transcribed rather than rediscovered, and both of which silently
     * produce an empty list — which the caller reads as "refuse the destination".
     *
     * **`linkUser()`, not `getUser()`.** The latter serves rows out of the cached
     * `all-linked-users` map and, on a miss, falls through to `queryObjects('user', …)`,
     * which does not link roles at all. A freshly registered account is exactly what a
     * first sign-in produces and exactly what is not in that cache.
     *
     * **The link row's `name`, not `rolesList`.** Despite the name, `rolesList` is
     * `array_keys($user['roles'])`, i.e. numeric `user_role.id` values — `[7, 16, 23, 40]`
     * for an account holding the four default roles. The ACL is keyed on names.
     *
     * @return list<string>
     */
    private function aclRoles(User $user): array
    {
        $roles = [];
        $row   = ['userId' => (int) $user->getId(), 'roles' => []];
        $this->userTable()->linkUser($row);

        /** @var mixed $links */
        $links = $row['roles'] ?? null;
        if (is_array($links)) {
            foreach ($links as $link) {
                /** @var mixed $name */
                $name = is_array($link) ? ($link['name'] ?? null) : null;
                if (is_string($name) && '' !== $name) {
                    $roles[] = $name;
                }
            }
        }

        //the per-user role JUser\Provider\Role\UserIdRoles contributes
        $roles[] = 'user_' . (int) $user->getId();

        return $roles;
    }

    /**
     * A router whose base URL is empty, **cloned from the shared one**.
     *
     * Not an optimisation and not caution — without it this class's answer depends on
     * whether `App\Laminas\RouteUrl` happened to be used earlier in the same request.
     * `RouteUrl` sets the shared router's base URL to `<base>/<alias>` so that assembled
     * links carry the locale prefix, exactly as SlmLocale does; and
     * `TreeRouteStack::match()` uses `strlen($this->baseUrl)` as a **path offset**. So with
     * the base left at `/en`, matching `/shrines` starts three characters in, looks for
     * `ines`, and finds nothing. Every destination would be refused, but only on pages
     * that had already rendered a link — which is most of them, and would have made this
     * look intermittent.
     *
     * A clone is enough: `match()` reads the route list and writes only `$baseUrl` and
     * `$requestUri`, both of which are the state being isolated. Setting the base to the
     * empty string also stops `match()` adopting the base from the request, which it does
     * when the property is still null.
     *
     * @return TreeRouteStack<HttpRouteInterface>
     */
    private function router(): TreeRouteStack
    {
        /** @var TreeRouteStack<HttpRouteInterface> $shared */
        $shared = $this->laminas->get(RouteStackInterface::class);

        $router = clone $shared;
        $router->setBaseUrl('');

        return $router;
    }

    private function authorize(): Authorize
    {
        /** @var Authorize $authorize */
        $authorize = $this->laminas->get(Authorize::class);

        return $authorize;
    }

    private function userTable(): UserTable
    {
        /** @var UserTable $table */
        $table = $this->laminas->get(UserTable::class);

        return $table;
    }
}
