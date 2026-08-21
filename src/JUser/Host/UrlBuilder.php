<?php

declare(strict_types=1);

namespace App\JUser\Host;

use App\Laminas\RouteUrl;
use JUser\Host\UrlBuilderInterface;
use Laminas\Uri\Http as HttpUri;
use Symfony\Component\HttpFoundation\RequestStack;
use RuntimeException;

/**
 * `JUser\Host\UrlBuilderInterface` over `App\Laminas\RouteUrl`.
 *
 * Thin, because `RouteUrl` already does the hard part: it puts the locale prefix back the
 * way SlmLocale does, by setting the router's base URL, so every link JUser renders carries
 * `/en` exactly as a link rendered by any other ported page does.
 *
 * Two details are not thin.
 *
 * ## The query is only passed when there is one
 *
 * `TreeRouteStack::assemble()` tests `isset($options['query'])`, not whether it is empty, so
 * handing it `[]` calls `Uri::setQuery([])` on every single link. Harmless today and exactly
 * the kind of thing that stops being harmless, so the key is omitted instead.
 *
 * ## `force_canonical` needs a request URI, and nothing under Symfony sets one
 *
 * An absolute URL is assembled by filling in the scheme and host from the router's
 * `requestUri` — which `TreeRouteStack::match()` sets, and which therefore is **never set on
 * a Symfony-served request**: nothing matches against that router. Left alone, `assemble()`
 * with `force_canonical` throws `Request URI has not been set`.
 *
 * That has been true all along and was invisible because exactly one caller wanted an
 * absolute URL — the emailed sign-in link — and `JUser\Service\MailerFactory` primes the
 * router itself, from the laminas `Request` service, precisely to avoid it. Now that the
 * link comes from here instead, the priming has to come with it. Taken from the Symfony
 * request rather than from the laminas one: the two agree, and the request this module is
 * answering is the honest source.
 */
final class UrlBuilder implements UrlBuilderInterface
{
    public function __construct(
        private readonly RouteUrl $urls,
        private readonly RequestStack $requests
    ) {
    }

    public function path(string $route, array $params = [], array $query = []): string
    {
        return $this->urls->path($route, $params, $this->options($query));
    }

    public function url(string $route, array $params = [], array $query = []): string
    {
        $this->primeRequestUri();

        return $this->urls->path($route, $params, $this->options($query) + ['force_canonical' => true]);
    }

    /**
     * @param array<string, string> $query
     * @return array<string, mixed>
     */
    private function options(array $query): array
    {
        return [] === $query ? [] : ['query' => $query];
    }

    /**
     * See the class docblock: without this, an absolute URL is an exception.
     *
     * It **sets** rather than checks-then-sets, which is both shorter and the only version
     * static analysis can read: `TreeRouteStack::getRequestUri()` has no declared return
     * type and its property is annotated non-nullable, so `null !== $router->getRequestUri()`
     * is an always-true branch to PHPStan and everything after it unreachable — which is
     * also why `JUser\Service\MailerFactory` reaches for `isset()` there.
     *
     * Overwriting is safe: `assemble()` reads only the scheme and host off this URI, and the
     * request being answered is the same request whatever set it first.
     */
    private function primeRequestUri(): void
    {
        $request = $this->requests->getMainRequest();
        if (null === $request) {
            //A console process asking for an absolute URL has no host to put in it, and
            //guessing one would put a wrong link in an email. `bin/console` has no route
            //that does this; if one appears, it must be given a base URL explicitly.
            throw new RuntimeException(
                'An absolute URL was requested with no HTTP request to take the host from.'
            );
        }

        $this->urls->router()->setRequestUri(new HttpUri($request->getSchemeAndHttpHost()));
    }
}
