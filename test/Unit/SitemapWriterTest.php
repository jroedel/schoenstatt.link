<?php

declare(strict_types=1);

namespace SchoenstattTest\Unit;

use App\Sitemap\SitemapEntry;
use App\Sitemap\SitemapSection;
use App\Sitemap\SitemapWriter;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function is_dir;
use function mkdir;
use function preg_match;
use function preg_match_all;
use function rmdir;
use function scandir;
use function substr_count;
use function sys_get_temp_dir;
use function unlink;

require_once __DIR__ . '/../../src/Sitemap/SitemapEntry.php';
require_once __DIR__ . '/../../src/Sitemap/SitemapSection.php';
require_once __DIR__ . '/../../src/Sitemap/SitemapWriter.php';

/**
 * The writer that replaced samdark/sitemap, and the classification that decides which file
 * each page lands in.
 *
 * A unit test: it writes into a temporary directory, needs no database, no container and no
 * running app — see `php composer.phar unit`. What it pins is the handful of properties whose
 * failure is silent, every one of which has a Google error attached:
 *
 * 1. **Files are cleaned up.** A section that shrinks from two parts to one leaves the second
 *    on disk, and Apache serves it to any crawler that remembers the URL. Nothing about that
 *    is observable from the index.
 * 2. **Writes are atomic and leave no `.tmp` behind.** Apache serves these files while they
 *    are being rewritten.
 * 3. **`pub_lang_es` is not a publication.** Three of PageBuilder's page ids start with `pub_`
 *    and are not records; misfiling one gives it a `<lastmod>` read from whichever publication
 *    shares its trailing digits.
 * 4. **`<lastmod>` is a W3C datetime in UTC**, which is what "Invalid date" is about.
 */
