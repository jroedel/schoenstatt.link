<?php

declare(strict_types=1);

namespace App\JUser\Host;

use App\Laminas\ServiceBridge;
use App\Locale\Locales;
use JUser\Host\RouteResolverInterface;
use Laminas\Http\Request as LaminasRequest;
use Laminas\Router\Http\RouteInterface as HttpRouteInterface;
use Laminas\Router\Http\TreeRouteStack;
use Laminas\Router\RouteMatch;
use Laminas\Router\RouteStackInterface;
use Throwable;

use function explode;
use function implode;
use function is_string;
use function ltrim;
use function parse_url;
use function str_starts_with;

use const PHP_URL_PATH;
use const PHP_URL_QUERY;

/**
 * `JUser\Host\RouteResolverInterface` over the laminas router.
 *
 * **This class is where the four hard-won details of the old `App\JUser\RedirectTarget`
 * live now**, and it is the reason that class could shrink from 318 lines to 113. None of
 * them is JUser's business; every one of them is a fact about *this* application's routing,
 * and getting any of them wrong refuses every destination on the site while nothing fails
 * anywhere — "no route" is a legitimate answer.
 *
 * ## The locale prefix has to come off first
 *
 * Under a laminas dispatch the router only ever sees `/shrines`, because SlmLocale's
 * listener strips the locale segment off the request and sets the router's base URL. Under a
 * Symfony dispatch no MVC listener runs at all, so the router sees exactly what it is handed
 * — measured through `App\Laminas\ServiceBridge` on 2026-08-21:
 *
 *     /en/shrines  => NO MATCH          /shrines => shrines
 *     /en/users    => NO MATCH          /        => welcome
 *     /en/         => NO MATCH
 *
 * Every `?redirect=` the route guards emit carries the prefix. So the *first* attempt at
 * this, which asked the router directly, refused every destination and sent every visitor to
 * the home page after signing in — silently, and looking exactly like the "three redirects,
 * no explanation" defect that had just been fixed.
 *
 * ## On a clone, with an empty base URL
 *
 * Not caution: without it the answer depends on whether `App\Laminas\RouteUrl` happened to
 * assemble a link earlier in the same request. `RouteUrl` sets the shared router's base URL
 * to `<base>/<alias>` so that assembled links carry the prefix — and
 * `TreeRouteStack::match()` uses `strlen($this->baseUrl)` as a **path offset**. With the base
 * left at `/en`, matching `/shrines` starts three characters in, looks for `ines`, and finds
 * nothing. Every destination refused, but only on pages that had already rendered a link —
 * which is most of them, and would have made this look intermittent.
 *
 * A clone is enough: `match()` reads the route list and writes only `$baseUrl` and
 * `$requestUri`, which are exactly the state being isolated. Setting the base to the empty
 * string also stops `match()` adopting it from the request, which it does while the property
 * is null.
 *
 * ## The laminas router is still the right one to ask
 *
 * Not the Symfony one, and not both. Every ported route keeps a laminas route declared —
 * that is the `$ported()` contract in config/symfony/routes.php, and it is what lets
 * `laminas_path()` and the BjyAuthorize guards go on naming them — so the laminas router
 * knows the whole site while the Symfony router knows only the ported part. The day a Symfony
 * route ships with no laminas twin, this has to consult both, and the symptom will be that
 * signing in towards that page lands on the home page instead.
 */
final class RouteResolver implements RouteResolverInterface
{
    public function __construct(private readonly ServiceBridge $laminas)
    {
    }

    public function routeFor(string $path): ?string
    {
        try {
            $request = new LaminasRequest();
            $request->setUri($this->withoutLocalePrefix($path));
            $match = $this->router()->match($request);
        } catch (Throwable) {
            //the router throws for a malformed URI and for a part route that may not
            //terminate; either way the answer is "not a destination"
            return null;
        }

        if (! $match instanceof RouteMatch) {
            return null;
        }

        $route = $match->getMatchedRouteName();

        return is_string($route) && '' !== $route ? $route : null;
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

    /** @return TreeRouteStack<HttpRouteInterface> */
    private function router(): TreeRouteStack
    {
        /** @var TreeRouteStack<HttpRouteInterface> $shared */
        $shared = $this->laminas->get(RouteStackInterface::class);

        $router = clone $shared;
        $router->setBaseUrl('');

        return $router;
    }
}
