<?php

declare(strict_types=1);

namespace App\Laminas;

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
 * A leftover from the laminas router, which tested `isset($options['query'])` rather than
 * whether it was empty and so called `Uri::setQuery([])` on every link. Kept because it is
 * still the honest shape: "no query" and "an empty query" are different things to say.
 *
 * ## An absolute URL needs a host, and a console process has none
 *
 * `url()` fills in the scheme and host from the request being answered. Under laminas that
 * meant priming a `requestUri` on the router — never set on a Symfony-served request, since
 * nothing matched against it — and `assemble()` threw `Request URI has not been set`
 * without one. Symfony takes them from the RequestContext instead, and would quietly answer
 * `localhost` if they were absent.
 *
 * So the refusal here is deliberate and is the interesting part: with no request there is no
 * host, and guessing one puts a wrong link in an email rather than raising. It is the one
 * thing on this surface whose breakage shows up on no page at all — exactly one caller wants
 * an absolute URL, the emailed sign-in link.
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
     * Scheme and host for an absolute URL, which Symfony's generator takes from the
     * RequestContext.
     *
     * This used to prime a request URI on the laminas router as well, because
     * `TreeRouteStack::assemble()` with `force_canonical` threw `Request URI has not been
     * set` without one. Nothing assembles through that router since step 6, so only the
     * RequestContext is set now.
     *
     * Still a hard refusal when there is no request: a console process asking for an
     * absolute URL has no host to put in it, and Symfony would quietly answer `localhost`
     * rather than raise — which is a wrong link in an email instead of a stack trace.
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

        $this->urls->setCanonicalHost($request->getScheme(), $request->getHttpHost());
    }
}
