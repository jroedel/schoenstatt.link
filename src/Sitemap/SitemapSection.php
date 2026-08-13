<?php

declare(strict_types=1);

namespace App\Sitemap;

use function preg_match;
use function str_starts_with;
use function strlen;
use function substr;

use const PHP_INT_MAX;

/**
 * The four files the sitemap is split into, and how a navigation page is assigned to one.
 *
 * ## Why per-entity files rather than numbered parts
 *
 * The split has to happen — 36,730 URLs is close enough to the 50,000 limit that one file
 * is not a safe long-term answer — and once it happens the filenames become URLs Google
 * crawls and remembers. Numbered parts (`pages_2.xml`, `pages_3.xml`) shift their contents
 * every time the catalogue grows, so the same URL means something different on each build
 * and a crawler re-reads all of them. A file per entity kind is stable: a new publication
 * changes `sitemap-publications.xml` and nothing else.
 *
 * ## The id prefixes are PageBuilder's, not a URL convention
 *
 * Classification reads `Application\Navigation\PageBuilder`'s own page ids — `pub_1234`,
 * `assoc_319`, `composition_88` — never the assembled path. That is deliberate: the path
 * is a routing detail that has already changed once, while the id is the thing PageBuilder
 * sets precisely so a consumer can tell *which record* a page is. `ASSOCIATIONS` needed
 * PageBuilder to start setting one; the other two already did.
 *
 * Anything unrecognised lands in `PAGES`, which is the right default — it is the small
 * file, it carries no `<lastmod>`, and a page kind nobody has classified yet is still
 * published rather than silently dropped.
 */
enum SitemapSection: string
{
    case PAGES        = 'pages';
    case ASSOCIATIONS = 'associations';
    case PUBLICATIONS = 'publications';
    case COMPOSITIONS = 'compositions';

    /**
     * The SionModel entity name whose change rows date this section's records.
     *
     * Null for PAGES: a static page has no record behind it, so there is nothing to read a
     * modification time from and it gets no `<lastmod>` at all. Google treats a missing
     * lastmod as "no information", which is exactly the truth here; inventing the build
     * time instead would tell every crawler that all 37 static pages change on every build.
     */
    public function entity(): ?string
    {
        return match ($this) {
            self::PAGES        => null,
            self::ASSOCIATIONS => 'association',
            self::PUBLICATIONS => 'publication',
            self::COMPOSITIONS => 'composition',
        };
    }

    /** PageBuilder's id prefix, or null for the catch-all section. */
    public function idPrefix(): ?string
    {
        return match ($this) {
            self::PAGES        => null,
            self::ASSOCIATIONS => 'assoc_',
            self::PUBLICATIONS => 'pub_',
            self::COMPOSITIONS => 'composition_',
        };
    }

    /**
     * The file this section's first part is written as, relative to the docroot.
     *
     * Flat, at the docroot root, and that is the whole point of the 2026-08-13 rewrite: a
     * sitemap may only list URLs at or below its own directory, so a file under
     * `/sitemap/` could not legally contain a single `/en/…` URL. See SitemapWriter.
     */
    public function filename(): string
    {
        return 'sitemap-' . $this->value . '.xml';
    }

    /**
     * Which section a navigation page belongs to.
     *
     * `pub_lang_es` and `pub_one_fifty_preguntas` are PageBuilder ids that *start with*
     * `pub_` and are not publications — they are the literature index's language filters
     * and one hand-written page. Both must land in PAGES, so a prefix match is not enough
     * and the remainder has to be a bare record id. Getting this wrong is invisible: the
     * page still appears in a sitemap, just in the wrong file with a `<lastmod>` read from
     * whichever publication happens to share the trailing digits.
     */
    public static function forPageId(?string $pageId): self
    {
        if (null === $pageId) {
            return self::PAGES;
        }

        foreach ([self::ASSOCIATIONS, self::PUBLICATIONS, self::COMPOSITIONS] as $section) {
            $prefix = $section->idPrefix();
            if (null !== $prefix && str_starts_with($pageId, $prefix)) {
                return null === self::recordId($pageId, $prefix) ? self::PAGES : $section;
            }
        }

        return self::PAGES;
    }

    /**
     * The record id inside a page id, or null when what follows the prefix is not one.
     *
     * Deliberately strict: only digits, and only a value that survives the round trip
     * through int. `pub_lang_es` and `pub_one_fifty_preguntas` both fail here, which is how
     * they reach PAGES.
     */
    public static function recordId(string $pageId, string $prefix): ?int
    {
        if (! str_starts_with($pageId, $prefix)) {
            return null;
        }

        $remainder = substr($pageId, strlen($prefix));
        if ('' === $remainder || 1 !== preg_match('/^[1-9][0-9]{0,17}$/', $remainder)) {
            return null;
        }

        $id = (int) $remainder;

        return (string) $id === $remainder && $id < PHP_INT_MAX ? $id : null;
    }
}
