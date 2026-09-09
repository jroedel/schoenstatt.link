<?php

declare(strict_types=1);

namespace App\JUser\Host;

use App\Http\SymfonyRoutes;
use App\Locale\Locales;
use JUser\Host\RouteResolverInterface;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Throwable;

use function explode;
use function implode;
use function in_array;
use function is_string;
use function ltrim;
use function parse_url;
use function str_starts_with;

use const PHP_URL_PATH;
use const PHP_URL_QUERY;

/**
 * `JUser\Host\RouteResolverInterface` over the Symfony router.
 *
 * **This class is where the four hard-won details of the old `App\JUser\RedirectTarget`
 * live now**, and it is the reason that class could shrink from 318 lines to 113. None of
 * them is JUser's business; every one of them is a fact about *this* application's routing,
 * and getting any of them wrong refuses every destination on the site while nothing fails
 * anywhere — "no route" is a legitimate answer.
 *
 * ## The locale prefix has to come off first
 *
 * Every `?redirect=` the route guards emit carries the locale prefix, and the matcher is
 * asked about the *unprefixed* path: the prefixed twin of a route is a separate declaration
 * (`shrines` serves `/shrines`, `shrines.locale` serves `/{_locale}/shrines`), and stripping
 * first means one answer rather than a name that has to be un-suffixed afterwards.
 *
 * Getting this wrong refuses every destination on the site while nothing fails anywhere —
 * "no route" is a legitimate answer. The *first* attempt at this, against the laminas
 * router, did exactly that: it asked about the prefixed path, matched nothing, and sent
 * every visitor to the home page after signing in.
 *
 * ## Why the Symfony router, since step 6
 *
 * It used to be the laminas one, on a clone with an empty base URL — because
 * `TreeRouteStack::match()` uses `strlen($this->baseUrl)` as a **path offset**, so a shared
 * router whose base `App\Laminas\RouteUrl` had already set to `/en` matched `/shrines`
 * three characters in, looked for `ines`, and found nothing. That made the answer depend on
 * whether a link had been rendered earlier in the same request. Symfony's matcher holds no
 * such state, so the hazard is gone rather than worked around.
 *
 * The old reason for preferring laminas — that it knew the whole site while Symfony knew
 * only the ported part — expired when the last route was ported. Symfony now knows every
 * route it serves, and two names it does **not** answer are handled explicitly below.
 *
 * Measured against the laminas router over a corpus generated from the route collection:
 * 111 paths resolve to the same name, and the differences are understood — see
 * test/Integration/RouteMatchParityTest.
 */
final class RouteResolver implements RouteResolverInterface
{
    public function __construct()
    {
    }

    /**
     * Routes that match but are not destinations.
     *
     * `not-found` is the catch-all and matches **every** path, which is the one thing that
     * cannot be ported naively: the laminas router answered null for a path it did not
     * recognise, and this must keep doing that. Without the exclusion `valid()` would
     * accept any string beginning with a slash as a redirect target, which is not the
     * open redirect its other guards prevent but is still "nowhere to go" reported as
     * somewhere. The `/api` refusals are excluded for the same reason.
     */
    private const NOT_A_DESTINATION = [
        'not-found',
        'api-not-found',
        'api-not-found/rest',
    ];

    /**
     * Symfony route name → the name the **ACL** knows, where the two differ.
     *
     * The answer goes to `JUser\Page\RedirectTarget::refusedRoute()`, which hands it to
     * `Access::userMayReachRoute()` — and that looks up `route/<name>` and **denies when the
     * resource does not exist**. The ACL is keyed on the laminas route names throughout, so
     * returning Symfony's name for a page the two spell differently would tell a signed-in
     * user they may not reach a page they can, with nothing failing anywhere.
     *
     * Two routes are affected and both were renamed when they were ported.
     * test/Integration/RouteMatchParityTest compares every route against the laminas router
     * and fails if this map is ever incomplete, so a third divergence cannot arrive quietly.
     */
    private const ACL_NAME = [
        'sm-cache-status'           => 'sion-model/cache-status',
        'sm-clear-persistent-cache' => 'sion-model/clear-persistent-cache',
    ];

    public function routeFor(string $path): ?string
    {
        $matcher = new UrlMatcher(SymfonyRoutes::collection(), new RequestContext());

        //**The matcher takes a path, not a URI.** The laminas router was handed a
        //`Laminas\Http\Request` and parsed the query off itself; Symfony's matches the
        //string it is given, so `/assignments/search?search=Walter` matches nothing and
        //every `?redirect=` carrying a query — which is most of the ones worth returning
        //to — would be refused as "no route". Caught by the sign-in smoke tests.
        $withoutPrefix = $this->withoutLocalePrefix($path);
        $pathOnly      = parse_url($withoutPrefix, PHP_URL_PATH);
        if (! is_string($pathOnly) || '' === $pathOnly) {
            return null;
        }

        try {
            $match = $matcher->match($pathOnly);
        } catch (Throwable) {
            //ResourceNotFoundException for a path no route claims, MethodNotAllowedException
            //for one whose route exists but not for a GET — `comments/create` is POST-only,
            //and a browser cannot be redirected there either way. The laminas router named
            //it, having no method constraint to consult; nothing asked it to.
            return null;
        }

        $route = $match['_route'] ?? null;
        if (! is_string($route) || '' === $route || in_array($route, self::NOT_A_DESTINATION, true)) {
            return null;
        }

        return self::ACL_NAME[$route] ?? $route;
    }

    /**
     * `/en/shrines` → `/shrines`; anything else unchanged.
     *
     * Only a *leading* segment that is a known alias, and only when it is the whole segment:
     * `/entity/1` must not lose its first four characters. `/en` alone becomes `/`, because
     * the empty path is not something the router can match and the locale home page is
     * `welcome`.
     *
     * The query string is kept, because a route can match on it and because dropping it here
     * would silently answer a different question from the one the caller asked.
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
}
