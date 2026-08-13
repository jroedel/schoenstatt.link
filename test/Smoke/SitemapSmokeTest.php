<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use function array_slice;
use function count;
use function explode;
use function gzdecode;
use function implode;
use function preg_match_all;
use function str_starts_with;

/**
 * `/sitemap.xml` — the sitemap index, ported 2026-08-13, and the two defects the port
 * fixed.
 *
 * The laminas action wrote four files and served one. `samdark\sitemap\Sitemap` splits at
 * 10 MB; nothing wrote an index; `public/robots.txt` names `/en/sitemap.xml`. So a crawler
 * saw 3,022 of 10,974 pages — every association, the first 2,500 publications, and **no
 * composition at all**. Nothing about that was observable from the response: it was a
 * valid, well-formed sitemap of the wrong size.
 *
 * The assertions below are therefore about *coverage*, not about status codes. Each one
 * fails if the index stops listing every part, if a part stops being reachable, or if the
 * merged publications come back.
 */
class SitemapSmokeTest extends SmokeTestCase
{
    /**
     * The whole thing: every URL from every part the index names.
     *
     * @return list<string>
     */
    private function publishedUrls(): array
    {
        $index = $this->body('/sitemap.xml');

        preg_match_all('#<loc>([^<]+)</loc>#', $index, $parts);
        self::assertNotEmpty($parts[1], 'the sitemap index lists no parts at all');

        $urls = [];
        foreach ($parts[1] as $partUrl) {
            //the index publishes absolute URLs; the smoke client speaks paths
            $path = '/' . implode('/', array_slice(explode('/', $partUrl), 3));
            preg_match_all('#<loc>([^<]+)</loc>#', $this->body($path), $found);
            foreach ($found[1] as $url) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    /** A sitemap file's decompressed body. Everything it serves is gzip, index included. */
    private function body(string $path): string
    {
        $response = $this->get($path);
        self::assertSame(200, $response['status'], "$path did not answer 200");

        $body = $response['body'];
        //The files are written gzipped and the response declares it, so curl hands back
        //the compressed bytes. The index was NOT gzipped at first while the header said it
        //was — a 354-byte file no client could read — which is why this is asserted rather
        //than assumed.
        if (str_starts_with($body, "\x1f\x8b")) {
            $decoded = gzdecode($body);
            self::assertNotFalse($decoded, "$path claims gzip but does not decompress");

            return $decoded;
        }

        return $body;
    }

    /**
     * Every part the index names is reachable, and together they cover the whole site.
     *
     * The count is asserted as a floor rather than an exact number because the catalogue
     * grows. 7,000 is well above the 3,022 the old endpoint published and well below the
     * 7,347 this capsule's data produces, so it fails on a regression to single-file
     * serving and not on a new publication.
     */
    public function testTheIndexLeadsToEveryPage(): void
    {
        $urls = $this->publishedUrls();

        self::assertGreaterThan(
            7000,
            count($urls),
            'the sitemap is publishing far fewer pages than it should — the most likely cause is '
            . 'serving one part instead of the index, which is exactly what the laminas action did'
        );
    }

    /**
     * Compositions are in it.
     *
     * They are last in the navigation tree, so they fell entirely into the parts nobody
     * served: the old sitemap contained zero of the site's 335 songs. One entity kind
     * missing completely is the signature of truncation, and it is a sharper check than a
     * count.
     */
    public function testEveryEntityKindIsRepresented(): void
    {
        $urls = implode("\n", $this->publishedUrls());

        //an association, a publication and a composition identifier respectively
        self::assertMatchesRegularExpression('#/en/SL\d+A/#', $urls, 'no association in the sitemap');
        self::assertMatchesRegularExpression('#/en/SL\d+L/#', $urls, 'no publication in the sitemap');
        self::assertMatchesRegularExpression(
            '#/en/SL\d+C/#',
            $urls,
            'no composition in the sitemap — the tail of the tree is being dropped, which is the '
            . 'truncation this port fixed'
        );
    }

    /**
     * A merged publication is not a page, so it is not in the sitemap.
     *
     * `/en/SL200417L` answers 301 to `/en/SL207340L`; 3,627 of the 10,104 public
     * publications here are merged. Offering a crawler thousands of permanent redirects as
     * canonical URLs is the thing this excludes.
     */
    public function testMergedPublicationsAreExcluded(): void
    {
        $urls = implode("\n", $this->publishedUrls());

        self::assertStringNotContainsString(
            '/en/SL200417L',
            $urls,
            'a merged publication is in the sitemap; it only ever answers a 301 to the edition it '
            . 'was merged into'
        );
        self::assertStringContainsString(
            '/en/SL207340L',
            $urls,
            'the surviving edition of that merge is missing, so the exclusion is too broad'
        );
    }

    /** A part name the generator never wrote is a 404, not a file read. */
    public function testAnUnknownPartIsNotFound(): void
    {
        self::assertSame(404, $this->get('/sitemap/pages_99.xml')['status']);
    }

    /**
     * A path that tries to leave the directory never reaches the controller: the route's
     * own `filename` requirement refuses it, so it falls through to the legacy catch-all.
     */
    public function testAPathTraversalDoesNotMatchTheRoute(): void
    {
        self::assertNotSame(
            200,
            $this->get('/sitemap/../../config/autoload/local.php')['status'],
            'the sitemap part route accepted a filename outside the sitemap directory'
        );
    }
}
