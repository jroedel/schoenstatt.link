<?php

declare(strict_types=1);

namespace App\Sitemap;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use XMLWriter;

use function basename;
use function count;
use function file_put_contents;
use function glob;
use function in_array;
use function is_array;
use function is_dir;
use function mkdir;
use function rename;
use function rtrim;
use function sprintf;
use function strlen;
use function unlink;

/**
 * Writes the sitemap files. Replaces samdark/sitemap, which this used to use.
 *
 * ## Why the package went
 *
 * Three of the four things it did for us were wrong for this site, and the fourth is
 * `XMLWriter`:
 *
 *  - It splits at a hard-coded 10 MiB, an eighth of what the protocol allows, so it turned
 *    one legal sitemap into four and left the caller to write an index — which the caller
 *    then didn't, publishing 3,022 of 10,974 pages for years.
 *  - `getSitemapUrls($baseUrl)` is `$baseUrl . basename($file)`, so the part URLs are
 *    whatever directory string you hand it. That is how the parts came to live under
 *    `/sitemap/`, which is the bug this class exists to fix (see below).
 *  - Its gzip mode writes gzip bytes into a `.xml` filename, which forced the app to serve
 *    every part through PHP with a hand-set `Content-Encoding`. Apache cannot serve such a
 *    file correctly on its own, and that was the only reason the sitemap had to be
 *    generated inside a web request at all.
 *
 * What is left is a little over a hundred lines of XMLWriter, and the app gains atomic
 * writes, `<lastmod>`, and a size limit taken from the specification rather than from a
 * library default.
 *
 * ## The directory rule, which is the whole reason this was rewritten
 *
 * > A Sitemap file located at http://example.com/catalog/sitemap.xml can include any URLs
 * > starting with http://example.com/catalog/ but can not include URLs starting with
 * > http://example.com/images/.
 *
 * Every file this class writes therefore goes at the **docroot root**, because every URL in
 * them starts with `/en/`, `/es/`, `/de/`, `/pt/` or `/it/`. The previous layout put the
 * parts under `/sitemap/` and the index at `/en/sitemap.xml` (robots.txt named the prefixed
 * form), breaking the rule twice over — Google's Sitemaps report calls it "URL not allowed"
 * and it applied to all 36,730 URLs, which is to say the entire sitemap.
 *
 * ## Atomic, because Apache serves these files directly
 *
 * Nothing coordinates a crawler's GET with a rebuild. Every file is written to `.tmp` and
 * `rename()`d into place, which is atomic within a filesystem, so a request sees either the
 * previous complete file or the next one and never a half-written `<urlset>`. The old code
 * wrote in place and got away with it only because PHP served the files and PHP was also
 * what wrote them.
 */
final class SitemapWriter
{
    /** Protocol maxima. Google rejects a sitemap that exceeds either. */
    private const SPEC_MAX_URLS  = 50000;
    private const SPEC_MAX_BYTES = 52428800;

    /**
     * What we actually split at: 10% under the ceiling.
     *
     * Headroom rather than exactness, because the byte limit is on the finished file and an
     * entry cannot be un-written once it has pushed the count over. Today every section
     * fits in one file — publications, the big one, is ~23 MB of the 45 allowed here.
     */
    private const MAX_URLS  = 45000;
    private const MAX_BYTES = 47185920;

    private const NS_SITEMAP = 'http://www.sitemaps.org/schemas/sitemap/0.9';
    private const NS_XHTML   = 'http://www.w3.org/1999/xhtml';

    /** @var list<string> basenames written by this run, in index order */
    private array $written = [];

    /** @var array<string, DateTimeImmutable> newest lastmod seen per written basename */
    private array $fileLastModified = [];

    /**
     * @param string $directory absolute path of the docroot the files are written into
     * @param string $baseUrl scheme and host, no trailing slash
     * @param list<string> $languages locale prefixes; every page is published under each
     */
    public function __construct(
        private readonly string $directory,
        private readonly string $baseUrl,
        private readonly array $languages
    ) {
        if ([] === $this->languages) {
            throw new RuntimeException('A sitemap needs at least one language prefix.');
        }
    }

