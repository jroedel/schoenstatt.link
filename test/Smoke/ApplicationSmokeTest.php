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
     */
    public function testSitemapRenders(): void
    {
        $response = $this->get('/en/sitemap.xml');

        $this->assertSame(200, $response['status'], 'GET /en/sitemap.xml should render');
        $this->assertStringContainsString('xml', $response['contentType']);
        $this->assertStringContainsString('<urlset', $response['body']);
    }

    private function titleOf(string $body): string
    {
        if (preg_match('#<title>(.*?)</title>#is', $body, $matches) !== 1) {
            $this->fail('Response has no <title> element');
        }

        return trim(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
