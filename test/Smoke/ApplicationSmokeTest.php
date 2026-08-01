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
