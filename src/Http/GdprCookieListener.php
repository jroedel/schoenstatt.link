<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpKernel\Event\ResponseEvent;

use function header_remove;
use function is_string;
use function setcookie;
use function session_get_cookie_params;
use function session_name;
use function session_status;
use function time;

use const PHP_SESSION_ACTIVE;

/**
 * Strips every cookie off a Symfony-served response unless the visitor has
 * consented, which is what Application\View\GdprStrategy::onFinish() does for a
 * laminas-served one.
 *
 * A ported route was supposed to be session-free, and the maintenance endpoints
 * were. An HTML page is not: the layout asks isAllowed() whether to show the
 * moderator chrome, BjyAuthorize answers by asking JUser for the identity, JUser
 * reads it out of the session, and reading the session calls session_start() —
 * which writes a Set-Cookie into PHP's SAPI header list before any listener here
 * gets a say. Measured: session_status() goes from NONE to ACTIVE across a single
 * isAllowed() call. Without this listener the first ported HTML page would be the
 * only page on the site that sets a session cookie on an unconsented visitor,
 * and nothing in the app would notice.
 *
 * Reading the session is not a mistake to be undone, incidentally — it is why a
 * logged-in moderator still sees the moderator chrome on a ported page.
 *
 * The work happens on PHP's SAPI header list rather than on the Response, exactly
 * as the laminas strategy does, because that is where session_start() put the
 * cookie: `$response->headers->clearCookie()` cannot see it. Symfony's
 * sendHeaders() appends to that same list instead of resetting it, so what this
 * listener removes stays removed. Order matters within the method: remove first,
 * then re-add the two expiry cookies.
 *
 * Not reproduced, because SlmLocale is not ported: the strategy's laminas
 * counterpart is followed by SlmLocale re-setting `slm_locale` at a later
 * priority, so a laminas response carries `slm_locale=en_US` and a ported one
 * carries no locale cookie. Nothing reads that cookie on a ported route — the
 * locale comes from the path — so the difference is inert until a route that
 * relies on cookie-detected locale moves.
 */
final class GdprCookieListener
{
    public const CONSENT_COOKIE = 'EU_COOKIE_LAW_CONSENT';

    public function __invoke(ResponseEvent $event): void
    {
        if (! $event->isMainRequest() || ! SymfonyRoute::isPorted($event->getRequest())) {
            return;
        }

        if ('true' === $event->getRequest()->cookies->get(self::CONSENT_COOKIE)) {
            return;
        }

        header_remove('Set-Cookie');
        //header_remove() cannot see cookies still sitting on the Response object,
        //waiting for sendHeaders() to emit them after this listener has run
        $response = $event->getResponse();
        foreach ($response->headers->getCookies() as $cookie) {
            $response->headers->removeCookie($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
        }

        $expired = time() - 42000;
        $sessionName = session_name();
        if (PHP_SESSION_ACTIVE === session_status() && is_string($sessionName)) {
            $params = session_get_cookie_params();
            setcookie($sessionName, '', [
                'expires'  => $expired,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
            ]);
        }
        //the locale cookie SlmLocale would have set; expired here for the same
        //reason the laminas strategy expires it — it is not strictly necessary
        setcookie('slm_locale', '', $expired, '/');
    }
}
