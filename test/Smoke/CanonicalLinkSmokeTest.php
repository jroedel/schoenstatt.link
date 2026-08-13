<?php

namespace SchoenstattTest\Smoke;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The canonical and hreflang links every page emits — the tags that decide whether Google
 * indexes one language or five.
 *
 * ## This suite used to assert the bug
 *
 * Until 2026-08-13 it said, as characterizations: "canonical always points at the English
 * page", "the current language is omitted", and — by name —
 * `testANonEnglishPageStillAdvertisesTheEnglishCanonical()`. Those recorded what the layouts
 * did, faithfully, and what they did was ask Google not to index four fifths of the site:
 *
 * 1. **A canonical naming another language is an instruction to drop this page.** Google
 *    reads `<link rel="canonical">` as "index that one instead", so every German, Spanish,
 *    Portuguese and Italian page was pointing at its English equivalent and asking to be
 *    left out — while the sitemap went on offering all five, which is the contradiction
 *    Search Console reports as "Alternate page with proper canonical tag".
 * 2. **An hreflang set that omits its own page is not reciprocal**, and Google discards a
 *    non-reciprocal cluster entirely. So the one mechanism that would have let the five
 *    copies coexist without competing was inert.
 *
 * A characterization test is supposed to fail when behaviour changes, and this one did its
 * job: all three assertions broke when the layouts were fixed. What they assert now is the
 * corrected contract.
 *
 * ## Both layouts, deliberately
 *
 * Production serves ported routes through `templates/layout.html.twig` and bridged ones
 * through `module/Application/view/layout/layout.phtml`, so the two have to agree. The tests
 * below cover a Twig-rendered page (`/en/`, a ported route) and a `.phtml`-rendered one
 * (`/en/user/login`, still bridged), because a fix applied to one layout and not the other
 * is invisible until a crawler reaches the wrong kind of page.
 *
 * @see \App\View\SiteChrome::canonicalLinks()
 * @see \App\View\PreferredUrls
 */
class CanonicalLinkSmokeTest extends SmokeTestCase
{
    /** The five languages both layouts advertise. */
    private const LANGUAGES = ['en', 'es', 'de', 'pt', 'it'];

    /**
     * A ported route (Twig) and a bridged one (.phtml), so both layouts are covered.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function pages(): array
    {
        return [
            'the home page, Twig'            => ['/en/', 'en'],
            'the home page in German, Twig'  => ['/de/', 'de'],
            'a bridged page, .phtml'         => ['/en/user/login', 'en'],
            'a bridged page in German'       => ['/de/user/login', 'de'],
        ];
    }

    /**
     * Each language is canonical for itself.
     *
     * The single most important assertion here: if this fails for a non-English page, that
     * page has stopped asking to be indexed.
     */
    #[DataProvider('pages')]
    public function testEachLanguageIsCanonicalForItself(string $path, string $language): void
    {
        $links     = $this->linkTags($this->get($path)['body']);
        $canonical = array_values(array_filter($links, fn(array $l) => ($l['rel'] ?? '') === 'canonical'));

        $this->assertCount(1, $canonical, "expected exactly one rel=canonical on $path");

        $canonicalPath = (string) parse_url($canonical[0]['href'], PHP_URL_PATH);
        $this->assertSame(
            '/' . $language . '/',
            substr($canonicalPath, 0, strlen($language) + 2),
            "$path declares $canonicalPath canonical, which is a different language — that asks "
            . 'Google not to index this page'
        );
        $this->assertSame(
            (string) parse_url($this->baseUrl(), PHP_URL_SCHEME),
            parse_url($canonical[0]['href'], PHP_URL_SCHEME),
            'canonical scheme comes from the request, not from a re-detection of $_SERVER'
        );
    }

    /**
     * The hreflang set names all five languages *including this page's own*, plus x-default.
     *
     * Google: each version must list every version, itself included. A set missing its own
     * entry is not reciprocal and the whole cluster is ignored — which is what happened here
     * for years, because the loop skipped the current language.
     */
    #[DataProvider('pages')]
    public function testTheHreflangSetIsCompleteAndSelfReferencing(string $path, string $language): void
    {
        $links = $this->linkTags($this->get($path)['body']);

        $alternates = [];
        foreach ($links as $link) {
            if (($link['rel'] ?? '') === 'alternate' && isset($link['hreflang'])) {
                $alternates[$link['hreflang']] = $link['href'];
            }
        }

        $expected = self::LANGUAGES;
        $expected[] = 'x-default';
        sort($expected);
        $actual = array_keys($alternates);
        sort($actual);

        $this->assertSame(
            $expected,
            $actual,
            'the hreflang set must list every language including this one, plus x-default'
        );
        $this->assertArrayHasKey(
            $language,
            $alternates,
            "$path does not name itself in its own hreflang set, so Google discards the cluster"
        );

        //x-default is the copy for a searcher whose language we do not publish: English.
        $this->assertSame(
            $alternates['en'],
            $alternates['x-default'],
            'x-default should name the English URL'
        );
    }

    /**
     * The canonical is one of the URLs the page's own hreflang set names.
     *
     * A canonical outside the set is the specific contradiction that made this a bug rather
     * than a cosmetic inconsistency: it says "index that other page" about a page the set
     * claims is a peer.
     */
    #[DataProvider('pages')]
    public function testTheCanonicalIsAMemberOfItsOwnHreflangSet(string $path, string $language): void
    {
        $links = $this->linkTags($this->get($path)['body']);

        $canonical  = null;
        $alternates = [];
        foreach ($links as $link) {
            if (($link['rel'] ?? '') === 'canonical') {
                $canonical = $link['href'];
            }
            if (($link['rel'] ?? '') === 'alternate' && isset($link['hreflang'])) {
                $alternates[] = $link['href'];
            }
        }

        $this->assertNotNull($canonical);
        $this->assertContains(
            $canonical,
            $alternates,
            "the canonical of $path ($language) is not one of the URLs its own hreflang set names"
        );
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

    /**
     * A record page canonicalises its *preferred* URL, not the one that was requested.
     *
     * The slug selects nothing — `sw_id` does — so several URLs serve each record and the
     * site does not redirect between them. Declaring whichever one was asked for canonical
     * would make every variant its own indexable page. Associations make this sharp: their
     * slug is stored per locale, so the German page's preferred URL carries the German slug
     * even when the visitor arrived on the English one.
     *
     * @see \App\View\PreferredUrls
     */
    public function testARecordPageCanonicalisesItsPreferredUrl(): void
    {
        //the Original Shrine, requested with no slug at all
        $links     = $this->linkTags($this->get('/de/SL100319A')['body']);
        $canonical = array_values(array_filter($links, fn(array $l) => ($l['rel'] ?? '') === 'canonical'));
        $this->assertCount(1, $canonical);

        $path = (string) parse_url($canonical[0]['href'], PHP_URL_PATH);
        $this->assertMatchesRegularExpression(
            '#^/de/SL100319A/.+#',
            $path,
            'a slugless request should canonicalise to the slugged, per-locale form'
        );

        //and the same record reached through the *English* slug still says the German one
        $links     = $this->linkTags($this->get('/de/SL100319A/original-schoenstatt-shrine')['body']);
        $canonical = array_values(array_filter($links, fn(array $l) => ($l['rel'] ?? '') === 'canonical'));
        $this->assertCount(1, $canonical);
        $this->assertSame(
            $path,
            (string) parse_url($canonical[0]['href'], PHP_URL_PATH),
            'two URL forms of one record must agree on a single canonical, or both get indexed'
        );
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