final class SitemapWriterTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/sitemap-writer-test-' . getmypid();
        if (! is_dir($this->directory)) {
            mkdir($this->directory, 0o775, true);
        }
        $this->clear();
    }

    protected function tearDown(): void
    {
        $this->clear();
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    private function clear(): void
    {
        foreach ((array) glob($this->directory . '/*') as $path) {
            if (is_string($path) && file_exists($path)) {
                unlink($path);
            }
        }
    }

    private function writer(): SitemapWriter
    {
        return new SitemapWriter($this->directory, 'https://example.test', ['en', 'de']);
    }

    private function read(string $basename): string
    {
        $path = $this->directory . '/' . $basename;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function testItWritesOneUrlPerLanguageWithEveryAlternate(): void
    {
        $writer = $this->writer();
        $writer->writeSection(SitemapSection::PAGES, [new SitemapEntry('music')]);
        $writer->writeIndex();

        $xml = $this->read('sitemap-pages.xml');

        self::assertStringContainsString('<loc>https://example.test/en/music</loc>', $xml);
        self::assertStringContainsString('<loc>https://example.test/de/music</loc>', $xml);
        //two <url> elements, each carrying both alternates
        self::assertSame(2, substr_count($xml, '<url>'));
        self::assertSame(4, substr_count($xml, '<xhtml:link'));
        self::assertStringContainsString('xmlns:xhtml="http://www.w3.org/1999/xhtml"', $xml);
    }

    /** The home page's tail is empty, which must give `/en/` and not `/en`. */
    public function testTheHomePageKeepsItsTrailingSlash(): void
    {
        $writer = $this->writer();
        $writer->writeSection(SitemapSection::PAGES, [new SitemapEntry('')]);
        $writer->writeIndex();

        self::assertStringContainsString('<loc>https://example.test/en/</loc>', $this->read('sitemap-pages.xml'));
    }

    public function testLastmodIsAW3cDatetimeInUtc(): void
    {
        $writer = $this->writer();
        $writer->writeSection(SitemapSection::ASSOCIATIONS, [
            //given in a non-UTC zone on purpose: the file must still say UTC
            new SitemapEntry('SL1A', new DateTimeImmutable('2026-08-13 14:40:32', new DateTimeZone('+02:00'))),
        ]);
        $writer->writeIndex();

        self::assertStringContainsString(
            '<lastmod>2026-08-13T12:40:32+00:00</lastmod>',
            $this->read('sitemap-associations.xml')
        );
    }

    public function testAnEntryWithNoLastmodEmitsNone(): void
    {
        $writer = $this->writer();
        $writer->writeSection(SitemapSection::PAGES, [new SitemapEntry('developers')]);
        $writer->writeIndex();

        self::assertStringNotContainsString('<lastmod>', $this->read('sitemap-pages.xml'));
    }

    /** An empty section writes no file at all — an empty `<urlset>` is a Google error. */
    public function testAnEmptySectionWritesNoFile(): void
    {
        $writer = $this->writer();
        $writer->writeSection(SitemapSection::COMPOSITIONS, []);
        $writer->writeSection(SitemapSection::PAGES, [new SitemapEntry('music')]);
        $writer->writeIndex();

        self::assertFileDoesNotExist($this->directory . '/sitemap-compositions.xml');
        self::assertStringNotContainsString('compositions', $this->read('sitemap.xml'));
    }

    /**
     * Nothing at all is a failure, not a publication.
     *
     * If every section came back empty the cause is a database hiccup or a navigation
     * container that did not build — not a site with no pages. Writing the index anyway
     * would publish Google's "Empty sitemap" error *and* take every part with it, because
     * `removeOrphans()` deletes what this run did not write. Throwing before either happens
     * leaves the last good files in place and makes the command exit non-zero.
     */
    public function testItRefusesToPublishAnEmptyIndex(): void
    {
        file_put_contents($this->directory . '/sitemap-pages.xml', '<urlset/>');

        $writer = $this->writer();
        $writer->writeSection(SitemapSection::PAGES, []);

        try {
            $writer->writeIndex();
            self::fail('an index with no sitemaps should not be written');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('no sitemaps', $e->getMessage());
        }

        //the previous build survives untouched
        self::assertFileExists($this->directory . '/sitemap-pages.xml');
    }

    /**
     * The index names every file written, at the root, and lists no other index.
     *
     * "Nested sitemap indexes" is its own Google error, and the root-level path is the fix for
     * the directory-scope violation that voided the whole sitemap.
     */
    public function testTheIndexNamesRootLevelFilesOnly(): void
    {
        $writer = $this->writer();
        $writer->writeSection(SitemapSection::PAGES, [new SitemapEntry('music')]);
        $writer->writeSection(SitemapSection::ASSOCIATIONS, [new SitemapEntry('SL1A')]);
        $writer->writeIndex();

        $index = $this->read('sitemap.xml');

        self::assertStringContainsString('<sitemapindex', $index);
        self::assertStringContainsString('<loc>https://example.test/sitemap-pages.xml</loc>', $index);
        self::assertStringContainsString('<loc>https://example.test/sitemap-associations.xml</loc>', $index);

        //Checked against the <loc> values rather than the whole document, because the
        //sitemaps.org namespace URI itself ends `/schemas/sitemap/0.9` and matches a naive
        //search for a subdirectory. Every listed file must be one path segment deep.
        preg_match_all('#<loc>([^<]+)</loc>#', $index, $listed);
        self::assertNotEmpty($listed[1]);
        foreach ($listed[1] as $loc) {
            self::assertSame(
                1,
                preg_match('#^https://example\.test/sitemap-[a-z]+(-[0-9]+)?\.xml$#', $loc),
                "$loc is not a root-level sitemap file, so the URLs inside it are out of scope"
            );
        }
    }

    /**
     * A file from an earlier build that this one did not write is deleted.
     *
     * Apache serves whatever is on disk, so a leftover part keeps handing a crawler a stale
     * half of the catalogue for as long as it exists — and the index gives no hint of it.
     */
    public function testItRemovesFilesAnEarlierBuildLeftBehind(): void
    {
        file_put_contents($this->directory . '/sitemap-publications-2.xml', '<urlset/>');
        file_put_contents($this->directory . '/sitemap-gone.xml', '<urlset/>');

        $writer = $this->writer();
        $writer->writeSection(SitemapSection::PAGES, [new SitemapEntry('music')]);
        $writer->writeIndex();

        self::assertFileDoesNotExist($this->directory . '/sitemap-publications-2.xml');
        self::assertFileDoesNotExist($this->directory . '/sitemap-gone.xml');
        self::assertFileExists($this->directory . '/sitemap-pages.xml');
    }

    /** Nothing half-written is left where Apache would serve it. */
    public function testItLeavesNoTemporaryFiles(): void
    {
        $writer = $this->writer();
        $writer->writeSection(SitemapSection::PAGES, [new SitemapEntry('music')]);
        $writer->writeIndex();

        foreach ((array) scandir($this->directory) as $entry) {
            self::assertIsString($entry);
            self::assertStringEndsNotWith('.tmp', $entry);
        }
    }

    public function testWrittenFilesNamesTheIndexFirst(): void
    {
        $writer = $this->writer();
        $writer->writeSection(SitemapSection::PAGES, [new SitemapEntry('music')]);
        $writer->writeIndex();

        self::assertSame(['sitemap.xml', 'sitemap-pages.xml'], $writer->writtenFiles());
    }

    /**
     * Which file a page id lands in.
     *
     * The three `pub_`-prefixed non-records are the whole reason this is not a prefix match:
     * `pub_lang_es` is the literature index's Spanish filter and `pub_one_fifty_preguntas` is
     * a hand-written page.
     */
    #[DataProvider('pageIds')]
    public function testItClassifiesPageIds(?string $pageId, SitemapSection $expected): void
    {
        self::assertSame($expected, SitemapSection::forPageId($pageId));
    }

    /**
     * The **route** decides the file, so a missing id cannot misfile a record.
     *
     * This is the regression test for a live failure on 2026-08-13. PageBuilder's branches
     * are cached in APCu, an APCu segment belongs to the SAPI that created it, and
     * `assoc_<id>` had just been added to the association branch — so the console built
     * branches carrying it while the web SAPI still served one cached before the change.
     * Classifying on the id alone, that build filed all 250 associations under
     * `sitemap-pages.xml` and `removeOrphans()` then **deleted**
     * `sitemap-associations.xml`. A stale cache became a deleted file, silently.
     *
     * With the route deciding, the worst a missing id can now cost is the `<lastmod>` and
     * the per-locale slug.
     */
    #[DataProvider('routedPages')]
    public function testTheRouteDecidesTheSectionEvenWithNoId(
        ?string $pageId,
        string $route,
        SitemapSection $expected
    ): void {
        self::assertSame($expected, SitemapSection::forPage($pageId, $route));
    }

    /** @return array<string, array{0: string|null, 1: string, 2: SitemapSection}> */
    public static function routedPages(): array
    {
        return [
            'an association with no id at all' => [null, 'association', SitemapSection::ASSOCIATIONS],
            'an association with its id'       => ['assoc_319', 'association', SitemapSection::ASSOCIATIONS],
            'a publication with no id'         => [null, 'publication', SitemapSection::PUBLICATIONS],
            'a composition with no id'         => [null, 'composition', SitemapSection::COMPOSITIONS],
            //the literature index's language filter: a `pub_`-prefixed id on its own route
            'a language filter'                => ['pub_lang_es', 'publications/index', SitemapSection::PAGES],
            'the hand-made page'               => [
                'pub_one_fifty_preguntas',
                'publications/one-fifty-preguntas',
                SitemapSection::PAGES,
            ],
            'a static page'                    => [null, 'shrines', SitemapSection::PAGES],
            //no route at all: the id decides, which is the pre-2026-08-13 behaviour
            'no route, id decides'             => ['pub_1234', '', SitemapSection::PUBLICATIONS],
            'no route and no id'               => [null, '', SitemapSection::PAGES],
        ];
    }

    /** @return array<string, array{0: string|null, 1: SitemapSection}> */
    public static function pageIds(): array
    {
        return [
            'no id at all'         => [null, SitemapSection::PAGES],
            'a publication'        => ['pub_1234', SitemapSection::PUBLICATIONS],
            'an association'       => ['assoc_319', SitemapSection::ASSOCIATIONS],
            'a composition'        => ['composition_88', SitemapSection::COMPOSITIONS],
            'a language filter'    => ['pub_lang_es', SitemapSection::PAGES],
            'the empty language'   => ['pub_lang_', SitemapSection::PAGES],
            'the hand-made page'   => ['pub_one_fifty_preguntas', SitemapSection::PAGES],
            'a dictionary'         => ['dict_es', SitemapSection::PAGES],
            'a library'            => ['lib_7', SitemapSection::PAGES],
            'a leading zero is no id' => ['pub_007', SitemapSection::PAGES],
            'a bare prefix'        => ['pub_', SitemapSection::PAGES],
        ];
    }

    #[DataProvider('recordIds')]
    public function testItReadsRecordIds(string $pageId, string $prefix, ?int $expected): void
    {
        self::assertSame($expected, SitemapSection::recordId($pageId, $prefix));
    }

    /** @return array<string, array{0: string, 1: string, 2: int|null}> */
    public static function recordIds(): array
    {
        return [
            'a publication'      => ['pub_1234', 'pub_', 1234],
            'a library'          => ['lib_7', 'lib_', 7],
            'the wrong prefix'   => ['pub_1234', 'lib_', null],
            'not a number'       => ['pub_lang_es', 'pub_', null],
            'zero is not an id'  => ['pub_0', 'pub_', null],
            'no leading zeros'   => ['pub_012', 'pub_', null],
            'nothing after it'   => ['pub_', 'pub_', null],
        ];
    }
}
