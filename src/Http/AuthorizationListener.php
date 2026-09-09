<?php

declare(strict_types=1);

namespace App\Http;

use App\Authorization\RouteGuard;
use Closure;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Runs the authorization check for every Symfony-served route, on
 * `kernel.request`.
 *
 * ## Why kernel.request and not kernel.controller
 *
 * Both fire for every routed request, so both are bypass-proof against a route
 * added later — that property comes from listening to the *event* rather than
 * decorating a controller, and no entry in `config/symfony/routes.php` can opt out
 * of it. `kernel.request` is the earlier of the two, and earlier is better here for
 * a reason this codebase has already been bitten by: `kernel.controller` fires
 * *after* `ContainerControllerResolver` has resolved and therefore **constructed**
 * the controller, so a refused request would still run its factory. On the laminas
 * side that exact shape — a controller built before the request was known to be
 * allowed — is the "eager controller_services trap" that 500'd every action of a
 * controller once already. Refusing before anything is built keeps a denial free of
 * side effects.
 *
 * It is also where laminas does it. `BjyAuthorize\Guard\Route` listens on
 * `MvcEvent::EVENT_ROUTE`, i.e. immediately after routing and before dispatch,
 * which is what `kernel.request` after `RouterListener` is.
 *
 * ## Priority
 *
 * Below `RouterListener`'s 32, because the check reads `_route` and the route
 * defaults and there is nothing to read until routing has happened; and below
 * `App\Http\LocaleListener`'s default 0, because the 403 page is rendered through
 * Twig and every translated string in the layout would otherwise come out in
 * `en_US_POSIX`. Stated as an
 * explicit negative number rather than left to registration order, so that adding a
 * listener cannot silently reorder these two.
 */
final class AuthorizationListener
{
    /**
     * Fires after RouterListener (32) and LocaleListener (0). Any listener that
     * needs to run *before* the check must sit above this, and must not answer the
     * request itself.
     */
    public const PRIORITY = -16;

    /**
     * @param Closure(): RouteGuard $guard resolved when the listener fires rather
     *        than when it is registered. App\Kernel builds the guard from the same
     *        lazily-shared RouteUrl a ported controller gets, and that object reads
     *        the request's base URL — at registration time there is no request yet,
     *        so building it then would memoize an empty base URL for the whole
     *        request.
     */
    public function __construct(private readonly Closure $guard)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        //sub-requests inherit the main request's authorization: nothing issues one
        //today, and a forwarded render must not be able to re-open the question
        if (! $event->isMainRequest()) {
            return;
        }

        $refusal = ($this->guard)()->refuse($event->getRequest());
        if (null !== $refusal) {
            //stops propagation, so no controller is resolved and no later
            //kernel.request listener runs — the response goes straight to
            //kernel.response, where the CSP, GDPR and Cache-Control listeners still
            //see it
            $event->setResponse($refusal);
        }
    }
}
