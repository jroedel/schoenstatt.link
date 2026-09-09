<?php

declare(strict_types=1);

namespace App\Laminas;

use Laminas\Uri\Http as HttpUri;
use RuntimeException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Route-name-to-URL for a module that declares its own host contract for it.
 *
 * `JUser\Host\UrlBuilderInterface` and `JTranslate\Host\UrlBuilderInterface` declare the
 * same two methods and are deliberately separate interfaces — JUser depends on JTranslate,
 * so JTranslate cannot depend back — which leaves this, the implementation, as the thing
 * that must exist once. The adapters in `src/JUser/Host/` and `src/JTranslate/Host/` are
 * `implements` clauses over this object and hold no logic.
 *
 * Thin over `App\Laminas\RouteUrl`, because that class already does the hard part: it puts
 * the locale prefix back the way SlmLocale does, by setting the router's base URL, so every
 * link a module renders carries `/en` exactly as a link from any other ported page does.
 *
 * Two details are not thin, and both were bought on the JUser port.
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
 * That was invisible for as long as exactly one caller wanted an absolute URL — the emailed
 * sign-in link — because `JUser\Service\MailerFactory` primes the router itself, precisely
 * to avoid it. The priming lives here now, so any module asking for `url()` gets a link that
 * works rather than an exception. It is the one thing on that surface whose breakage shows
 * up on no page at all.
 */
final class HostUrls
{
    public function __construct(
        private readonly RouteUrl $urls,
        private readonly RequestStack $requests
    ) {
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, string> $query
     */
    public function path(string $route, array $params = [], array $query = []): string
    {
        return $this->urls->path($route, $params, $this->options($query));
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, string> $query
     */
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
        //and the same host for the Symfony generator, which is what path() assembles with
        //since step 4 of the laminas exit; it reads scheme and host off the RequestContext.
        $this->urls->setCanonicalHost($request->getScheme(), $request->getHttpHost());
    }
}
