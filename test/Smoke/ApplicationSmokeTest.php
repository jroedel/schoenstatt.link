<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

/**
 * Application module: the site entry point, static informational pages, the
 * sitemap and the API documentation route.
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
     */
    public function testUnknownApiUrlIsACleanNotFound(): void
    {
        $response = $this->get('/api/there-is-no-such-endpoint', true);

        $this->assertSame(404, $response['status'], 'an unknown API path should 404');
        $this->assertStringNotContainsString('Fatal error', $response['body']);
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
     * The API route serves a Swagger UI shell that loads the OpenAPI document
     * client side, so the spec URL is the only server-rendered marker.
     */
    public function testApiDocumentationPageRenders(): void
    {
        $response = $this->assertRendersOk('/en/api/v1');

        $this->assertStringContainsString('/api/v1.yaml', $response['body']);
    }

    /**
     * Requires the data/sitemap directory to exist (tracked via .gitkeep);
     * without it the samdark/sitemap writer throws and the route 500s.
     *
     * `<sitemapindex>`, not `<urlset>`, since the route was ported on 2026-08-13: the URLs
     * live in the parts the index names, because one file only ever carried 3,022 of the
     * site's pages. The coverage this asserted nothing about is
     * test/Smoke/SitemapSmokeTest's subject; what stays here is that the route answers XML
     * at all, which is what the .gitkeep sentence is really about.
     */
    public function testSitemapRenders(): void
    {
        $response = $this->get('/en/sitemap.xml');

        $this->assertSame(200, $response['status'], 'GET /en/sitemap.xml should render');
        $this->assertStringContainsString('xml', $response['contentType']);
        //the file is written gzipped, but whether these bytes arrive compressed depends on
        //the client's Accept-Encoding, so the magic number decides rather than the header
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