    /**
     * Write one section, splitting into `-2`, `-3`, … parts if it outgrows a single file.
     *
     * Takes an iterable and holds one entry at a time on purpose: publications is 6,477
     * records, and streaming is what keeps the finished XML from existing twice over.
     *
     * @param iterable<SitemapEntry> $entries
     */
    public function writeSection(SitemapSection $section, iterable $entries): void
    {
        $perEntry = count($this->languages);
        $part     = 1;
        $writer   = null;
        $buffer   = '';
        $urls     = 0;

        foreach ($entries as $entry) {
            if (
                null !== $writer
                && ($urls + $perEntry > self::MAX_URLS || strlen($buffer) > self::MAX_BYTES)
            ) {
                $this->finish($writer, $buffer, $this->partName($section, $part), $urls);
                $writer = null;
                $buffer = '';
                $part++;
                $urls = 0;
            }

            if (null === $writer) {
                $writer = $this->begin();
            }

            $this->writeUrl($writer, $entry, $this->partName($section, $part));
            //drain the writer into our own buffer after every entry, which is both how the
            //byte count stays exact and how XMLWriter's internal buffer stays small
            $buffer .= $writer->outputMemory(true);
            $urls   += $perEntry;
        }

        //A section with no entries writes no file and is simply absent from the index.
        //Emitting an empty <urlset> instead is Google's "Empty sitemap" error.
        if (null !== $writer) {
            $this->finish($writer, $buffer, $this->partName($section, $part), $urls);
        }
    }

    /**
     * Write `sitemap.xml`, the index naming every file written since construction, and
     * remove any `sitemap-*.xml` an earlier build left behind.
     *
     * The cleanup matters because the part names are content-dependent: a section that
     * shrinks from two files to one leaves `sitemap-publications-2.xml` on disk, and Apache
     * would go on serving that stale half of the catalogue to anyone who still has the URL —
     * a crawler very much does — for as long as the file exists.
     *
     * @return string absolute path of the index
     */
    public function writeIndex(): string
    {
        //Refuse to publish nothing. An index with no children is Google's "Empty sitemap"
        //error, and getting here means every section came back empty — a database hiccup or
        //a navigation container that failed to build, not a site with no pages. Throwing
        //leaves the previous complete set of files in place, because publish() has not run
        //and removeOrphans() runs only after it: the sitemap goes stale rather than blank,
        //and `sitemap:build` reports a failure cron can be made to mail.
        if ([] === $this->written) {
            throw new RuntimeException(
                'Refusing to write a sitemap index with no sitemaps in it; the previous files are kept.'
            );
        }

        $writer = new XMLWriter();
        $writer->openMemory();
        $writer->setIndent(true);
        $writer->setIndentString(' ');
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('sitemapindex');
        $writer->writeAttribute('xmlns', self::NS_SITEMAP);

        foreach ($this->written as $basename) {
            $writer->startElement('sitemap');
            $writer->writeElement('loc', $this->baseUrl . '/' . $basename);
            if (isset($this->fileLastModified[$basename])) {
                $writer->writeElement('lastmod', $this->stamp($this->fileLastModified[$basename]));
            }
            $writer->endElement();
        }

        $writer->endElement();
        $writer->endDocument();

        $path = $this->publish('sitemap.xml', $writer->outputMemory(true));
        $this->removeOrphans();

        return $path;
    }

    /** @return list<string> every basename this run published, index first */
    public function writtenFiles(): array
    {
        return ['sitemap.xml', ...$this->written];
    }

    private function begin(): XMLWriter
    {
        $writer = new XMLWriter();
        $writer->openMemory();
        $writer->setIndent(true);
        $writer->setIndentString(' ');
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('urlset');
        $writer->writeAttribute('xmlns', self::NS_SITEMAP);
        //declared on the root even if no entry ends up needing it: an xhtml:link with an
        //undeclared prefix is not well-formed XML, and the alternates are unconditional
        $writer->writeAttribute('xmlns:xhtml', self::NS_XHTML);

        return $writer;
    }

