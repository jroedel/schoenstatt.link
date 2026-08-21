<?php

declare(strict_types=1);

namespace App\Controller;

use App\JUser\CookieExplainer;
use App\JUser\RedirectTarget;
use App\JUser\SignIn;
use App\Laminas\RouteUrl;
use JUser\Controller\LoginController;
use JUser\Model\User;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;
use Laminas\Session\Container as SessionContainer;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Error\Error as TwigError;

use function is_string;
use function sprintf;
use function trim;

/**
 * `GET /user/verify?token=…` — redeem a sign-in link.
 *
 * `LoginController::verifyAction()`, ported. **The one request on this site that creates an
 * authenticated session**, which is why every branch below is spelled out rather than
 * collapsed.
 *
 * ## Order of operations, and none of it is arbitrary
 *
 *  1. **consent first.** No cookies means no session to write the identity into, so the
 *     explainer is served at this URL with a 200 and the token is left unspent — the link
 *     still works once the visitor consents. Reproduces
 *     `Application\View\GdprStrategy::onRoute()`, which does not run for a ported route.
 *  2. **redeem, which burns the token.** `redeemToken()` refuses an unknown, malformed or
 *     expired token and consumes a valid one in the same call.
 *  3. **refuse a deactivated account.** After the burn, deliberately: answering the
 *     question needs the row, and the row is what `redeemToken()` looks up. Spending a
 *     rejected credential is the right outcome anyway.
 *  4. **regenerate the session id**, then write the identity. Session fixation: an
 *     attacker who fixed the id before this holds an authenticated session after it,
 *     unless the id changes at the moment the privilege level does.
 *     `test/Smoke/AuthSmokeTest::testSigningInIssuesANewSessionId` pins it.
 *  5. **check the destination while there is still a page to say it on** — see below.
 *
 * ## Signed in, and not allowed where you were going
 *
 * Two different facts, and before 2026-08-20 the second silently ate the first: a visitor
 * who asked for `/en/admin` without an account got a link, clicked it, was signed in, was
 * bounced by the route guard back to the sign-in page — which, now that they had an
 * identity, redirected them to the welcome page. Three redirects, no explanation, and the
 * natural reading is that the link did not work.
 *
 * So the destination is checked here and reported with **two** flash messages, one good
 * and one bad, because collapsing them loses the half the visitor needs most: that they
 * *are* signed in and need not try the link again — they could not, it is single-use.
 *
 * `App\JUser\RedirectTarget::refusedRoute()` is what answers it, and it asks about the
 * account's roles rather than calling `Authorize::isAllowed()`. That is not a shortcut
 * avoided out of caution: `load()` bakes the identity's roles into the ACL once per
 * request, and on this request it already ran while the visitor was anonymous. Its
 * docblock has the measurement.
 */
final class VerifyController
{
    /**
     * The session key the destination is remembered under.
     *
     * `redirect`, which is what `LoginController` writes — the laminas controller is kept
     * for one release as the rollback path, so both must name the same slot or a visitor
     * caught across the flip loses where they were going.
     */
    public const DESTINATION = 'redirect';

    public function __construct(
        private readonly SignIn $signIn,
        private readonly RedirectTarget $targets,
        private readonly CookieExplainer $explainer,
        private readonly Environment $twig,
        private readonly RouteUrl $urls
    ) {
    }

    /** @throws TwigError */
    public function __invoke(Request $request): Response
    {
        if ($this->signIn->wantsCookiesFirst($request)) {
            return $this->explainer->response();
        }

        /** @var mixed $token */
        $token = $request->query->get('token');
        if (! is_string($token) || '' === trim($token)) {
            return $this->failed();
        }

        $user = $this->signIn->tokens()->redeemToken($token);
        if (! $user instanceof User) {
            return $this->failed();
        }

        if (1 != $user->getState()) {
            $this->signIn->logger()?->notice(
                'JUser: Refused a login link for a deactivated account.',
                ['userId' => $user->getId()]
            );

            return $this->deactivated();
        }

        //new privilege level, new session id
        $this->signIn->sessionManager()->regenerateId(true);
        $this->signIn->authService()->getStorage()->write((int) $user->getId());

        $this->signIn->logger()?->info(
            'JUser: A user signed in with a login link.',
            ['userId' => $user->getId()]
        );

        $destination = $this->destination($request);

        $refused = $this->targets->refusedRoute($destination, $user);
        if (null !== $refused) {
            $this->signIn->logger()?->info(
                'JUser: Signed a user in, but their destination was not permitted.',
                ['userId' => $user->getId(), 'route' => $refused]
            );
            $this->signIn->flash(FlashMessenger::NAMESPACE_SUCCESS, 'You are signed in.');
            $this->signIn->flash(FlashMessenger::NAMESPACE_ERROR, sprintf(
                'Your account does not have access to %s, so we have brought you here instead.',
                $this->targets->pathOf($destination)
            ));

            return $this->toLoginRedirect();
        }

        return new RedirectResponse($destination, Response::HTTP_FOUND);
    }

    /**
     * Where to send a freshly signed-in visitor: the link's own `?redirect=`, else the one
     * remembered in the session, else the configured post-login route.
     *
     * The link's copy is validated **again** here, and not because it was not validated
     * when it was issued: between there and here it was a query parameter in an email.
     */
    private function destination(Request $request): string
    {
        $redirect = $this->targets->valid($request->query->get('redirect'));
        if (null === $redirect) {
            $container = self::destinationContainer($this->signIn);
            /** @var mixed $remembered */
            $remembered = $container[self::DESTINATION] ?? null;
            if (null !== $remembered) {
                $redirect = $this->targets->valid($remembered);
                unset($container[self::DESTINATION]);
            }
        }

        return $redirect ?? $this->urls->path($this->signIn->loginRedirectRoute());
    }

    /**
     * The session slot the destination is remembered in.
     *
     * Named by `LoginController::SESSION_NAMESPACE` rather than by a literal, and that
     * matters: the laminas controller is kept for one release as the rollback path, so a
     * visitor could ask for a link under one front controller and click it under the other.
     * Two different namespaces would lose the destination silently, and only for the
     * visitors caught across the flip. Static because `App\Controller\SignInController`
     * writes the slot this reads.
     *
     * @return SessionContainer<string, mixed>
     */
    public static function destinationContainer(SignIn $signIn): SessionContainer
    {
        return new SessionContainer(LoginController::SESSION_NAMESPACE, $signIn->sessionManager());
    }

    /** @throws TwigError */
    private function failed(): Response
    {
        return new Response(
            $this->twig->render('juser/verify-failed.html.twig', [
                'page_title' => "This sign-in link didn't work",
            ]),
            Response::HTTP_BAD_REQUEST
        );
    }

    /** @throws TwigError */
    private function deactivated(): Response
    {
        return new Response(
            $this->twig->render('juser/deactivated.html.twig', [
                'page_title' => 'This account has been deactivated',
            ]),
            Response::HTTP_FORBIDDEN
        );
    }

    private function toLoginRedirect(): RedirectResponse
    {
        return new RedirectResponse(
            $this->urls->path($this->signIn->loginRedirectRoute()),
            Response::HTTP_FOUND
        );
    }
}
