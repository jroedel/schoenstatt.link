<?php

namespace SchoenstattTest\Smoke;

/**
 * Characterizes the canonical / alternate-language link tags that layout.phtml
 * emits on every page.
 *
 * These are SEO-visible output and they depend on the *request's* scheme and
 * host, which the layout used to reach through the deprecated
 * `getHelperPluginManager()->getServiceLocator()->get('request')` shim and now
 * gets from the `requestUri` view helper. Nothing asserted them before, so the
 * mechanism could be swapped without any test noticing — hence this suite.
 *
 * @see \Application\View\Helper\RequestUri
 */
class CanonicalLinkSmokeTest extends SmokeTestCase
{
    /** The five locales layout.phtml advertises, keyed by hreflang. */
    private const LANGUAGES = ['en', 'es', 'de', 'pt', 'it'];

    public function testHomePageDeclaresExactlyOneCanonicalUrl(): void
    {
        $links = $this->linkTags($this->get('/en/')['body']);

        $canonical = array_values(array_filter($links, fn(array $l) => ($l['rel'] ?? '') === 'canonical'));
        $this->assertCount(1, $canonical, 'expected exactly one rel=canonical link');

        $parts = parse_url($canonical[0]['href']);
        $this->assertSame('/en/', $parts['path'] ?? null, 'canonical always points at the English page');
        $this->assertSame(
            (string) parse_url($this->baseUrl(), PHP_URL_SCHEME),
            $parts['scheme'] ?? null,
            'canonical scheme comes from the request, not from a re-detection of $_SERVER'
        );
        $this->assertSame(
            (string) parse_url($this->baseUrl(), PHP_URL_HOST),
            $parts['host'] ?? null,
            'canonical host comes from the request'
        );
    }

    public function testHomePageDeclaresAnAlternateForEveryOtherLanguage(): void
    {
        $links = $this->linkTags($this->get('/en/')['body']);

        $alternates = [];
        foreach ($links as $link) {
            if (($link['rel'] ?? '') === 'alternate' && isset($link['hreflang'])) {
                $alternates[$link['hreflang']] = $link['href'];
            }
        }

        // The current language is omitted; the other four are advertised.
        $expected = array_values(array_diff(self::LANGUAGES, ['en']));
        sort($expected);
        $actual = array_keys($alternates);
        sort($actual);
        $this->assertSame($expected, $actual, 'one alternate per non-current language');

        foreach ($alternates as $language => $href) {
            $this->assertSame(
                '/' . $language . '/',
                parse_url($href, PHP_URL_PATH),
                "alternate for $language points at that language's home page"
            );
            $this->assertSame(
                (string) parse_url($this->baseUrl(), PHP_URL_HOST),
                parse_url($href, PHP_URL_HOST),
                "alternate for $language is absolute against the request host"
            );
        }
    }

    public function testAlternateLinksDropTheLangQueryParameter(): void
    {
        // ?lang= is how a visitor overrides locale detection; carrying it into
        // the canonical set would advertise five URLs that all force one locale.
        $links = $this->linkTags($this->get('/en/?lang=de')['body']);

        foreach ($links as $link) {
            if (! in_array($link['rel'] ?? '', ['canonical', 'alternate'], true)) {
                continue;
            }
            $query = [];
            parse_str((string) parse_url($link['href'], PHP_URL_QUERY), $query);
            $this->assertArrayNotHasKey('lang', $query, 'lang is stripped from ' . $link['href']);
        }
    }

    public function testANonEnglishPageStillAdvertisesTheEnglishCanonical(): void
    {
        $links = $this->linkTags($this->get('/de/')['body']);

        $canonical = array_values(array_filter($links, fn(array $l) => ($l['rel'] ?? '') === 'canonical'));
        $this->assertCount(1, $canonical);
        $this->assertSame('/en/', parse_url($canonical[0]['href'], PHP_URL_PATH));
    }

    /**
     * Pull the <link> tags out of a response body as attribute maps, with
     * entity-escaped attribute values decoded.
     *
     * Deliberately attribute-order agnostic: the assertions above are about
     * which URLs are advertised, not about how laminas-view happens to
     * serialize a tag.
     *
     * @return list<array<string, string>>
     */
    private function linkTags(string $body): array
    {
        $this->assertNotSame('', $body, 'empty body — check for the fatal-200 wedge, not a test failure');

        preg_match_all('/<link\b([^>]*)>/i', $body, $tagMatches);
        $tags = [];
        foreach ($tagMatches[1] as $attributeString) {
            preg_match_all('/([a-z-]+)\s*=\s*"([^"]*)"/i', $attributeString, $attrMatches, PREG_SET_ORDER);
            $attributes = [];
            foreach ($attrMatches as $attr) {
                $attributes[strtolower($attr[1])] = html_entity_decode($attr[2], ENT_QUOTES | ENT_HTML5);
            }
            if (isset($attributes['href'])) {
                $tags[] = $attributes;
            }
        }
        $this->assertNotEmpty($tags, 'no <link> tags found in the response');
        return $tags;
    }
}
