<?php

declare(strict_types=1);

namespace App\Sitemap;

use App\Laminas\ServiceBridge;
use App\Locale\Locales;
use App\View\NavigationTree;
use Books\Model\PublicationsTable;
use Laminas\Cache\Storage\StorageInterface;
use samdark\sitemap\Index;
use samdark\sitemap\Sitemap;
use Throwable;

use function file_exists;
use function is_dir;
use function array_keys;
use function is_int;
use function mkdir;
use function preg_match;
use function rtrim;
use function str_starts_with;
use function strlen;
use function substr;
use function time;

/**
 * Writes the sitemap files, and knows when it does not have to.
 *
 * ## What the laminas version did, and what this changes
 *
 * `IndexController::sitemapAction()` walked the navigation container, handed every page to
 * `samdark\sitemap\Sitemap`, and served back `data/sitemap/sitemap.xml`. Two things were
 * wrong with that, both found by diffing this port against it:
 *
 * 1. **Three quarters of the site was never published.** The library splits at 10 MB and
 *    wrote four files — 3,022 + 3,000 + 3,032 + 1,920 = 10,974 pages — and the action read
 *    back only the first. Nothing wrote an index, and `public/robots.txt` names
 *    `/en/sitemap.xml`, so a crawler saw 3,022 pages: every association, the first 2,500
 *    publications, and **not one composition**. The library ships `Index` and
 *    `Sitemap::getSitemapUrls()` for exactly this; they were simply never called.
 * 2. **It rebuilt everything on every request** — about 11,000 route assemblies, 1.25 s
 *    measured on the capsule — with no caching of any kind.
 *
 * So `/sitemap.xml` is now a sitemap *index* pointing at `/sitemap/pages.xml`,
 * `/sitemap/pages_2.xml` and so on. robots.txt keeps working unchanged, which is the point
 * of putting the index at the old URL.
 *
 * ## Merged publications are excluded
 *
 * A merged publication answers **301** to its surviving edition — that is all its URL does.
 * 3,627 of the 10,104 public publications here are merged, so more than a third of the
 * publication entries the old sitemap did publish were permanent redirects presented to
 * crawlers as canonical pages. They are dropped here rather than in
 * `Application\Navigation\PageBuilder`, which would also change the laminas menus and
 * breadcrumbs: in a menu a merged publication is merely harmless, and this port's job is
 * the sitemap.
 *
 * ## Freshness without putting 40 MB in APCu
 *
 * The files stay on disk and APCu holds only a **stamp** — the generation time under one
 * small key. That is deliberate and is a deviation from "cache it in APCu" as literally
 * stated: the four uncompressed parts are about 38 MB, `apc.ttl` is 0 on this host, and a
 * failed allocation there expunges the *entire* segment rather than evicting (see
 * docs/caching.md). Putting the payload in APCu would make the sitemap capable of wiping
 * every other cache on the site.
 *
 * The stamp lives in the same segment the navigation branches do, so
 * `cache:flush-persistent` — the thing that already refreshes navigation — refreshes this
 * too: the stamp disappears with everything else and the next request regenerates. MAX_AGE
 * is the backstop for a site that is never flushed.
 */
final class SitemapGenerator
{
    /** Where the parts are written, relative to the application root. */
    public const DIRECTORY = 'data/sitemap';

    /** The first part's filename; the library derives `pages_2.xml`, `pages_3.xml`, … */
    public const BASENAME = 'pages.xml';

    /** The URL path the parts are served under, matching config/symfony/routes.php. */
    public const PART_PATH = '/sitemap/';

    /** APCu key holding the generation time. Small on purpose — see the class docblock. */
    private const STAMP_KEY = 'sitemap-generated-at';