    /**
     * One `<url>` per language, each carrying the whole alternate set.
     *
     * Every locale is declared for every page whether a translation of it exists or not.
     * That is inherited behaviour and is deliberately kept: the alternate set here matches
     * the one the pages themselves publish, and hreflang annotations only work when the two
     * agree — an entry the page does not claim is worse than a redundant one.
     *
     * The per-locale slug *is* honoured, via `SitemapEntry::tailFor()`; it was not until
     * 2026-08-13, when the canonical links stopped naming the English URL for every
     * language and it started to matter which of the two forms was advertised.
     */
    private function writeUrl(XMLWriter $writer, SitemapEntry $entry, string $basename): void
    {
        $alternates = [];
        foreach ($this->languages as $language) {
            //tailFor(), not $entry->tail: an association's slug differs per locale, and the
            //URL published here has to be the one that language's page calls canonical
            $alternates[$language] = $this->baseUrl . '/' . $language . '/' . $entry->tailFor($language);
        }

        foreach ($alternates as $url) {
            $writer->startElement('url');
            $writer->writeElement('loc', $url);
            if (null !== $entry->lastModified) {
                $writer->writeElement('lastmod', $this->stamp($entry->lastModified));
            }
            foreach ($alternates as $language => $alternate) {
                $writer->startElement('xhtml:link');
                $writer->writeAttribute('rel', 'alternate');
                $writer->writeAttribute('hreflang', $language);
                $writer->writeAttribute('href', $alternate);
                $writer->endElement();
            }
            $writer->endElement();
        }

        if (null === $entry->lastModified) {
            return;
        }

        $seen = $this->fileLastModified[$basename] ?? null;
        if (null === $seen || $entry->lastModified > $seen) {
            $this->fileLastModified[$basename] = $entry->lastModified;
        }
    }

    /**
     * W3C Datetime, which is what Google's "Invalid date" error is about.
     *
     * Rendered in UTC because that is what the database holds — the change rows are UTC
     * while this server's own logs are local time, a discrepancy that has misdirected a log
     * search here before. `DateTimeImmutable::ATOM` gives `2026-08-13T12:40:32+00:00`.
     */
    private function stamp(DateTimeImmutable $when): string
    {
        return $when->setTimezone(new DateTimeZone('UTC'))->format(DateTimeImmutable::ATOM);
    }

    private function partName(SitemapSection $section, int $part): string
    {
        if (1 === $part) {
            return $section->filename();
        }

        return sprintf('sitemap-%s-%d.xml', $section->value, $part);
    }

    private function finish(XMLWriter $writer, string $buffer, string $basename, int $urls): void
    {
        if ($urls > self::SPEC_MAX_URLS) {
            throw new RuntimeException(
                sprintf('%s holds %d URLs, over the limit of %d.', $basename, $urls, self::SPEC_MAX_URLS)
            );
        }

        $writer->endElement();
        $writer->endDocument();
        $this->publish($basename, $buffer . $writer->outputMemory(true));
        $this->written[] = $basename;
    }

    /**
     * Write bytes to `<directory>/<basename>` atomically.
     *
     * @return string the absolute path written
     */
    private function publish(string $basename, string $xml): string
    {
        if (! is_dir($this->directory) && ! mkdir($this->directory, 0o775, true) && ! is_dir($this->directory)) {
            throw new RuntimeException(sprintf('Cannot create the sitemap directory %s.', $this->directory));
        }

        if (strlen($xml) > self::SPEC_MAX_BYTES) {
            throw new RuntimeException(
                sprintf('%s would be %d bytes, over the limit of %d.', $basename, strlen($xml), self::SPEC_MAX_BYTES)
            );
        }

        $path = rtrim($this->directory, '/') . '/' . $basename;
        $temp = $path . '.tmp';

        if (false === file_put_contents($temp, $xml)) {
            throw new RuntimeException(sprintf('Cannot write %s.', $temp));
        }
        if (! rename($temp, $path)) {
            unlink($temp);

            throw new RuntimeException(sprintf('Cannot move %s into place.', $temp));
        }

        return $path;
    }

    /** Delete `sitemap-*.xml` files this build did not write. */
    private function removeOrphans(): void
    {
        $found = glob(rtrim($this->directory, '/') . '/sitemap-*.xml');
        if (! is_array($found)) {
            return;
        }

        foreach ($found as $path) {
            if (! in_array(basename($path), $this->written, true)) {
                unlink($path);
            }
        }
    }
}
