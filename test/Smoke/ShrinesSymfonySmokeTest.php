<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The shrine index, now served by the Symfony kernel and rendered with Twig.
 *
 * SchoenstattSmokeTest already asserts that /en/shrines renders; that test passed
 * before the port and passes after it, which is the point of it and also its limit
 * — the catch-all route would satisfy it either way. These assertions are about the
 * things only the ported route can get wrong: whether Symfony served the page at
 * all, whether the locale prefix still decides the language, and whether a path
 * that merely *looks* locale-prefixed still falls through to laminas-mvc.
 *
 * Nothing here asserts exact markup. The two renderings were compared row by row
 * during the port and agree; a byte comparison in a test would be defeated anyway,
 * because the language chooser picks its flags with Rand::getInteger() and the CSP
 * nonce is fresh per request.
 */
class ShrinesSymfonySmokeTest extends SmokeTestCase
{
    /**
     * The discriminator between the two front controllers: laminas sends
     * `Set-Cookie: slm_locale=en_US` on every response, because SlmLocale's cookie
     * strategy sets it at MvcEvent::FINISH — after GdprStrategy has stripped the
     * others. SlmLocale does not run for a ported route, so a locale cookie coming
     * back means the request went through App\Http\LegacyBridge and this test is
     * measuring the wrong thing.
     */
    public function testShrinesIsServedBySymfonyAndNotBridgedToLaminas(): void
    {
        $response = $this->request('GET', '/en/shrines');

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('text/html', $response['contentType']);
        $this->assertStringNotContainsString(
            'slm_locale=en_US',
            $response['headers']['set-cookie'] ?? '',
            'a slm_locale cookie means SlmLocale ran, i.e. laminas-mvc served this — '
            . 'is SYMFONY_KERNEL=1 on this instance, and is the route still above the catch-all?'
        );
        $this->assertStringContainsString('Schoenstatt Shrine', $response['body']);
    }

    /**
     * The page carries an inline <script> that sizes the progress bars, so it needs
     * both the policy and the nonce that whitelists that one script. A ported route
     * gets neither for free: SionModel\Mvc\CspListener is an MVC listener, and
     * App\Http\CspListener is its counterpart.
     */
    public function testShrinesStillCarriesItsContentSecurityPolicyAndNonce(): void
    {
        $response = $this->request('GET', '/en/shrines');

        $csp = $response['headers']['content-security-policy'] ?? '';
        $this->assertMatchesRegularExpression(
            "/script-src 'self' 'nonce-([^']+)'/",
            $csp,
            'no CSP nonce on the ported page: the inline progress-bar script would be blocked'
        );
        $this->assertSame(1, preg_match("/'nonce-([^']+)'/", $csp, $matches));
        $this->assertStringContainsString(
            'nonce="' . $matches[1] . '"',
            $response['body'],
            'the header nonce and the script nonce must be the same value'
        );
    }

    /**
     * Reading the session is what lets a signed-in moderator keep the moderator
     * chrome, and session_start() writes a cookie before any listener can object.
     * Without consent that cookie has to leave again, which is what
     * Application\View\GdprStrategy does for every laminas-served page.
     */
    public function testAnUnconsentedVisitorGetsNoUsableSessionCookie(): void
    {
        //asserted through a real cookie jar rather than the Set-Cookie header,
        //because an expired cookie is still a Set-Cookie header — what matters is
        //whether a browser would keep it
        $jar = $this->newCookieJar(false);
        $this->get('/en/shrines', false, $jar);

        $this->assertStringNotContainsString(
            'PHPSESSID',
            (string) file_get_contents($jar),
            'a session cookie survived without GDPR consent'
        );
    }

    /**
     * Symfony's ResponseHeaderBag invents `no-cache, private` for a response that
     * set no cache directives, and sendHeaders() appends it next to the one PHP's
     * session cache limiter already sent. LaminasResponseConverter refuses to do
     * that to a bridged response; App\Http\InventedCacheControlListener refuses for
     * a native one, so that the two front controllers agree about this page.
     */
    public function testTheSymfonyServedPageDoesNotGainAnInventedCacheControl(): void
    {
        $cacheControl = $this->request('GET', '/en/shrines')['headers']['cache-control'] ?? '';

        $this->assertStringNotContainsString('private', $cacheControl, 'Cache-Control: ' . $cacheControl);
    }

