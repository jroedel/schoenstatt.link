<?php

declare(strict_types=1);

namespace App\Authorization;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

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
     * @param string $loginUrl assembled from the `zfcuser/login` laminas route, so it
     *        carries the locale prefix the way SlmLocale makes it
     * @param string $returnTo the path to come back to, query string already dropped
     *        the way laminas drops it (it re-assembles from route parameters)
     */
    public static function signIn(string $loginUrl, string $returnTo): RedirectResponse
    {
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
