<?php

declare(strict_types=1);

namespace App\View;

use App\Laminas\RouteUrl;
use App\Locale\Locales;

use function is_string;

/**
 * The one URL a record should be indexed under, in each language.
 *
 * ## Why a record page cannot answer this from its request path
 *
 * Because several URLs serve the same record and the site does not redirect between
 * them. `/en/SL100319A`, `/en/SL100319A/original-shrine` and
 * `/en/SL100319A/whatever-slug-you-like` all answer 200 with the same page — the slug
 * is decorative, and only `sw_id` selects the record. So "the canonical URL is the one
 * you asked for" would declare every one of those forms canonical, which is the
 * definition of duplicate content.
 *
 * Worse for associations, which are the reason this class exists: their slug is stored
 * **per locale** (`SlugEn`, `SlugEs`, `SlugDe`, `SlugPt`, `SlugIt`) and 428 of 498
 * German slugs differ from the English one. The navigation menus already link to the
 * per-locale form, so a German visitor is sent to `/de/SL100319A/urheiligtum` while
 * anything built by swapping the locale prefix of the current path would produce
 * `/de/SL100319A/original-shrine`. Both work; only one should be indexed.
 *
 * Publications and compositions have a single `Slug` column, so their five URLs differ
 * only in the prefix — but they still need this, because a request that omits the slug
 * would otherwise canonicalise to the slugless form while the sitemap advertises the
 * slugged one.
 *
 * ## What it produces
 *
 * A language-keyed map of paths, assembled through the router rather than by string
 * surgery, so a change to the route definition cannot leave this behind. Hand it to
 * `SiteChrome::canonicalLinks()`, which uses it for the canonical *and* for the
 * hreflang set — the two have to agree or the hreflang cluster is ignored.
 */
final class PreferredUrls
{
    public function __construct(private readonly RouteUrl $urls)
    {
    }

    /**
     * The preferred path for one record, per language.
     *
     * @param array<string, mixed> $params route parameters other than the slug
     * @param array<string, string>|null $slugByLocale locale-keyed slugs, as
     *        `SchoenstattTable` builds them for an association. Null, or a locale it has
     *        no entry for, falls back to `$params['slug']` — which is right for the two
     *        entities that have only one slug, and is also what happens if a slug column
     *        is empty.
     * @return array<string, string> language code => path
     */
    public function forRecord(string $route, array $params, ?array $slugByLocale = null): array
    {
        $paths = [];
        foreach (Locales::ALIASES as $alias => $locale) {
            $localeParams = $params;
            $slug         = $slugByLocale[$locale] ?? $params['slug'] ?? null;
            if (is_string($slug) && '' !== $slug) {
                $localeParams['slug'] = $slug;
            }

            $paths[(string) $alias] = $this->urls->path($route, $localeParams, [], $locale);
        }

        return $paths;
    }
}
