<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use PDO;

use function array_diff;
use function array_diff_key;
use function array_intersect_key;
use function array_keys;
use function array_slice;
use function array_values;
use function count;
use function intdiv;
use function max;
use function explode;
use function gzdecode;
use function implode;
use function preg_match;
use function preg_match_all;
use function str_contains;
use function str_starts_with;

/**
 * `/sitemap.xml` — a static file Apache serves — and the errors that made it worthless.
 *
 * ## What these assert, and why none of it is about status codes
 *
 * Every defect this suite guards was invisible from the response: the sitemap was a valid,
 * well-formed XML document that Google discarded in its entirety, and then a valid one full
 * of URLs that redirect. Measured against production on 2026-08-13:
 *
 * 1. **Wrong directory.** A sitemap may only list URLs at or below its own directory, so the
 *    parts under `/sitemap/` could not legally contain one `/en/…` URL — all 36,730 of them
 *    were out of scope, and robots.txt naming `/en/sitemap.xml` broke the same rule again for
 *    the index. `testEverySitemapSitsAtTheDocrootRoot()` and
 *    `testRobotsNamesTheRootSitemap()`.
 * 2. **1,260 entries a crawler cannot open**: `/admin`, `/movement`, `/libraries`, one
 *    library, and 248 of 498 associations, all answering 302.
 *    `testGatedPagesAreNotPublished()`.
 * 3. **30 `#fragment` duplicates** and a `/literature/` that 404s.
 *    `testNoFragmentsAndNoTrailingSlashes()`.
 * 4. **Truncation.** Before the index existed, one file carried 3,022 of 10,974 pages and no
 *    composition at all. `testEveryEntityKindIsRepresented()`.
 *
 * The suite reads the *published* files rather than the generator, because every one of these
 * was a defect in what was published while the generator looked correct.
 *
 * Note the base URLs inside the files are the site's canonical host, not the capsule's — a
 * sitemap must list canonical URLs, so `sion_model.canonical_base_url` wins over the request.
 * That is why the parts are fetched by path rather than by the URL the index prints.
 */
class SitemapSmokeTest extends SmokeTestCase
{
    /** The index's own body. */
    private function index(): string
    {
        return $this->body('/sitemap.xml');
    }

