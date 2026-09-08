<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\Request;

use function is_string;
use function str_ends_with;
use function strlen;
use function substr;

/**
 * Which of the two front controllers is answering *this* request, and under which
 * name.
 *
 * Every kernel listener needs this question answered, because `legacy` is a route
 * like any other: a listener that fires on it would be duplicating something
 * laminas-mvc already does inside App\Http\LegacyBridge (set the locale, strip
 * cookies, send a CSP header). Asking `_route` is the only honest way to tell,
 * since by the time kernel.response runs the response bodies look alike.
 *
 * The name convention is the other half: a ported route is named after the
 * laminas route it shadows, and its locale-prefixed twin adds `.locale`. So
 * `routeName()` answers `shrines` for both `/shrines` and `/en/shrines`, which is
 * what the layout compares against to mark a navigation item active — no
 * controller has to remember to pass it.
 */
final class SymfonyRoute
{
    /** The catch-all route name — App\Controller\NotFoundController, formerly the LegacyBridge. */
    public const CATCH_ALL_ROUTE = 'not-found';
    public const LOCALE_SUFFIX = '.locale';

    /** True when Symfony itself is serving this request rather than bridging it to laminas-mvc. */
    public static function isPorted(Request $request): bool
    {
        $route = $request->attributes->get('_route');

        return is_string($route) && self::CATCH_ALL_ROUTE !== $route;
    }

    /** The laminas route name a ported route stands in for, or '' when the request is bridged. */
    public static function routeName(Request $request): string
    {
        $route = $request->attributes->get('_route');
        if (! is_string($route) || self::CATCH_ALL_ROUTE === $route) {
            return '';
        }

        return str_ends_with($route, self::LOCALE_SUFFIX)
            ? substr($route, 0, -1 * strlen(self::LOCALE_SUFFIX))
            : $route;
    }
}
