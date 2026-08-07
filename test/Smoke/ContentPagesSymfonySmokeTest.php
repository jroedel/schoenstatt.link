<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use PHPUnit\Framework\Attributes\DataProvider;

use function is_array;
use function json_decode;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function str_contains;
use function trim;

/**
 * The five static content pages, ported to the Symfony kernel 2026-08-07:
 * `/`, /developers, /acknowledgements, /privacy and /shrines/submitting-photos.
 *
 * ApplicationSmokeTest already asks whether these paths return 200, and passed before
 * the port as well — the catch-all satisfies that either way, which is its limit. What
 * is asserted here is what only the ported route can get wrong.
 *
 * ShrinesSymfonySmokeTest carries the mechanism assertions that belong to App\Kernel's
 * listeners rather than to a route — the GDPR cookie, the invented Cache-Control, the
 * unknown-prefix fall-through — and they are not repeated. What is repeated per page is
 * the pair that cannot be inherited: that Symfony served *this* path, and that its
 * unprefixed form redirects the way SlmLocale would.
 *
 * Three things here are specific to this batch and are the reason it is its own file:
 *
 * - **`/` is the site's front page**, and the only ported path whose whole route is the
 *   locale prefix. That is what made App\Laminas\RouteUrl::localized() drop a trailing
 *   slash and point every canonical and hreflang link on the front page one redirect
 *   away from itself; CanonicalLinkSmokeTest is what caught it and still guards it.
 * - **The submitting-photos page is the only one whose body is chosen by locale**, so
 *   it is the end-to-end proof that App\Http\LocaleListener works. Spanish gets the
 *   Spanish text; Portuguese gets the English text, because the .phtml compares against
 *   the literal 'es_ES' and falls back for everything else.
 * - **An empty page title is a real value.** / and /privacy set no headTitle, so
 *   laminas renders `<title>Schoenstatt Link</title>` with no separator, and a layout
 *   that interpolated an empty string would render " - Schoenstatt Link".
 */
class ContentPagesSymfonySmokeTest extends SmokeTestCase
{
    /**
     * path, a distinctive phrase from its Markdown body, and its expected <title>.
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function pages(): array
    {
        return [
            'welcome'           => ['/en/', 'Schoenstatt Link is effectively a database', 'Schoenstatt Link'],
            'developers'        => [
                '/en/developers',
                'Schoenstatt Link hopes to provide APIs',
                'Developers Center - Schoenstatt Link',
            ],
            'acknowledgements'  => [
                '/en/acknowledgements',
                'responsibly disclose security vulnerabilites',
                'Security research acknowledgements - Schoenstatt Link',
            ],
            'privacy'           => [
                '/en/privacy',
                'What personal data do we potentially collect?',
                'Schoenstatt Link',
            ],
            'submitting-photos' => [
                '/en/shrines/submitting-photos',
                'Files are named by the identifier of the shrine',
                'Submitting photos - Schoenstatt Link',
            ],
        ];
    }

    /**
     * The discriminator between the two front controllers, as in
     * ShrinesSymfonySmokeTest: laminas sends `Set-Cookie: slm_locale=en_US` on every
     * response, because SlmLocale's cookie strategy sets it at MvcEvent::FINISH.
     * SlmLocale does not run for a ported route, so a locale cookie coming back means
     * the request went through App\Http\LegacyBridge and every other assertion here is
     * measuring the laminas page instead.
     */
    #[DataProvider('pages')]
    public function testThePageIsServedBySymfonyAndNotBridgedToLaminas(
        string $path,
        string $phrase,
        string $title
    ): void {
        $response = $this->request('GET', $path);

        self::assertSame(200, $response['status']);
        self::assertStringContainsString('text/html', $response['contentType']);
        self::assertStringNotContainsString(
            'slm_locale=en_US',
            $response['headers']['set-cookie'] ?? '',
            'a slm_locale cookie means SlmLocale ran, i.e. laminas-mvc served this — is '
            . 'SYMFONY_KERNEL=1 on this instance, and is the route still above the catch-all?'
        );
        self::assertStringContainsString($phrase, $response['body'], 'the Markdown body did not render');
        self::assertSame($title, $this->title($response['body']));
    }

    /**
     * SlmLocale answers an unprefixed path with a redirect to the negotiated language
     * rather than serving the same body at two URLs, and App\Controller\
     * ContentPageController reproduces that. The route name is what it builds the
     * target from, so getting this wrong on one page would get it wrong on all five.
     */
    #[DataProvider('pages')]
    public function testTheUnprefixedFormRedirectsToTheNegotiatedLanguage(
        string $path,
        string $phrase,
        string $title
    ): void {
        $unprefixed = '/en/' === $path ? '/' : (string) preg_replace('#^/en#', '', $path);

        $response = $this->request('GET', $unprefixed);

        self::assertSame(302, $response['status'], "$unprefixed should redirect, not render");
        self::assertStringEndsWith(
            $path,
            $response['redirect'],
            'Accept-Language is pinned to en by the test client, so the target is the English page'
        );
    }

    /**
     * `/en` is a 404 today and must stay one: SlmLocale strips the segment and the
     * empty path matches no laminas route. The ported `welcome` route declares
     * `/{_locale}/` with the slash required, which is what keeps `/en` falling through
     * to the catch-all instead of being swallowed by the front page.
     */
    public function testTheLocalePrefixWithoutATrailingSlashStaysA404(): void
    {
        self::assertSame(404, $this->request('GET', '/en')['status']);
    }

