<?php

declare(strict_types=1);

namespace App\Authorization;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

use function rawurlencode;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * The four shapes a refusal can take, and nothing else.
 *
 * Split out of App\Authorization\RouteGuard so the *shape* of a denial can be
 * asserted without a container, a database or a session — which matters more here
 * than it usually would. Everything the guard needs to reach an answer (the ACL, the
 * authentication service) touches laminas-session, and laminas-session cannot be
 * built under the CLI SAPI once anything has been written to stdout: a PHPUnit run
 * has, so `Laminas\Session\Config\ConfigInterface` throws "'session.cache_expire' is
 * not a valid sessions-related ini setting". That is why the guard's *decision* is
 * only ever exercised over HTTP by the smoke suite, and why its *output* is
 * exercised here instead, by test/Integration/RouteDenialShapeTest — which is the
 * only way the JSON branches get covered at all, since no route declares
 * DenialStyle::Json yet.
 *
 * Static and stateless on purpose: there is no decision left to make by the time one
 * of these is called.
 */
final class Denial
{
    public const FORBIDDEN_TEMPLATE = 'error/403.html.twig';

    /** What BjyAuthorize\Guard\Route prefixes a route name with to make a resource. */
    private const ROUTE_RESOURCE_PREFIX = 'route/';

    /**
     * Anonymous visitor, HTML route: 302 to the sign-in page carrying the page they
     * wanted — `/en/user/login?redirect=/en/admin`, measured on the laminas side.
     *
     * ## The query string is carried, and this is a deliberate improvement
     *
     * **Both front controllers used to drop it**, so this is not a parity fix.
     * `JUser\View\RedirectionStrategy` assembles the return trip from
     * `$routeMatch->getParams()` with only `name` in its options — there is no `query`
     * option — so laminas loses it too. Verified on both sides: anonymous
     * `/en/assignments/search?search=Walter` answers
     * `?redirect=/en/assignments/search` on laminas and on Symfony alike.
     *
     * Carrying it matters now because batch 6 ported the routes whose *input is* the
     * query string. A member who follows a bookmarked search, signs in, and lands on a
     * blank contact search has lost their query — and on `/assignments/search` a blank
     * query returns every entity, so they get a 595 KB page instead of their results.
     *
     * ## Why only the query is encoded
     *
     * The path is appended literally and the query percent-encoded, which is not
     * squeamishness about aesthetics — it is what the receiving end accepts.
     * `JUser\Controller\LoginController::validRedirect()` refuses anything that does not
     * `$router->match()`, and it reads the value through `fromQuery('redirect')`, i.e.
     * already percent-decoded. So:
     *
     * - encoding the query is **necessary**: an unencoded `&` would end the `redirect`
     *   parameter and truncate the return trip at the first one.
     * - encoding the path too would be harmless but would rewrite
     *   `?redirect=/en/admin` into `?redirect=%2Fen%2Fadmin` on every guarded route on
     *   the site, for no gain — and that exact string is pinned by several smoke tests
     *   and by nine entries in the baseline diff.
     *
     * Measured against the real guard: with the router's base URL set the way
     * `SlmLocale\Strategy\UriPathStrategy` sets it, `/en/texts?search=Bund` matches route
     * `texts`, so `validRedirect()` returns it intact. A pre-encoded `%3F` does **not**
     * match, which is why the encoding has to be the outer layer PHP strips rather than
     * something baked into the value.
     *
     * @param string $loginUrl assembled from the `zfcuser/login` laminas route, so it
     *        carries the locale prefix the way SlmLocale makes it
     * @param string $returnTo the path to come back to, with no query string
     * @param string|null $query the request's raw query string, or null when there is
     *        none. Appended to $returnTo, percent-encoded.
     */
    public static function signIn(string $loginUrl, string $returnTo, ?string $query = null): RedirectResponse
    {
        if (null !== $query && '' !== $query) {
            $returnTo .= rawurlencode('?' . $query);
        }

        return new RedirectResponse($loginUrl . '?redirect=' . $returnTo, Response::HTTP_FOUND);
    }

    /**
     * Signed in but not allowed, HTML route: 403 with the error page inside the
     * shared layout — which is where laminas puts it too, since
     * BjyAuthorize\View\UnauthorizedStrategy adds its ViewModel as a *child* of the
     * layout's.
     */
    public static function forbiddenPage(Environment $twig, string $resource): Response
    {
        return new Response(
            $twig->render(self::FORBIDDEN_TEMPLATE, [
                'page_title' => '403 Forbidden',
                'subject'    => self::subjectOf($resource),
            ]),
            Response::HTTP_FORBIDDEN
        );
    }

    /**
     * Anonymous visitor, JSON route: 401 and no redirect.
     *
     * Deliberately *not* the 302 laminas sends. A deploy hook or monitor that follows
     * a redirect is handed an HTML sign-in page and reads 200 as success — the exact
     * failure App\Http\MaintenanceKey::refuse() was written to avoid, and the reason
     * DenialStyle is declared per route instead of sniffed from `Accept`. Same
     * `message` key as that class, so one caller can parse both.
     */
    public static function unauthenticatedJson(): JsonResponse
    {
        return new JsonResponse(
            ['message' => 'Unauthorized: this endpoint requires an authenticated session'],
            Response::HTTP_UNAUTHORIZED
        );
    }

    /** Signed in but not allowed, JSON route: 403, naming the resource that refused. */
    public static function forbiddenJson(string $resource): JsonResponse
    {
        return new JsonResponse(
            ['message' => 'Forbidden: you are not authorized to access ' . self::subjectOf($resource)],
            Response::HTTP_FORBIDDEN
        );
    }

    /**
     * What the sentence names. bjy-authorize's own 403 template prints the *route
     * name* ("You are not authorized to access admin."), because the guard hands it
     * the matched route rather than the resource it derived from it. Reproduced, so
     * the two front controllers say the same thing about the same page.
     */
    private static function subjectOf(string $resource): string
    {
        return str_starts_with($resource, self::ROUTE_RESOURCE_PREFIX)
            ? substr($resource, strlen(self::ROUTE_RESOURCE_PREFIX))
            : $resource;
    }
}
