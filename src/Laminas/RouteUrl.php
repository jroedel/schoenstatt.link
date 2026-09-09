<?php

declare(strict_types=1);

namespace App\Laminas;

use App\Http\SymfonyRoutes;
use App\Locale\Locales;
use Locale;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;

use function array_merge;
use function is_array;
use function array_shift;
use function explode;
use function http_build_query;
use function implode;
use function ltrim;
use function parse_str;
use function rtrim;
use function str_ends_with;
use function strlen;
use function substr;

/**
 * Every URL the application generates.
 *
 * The single seam: ~150 PHP call sites and every `laminas_path()` in a template come
 * through {@see path()}, which is what allowed the engine underneath to be replaced in one
 * commit rather than in two hundred. It assembled laminas routes until step 6 of the
 * laminas exit and generates Symfony ones now.
 *
 * ## The locale prefix is a route, not a base URL
 *
 * laminas produced it by setting the shared router's base URL to `<base>/<alias>` once, the
 * way SlmLocale's UriPathStrategy did. Symfony declares it instead: `shrines` serves
 * `/shrines` and `shrines.locale` serves `/{_locale}/shrines`, so `path()` resolves to the
 * prefixed twin and passes the locale as a parameter. Routes with no twin — the `/api`
 * refusals, which answer their own unprefixed path deliberately — fall back to the plain
 * name.
 *
 * That also retired a hazard rather than moving it: the base URL lived on a *shared*
 * router, so anything else matching or assembling against it in the same request saw
 * whatever locale was set last. Nothing is shared now; the RequestContext belongs to this
 * object.
 *
 * ## Absolute URLs
 *
 * `force_canonical` becomes `ABSOLUTE_URL`, whose scheme and host come off the
 * RequestContext — {@see setCanonicalHost()}, which {@see HostUrls} calls for the one
 * caller that wants one, the emailed sign-in link.
 */
final class RouteUrl
{
    private ?UrlGenerator $generator = null;

    private ?RequestContext $context = null;

    public function __construct(private readonly string $baseUrl)
    {
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $options
     * @param string|null $locale the prefix to assemble under; null means the request's
     *        own, which is what every HTML page wants. `/api/v3` names one explicitly
     *        because an API request carries no locale at all, so Locale::getDefault()
     *        there is whatever php.ini last said rather than anything the caller chose.
     */
    public function path(
        string $routeName,
        array $params = [],
        array $options = [],
        ?string $locale = null
    ): string {
        $routes = SymfonyRoutes::collection();

        //The locale prefix is a **separate route** on the Symfony side — `shrines` serves
        //`/shrines`, `shrines.locale` serves `/{_locale}/shrines` — where laminas produced
        //it by setting one base URL. So the prefixed twin is what reproduces laminas, and
        //the plain name is the fallback for the handful of routes that have no twin (the
        ///api ones, which answer their own unprefixed path on purpose).
        $name = null !== $routes->get($routeName . '.locale') ? $routeName . '.locale' : $routeName;

        if ($name !== $routeName) {
            $params['_locale'] = Locales::aliasFor($locale ?? Locale::getDefault());
        }

        //laminas took a query string as an option; Symfony turns any parameter the route
        //does not consume into one, so they merge into the same array.
        if (isset($options['query']) && is_array($options['query'])) {
            $params = array_merge($params, $options['query']);
        }

        return $this->generator()->generate(
            $name,
            $params,
            ($options['force_canonical'] ?? false)
                ? UrlGeneratorInterface::ABSOLUTE_URL
                : UrlGeneratorInterface::ABSOLUTE_PATH
        );
    }

    /**
     * Scheme and host for `force_canonical`, which Symfony takes from the RequestContext
     * rather than from a request URI on the router.
     *
     * {@see \App\Laminas\HostUrls} is the one caller: an absolute URL is requested only
     * for the links JUser emails, and a console process that asks for one without setting
     * this gets Symfony's `localhost` rather than an exception — which is why HostUrls
     * still refuses first when there is no request to take a host from.
     */
    public function setCanonicalHost(string $scheme, string $host): void
    {
        $context = $this->context();
        $context->setScheme($scheme);
        $context->setHost($host);
    }

    private function generator(): UrlGenerator
    {
        return $this->generator ??= new UrlGenerator(SymfonyRoutes::collection(), $this->context());
    }

    private function context(): RequestContext
    {
        if (null === $this->context) {
            $this->context = new RequestContext();
            //the application's mount point, without the locale segment: that segment is
            //part of each `.locale` route's own path, not part of the base URL
            $this->context->setBaseUrl(rtrim($this->baseUrl, '/'));
        }

        return $this->context;
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

        //the trailing slash is carried across explicitly, because the home page is the
        //one path where it is the only thing left after the locale segment is removed:
        //`/en/` would otherwise come back as `/en`, and every hreflang and canonical
        //link on the site's front page would point one redirect away from the page
        //that declared them. Latent until `welcome` was ported — measured against the
        //laminas rendering, which says `http://localhost/en/`.
        $trailingSlash = str_ends_with($parts[0], '/') ? '/' : '';

        $base     = rtrim($this->baseUrl, '/');
        $relative = ltrim(substr(rtrim($parts[0], '/'), strlen($base)), '/');
        $segments = '' === $relative ? [] : explode('/', $relative);
        if ([] !== $segments && Locales::isAlias($segments[0])) {
            array_shift($segments);
        }

        $result = $base . '/' . Locales::aliasFor($locale);
        $rest   = implode('/', $segments);
        if ('' !== $rest) {
            $result .= '/' . $rest;
        }
        $result .= $trailingSlash;

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
}
