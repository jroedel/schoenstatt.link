<?php

declare(strict_types=1);

namespace App\Controller;

use App\JUser\SignIn;
use App\Laminas\RouteUrl;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * `/user/logout` — sign out.
 *
 * `LoginController::logoutAction()`, ported, and the three calls are all of it:
 * `clearIdentity()` empties the auth storage, `forgetMe()` drops the remember-me lifetime
 * off the session cookie, and `regenerateId(true)` issues a new session id and **deletes
 * the old one server-side**, so the id that was authenticated a moment ago cannot be
 * replayed.
 *
 * Guarded `user`, i.e. any signed-in account. An anonymous visitor asking for it is
 * redirected to the sign-in page by the guard rather than being quietly told "done" —
 * `test/Smoke/UserSmokeTest::testLogoutRedirectsToLogin` covers the signed-in shape and
 * `RouteDenialShapeTest` the anonymous one.
 *
 * **No consent gate here**, unlike the other two auth routes, and that is the laminas
 * behaviour rather than an omission: `GdprStrategy::onRoute()` names only `zfcuser/login`
 * and `zfcuser/verify`. Signing *out* needs no cookie to be set — it needs one removed —
 * and an unconsented visitor has no session to end anyway.
 */
final class LogoutController
{
    public function __construct(
        private readonly SignIn $signIn,
        private readonly RouteUrl $urls
    ) {
    }

    public function __invoke(): RedirectResponse
    {
        $this->signIn->authService()->clearIdentity();
        $this->signIn->sessionManager()->forgetMe();
        $this->signIn->sessionManager()->regenerateId(true);

        return new RedirectResponse(
            $this->urls->path($this->signIn->logoutRedirectRoute()),
            Response::HTTP_FOUND
        );
    }
}