    /** Every configured locale prefix answers, and the prefix is what picks the language. */
    #[DataProvider('localePathProvider')]
    public function testEachLocalePrefixRenders(string $path, string $slugFragment): void
    {
        $response = $this->assertRendersOk($path);

        $this->assertStringContainsString(
            $slugFragment,
            $response['body'],
            "$path should link to shrines using that locale's slugs"
        );
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function localePathProvider(): array
    {
        return [
            'english'    => ['/en/shrines', '/en/SL100458A/schoenstatt-shrine-mont-sion-gikungu'],
            'german'     => ['/de/shrines', '/de/SL100458A/schoenstatt-heiligtum-mont-sion-gikungu'],
            'spanish'    => ['/es/shrines', '/es/SL100458A/santuario-de-schoenstatt-mont-sion-gikungu'],
            'portuguese' => ['/pt/shrines', '/pt/SL100458A/santuario-de-schoenstatt-mont-sion-gikungu'],
            'italian'    => ['/it/shrines', '/it/SL100458A/schoenstatt-shrine-mont-sion-gikungu'],
        ];
    }

    /**
     * The unprefixed form redirects rather than serving a second copy of the page,
     * which is what SlmLocale's `redirect_when_found` does today — and what keeps
     * the canonical link pointing at a URL that is not also served here.
     */
    public function testTheUnprefixedPathRedirectsToTheDefaultLanguage(): void
    {
        $response = $this->get('/shrines');

        $this->assertSame(302, $response['status']);
        $this->assertStringEndsWith('/en/shrines', $response['redirect']);
    }

    /**
     * And the language it redirects to is negotiated, not hard-coded: the
     * `slm_locale` cookie comes before Accept-Language in `slm_locale.strategies`,
     * and App\Http\LocaleListener keeps that order.
     *
     * The cookie rather than an Accept-Language header, because SmokeTestCase pins
     * Accept-Language on every request so results do not depend on the environment —
     * an extra header is merged alongside it and loses. Header negotiation was
     * verified by hand during the port; the ordering rule is what this pins.
     */
    public function testTheUnprefixedPathHonoursTheLocaleCookie(): void
    {
        $jar = $this->newCookieJar();
        file_put_contents($jar, implode("\t", [
            (string) parse_url($this->baseUrl(), PHP_URL_HOST),
            'FALSE',
            '/',
            'FALSE',
            '2147483647',
            'slm_locale',
            'de_DE',
        ]) . "\n", FILE_APPEND);

        $response = $this->get('/shrines', false, $jar);

        $this->assertSame(302, $response['status']);
        $this->assertStringEndsWith('/de/shrines', $response['redirect']);
    }

    /**
     * `_locale` is constrained to the five configured aliases precisely so that this
     * keeps happening: an unconstrained `/{_locale}/shrines` would swallow every
     * two-segment path and answer it in English. laminas-mvc still owns this URL, and
     * it answers with SlmLocale's prefix redirect.
     */
    public function testAnUnknownLocalePrefixStillFallsThroughToLaminas(): void
    {
        $response = $this->get('/xx/shrines');

        $this->assertSame(302, $response['status']);
        $this->assertStringContainsString(
            '/en/xx/shrines',
            $response['redirect'],
            'an unknown prefix should reach laminas, which prepends the detected locale'
        );
    }

    /**
     * The chrome the layout builds without a Navigation service: the navbar comes
     * from the raw `navigation` config, ACL-filtered, and the current page's item is
     * marked active. Movement and Admin declare resources a guest is denied.
     */
    public function testTheNavbarIsAclFilteredAndMarksTheCurrentPage(): void
    {
        $body = $this->assertRendersOk('/en/shrines')['body'];

        $this->assertStringContainsString('<a href="/en/shrines" aria-current="page">Shrines</a>', $body);
        $this->assertStringContainsString('>Literature</a>', $body);
        $this->assertStringNotContainsString('>Movement</a>', $body, 'route/schoenstatt is denied to guests');
        $this->assertStringNotContainsString('>Admin</a>', $body, 'route/admin is denied to guests');
        $this->assertStringContainsString('<a class="btn" href="/en/user/login">Sign in</a>', $body);
    }

    /**
     * Links to pages that have *not* moved are assembled by the laminas router
     * (App\Laminas\RouteUrl), locale prefix included — that is the mechanism that
     * keeps a half-migrated site navigable.
     */
    public function testLinksToUnportedPagesKeepTheirLocalePrefix(): void
    {
        $body = $this->assertRendersOk('/en/shrines')['body'];

        $this->assertStringContainsString('href="/en/wayside-shrines"', $body);
        $this->assertStringContainsString('href="/en/shrines/submitting-photos"', $body);
        $this->assertStringContainsString('href="/en/"', $body, 'the navbar brand');
        $this->assertStringContainsString('<link href="', $body);
        $this->assertStringContainsString('/en/shrines" rel="canonical">', $body);
    }
}
