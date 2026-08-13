<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Http\SymfonyRoute;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use BjyAuthorize\Service\Authorize;
use Closure;
use Laminas\Authentication\AuthenticationService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

use function is_string;

/**
 * The route guard for the Symfony front controller — the counterpart of
 * `BjyAuthorize\Guard\Route`, which cannot run here.
 *
 * ## Why anything has to exist here at all
 *
 * `BjyAuthorize\Guard\Route` is a listener on `MvcEvent::EVENT_ROUTE`. A
 * Symfony-served route never boots laminas-mvc — `App\Http\LegacyBridge` is what
 * calls `Application::init()` — so the guard is simply not in the request. That is
 * not a visible failure: the page renders, and it renders for everyone. Of the
 * site's 191 routes, 149 restrict access, and until this class existed not one of
 * them could be ported, because porting it would have silently published it.
 *
 * ## What it reproduces, and the one place it deliberately differs
 *
 * The denial contract is `JUser\View\RedirectionStrategy::onDispatchError()`, which
 * has two branches — measured against the live capsule on `/en/admin` before this
 * was written, not read off the source:
 *
 *   * **No identity** → `302` to `zfcuser/login` with the page the visitor wanted
 *     in `?redirect=`. Observed: `/en/user/login?redirect=/en/admin`. The redirect
 *     value carries the locale prefix, because laminas gets it by re-assembling the
 *     matched route and SlmLocale has hooked assembly.
 *   * **Identity, but not allowed** → `403` rendering the `error/403` template
 *     inside the site layout. Observed body: `<h1>403 Forbidden</h1>` and
 *     "You are not authorized to access admin."
 *
 * Two differences, both intentional: `templates/error/403.html.twig` replaces
 * bjy-authorize's own `view/error/403.phtml`, so that retiring that abandoned
 * package cannot take the refusal page with it, and a `DenialStyle::Json` route
 * answers `401`/`403` with a JSON body instead of redirecting. Both shapes live in
 * App\Authorization\Denial, which is where the reasoning for each is written down.
 *
 * ## Identity is not the missing piece
 *
 * Only the *guard* was missing. Asking BjyAuthorize anything makes it ask JUser for
 * the identity, JUser reads the session and reading the session starts it, so
 * `isAllowed()` answers `guest` for an anonymous visitor and the real roles for a
 * signed-in one with no MvcEvent anywhere. The authentication service is read
 * directly here rather than through `Authorize`, because `Authorize::getIdentity()`
 * returns the literal string `bjyauthorize-identity` — the meta-role, not the user
 * — and branching on its truthiness would send every visitor to the 403 page.
 */
final class RouteGuard
{
    /** The laminas route the denial redirects an anonymous visitor to. */
    private const LOGIN_ROUTE = 'zfcuser/login';

    /**
     * @param Closure(): Environment $twig deferred on purpose. Only the 403 branch
     *        renders anything, and building the environment eagerly would put two
     *        stat() calls (TwigFactory's cache-writability probe) on every request
     *        including /_health, whose entire contract is that it costs nothing.
     */
    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly RouteUrl $urls,
        private readonly Closure $twig
    ) {
    }

    /**
     * The response to send *instead* of dispatching, or null when the request may
     * proceed.
     *
     * A bridged request returns null immediately: laminas-mvc runs its own guard
     * inside `LegacyBridge`, and checking here as well would mean two guards
     * disagreeing about one request.
     *
     * @throws UndeclaredRouteAccess when a Symfony-served route declares nothing.
     */
    public function refuse(Request $request): ?Response
    {
        if (! SymfonyRoute::isPorted($request)) {
            return null;
        }

        $access = self::declaredAccess($request);
        if (null === $access->resource) {
            return null;
        }
        if ($this->authorize()->isAllowed($access->resource)) {
            return null;
        }

        return $this->deny($request, $access->resource, $access->denialStyle);
    }

    /**
     * The route's declaration, or an exception naming the route.
     *
     * @throws UndeclaredRouteAccess
     */
    public static function declaredAccess(Request $request): RouteAccess
    {
        $access = $request->attributes->get(RouteAccess::ATTRIBUTE);
        if ($access instanceof RouteAccess) {
            return $access;
        }

        $route = $request->attributes->get('_route');

        throw UndeclaredRouteAccess::forRoute(
            is_string($route) ? $route : '(unnamed)',
            $request->getPathInfo()
        );
    }

    /**
     * Which of the four shapes in App\Authorization\Denial this refusal takes.
     *
     * The identity is what picks the branch within a style, and it is read here
     * rather than inferred from the failed `isAllowed()`: an anonymous visitor and a
     * signed-in one who lacks the role both fail that check, and sending the second
     * to the sign-in page would loop them straight back.
     */
    private function deny(Request $request, string $resource, DenialStyle $style): Response
    {
        $authenticated = null !== $this->identity();

        if (DenialStyle::Json === $style) {
            return $authenticated ? Denial::forbiddenJson($resource) : Denial::unauthenticatedJson();
        }

        return $authenticated
            ? Denial::forbiddenPage(($this->twig)(), $resource)
            : Denial::signIn(
                $this->urls->path(self::LOGIN_ROUTE),
                $this->returnPath($request),
                $request->getQueryString()
            );
    }

    /**
     * Where the sign-in page should send the visitor back to.
     *
     * The request's own path, not a re-assembly of the matched route. Laminas has to
     * re-assemble, because an MvcEvent hands it a route name and parameters rather
     * than a URL; it wraps that in try/catch and falls back to the bare login URL,
     * since a numeric route name (`'404'`) arrives as an int and the router's
     * explode() turns a mere denial into a TypeError. Reading the path cannot fail
     * that way, so the fallback branch has nothing left to guard and is not
     * reproduced — and the value is identical, locale prefix included, which is what
     * was measured on the laminas side (`?redirect=/en/admin`).
     *
     * **The path only.** The query string is added by `Denial::signIn()`, which has to
     * encode it — see that method for why the two halves are treated differently and why
     * carrying it at all is a deliberate improvement over laminas rather than a parity
     * fix. This used to say the query "is dropped, matching laminas", which was true and
     * is no longer what we want: batch 6 ported the routes whose input *is* the query.
     *
     * `getQueryString()` rather than a re-assembly of `$request->query`: it returns the
     * raw string the client sent, normalised only in key order, so a value that arrived
     * percent-encoded stays that way instead of being decoded and re-encoded here.
     */
    private function returnPath(Request $request): string
    {
        return $request->getBaseUrl() . $request->getPathInfo();
    }

    /**
     * The signed-in user, or null. Deliberately the same question
     * JUser\View\RedirectionStrategy asks, because it is what decides which of the
     * two denial branches a visitor gets.
     */
    private function identity(): mixed
    {
        /** @var AuthenticationService $auth */
        $auth = $this->laminas->get('JUser\AuthService');

        return $auth->getIdentity();
    }

    private function authorize(): Authorize
    {
        /** @var Authorize $authorize */
        $authorize = $this->laminas->get(Authorize::class);

        return $authorize;
    }
}