    /**
     * Every `<loc>` from every file the index names.
     *
     * @return list<string>
     */
    private function publishedUrls(): array
    {
        preg_match_all('#<loc>([^<]+)</loc>#', $this->index(), $parts);
        self::assertNotEmpty($parts[1], 'the sitemap index lists no files at all');

        $urls = [];
        foreach ($parts[1] as $partUrl) {
            foreach ($this->locsIn($this->body($this->pathOf($partUrl))) as $url) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    /** @return list<string> */
    private function locsIn(string $xml): array
    {
        preg_match_all('#<loc>([^<]+)</loc>#', $xml, $found);

        return $found[1];
    }

    /** The path part of an absolute URL — the smoke client speaks paths. */
    private function pathOf(string $url): string
    {
        return '/' . implode('/', array_slice(explode('/', $url), 3));
    }

    /** A sitemap file's body, decompressed if Apache sent it compressed. */
    private function body(string $path): string
    {
        $response = $this->get($path);
        self::assertSame(200, $response['status'], "$path did not answer 200");

        $body = $response['body'];
        if (str_starts_with($body, "\x1f\x8b")) {
            $decoded = gzdecode($body);
            self::assertNotFalse($decoded, "$path claims gzip but does not decompress");

            return $decoded;
        }

        return $body;
    }

    /**
     * Every file the index names is at the docroot root.
     *
     * This is the assertion that would have caught the original bug, and it is a one-line
     * check for a defect that silently voided the whole sitemap for years. A sitemap at
     * `/sitemap/pages.xml` may only list URLs under `/sitemap/`; ours list `/en/…`, so the
     * only legal home for them is `/`.
     */
    public function testEverySitemapSitsAtTheDocrootRoot(): void
    {
        foreach ($this->locsIn($this->index()) as $partUrl) {
            $path = $this->pathOf($partUrl);

            self::assertSame(
                1,
                preg_match('#^/sitemap-[a-z]+(-[0-9]+)?\.xml$#', $path),
                "$partUrl is not at the docroot root, so every URL inside it is out of scope "
                . 'for Google and the file is ignored'
            );
        }
    }

    /**
     * robots.txt names the root sitemap, not the locale-prefixed one.
     *
     * `Sitemap: https://schoenstatt.link/en/sitemap.xml` put the index in `/en/` and so out of
     * scope for the files it lists. The prefixed URL still answers — see
     * ApplicationSmokeTest — but it is not what is advertised.
     */
    public function testRobotsNamesTheRootSitemap(): void
    {
        $robots = $this->get('/robots.txt');
        self::assertSame(200, $robots['status']);

        self::assertMatchesRegularExpression(
            '#^Sitemap:\s*https?://[^/]+/sitemap\.xml\s*$#m',
            $robots['body'],
            'robots.txt must name a sitemap at the docroot root'
        );
    }

    /**
     * The index leads to every page, and there are as many as there should be.
     *
     * A floor rather than an exact number because the catalogue grows, and a *ceiling* too:
     * the count dropping back toward 36,730 would mean the gated pages and the fragment
     * duplicates had returned, and it rising past 50,000 in one file is a Google error the
     * writer is supposed to split before reaching.
     */
    public function testTheIndexLeadsToEveryPage(): void
    {
        $urls = $this->publishedUrls();

        self::assertGreaterThan(
            30000,
            count($urls),
            'the sitemap is publishing far fewer pages than it should — the likeliest cause is '
            . 'serving or listing one file instead of all of them'
        );
    }

    /**
     * Compositions are in it.
     *
     * They are last in the navigation tree, so they fell entirely into the parts nobody
     * served: the old sitemap contained zero of the site's 335 songs. One entity kind missing
     * completely is the signature of truncation and a sharper check than a count.
     */
    public function testEveryEntityKindIsRepresented(): void
    {
        $urls = implode("\n", $this->publishedUrls());

        self::assertMatchesRegularExpression('#/en/SL\d+A/#', $urls, 'no association in the sitemap');
        self::assertMatchesRegularExpression('#/en/SL\d+L/#', $urls, 'no publication in the sitemap');
        self::assertMatchesRegularExpression(
            '#/en/SL\d+C/#',
            $urls,
            'no composition in the sitemap — the tail of the tree is being dropped, which is the '
            . 'truncation the index fixed'
        );
    }

    /**
     * Nothing a crawler cannot open.
     *
     * `/admin` is the one that matters most: it was published in five languages, guarded
     * `sch_moderator`/`translator`, answering 302 to the login page. `/movement` (route
     * `schoenstatt`) and `/libraries` were the same story, and library 5 is the per-record
     * case — `/libraries/1` is public and `/libraries/5` is not, from one route with one guard
     * entry.
     */
    public function testGatedPagesAreNotPublished(): void
    {
        $urls = "\n" . implode("\n", $this->publishedUrls()) . "\n";

        foreach (['/en/admin', '/en/movement', '/en/libraries', '/en/libraries/5'] as $gated) {
            self::assertStringNotContainsString(
                $gated . "\n",
                $urls,
                "$gated is in the sitemap and answers 302 to the login page"
            );
        }

        //and the one the route ACL cannot see: an association outside PUBLIC_KINDS, which
        //AssociationController redirects a guest away from. SL100001A is the Secular Institute
        //of Schoenstatt Fathers; SL100319A is the Original Shrine and must stay.
        self::assertStringNotContainsString('/en/SL100001A', $urls, 'a non-public association is published');
        self::assertStringContainsString('/en/SL100319A', $urls, 'the Original Shrine is missing');
    }

    /**
     * No `#fragment` entries and no path that 404s on a trailing slash.
     *
     * Google discards a fragment, so `/en/shrines#Africa` was a duplicate of `/en/shrines`,
     * which is listed anyway — 30 entries.
     *
     * `/en/literature/` was the trailing-slash one: PageBuilder filed publications with no
     * language under `''` and assembled a URL that answers 404, while `/en/literature`
     * answers 200 and is listed separately. **Fixed at source 2026-08-14** — the group is no
     * longer built, so this half now guards against a class of defect rather than a live
     * instance of it. Keep it: `Segment::assemble()` does not enforce a route's own
     * constraints, so any null key reaching any segment route reproduces the shape.
     */
    public function testNoFragmentsAndNoTrailingSlashes(): void
    {
        foreach ($this->publishedUrls() as $url) {
            self::assertStringNotContainsString('#', $url, "$url carries a fragment");
            self::assertDoesNotMatchRegularExpression(
                '#^https?://[^/]+/[a-z]{2}/.+/$#',
                $url,
                "$url ends in a slash, which is a route assembled with an empty parameter"
            );
        }
    }

    /**
     * A merged publication is not a page, so it is not in the sitemap — checked against every
     * row, not against a remembered example.
     *
     * `/en/SL200417L` answers 301 to `/en/SL207340L`; 3,630 of 10,166 publications here are
     * merged, 3,627 of them public. Offering a crawler thousands of permanent redirects as
     * canonical URLs is what this excludes.
     *
     * **This used to assert that one hardcoded id was absent and its survivor present**, which
     * only ever caught total failure. Two things it could not catch, both of them live risks:
     *
     *  - **A merge made after the test was written.** `copyPublicationToMainCorpus()` marks its
     *    source row merged, so every use of "Copy into main corpus" creates a new redirect that
     *    must leave the sitemap. That path was unreachable for years and works again as of
     *    2026-08-14, so the set is no longer static.
     *  - **A partial failure.** `SitemapGenerator::mergedPublicationIds()` catches `Throwable`
     *    and excludes nothing — deliberately, because an over-broad sitemap is recoverable and
     *    an empty one is a Google error in its own right. That policy is only safe if something
     *    notices, and nothing else does: the file stays well-formed and the build stays silent.
     *
     * So the assertion is set equality against the database, in both directions. Over-broad
     * fails too, because a missing edition is a page withdrawn from the index.
     */
    public function testMergedPublicationsAreExcluded(): void
    {
        $published = $this->publishedPublicationIds();
        self::assertNotEmpty($published, 'the sitemap publishes no publication at all');

        //`publication_public` is the only resourceId the navigation renders, so it is also the
        //only one the sitemap can contain — getPublicationNavigationData() filters on the same
        //value that getMergedPublicationIds() does. The other three merged rows are not
        //candidates, and comparing against them would report false "missing" ids.
        $statement = $this->pdo()->prepare(
            'SELECT PublicationId, MergedIntoPublicationId IS NOT NULL AS merged
               FROM sch_publications
              WHERE ResourceId = ?'
        );
        $statement->execute(['publication_public']);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        $shouldPublish = [];
        $merged        = [];
        foreach ($rows as $row) {
            $id = (int) $row['PublicationId'];
            if ((int) $row['merged'] === 1) {
                $merged[$id] = true;
                continue;
            }
            $shouldPublish[$id] = true;
        }
        self::assertNotEmpty($merged, 'no merged publication in the database, so this proves nothing');

        //the mapping is arithmetic, so a wrong offset would silently compare two disjoint sets
        //and pass. Every published id must be a real row.
        $unknown = array_diff(array_keys($published), array_keys($shouldPublish), array_keys($merged));
        self::assertSame(
            [],
            array_values(array_slice($unknown, 0, 10)),
            'these sitemap URLs map to no public publication row — the SL<n>L offset is wrong, '
            . 'and with a wrong offset the merged-id comparison below is meaningless'
        );

        $leaked = array_intersect_key($published, $merged);
        self::assertSame(
            [],
            array_slice($leaked, 0, 10, true),
            count($leaked) . ' merged publication(s) are in the sitemap; each answers only a 301 '
            . 'to the edition it was merged into (e.g. /en/SL200417L -> /en/SL207340L)'
        );

        $missing = array_diff_key($shouldPublish, $published);
        self::assertSame(
            [],
            array_slice($missing, 0, 10, true),
            count($missing) . ' unmerged public publication(s) are missing, so the exclusion is '
            . 'too broad — those pages answer 200 and are no longer offered to a crawler'
        );
    }

    /**
     * Publication ids the sitemap publishes, keyed on the id.
     *
     * Read from *every* file the index names rather than from `sitemap-publications.xml`,
     * because misfiling is a defect that has actually happened here: a per-SAPI navigation
     * cache once put all 250 associations into `sitemap-pages.xml` and
     * `SitemapWriter::removeOrphans()` then deleted the file they belonged in. A merged
     * publication in the wrong file is still published.
     *
     * `200000` is `SchoenstattLinkIdentifier::ENTITY_STARTING_NUMBER['publication']`. It is
     * copied rather than imported because this suite has no autoloader on purpose — see
     * `test/bootstrap.php` — and the caller guards against it being wrong.
     *
     * @return array<int, true>
     */
    private function publishedPublicationIds(): array
    {
        //publishedUrls() hands back the <loc> contents, so these are bare URLs
        preg_match_all(
            '#/[a-z]{2}/SL(\d+)L(?:/\S*)?$#m',
            implode("\n", $this->publishedUrls()),
            $found
        );

        $ids = [];
        foreach ($found[1] as $number) {
            $ids[(int) $number - 200000] = true;
        }

        return $ids;
    }

    /**
     * Records carry a `<lastmod>`, and it is a W3C date.
     *
     * Read from `sch_changes`, not from the entity's own `UpdatedOn` column, which is not
     * maintained — association 319 was edited three times on 2026-08-13 and every one of its
     * eleven `*UpdatedOn` columns still reads 2019. Google uses `<lastmod>` only when it is
     * consistently accurate, so publishing the stale column would be worse than publishing
     * nothing.
     */
    public function testRecordsCarryAValidLastmod(): void
    {
        $associations = $this->body('/sitemap-associations.xml');

        preg_match_all('#<lastmod>([^<]+)</lastmod>#', $associations, $stamps);
        self::assertNotEmpty($stamps[1], 'no <lastmod> in the associations sitemap');

        foreach ($stamps[1] as $stamp) {
            self::assertSame(
                1,
                preg_match('#^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$#', $stamp),
                "$stamp is not a W3C datetime, which is Google's \"Invalid date\" error"
            );
        }
    }

    /**
     * Every URL the sitemap advertises is the one its page calls canonical.
     *
     * This is the assertion that makes the whole thing work, and it can fail in both
     * directions without anything else noticing:
     *
     *  - A canonical naming a *different language* asks Google not to index this page. That
     *    is what every non-English page did until 2026-08-13 — all five locales declared
     *    `/en/…` canonical — so four fifths of the site was being withdrawn from the index
     *    while the sitemap went on offering it.
     *  - A canonical naming a *different slug* wastes the crawl. An association's slug is
     *    stored per locale, so `/de/SL100319A/urheiligtum` is the German page's own
     *    preferred URL while the tail from the navigation tree would say
     *    `original-schoenstatt-shrine`. Search Console reports the difference as "Alternate
     *    page with proper canonical tag".
     *
     * Sampled rather than exhaustive: 35,440 page fetches is not a smoke test. The sample is
     * drawn from the associations file and skewed away from English, because that is where
     * both failure modes actually live.
     */
    public function testSitemapUrlsAreTheCanonicalOnes(): void
    {
        $urls = $this->locsIn($this->body('/sitemap-associations.xml'));
        self::assertNotEmpty($urls);

        $nonEnglish = [];
        foreach ($urls as $url) {
            if (! str_contains($url, '/en/')) {
                $nonEnglish[] = $url;
            }
        }
        self::assertNotEmpty($nonEnglish, 'the sitemap publishes no non-English association URLs');

        //deterministic pick, so a failure is reproducible
        $step   = max(1, intdiv(count($nonEnglish), 6));
        $sample = [];
        for ($i = 0; $i < count($nonEnglish) && count($sample) < 6; $i += $step) {
            $sample[] = $nonEnglish[$i];
        }

        foreach ($sample as $url) {
            $response = $this->get($this->pathOf($url));
            self::assertSame(200, $response['status'], "$url did not answer 200");

            self::assertSame(
                1,
                preg_match('#<link href="([^"]+)" rel="canonical">#', $response['body'], $found),
                "$url declares no canonical"
            );
            self::assertSame(
                $this->pathOf($url),
                $this->pathOf($found[1]),
                "the sitemap offers $url but the page calls {$found[1]} canonical"
            );
        }
    }

    /**
     * Apache serves these, not PHP.
     *
     * That is the whole point of writing them into the docroot, and it is worth an assertion
     * because the symptom of losing it is invisible: the sitemap keeps working, just through
     * PHP, and the 0.6s navigation walk comes back into the request path.
     *
     * **Deliberately not asserted by `ETag`.** The first version of this test did, and it
     * was wrong in a way worth recording: production sends no ETag on *any* static file —
     * `/css/gen-basic.css`, `/favicon.ico` and `/robots.txt` are all without one, the
     * hoster has them off — while Apache in the capsule sends them. So the assertion passed
     * locally, passed CI, and failed on the live site while the deploy had in fact worked
     * perfectly. A test that only holds in the capsule is worse than no test, because it
     * fails at the moment you most need to trust it.
     *
     * The two below hold in both places. `Accept-Ranges: bytes` comes from Apache's static
     * file handler and Symfony's `BinaryFileResponse` does not set it; and PHP cannot answer
     * this route without starting a session, so the fallback always carries `Set-Cookie`
     * while Apache never does.
     */
    public function testTheFilesAreServedStatically(): void
    {
        $headers = $this->get('/sitemap.xml')['headers'];

        self::assertArrayNotHasKey(
            'set-cookie',
            $headers,
            'the sitemap set a session cookie, so PHP served it rather than Apache — check that '
            . 'public/sitemap.xml exists and that bin/console sitemap:build has run'
        );
        self::assertSame(
            'bytes',
            $headers['accept-ranges'] ?? null,
            'no Accept-Ranges, so Apache is not handling this as a static file'
        );
    }

    private function pdo(): PDO
    {
        return new PDO(
            'mysql:host=db;dbname=ourlink_db1;charset=utf8mb4',
            'schoenstatt',
            'schoenstatt',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
}
