<?php

declare(strict_types=1);

namespace App\Laminas;

use App\Locale\Locales;
use Laminas\Router\Http\RouteInterface as HttpRouteInterface;
use Laminas\Router\Http\TreeRouteStack;
use Locale;

use function array_merge;
use function array_shift;
use function explode;
use function http_build_query;
use function implode;
use function ltrim;
use function parse_str;
use function rtrim;
use function strlen;
use function substr;

/**
 * Assembles URLs for laminas routes from a Symfony-served request.
 *
 * This is the single reason a Twig layout is possible at all. Rendering any
 * existing .phtml dies in the `url` view helper, which needs an MvcEvent for its
 * RouteMatch — but the **router itself** needs nothing: `Router` resolves out of
 * App\Laminas\ServiceBridge with no bootstrap and assembles happily. So every
 * link on a ported page to a not-yet-ported page goes through here, and the pages
 * stay linked while the migration is half done.
 *
 * The locale prefix is put back the way SlmLocale\Strategy\UriPathStrategy does
 * it: by setting the router's base URL to `<base>/<alias>` once, after which every
 * assembled path carries it. Doing it that way rather than concatenating a prefix
 * onto each result means routes that are themselves locale-aware — there are none
 * today, but the route tree is free to grow some — behave the same under both
 * front controllers.
 *
 * `Router` is a shared service inside one request's ServiceBridge and nothing else
 * holds a reference to it on a ported route, so mutating its base URL is not
 * reaching into someone else's state; on a bridged request this class is never
 * built.
 */
final class RouteUrl
{
    /** @var TreeRouteStack<HttpRouteInterface>|null */
    private ?TreeRouteStack $router = null;

    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly string $baseUrl
    ) {
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $options
     */
    public function path(string $routeName, array $params = [], array $options = []): string
    {
        return $this->router()->assemble($params, array_merge($options, ['name' => $routeName]));
    }

    /**
     * The given path, re-pointed at another locale — what SlmLocale's `localeUrl`
     * view helper produces for the language chooser and the hreflang links.
     *
     * Implemented against the request path rather than by re-assembling the route,
     * because that is what the helper itself falls back to when there is no
     * RouteMatch, and a ported route has none. A `lang` query parameter is dropped
     * for the same reason the laminas layout drops it: it is a locale override, and
     * carrying it into a link that already names the locale would fight it.
     */
    public function localized(string $path, string $locale): string
    {
        $parts = explode('?', $path, 2);
        $query = $parts[1] ?? '';

        $base     = rtrim($this->baseUrl, '/');
        $relative = ltrim(substr($parts[0], strlen($base)), '/');
        $segments = '' === $relative ? [] : explode('/', $relative);
        if ([] !== $segments && Locales::isAlias($segments[0])) {
            array_shift($segments);
        }

        $result = $base . '/' . Locales::aliasFor($locale);
        $rest   = implode('/', $segments);
        if ('' !== $rest) {
            $result .= '/' . $rest;
        }

        if ('' !== $query) {
            /** @var array<string, mixed> $params */
            $params = [];
            parse_str($query, $params);
            unset($params['lang']);
            if ([] !== $params) {
                $result .= '?' . http_build_query($params);
            }
        }

        return $result;
    }

    /** @return TreeRouteStack<HttpRouteInterface> */
    private function router(): TreeRouteStack
    {
        if (null !== $this->router) {
            return $this->router;
        }

        /** @var TreeRouteStack<HttpRouteInterface> $router */
        $router = $this->laminas->get('Router');
        $router->setBaseUrl(rtrim($this->baseUrl, '/') . '/' . Locales::aliasFor(Locale::getDefault()));

        return $this->router = $router;
    }
}
