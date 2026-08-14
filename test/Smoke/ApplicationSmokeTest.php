<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Application module: the site entry point, static informational pages, the
 * sitemap, and the refusal that replaced the retired v1/v2 API.
 */
class ApplicationSmokeTest extends SmokeTestCase
{
    public function testRootRedirectsToEnglishHomepage(): void
    {
        $response = $this->get('/');

        $this->assertSame(302, $response['status'], 'GET / should redirect to the localized homepage');
        $this->assertStringEndsWith('/en/', $response['redirect'], 'GET / should land on /en/');
    }

    public function testEnglishHomepageRenders(): void
    {
        $response = $this->assertRendersOk('/en/');

        $this->assertStringContainsString('Schoenstatt Link', $this->titleOf($response['body']));
        $this->assertStringContainsString('Our mission', $response['body']);
    }

    /**
     * Regression: unknown web URLs used to match a global '/:*' catch-all
     * route named '404' (int!), get refused by the default-deny guard, and
     * fatal under HTTP 200 while assembling the int route name. They must
     * instead reach Laminas' normal 404 handling.
     */
    public function testUnknownWebUrlIsACleanNotFound(): void
    {
        $response = $this->get('/en/there-is-no-such-page-xyz');

        $this->assertSame(
            404,
            $response['status'],
            'an unknown URL should 404 (redirect target was: "' . $response['redirect'] . '")'
        );
        $this->assertStringNotContainsString('Fatal error', $response['body']);
        $this->assertStringNotContainsString('Stack trace', $response['body']);
    }

    /**
     * Unknown API paths keep their multidots-era JSON 404 (guard-approved).
     * SlmLocale first 302s /api/* to /<locale>/api/* — the locale segment
     * becomes the router's base URL — so the 404 is one redirect away.
     *
     * The /api/v3 case is the one that matters since the v1/v2 retirement: those
     * versions answer 410, and this pins that to them. An unknown path in a *live*
     * API is a typo, and telling a caller its endpoint is permanently gone when it
     * has merely misspelled one is a lie the caller would act on.
     *
     * @param string $path
     */
    #[DataProvider('unknownApiPaths')]
    public function testUnknownApiUrlIsACleanNotFound(string $path): void
    {
        $response = $this->get($path, true);

        $this->assertSame(404, $response['status'], $path . ' should 404, not 410');
        $this->assertStringNotContainsString('Fatal error', $response['body']);
        $this->assertArrayNotHasKey(
            'link',
            $response['headers'],
            $path . ' is not a retired version, so it must not advertise a successor'
        );
    }

    /** @return array<string, array{string}> */
    public static function unknownApiPaths(): array
    {
        return [
            'no version at all'      => ['/api/there-is-no-such-endpoint'],
            'a typo in a live v3'    => ['/api/v3/phrasez'],
            'a version never served' => ['/api/v9/associations'],
        ];
    }

    public function testDevelopersPageRenders(): void
    {
        $response = $this->assertRendersOk('/en/developers');

        $this->assertStringContainsString('Developers Center', $response['body']);
    }

    public function testPrivacyPageRenders(): void
    {
        $response = $this->assertRendersOk('/en/privacy');

        $this->assertStringContainsString('Privacy Policy', $response['body']);
    }

    public function testAcknowledgementsPageRenders(): void
    {
        $response = $this->assertRendersOk('/en/acknowledgements');

        $this->assertStringContainsString('Security research acknowledgements', $response['body']);
    }

    /**
     * The whole of /api/v1 and /api/v2 is gone, and this asserts what replaced it.
     *
     * These 26 paths were retired on 2026-08-14 after eight years of access logs showed
     * no client had used any of them since 2022. Three properties, each for its own
     * reason:
     *
     * - **410, not 404.** The resources existed and are permanently removed. Google
     *   treats a 410 as a signal to drop a URL from the index where a 404 is a softer
     *   "try again later", and two of these URLs were published as a schema.org
     *   `Dataset` distribution that is indexed today.
     * - **JSON, not the HTML error page.** RestApi's api-route-not-found catch-all is
     *   the whole mechanism; if it stops matching, the ordinary 404 page takes over and
     *   a machine caller gets markup it cannot read.
     * - **A successor-version Link.** RFC 5829's machine-readable way to say the API
     *   moved to /api/v3, which is the only forwarding a withdrawn endpoint can offer.
     *
     * The paths are one per former controller plus both documentation artefacts, so a
     * route tree resurrected by a bad merge fails here rather than in production.
     * testUnknownApiUrlIsACleanNotFound is the other half of the contract: it pins the
     * 410 to v1 and v2 so an unknown /api/v3 path stays a 404.
     */
    #[DataProvider('retiredApiPaths')]
    public function testRetiredApiPathIsAJsonGone(string $path): void
    {
        $response = $this->get($path, true);

        $this->assertSame(410, $response['status'], $path . ' should be 410 Gone now that /api/v1 is retired');
        $this->assertStringNotContainsString('Fatal error', $response['body']);
        $this->assertStringNotContainsString('<html', strtolower($response['body']), $path . ' should answer JSON');
        $this->assertStringContainsString(
            'rel="successor-version"',
            $response['headers']['link'] ?? '',
            $path . ' should point a stranded caller at /api/v3'
        );
    }