    /** Regenerate at least this often even if nothing ever flushes the cache. */
    private const MAX_AGE = 86400;

    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly NavigationTree $tree,
        private readonly string $root
    ) {
    }

    /**
     * Ensure the files on disk are current, and return the index's own path.
     *
     * @param string $serverUrl scheme and host the file's absolute URLs are built on,
     *        taken from the request exactly as laminas' `serverUrl` view helper does
     * @param bool $force ignore the stamp and rebuild; the maintenance endpoint's door
     */
    public function ensure(string $serverUrl, bool $force = false): string
    {
        $indexPath = $this->root . '/' . self::DIRECTORY . '/sitemap.xml';

        if (! $force && $this->isFresh() && file_exists($indexPath)) {
            return $indexPath;
        }

        $this->generate($indexPath, rtrim($serverUrl, '/'));
        $this->stamp();

        return $indexPath;
    }

    /** The absolute path of one part, or null when it is not a name we wrote. */
    public function partPath(string $filename): ?string
    {
        //`pages.xml`, `pages_2.xml`, … and nothing else. The filename reaches this from a
        //route parameter, so it decides which file is read: anything not matching the
        //shape the library writes is refused rather than resolved.
        if (1 !== preg_match('/^pages(_[1-9][0-9]{0,3})?\.xml$/', $filename)) {
            return null;
        }

        $path = $this->root . '/' . self::DIRECTORY . '/' . $filename;

        return file_exists($path) ? $path : null;
    }

    /**
     * Build every part and the index that lists them.
     *
     * The per-page work is the laminas action's, kept deliberately: one item per page, with
     * one URL per locale, each carrying the whole set as `xhtml:link` alternates. The
     * substring arithmetic is its too — the assembled URL is locale-prefixed, and each
     * language's copy is that URL with its own prefix swapped in.
     */
    private function generate(string $indexPath, string $serverUrl): void
    {
        $directory = $this->root . '/' . self::DIRECTORY;
        if (! is_dir($directory)) {
            mkdir($directory, 0o775, true);
        }

        $sitemap = new Sitemap($directory . '/' . self::BASENAME, true);
        $sitemap->setUseGzip(true);

        $bases = [];
        foreach (array_keys(Locales::ALIASES) as $language) {
            $bases[$language] = $serverUrl . '/' . $language . '/';
        }
        //the assembled hrefs carry a locale prefix already; each language's copy is that
        //URL with its own prefix swapped in, which is the laminas action's own arithmetic
        $prefixLength = strlen($bases[Locales::aliasFor(Locales::DEFAULT_LOCALE)]);

        $merged = $this->mergedPublicationIds();
        $seen   = [];

        foreach ($this->tree->flattened() as $page) {
            $href = $page['href'];
            if ('' === $href || ! str_starts_with($href, '/')) {
                continue;
            }
            if (null !== $page['id'] && $this->isMergedPublication($page['id'], $merged)) {
                continue;
            }

            $url = $serverUrl . $href;
            //Sitemap::url() deduplicates by returning null for a URL it has already
            //emitted, and the laminas action skipped those. `World` and `Shrines` share a
            //route, so this is not hypothetical.
            if (isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;

            $tail    = substr($url, $prefixLength);
            $locales = [];
            foreach ($bases as $language => $base) {
                $locales[$language] = $base . $tail;
            }
            $sitemap->addItem($locales);
        }

        $sitemap->write();

        $index = new Index($indexPath);
        //the parts are gzipped and the controller sends one `Content-Encoding: gzip` for
        //everything it serves, so the index has to be gzipped too. It was not, and the
        //symptom is a 354-byte index that no client can read.
        $index->setUseGzip(true);
        foreach ($sitemap->getSitemapUrls($serverUrl . self::PART_PATH) as $partUrl) {
            $index->addSitemap($partUrl);
        }
        $index->write();
    }

    /** @return array<int, int> merged publication ids, keyed on themselves */
    private function mergedPublicationIds(): array
    {
        /** @var PublicationsTable $table */
        $table = $this->laminas->get(PublicationsTable::class);

        /** @var array<int, int> $merged */
        $merged = $table->getMergedPublicationIds();

        return $merged;
    }

    /**
     * Whether a navigation page id names a publication that has been merged away.
     *
     * `pub_1234` is PageBuilder's own id format, not a convention inferred from the URL —
     * which is why the id is carried through the tree in the first place.
     *
     * @param array<int, int> $merged
     */
    private function isMergedPublication(string $pageId, array $merged): bool
    {
        if (! str_starts_with($pageId, 'pub_')) {
            return false;
        }

        $publicationId = (int) substr($pageId, 4);

        return isset($merged[$publicationId]);
    }

    private function isFresh(): bool
    {
        $cache = $this->cache();
        if (null === $cache) {
            return false;
        }

        try {
            $generatedAt = $cache->getItem(self::STAMP_KEY);
        } catch (Throwable) {
            return false;
        }

        return is_int($generatedAt) && (time() - $generatedAt) < self::MAX_AGE;
    }

    private function stamp(): void
    {
        $cache = $this->cache();
        if (null === $cache) {
            return;
        }

        try {
            $cache->setItem(self::STAMP_KEY, time());
        } catch (Throwable) {
            //a cache that will not take a 4-byte integer is not a reason to fail a request
            //that has already written the files
        }
    }

    private function cache(): ?StorageInterface
    {
        try {
            $cache = $this->laminas->get('SionModel\PersistentCache');
        } catch (Throwable) {
            return null;
        }

        return $cache instanceof StorageInterface ? $cache : null;
    }
}
