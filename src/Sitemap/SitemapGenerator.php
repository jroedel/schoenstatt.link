<?php

declare(strict_types=1);

namespace App\Sitemap;

use App\Laminas\ServiceBridge;
use App\Locale\Locales;
use App\View\NavigationTree;
use App\View\PreferredUrls;
use Books\Model\PublicationsTable;
use DateTimeImmutable;
use DateTimeZone;
use Schoenstatt\Model\SchoenstattTable;
use Throwable;

use function array_keys;
use function clearstatcache;
use function file_exists;
use function filemtime;
use function in_array;
use function is_array;
use function is_numeric;
use function is_string;
use function rtrim;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Builds the sitemap files, and knows when it does not have to.
 *
 * ## What this is now, after 2026-08-13
 *
 * Static files in the docroot, written by `bin/console sitemap:build` and served by Apache.
 * PHP is not in the request path at all: `public/.htaccess` passes any request whose
 * `REQUEST_FILENAME` exists straight through, and `mod_deflate` compresses `text/xml` on the
 * way out — which is why nothing here gzips anything any more, and why the old
 * `Content-Encoding: gzip` dance in `SitemapController` is gone with it.
 *
 * Four things were wrong before, all of them measured against production rather than
 * reasoned about:
 *
 * 1. **The files were in the wrong directory, so Google discarded every URL.** A sitemap may
 *    only list URLs at or below its own directory. The parts were served from `/sitemap/`
 *    and listed `/en/…`, and robots.txt named `/en/sitemap.xml` while the index it points at
 *    listed files under `/sitemap/` — two violations of the same rule, together covering all
 *    36,730 URLs. See SitemapWriter.
 * 2. **1,260 entries were pages an anonymous visitor cannot open.** See GuestAccess.
 * 3. **30 entries carried a `#fragment`**, which Google discards, making them duplicates of
 *    pages already listed. See `isPublishable()`.
 * 4. **No `<lastmod>` anywhere.** See ChangeLog.
 *
 * ## Freshness is a fact about the data, not a cached flag
 *
 * `isStale()` compares the newest row in `sch_changes` against the index file's mtime. That
 * is deliberately not a cache entry, and the reason is not tidiness: an APCu segment belongs
 * to the SAPI that created it, so a stamp written by this console process would be invisible
 * to the web server and vice versa. A filesystem timestamp and a database column are the
 * only two things both SAPIs agree on. It also means invalidation needs no hook in the write
 * path — editing an association makes the sitemap stale by definition, and the next build
 * notices.
 *
 * ## Merged publications are still excluded
 *
 * A merged publication answers 301 to its surviving edition, and 3,627 of the 10,104 public
 * publications here are merged. They are dropped here rather than in
 * `Application\Navigation\PageBuilder`, which would also change the laminas menus and
 * breadcrumbs: in a menu a merged publication is merely harmless.
 */
