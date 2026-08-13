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
 */
final class SitemapEntry
{
    public function __construct(
        public readonly string $tail,
        public readonly ?DateTimeImmutable $lastModified = null
    ) {
    }
}