    /** @return array<string, array{string}> */
    public static function retiredApiPaths(): array
    {
        return [
            'v1 documentation shell'  => ['/api/v1'],
            'v1 findByKind'           => ['/api/v1/associations/findByKind?kind=sch-shrine'],
            'v1 findByKindMd5'        => ['/api/v1/associations/findByKindMd5'],
            'v1 shrines.json'         => ['/api/v1/associations/shrines.json'],
            'v1 dictionary'           => ['/api/v1/dictionary'],
            'v1 literature'           => ['/api/v1/literature'],
            'v1 libraries'            => ['/api/v1/libraries/3'],
            'v1 login'                => ['/api/v1/users/login'],
            'v2 findByKind'           => ['/api/v2/associations/findByKind?kind=sch-shrine'],
            'v2 shrines.json'         => ['/api/v2/associations/shrines.json'],
            'the OpenAPI document'    => ['/api/v1.yaml'],
        ];
    }

    /**
     * The locale-prefixed sitemap URL still answers, because robots.txt named it for years.
     *
     * `/en/sitemap.xml` is no longer what robots.txt points at — the files moved to the
     * docroot root on 2026-08-13, because a sitemap may only list URLs at or below its own
     * directory and one under `/en/` could not legally list the `/de/` half of the site. The
     * prefixed form is kept working anyway: it is what Search Console and every crawler have
     * on file, and $ported() declares both paths.
     *
     * `<sitemapindex>`, not `<urlset>`: the URLs live in the four files the index names.
     * Coverage is test/Smoke/SitemapSmokeTest's subject; this asserts only that the old URL
     * answers XML.
     */
    public function testThePrefixedSitemapUrlStillAnswers(): void
    {
        $response = $this->get('/en/sitemap.xml');

        $this->assertSame(200, $response['status'], 'GET /en/sitemap.xml should still answer');
        $this->assertStringContainsString('xml', $response['contentType']);
        //Nothing gzips these at rest any more — Apache compresses on the fly — but whether
        //*these* bytes arrive compressed still depends on the client, so the magic number
        //decides rather than the header.
        $body = str_starts_with($response['body'], "\x1f\x8b")
            ? (string) gzdecode($response['body'])
            : $response['body'];
        $this->assertStringContainsString('<sitemapindex', $body);
    }

    /**
     * The security headers set by public/.htaccess (tracked since 2026-08-03,
     * shared verbatim between the capsule and production).
     */
    public function testSecurityHeadersAreSet(): void
    {
        $headers = $this->get('/en/')['headers'];

        $this->assertSame('nosniff', $headers['x-content-type-options'] ?? null);
        $this->assertSame('DENY', $headers['x-frame-options'] ?? null);
        $this->assertSame('strict-origin-when-cross-origin', $headers['referrer-policy'] ?? null);
        $this->assertStringContainsString('max-age=', $headers['strict-transport-security'] ?? '');
        // Removed 2026-08-03: the XSS auditor is gone from every browser and
        // the header itself enabled side-channel attacks.
        $this->assertArrayNotHasKey('x-xss-protection', $headers);
    }

    public function testHtmlResponsesAreCompressed(): void
    {
        $headers = $this->get('/en/')['headers'];

        // The test client negotiates compression (CURLOPT_ENCODING '').
        // gzip locally (mod_deflate); production upgrades to brotli.
        $this->assertMatchesRegularExpression(
            '/^(gzip|br)$/',
            $headers['content-encoding'] ?? '',
            'HTML should be served compressed'
        );
    }

    /**
     * Static-asset cache policies come straight from public/.htaccess.
     * Regression guard for the only-if-cached bug (a request-only directive
     * that sat in these responses for years).
     */
    public function testStaticImagesCacheForAYearImmutable(): void
    {
        $response = $this->get('/favicon-32.png');

        $this->assertSame(200, $response['status']);
        $this->assertSame(
            'max-age=31536000, public, immutable',
            $response['headers']['cache-control'] ?? null
        );
    }

    public function testStylesheetsCacheForAMonth(): void
    {
        $response = $this->get('/css/style.css');

        $this->assertSame(200, $response['status']);
        $this->assertSame('max-age=2628000, public', $response['headers']['cache-control'] ?? null);
    }

    /** gen-basic.css is regenerated in place, so it gets a 1-day carve-out. */
    public function testGeneratedCssCachesForADay(): void
    {
        $response = $this->get('/css/gen-basic.css');

        $this->assertSame(200, $response['status']);
        $this->assertSame('public,max-age=86400', $response['headers']['cache-control'] ?? null);
    }

    private function titleOf(string $body): string
    {
        if (preg_match('#<title>(.*?)</title>#is', $body, $matches) !== 1) {
            $this->fail('Response has no <title> element');
        }

        return trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
