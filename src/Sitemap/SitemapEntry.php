<?php

declare(strict_types=1);

namespace App\Sitemap;

use DateTimeImmutable;

/**
 * One page of the site, in every language, with the date it last changed.
 *
 * Held as the locale-independent **tail** of the path rather than as five finished URLs
 * because there are about 7,100 of these in memory at once and the writer expands each into
 * five `<url>` elements as it streams. Storing the expansion would be five times the arrays
 * for no gain.
 *
 * `$tail` is everything after the locale prefix, with no leading slash: `SL100319A`,
 * `literature/de`, or `''` for the home page. The writer prepends `<base>/<lang>/`.
 *
 * `$tailByLanguage` overrides it for the pages where one tail is not enough. Associations
 * are the only case: their slug is stored per locale and 428 of 498 German slugs differ
 * from the English one, so a single tail would advertise `/de/SL100319A/original-shrine`
 * while the German page's own canonical — and every German menu link — says
 * `/de/SL100319A/urheiligtum`. A sitemap that lists non-canonical URLs is not an error, but
 * it wastes the crawl and shows up in Search Console as "Alternate page with proper
 * canonical tag", so the two are kept in step. A language missing from the map falls back
 * to `$tail`.
 */
final class SitemapEntry
{
    /** @param array<string, string>|null $tailByLanguage language code => tail */
    public function __construct(
        public readonly string $tail,
        public readonly ?DateTimeImmutable $lastModified = null,
        public readonly ?array $tailByLanguage = null
    ) {
    }

    /** The tail to publish for one language. */
    public function tailFor(string $language): string
    {
        return $this->tailByLanguage[$language] ?? $this->tail;
    }
}
