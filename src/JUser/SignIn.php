<?php

declare(strict_types=1);

namespace App\JUser;

use App\Http\GdprCookieListener;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use JTranslate\I18n\TranslatableMessage;
use JUser\Model\UserTable;
use JUser\Service\LoginTokenService;
use JUser\Service\Mailer;
use Laminas\Authentication\AuthenticationService;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;
use Laminas\Session\ManagerInterface as SessionManagerInterface;
use Laminas\Session\SessionManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

use function is_array;
use function is_string;

/**
 * What all five ported sign-in routes need, in one place.
 *
 * The counterpart of `App\JUser\UserAdmin` for the *other* JUser surface, and the same
 * shape: lazy accessors over `App\Laminas\ServiceBridge`, so no controller holds a laminas
 * service as a constructor dependency and none of them can be built eagerly.
 *
 * ## Nothing here is authorization
 *
 * `UserAdmin` has no `refuse()` because the seven admin routes are guarded
 * `administrator`. These five are the opposite end: `zfcuser`, `zfcuser/login` and
 * `zfcuser/verify` are reachable by `guest`, because a page you visit *in order to* sign in
 * cannot require an identity. `zfcuser/logout` is guarded `user`. `sign-in-no-cookies` is
 * an Application route and public. So the route guards are the whole of it and there is
 * nothing for a controller to check — except the one thing below, which is not
 * authorization at all.
 *
 * ## The consent gate is not authorization and not a redirect
 *
 * `Application\View\GdprStrategy::onRoute()` swaps the *route match* for `zfcuser/login`
 * and `zfcuser/verify` when the visitor has not consented to cookies, so the explainer
 * renders **at the requested URL** with a 200. Not a redirect, which is why the emailed
 * link still works after consenting: the token is spent only on a successful redemption.
 *
 * That listener runs on `MvcEvent::EVENT_ROUTE` and therefore does not run at all for a
 * ported route — the laminas application is never entered. Its *other* half, `onFinish()`,
 * is already reproduced Symfony-side by `App\Http\GdprCookieListener`, which strips every
 * cookie off an unconsented response. This half is reproduced by the two controllers
 * asking `wantsCookiesFirst()` before doing anything, which lands in the same place a
 * route-match swap did: same URL, same status, same template.
 *
 * The cookie name comes from `GdprCookieListener` rather than being written again, because
 * a gate keyed on a different string from the one that strips the cookies would be worse
 * than no gate: the page would offer a form whose session cookie is then removed.
 */
final class SignIn
{
    /** Shared across every call to {@see flash()} — see that method on why it must be. */
    private ?FlashMessenger $flashMessenger = null;

    /** Where a signed-in visitor goes when they ask for a sign-in page. */
    private const DEFAULT_LOGIN_REDIRECT_ROUTE = 'welcome';