    /**
     * The locale-branching page, which is the only end-to-end check that
     * App\Http\LocaleListener set \Locale::getDefault() before the template ran.
     *
     * The Portuguese case is the one worth having: the original compares against the
     * literal 'es_ES' and falls back to English for every other language, so a
     * listener that merely set *some* locale would still pass the Spanish assertion.
     */
    public function testTheSubmittingPhotosBodyFollowsTheRequestedLocale(): void
    {
        $spanish = $this->assertRendersOk('/es/shrines/submitting-photos')['body'];
        self::assertStringContainsString('Enviando fotos', $spanish);
        self::assertStringNotContainsString('Submitting photos</h2>', $spanish);

        $english = $this->assertRendersOk('/en/shrines/submitting-photos')['body'];
        self::assertStringContainsString('Files are named by the identifier', $english);
        self::assertStringNotContainsString('Enviando fotos', $english);

        $portuguese = $this->assertRendersOk('/pt/shrines/submitting-photos')['body'];
        self::assertStringContainsString(
            'Files are named by the identifier',
            $portuguese,
            'only es_ES gets the Spanish text; every other language falls back to English'
        );
    }

    /**
     * Two pages set no headTitle and so must render the site name alone, with no
     * separator — measured against the laminas rendering, which says exactly
     * "Schoenstatt Link". The WebPage schema carries the same value, because the
     * laminas layout writes headTitle()[0] into it.
     */
    public function testAPageWithNoTitleRendersTheSiteNameWithoutASeparator(): void
    {
        foreach (['/en/', '/en/privacy'] as $path) {
            $body = $this->assertRendersOk($path)['body'];

            self::assertSame('Schoenstatt Link', $this->title($body), "wrong <title> on $path");
            self::assertFalse(
                str_contains($body, '<title> - '),
                "$path renders an empty title prefix and a stray separator"
            );
            self::assertSame(
                'Schoenstatt Link',
                $this->webPageSchemaName($body),
                "the WebPage schema on $path should carry the site name, as headTitle()[0] does"
            );
        }
    }

    /**
     * The breadcrumb trails, which the layout can no longer derive: the Navigation
     * service's factory needs an MvcEvent, so each ported page passes its own.
     *
     * The active flag is the part worth asserting. partial/breadcrumbs.phtml asks
     * Page\Mvc::isActive() non-recursively, so an *ancestor* crumb carries no class —
     * measured against the laminas rendering of /en/developers, where "Home" is a bare
     * <li>. A layout that marked every crumb active would look right and be wrong.
     */
    public function testBreadcrumbsMarkOnlyTheCurrentPageActive(): void
    {
        $body = $this->assertRendersOk('/en/developers')['body'];

        self::assertMatchesRegularExpression(
            '#<li>\s*<a href="/en/">Home</a>\s*</li>#',
            $body,
            'the Home crumb is an ancestor, so it should carry no active class'
        );
        self::assertMatchesRegularExpression(
            '#<li class="active">\s*Developers Center\s*</li>#',
            $body,
            'the last crumb is the current page: active, and not a link'
        );
    }

    /** /privacy is in no navigation container, so it has no trail at all — as today. */
    public function testPrivacyHasNoBreadcrumbs(): void
    {
        self::assertStringNotContainsString(
            '<ol class="breadcrumb">',
            $this->assertRendersOk('/en/privacy')['body']
        );
    }

    /**
     * A visitor holding a session written before the Zend → Laminas class renames used
     * to get an empty 200 from every ported HTML page, because the layout's
     * flash_messages() throws on a __PHP_Incomplete_Class value and only
     * JUser\Module::onBootstrap() ever pruned it. StaleSessionSmokeTest drives the real
     * scenario against `/`; this asserts the listener that fixes it is registered at
     * all, from the outside: a session cookie on a ported page must come back with a
     * page, not with nothing.
     */
    public function testAPortedPageServesAVisitorWhoArrivesWithASessionCookie(): void
    {
        $jar      = $this->newCookieJar();
        $first    = $this->get('/en/', false, $jar);
        $response = $this->get('/en/', false, $jar);

        self::assertSame(200, $first['status']);
        self::assertSame(200, $response['status']);
        self::assertStringContainsString('<title>', $response['body'], 'blank body on the second request');
    }

    private function title(string $body): string
    {
        return 1 === preg_match('#<title>(.*?)</title>#s', $body, $m) ? trim($m[1]) : '';
    }

    /**
     * The `name` of the WebPage node in the layout's JSON-LD, decoded rather than
     * string-matched: the two layouts serialize it differently — laminas hand-writes
     * pretty-printed JSON, App\Twig\ChromeExtension::jsonLd() emits it compact — and a
     * substring assertion would be testing the whitespace instead of the value.
     */
    private function webPageSchemaName(string $body): ?string
    {
        preg_match_all('#<script type=[\'"]application/ld\+json[\'"]>(.*?)</script>#s', $body, $blocks);
        foreach ($blocks[1] as $block) {
            $decoded = json_decode(trim((string) $block), true);
            foreach (is_array($decoded) ? $decoded : [] as $node) {
                if (is_array($node) && ($node['@type'] ?? null) === 'WebPage') {
                    return isset($node['name']) ? (string) $node['name'] : null;
                }
            }
        }

        return null;
    }
}