final class SitemapGenerator
{
    /** The index, and the only name robots.txt or Search Console ever needs. */
    public const INDEX = 'sitemap.xml';

    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly NavigationTree $tree,
        private readonly ChangeLog $changes,
        private readonly GuestAccess $access,
        private readonly PreferredUrls $preferred,
        private readonly string $docroot
    ) {
    }

    /**
     * Whether the files need rebuilding.
     *
     * True when the index is missing, when its mtime cannot be read, or when the change log
     * holds something newer. A change log we cannot query reads as stale, which costs a
     * rebuild nobody needed rather than freezing the sitemap forever.
     */
    public function isStale(): bool
    {
        $index = $this->docroot . '/' . self::INDEX;

        clearstatcache(true, $index);
        if (! file_exists($index)) {
            return true;
        }

        $builtAt = filemtime($index);
        if (false === $builtAt) {
            return true;
        }

        $newestChange = $this->changes->newest();
        if (null === $newestChange) {
            return true;
        }

        $built = new DateTimeImmutable('@' . $builtAt, new DateTimeZone('UTC'));

        return $newestChange > $built;
    }

    /**
     * Build every file and return the index's absolute path.
     *
     * @param string $baseUrl scheme and host, no trailing slash
     * @return list<string> the basenames written
     */
    public function build(string $baseUrl): array
    {
        $writer = new SitemapWriter($this->docroot, rtrim($baseUrl, '/'), $this->languages());

        foreach ($this->sections() as $section => $entries) {
            $writer->writeSection(SitemapSection::from($section), $entries);
        }
        $writer->writeIndex();

        return $writer->writtenFiles();
    }

    /** @return list<string> */
    private function languages(): array
    {
        $languages = [];
        foreach (array_keys(Locales::ALIASES) as $language) {
            $languages[] = (string) $language;
        }

        return $languages;
    }

    /**
     * Every publishable page, bucketed by the file it belongs in.
     *
     * Walks the navigation tree once — it is the expensive thing here, about 0.6 s for the
     * whole container — and returns arrays rather than generators because the buckets have to
     * be complete before the first one is written.
     *
     * @return array<string, list<SitemapEntry>>
     */
    private function sections(): array
    {
        $buckets = [];
        foreach (SitemapSection::cases() as $section) {
            $buckets[$section->value] = [];
        }

        $stamps           = $this->stampsBySection();
        $excluded         = $this->excludedRecordIds();
        $associationTails = $this->associationTailsByLanguage();
        $prefixLen        = strlen('/' . Locales::aliasFor(Locales::DEFAULT_LOCALE) . '/');
        $seen             = [];

        foreach ($this->tree->flattened() as $page) {
            $href = $page['href'];
            if (! $this->isPublishable($href, $page['route'], $page['id'])) {
                continue;
            }

            $section = SitemapSection::forPageId($page['id']);
            $prefix  = $section->idPrefix();
            $id      = null !== $prefix && is_string($page['id'])
                ? SitemapSection::recordId($page['id'], $prefix)
                : null;

            if (null !== $id && isset($excluded[$section->value][$id])) {
                continue;
            }

            //The tail is everything after the locale prefix; the writer puts each language's
            //own prefix back. Same arithmetic the laminas action used.
            $tail = substr($href, $prefixLen);
            if (isset($seen[$tail])) {
                //`World` and `Shrines` share a route, so a duplicate here is not theoretical
                continue;
            }
            $seen[$tail] = true;

            $buckets[$section->value][] = new SitemapEntry(
                $tail,
                null !== $id ? ($stamps[$section->value][$id] ?? null) : null,
                SitemapSection::ASSOCIATIONS === $section && null !== $id
                    ? ($associationTails[$id] ?? null)
                    : null
            );
        }

        return $buckets;
    }

    /**
     * Is this page one a crawler should be given?
     *
     * Five refusals, in the order they are cheapest to decide. Every one of them was a live
     * entry in the published sitemap on 2026-08-13, and each is a different Google error:
     *
     *  - **No href**, or one that is not a rooted path. Nothing to publish.
     *  - **A `#fragment`.** Google discards it, so `/en/shrines#Africa` is a duplicate entry
     *    for `/en/shrines`, which the tree also contains. 30 such entries; the old
     *    deduplication keyed on the whole URL, so the fragment defeated it.
     *  - **A path ending in `/`.** `Application\Navigation\PageBuilder` groups publications
     *    by language and files the ones with no language under `''`, so it assembles
     *    `publications/index` with `inLanguage => ''` and produces `/en/literature/`, which
     *    **404s** — while `/en/literature` is listed separately and answers 200. The
     *    navigation menu still shows that dead "Other Schoenstatt Literature" link; fixing
     *    that is a menu change and is deliberately not done here.
     *  - **A route `guest` may not reach** — `/admin`, `/movement`, `/libraries`, all 302 to
     *    the login page.
     *  - **A record `guest` may not see**, which is not the same question: `/libraries/1` and
     *    `/libraries/5` share one route and one guard entry, and the ACL separates them by a
     *    per-library resource. See GuestAccess::isAllowedAsGuest().
     */
    private function isPublishable(string $href, string $routeName, ?string $pageId): bool
    {
        if ('' === $href || ! str_starts_with($href, '/')) {
            return false;
        }
        if (str_contains($href, '#')) {
            return false;
        }
        //the locale root assembles as `/en/`, whose tail is '' — every *other* trailing slash
        //is a route assembled with an empty parameter
        $localeRoot = '/' . Locales::aliasFor(Locales::DEFAULT_LOCALE) . '/';
        if (str_ends_with($href, '/') && strlen($href) > strlen($localeRoot)) {
            return false;
        }
        if (! $this->access->isRoutePublic($routeName)) {
            return false;
        }

        return $this->isRecordVisible($pageId);
    }

    /**
     * The per-record half of the ACL, for the page kinds that have one.
     *
     * Only libraries today. Kept separate from the route check because it asks a different
     * question of a different resource, and because a page kind that gains a per-record rule
     * later should be added here rather than hidden inside the route lookup.
     */
    private function isRecordVisible(?string $pageId): bool
    {
        if (null === $pageId) {
            return true;
        }

        $libraryId = SitemapSection::recordId($pageId, 'lib_');
        if (null === $libraryId) {
            return true;
        }

        return $this->access->isAllowedAsGuest('library_' . $libraryId, 'show');
    }

    /**
     * Record ids that must not be published, per section.
     *
     * @return array<string, array<int, true>>
     */
    private function excludedRecordIds(): array
    {
        return [
            SitemapSection::PUBLICATIONS->value => $this->mergedPublicationIds(),
            SitemapSection::ASSOCIATIONS->value => $this->nonPublicAssociationIds(),
        ];
    }

    /**
     * Merged publications: their URL is a 301 and nothing else.
     *
     * @return array<int, true>
     */
    private function mergedPublicationIds(): array
    {
        $ids = [];

        try {
            /** @var PublicationsTable $table */
            $table = $this->laminas->get(PublicationsTable::class);
            /** @var array<int, int> $merged */
            $merged = $table->getMergedPublicationIds();
        } catch (Throwable) {
            return [];
        }

        foreach ($merged as $id) {
            $ids[(int) $id] = true;
        }

        return $ids;
    }

    /**
     * Associations `AssociationController` redirects an anonymous visitor away from.
     *
     * The rule is the controller's, not the ACL's, so it cannot be seen by
     * `GuestAccess::isRoutePublic()` or by `tools/acl-table.php`: `route/association` is
     * public and the controller then sends a guest to the home page for every kind outside
     * `PUBLIC_KINDS`. 248 of the 498 associations here, so this is the bulk of what the
     * filtering removes.
     *
     * A failure to read the kinds excludes nothing rather than everything — the same
     * direction every other guard here fails in.
     *
     * @return array<int, true>
     */
    private function nonPublicAssociationIds(): array
    {
        $public = $this->access->publicAssociationKinds();
        $ids    = [];

        try {
            /** @var SchoenstattTable $table */
            $table   = $this->laminas->get(SchoenstattTable::class);
            $objects = $table->getObjects('association');
        } catch (Throwable) {
            return [];
        }

        if (! is_array($objects)) {
            return [];
        }

        foreach ($objects as $object) {
            if (! is_array($object)) {
                continue;
            }
            $id   = $object['associationId'] ?? null;
            $kind = $object['kind'] ?? null;
            if (! is_numeric($id)) {
                continue;
            }
            if (! is_string($kind) || ! in_array($kind, $public, true)) {
                $ids[(int) $id] = true;
            }
        }

        return $ids;
    }

    /**
     * Per-language path tails for every association, keyed on association id.
     *
     * The navigation tree is built once, for one locale, so the tail it yields carries that
     * locale's slug — and an association's slug is stored per locale, 428 of 498 German ones
     * differing from the English. Publishing one tail for all five languages would advertise
     * URLs no page calls canonical and no menu links to, so the five are assembled here.
     *
     * Through the router, not by string concatenation: the shape of an association URL is a
     * route definition and has changed once already. The same `PreferredUrls` the record
     * pages use, so the sitemap and the canonical cannot drift apart.
     *
     * @return array<int, array<string, string>>
     */
    private function associationTailsByLanguage(): array
    {
        try {
            /** @var SchoenstattTable $table */
            $table   = $this->laminas->get(SchoenstattTable::class);
            $objects = $table->getObjects('association');
        } catch (Throwable) {
            return [];
        }

        if (! is_array($objects)) {
            return [];
        }

        $tails = [];
        foreach ($objects as $object) {
            if (! is_array($object)) {
                continue;
            }
            $id         = $object['associationId'] ?? null;
            $identifier = $object['identifier'] ?? null;
            if (! is_numeric($id) || ! is_string($identifier)) {
                continue;
            }

            $paths = $this->preferred->forRecord(
                'association',
                ['sw_id' => $identifier],
                is_array($object['slugByLocale'] ?? null) ? $object['slugByLocale'] : null
            );

            $byLanguage = [];
            foreach ($paths as $language => $path) {
                //strip `/<lang>/`, leaving the tail the writer puts each prefix back onto
                $prefix = '/' . $language . '/';
                if (str_starts_with($path, $prefix)) {
                    $byLanguage[$language] = substr($path, strlen($prefix));
                }
            }

            if ([] !== $byLanguage) {
                $tails[(int) $id] = $byLanguage;
            }
        }

        return $tails;
    }

    /**
     * Last-changed times per record, per section.
     *
     * @return array<string, array<int, DateTimeImmutable>>
     */
    private function stampsBySection(): array
    {
        $stamps = [];
        foreach (SitemapSection::cases() as $section) {
            $entity = $section->entity();
            $stamps[$section->value] = null === $entity ? [] : $this->changes->perRecord($entity);
        }

        return $stamps;
    }
}