    /** Where signing out goes. */
    private const DEFAULT_LOGOUT_REDIRECT_ROUTE = 'zfcuser/login';

    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly RouteUrl $urls
    ) {
    }

    /**
     * Has this visitor consented to cookies?
     *
     * Read off the Symfony request rather than `$_COOKIE`, which is what the laminas
     * strategy reads: same value, and a request object is testable.
     */
    public function hasConsented(Request $request): bool
    {
        return 'true' === $request->cookies->get(GdprCookieListener::CONSENT_COOKIE);
    }

    /**
     * Whether this request must be answered with the cookie explainer instead of the page
     * it asked for.
     *
     * The whole of `GdprStrategy::onRoute()`'s condition. Every auth entry point needs
     * cookies — a session for the identity and a CSRF token for the form — and without
     * consent `App\Http\GdprCookieListener` strips them off the response, so a form served
     * here would fail on submission with nothing to show the visitor.
     */
    public function wantsCookiesFirst(Request $request): bool
    {
        return ! $this->hasConsented($request);
    }

    public function authService(): AuthenticationService
    {
        /** @var AuthenticationService $service */
        $service = $this->laminas->get('zfcuser_auth_service');

        return $service;
    }

    public function table(): UserTable
    {
        /** @var UserTable $table */
        $table = $this->laminas->get(UserTable::class);

        return $table;
    }

    public function tokens(): LoginTokenService
    {
        /** @var LoginTokenService $service */
        $service = $this->laminas->get(LoginTokenService::class);

        return $service;
    }

    /**
     * The mailer, with the **locale-prefixed** router pushed into it.
     *
     * `JUser\Service\Mailer` assembles the sign-in link itself, with `force_canonical`, on
     * whatever router `MailerFactory` gave it — and under a Symfony dispatch that is the
     * raw container router, which no SlmLocale listener has touched. So it produced
     * `http://host/user/verify?token=…`: the *unprefixed twin* of a ported route, which
     * answers a 302 to the prefixed form. A browser survives that; a mail client that
     * pre-fetches, a scanner, or anything that does not follow the hop does not, and the
     * token is single-use.
     *
     * `App\Laminas\RouteUrl::router()` is the prepared one. Set here rather than left to
     * whatever ran earlier in the request, because "did something render a link before we
     * mailed?" is not a question a sign-in link's correctness should depend on.
     */
    public function mailer(): Mailer
    {
        /** @var Mailer $mailer */
        $mailer = $this->laminas->get(Mailer::class);
        $mailer->setRouter($this->urls->router());

        return $mailer;
    }

    /**
     * The concrete `SessionManager`, not `ManagerInterface`, and the narrowing is
     * load-bearing rather than tidy: `regenerateId()` and `forgetMe()` are what this
     * surface needs and the *interface* declares `regenerateId()` with no parameters, so
     * `regenerateId(true)` — delete the old session server-side, which is the whole point
     * at a privilege change — does not type-check against it. The container answers
     * `ManagerInterface::class` with a `SessionManager` (see
     * `Laminas\Session\Service\SessionManagerFactory`), so this is a narrowing of the
     * declared type to the real one, not a cast and not a hope.
     */
    public function sessionManager(): SessionManager
    {
        /** @var SessionManager $manager */
        $manager = $this->laminas->get(SessionManagerInterface::class);

        return $manager;
    }

    /** The route a signed-in visitor is sent to; `juser.login_redirect_route`. */
    public function loginRedirectRoute(): string
    {
        return $this->configuredRoute('login_redirect_route', self::DEFAULT_LOGIN_REDIRECT_ROUTE);
    }

    /** The route signing out lands on; `juser.logout_redirect_route`. */
    public function logoutRedirectRoute(): string
    {
        return $this->configuredRoute('logout_redirect_route', self::DEFAULT_LOGOUT_REDIRECT_ROUTE);
    }

    /**
     * The same two config keys `LoginControllerFactory` passes into the laminas
     * controller, read the same way — absent or non-string falls back to the constant, so
     * a misconfigured value cannot leave the site with no post-login destination.
     */
    private function configuredRoute(string $key, string $fallback): string
    {
        /** @var mixed $config */
        $config = $this->laminas->get('config');
        /** @var mixed $juser */
        $juser = is_array($config) ? ($config['juser'] ?? null) : null;
        /** @var mixed $value */
        $value = is_array($juser) ? ($juser[$key] ?? null) : null;

        return is_string($value) && '' !== $value ? $value : $fallback;
    }

    /**
     * Read by the *next* page, which is what redemption wants: it reports "you are signed
     * in" **and** "but not there" and then redirects, so both messages have to survive the
     * hop. Nothing on this surface needs a now-message; every message this flow produces
     * accompanies a redirect.
     *
     * ## One instance, and it has to be one
     *
     * `FlashMessenger::addMessage()` calls `getMessagesFromContainer()` the first time an
     * instance is used, which moves **every namespace** out of the session container into
     * that instance's own in-memory `$messages` and unsets it from the container. That is
     * correct for reading last request's messages — and fatal if a second *instance* does
     * it after the first has already written: the second's move takes the first's message
     * out of the session, keeps it in an object that is discarded at the end of the
     * request, and only the second message survives.
     *
     * So a fresh `new FlashMessenger()` per call silently keeps just the last one. Measured
     * 2026-08-21 on exactly the two-message case above: the "not allowed there" alert
     * rendered and "You are signed in." did not. The laminas controller never had this
     * problem because `$this->flashMessenger()` is a *shared* controller plugin — the same
     * object both times — so the bug is one a port introduces rather than one it inherits.
     *
     * Same signature reasoning as `App\JUser\UserAdmin::flash()`: the plugin *declares*
     * `string` and accepts a `JTranslate\I18n\TranslatableMessage`, which is deliberately
     * not `Stringable`, so narrowing here would turn a correct call into a fatal.
     *
     * @param string|TranslatableMessage $message
     */
    public function flash(string $namespace, mixed $message): void
    {
        $this->flashMessenger ??= new FlashMessenger();
        /** @phpstan-ignore argument.type (see the docblock: the declared `string` is wrong) */
        $this->flashMessenger->setNamespace($namespace)->addMessage($message);
    }

    public function logger(): ?LoggerInterface
    {
        /** @var mixed $config */
        $config = $this->laminas->get('config');
        /** @var mixed $juser */
        $juser = is_array($config) ? ($config['juser'] ?? null) : null;
        /** @var mixed $service */
        $service = is_array($juser) ? ($juser['logger_service'] ?? null) : null;

        if (is_string($service) && $this->laminas->has($service)) {
            /** @var LoggerInterface $logger */
            $logger = $this->laminas->get($service);

            return $logger;
        }
        if ($this->laminas->has(LoggerInterface::class)) {
            /** @var LoggerInterface $logger */
            $logger = $this->laminas->get(LoggerInterface::class);

            return $logger;
        }

        return null;
    }
}
