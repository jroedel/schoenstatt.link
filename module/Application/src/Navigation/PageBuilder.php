<?php

declare(strict_types=1);

namespace Application\Navigation;

use Books\Model\DictionaryTable;
use Books\Model\LibraryTable;
use Books\Model\MusicTable;
use Books\Model\PublicationsTable;
use Closure;
use Laminas\Cache\Storage\StorageInterface;
use Locale;
use Schoenstatt\Model\SchoenstattTable;
use Throwable;

use function array_key_exists;
use function array_values;
use function in_array;
use function is_array;
use function method_exists;
use function sprintf;

/**
 * The five database-derived navigation branches, built once and shared by both front
 * controllers.
 *
 * This was inline in `Application\Module::onBootstrap()` until 2026-08-13, and it moved
 * for one reason: the Symfony side needs the same tree — `/sitemap.xml` walks the whole
 * of it — and a second implementation of "one navigation page per publication" would be
 * two live implementations, on a site where both front controllers serve production
 * traffic. A drift between them would show up as a sitemap that lists pages the menu does
 * not, or a breadcrumb that exists on one kernel and not the other, and nothing would
 * fail. So there is one builder and two callers.
 *
 * Nothing about the behaviour changed in the move. Same cache keys, same array shapes,
 * same locale salt, same tolerance for a cache that refuses the write, and
 * `markDataLabels()` is the same static function it was — verified by capturing all 396
 * baseline responses through the laminas front controller before and after and diffing.
 *
 * ## Laziness is the contract
 *
 * A branch that is already cached must not resolve its table. `onBootstrap()` runs on
 * **every** laminas request, and hydrating 10,104 publications there would be a disaster;
 * on the Symfony side a page that asks for the tree pays, and one that does not pay
 * nothing. That is why the services arrive as a resolver closure rather than as
 * constructor-injected tables — an injected table is resolved whether or not the branch
 * needs building.
 *
 * Measured 2026-08-13, cold, on the capsule: 0.48 s and 51 MiB peak for the source rows
 * of all five branches; `publication-pages` alone is 2.24 MB in APCu, the largest single
 * entry in the persistent cache. That is the number that decides who may ask for this and
 * how often, and it is why the navbar reads a small projection instead (see
 * `App\View\SiteChrome`).
 */
final class PageBuilder
{
    /** @var list<string> */
    public const PAGES_CACHE_KEYS = [
        'dictionary-pages',
        'publication-pages',
        'association-pages',
        'library-pages',
        'music-pages',
    ];

    /**
     * Cache keys from PAGES_CACHE_KEYS whose *content* differs per locale and therefore may
     * not share a single cache item. Anything not listed here is cached once for all
     * locales; adding a key here multiplies its item count by the number of supported
     * locales, which on a shared APCu segment is the resource being conserved. See the
     * audit in branches().
     *
     * @var list<string>
     */
    public const LOCALE_DEPENDENT_PAGES_CACHE_KEYS = [
        'association-pages',
    ];

    /**
     * Custom navigation-page property: this label is a record's own text, not language.
     *
     * Read by partial/breadcrumbs.phtml on the laminas side and by the tree on the Symfony
     * side — the only two things that translate a page label at a depth these branches
     * reach.
     */
    public const LABEL_IS_DATA = 'labelIsData';

    /** The locale used for any request whose own locale has no slug column. */
    private const FALLBACK_LOCALE = 'en_US';

    /** @param Closure(string): mixed $services resolves a service id, lazily */
    public function __construct(private readonly Closure $services)
    {
    }

    /**
     * Every branch, keyed by its cache key, with data labels already marked.
     *
     * @param string|null $locale defaults to the current one, normalized to a locale the
     *        association slugs actually have a column for
     * @return array<string, mixed>
     */
    public function branches(?string $locale = null): array
    {
        $locale = $this->normalizeLocale($locale);
        $cache  = $this->cache();

        /*
         * Locale audit of PAGES_CACHE_KEYS (only locale-dependent content may be salted):
         *  - dictionary-pages: labels come from DictionaryTable::getAvailableDictionaryLanguages(),
         *      whose language names come from getLanguageNames() with no $inLanguage argument, i.e.
         *      always English. Route params are language codes. Locale-independent.
         *  - publication-pages: labels are untranslated publication titles and the same
         *      always-English language names; params are identifier/slug, neither localized.
         *      Locale-independent.
         *  - association-pages: embeds $object['slugByLocale'][$locale] and, since 2026-08-11,
         *      $object['nameByLocale'][$locale]. LOCALE-DEPENDENT, and now for two reasons.
         *  - library-pages: label is the raw LibraryName column, param is libraryId.
         *      Locale-independent.
         *  - music-pages: label is the raw composition name, params are identifier and the single
         *      Slug column. Locale-independent.
         * An English label in the cache is not itself a reason to salt: the navigation view
         * helper translates labels at render time. What it must *not* translate is a label that
         * is a record's own text, which is what LABEL_IS_DATA marks — see markDataLabels()
         * below. Those labels therefore stay in whatever language the record holds, and salting
         * a branch to fix that would multiply the item count instead. `nameByLocale` above is
         * the one case where the value was already per-locale for another reason.
         */
        $cacheKeys = [];
        foreach (self::PAGES_CACHE_KEYS as $baseKey) {
            $cacheKeys[$baseKey] = in_array($baseKey, self::LOCALE_DEPENDENT_PAGES_CACHE_KEYS, true)
                ? $baseKey . '-' . $locale
                : $baseKey;
        }

        $cachedItems     = null !== $cache ? $cache->getItems(array_values($cacheKeys)) : [];
        $pagesByCacheKey = [];
        foreach ($cacheKeys as $baseKey => $cacheKey) {
            if (isset($cachedItems[$cacheKey])) {
                $pagesByCacheKey[$baseKey] = $cachedItems[$cacheKey];
            }
        }

        //One closure per branch, called only when the cache did not answer — this is the
        //laziness the class docblock is about.
        $builders = [
            'dictionary-pages'  => fn (): array => $this->dictionaryPages(),
            'publication-pages' => fn (): array => $this->publicationPages(),
            'association-pages' => fn (): array => $this->associationPages($locale),
            'library-pages'     => fn (): array => $this->libraryPages(),
            'music-pages'       => fn (): array => $this->musicPages(),
        ];
        foreach ($builders as $baseKey => $build) {
            if (isset($pagesByCacheKey[$baseKey])) {
                continue;
            }
            $pages                     = $build();
            $pagesByCacheKey[$baseKey] = $pages;
            if (null !== $cache) {
                $this->cacheBranch($cache, $cacheKeys[$baseKey], $pages);
            }
        }

        /*
         * Mark the labels that are *record data* before they become pages, so the breadcrumb
         * partial leaves them alone. A publication's title, a shrine's name, a library's name,
         * a composition's name and a composed label like 'German Schoenstatt Literature' are
         * not language in any language: translating one cannot succeed, and a translator miss
         * is precisely how a phrase is filed. From 2026-08-10, when breadcrumb labels started
         * being translated at all, that filed one row per record — twice over, because the
         * partial falls back to the `default` domain — and within a day publication titles
         * were 61% of the whole phrase table (`docs/api-change-requests-response.md` §12).
         *
         * Applied to the arrays *after* they come out of the cache rather than written into
         * them where they are built: `apc.ttl` is 0 on this host, so a branch cached before
         * this shipped never expires, and a flag that lived in the cached value would then be
         * missing for as long as the item survived.
         */
        return self::markDataLabels($pagesByCacheKey);
    }

    /**
     * Flag every dynamically built navigation label that is record content.
     *
     * Static, pure and keyed on the same names as PAGES_CACHE_KEYS so it can be driven from a
     * test without a container: this decision is the one thing standing between the phrase
     * table and one row per publication, and it is not observable from a rendered page until
     * thousands of rows have arrived.
     *
     * The one branch that is *not* data is the region level of `shrinesByRegion`: its labels
     * are country and region names ('Germany', 'Argentina'), which are translated in all five
     * languages on purpose. Only its children — the shrines themselves — are data.
     *
     * @param array<string, mixed> $pagesByCacheKey branches as branches() holds them
     * @return array<string, mixed> the same structure, with LABEL_IS_DATA set where it belongs
     */
    public static function markDataLabels(array $pagesByCacheKey): array
    {
        foreach (['dictionary-pages', 'publication-pages', 'library-pages', 'music-pages'] as $key) {
            if (isset($pagesByCacheKey[$key]) && is_array($pagesByCacheKey[$key])) {
                $pagesByCacheKey[$key] = self::flagLabelsAsData($pagesByCacheKey[$key]);
            }
        }

        if (! isset($pagesByCacheKey['association-pages']) || ! is_array($pagesByCacheKey['association-pages'])) {
            return $pagesByCacheKey;
        }
        $associations = $pagesByCacheKey['association-pages'];
        foreach (['movement', 'shrinesWorld', 'waysideShrines'] as $key) {
            if (isset($associations[$key]) && is_array($associations[$key])) {
                $associations[$key] = self::flagLabelsAsData($associations[$key]);
            }
        }
        if (isset($associations['shrinesByRegion']) && is_array($associations['shrinesByRegion'])) {
            foreach ($associations['shrinesByRegion'] as $regionKey => $region) {
                if (isset($region['pages']) && is_array($region['pages'])) {
                    $region['pages'] = self::flagLabelsAsData($region['pages']);
                }
                $associations['shrinesByRegion'][$regionKey] = $region;
            }
        }
        $pagesByCacheKey['association-pages'] = $associations;

        return $pagesByCacheKey;
    }

    /**
     * Set LABEL_IS_DATA on each page of a list and, recursively, on its children.
     *
     * The key reaches the page as a custom property — Laminas\Navigation\Page\AbstractPage::set()
     * keeps anything it has no setter for — and comes back out of Page::get(self::LABEL_IS_DATA).
     *
     * @param array<int|string, mixed> $pages
     * @return array<int|string, mixed>
     */
    private static function flagLabelsAsData(array $pages): array
    {
        foreach ($pages as $index => $page) {
            if (! is_array($page)) {
                continue;
            }
            $page[self::LABEL_IS_DATA] = true;
            if (isset($page['pages']) && is_array($page['pages'])) {
                $page['pages'] = self::flagLabelsAsData($page['pages']);
            }
            $pages[$index] = $page;
        }

        return $pages;
    }

    /** @return list<array<string, mixed>> */
    private function dictionaryPages(): array
    {
        $pages = [];
        /** @var DictionaryTable $table */
        $table = ($this->services)(DictionaryTable::class);
        foreach ($table->getAvailableDictionaryLanguages() as $object) {
            $pages[] = [
                'label'  => sprintf('German to %s Dictionary', $object['inLanguageName']),
                'route'  => 'dictionary/inLanguage',
                'params' => ['inLanguage' => $object['inLanguage']],
                'id'     => 'dict_' . $object['inLanguage'],
            ];
        }

        return $pages;
    }

    /** @return list<array<string, mixed>> */
    private function publicationPages(): array
    {
        $pages           = [];
        $pagesByLanguage = [];
        /** @var PublicationsTable $table */
        $table = ($this->services)(PublicationsTable::class);
        //a narrow, 4-field projection restricted to publication_public rows; see
        //PublicationsTable::getPublicationNavigationData() for why we don't hydrate entities here
        foreach ($table->getPublicationNavigationData() as $publicationId => $object) {
            $inLanguage = isset($object['inLanguage'])
                && is_array($object['inLanguage'])
                && isset($object['inLanguage'][0])
                ? $object['inLanguage'][0]
                : null;
            if (! array_key_exists($inLanguage, $pagesByLanguage)) {
                $pagesByLanguage[$inLanguage] = [];
            }
            $pagesByLanguage[$inLanguage][] = [
                'label'  => $object['title'],
                'route'  => 'publication',
                'params' => [
                    'sw_id' => $object['identifier'],
                    'slug'  => $object['slug'],
                ],
                'id'     => 'pub_' . $publicationId,
            ];
        }

        /** @var DictionaryTable $dictionaryTable */
        $dictionaryTable = ($this->services)(DictionaryTable::class);
        $languageNames   = $dictionaryTable->getLanguageNames();
        foreach ($pagesByLanguage as $languageCode => $languagePages) {
            //A publication with no language has nothing to group it under: `publications/index`
            //is `/literature/:inLanguage` constrained to `[a-z]{2,2}`, and `Segment::assemble()`
            //does not enforce a route's own constraints, so an empty parameter quietly produced
            //`/en/literature/` — a URL that answers 404 while `/en/literature` answers 200.
            //Nothing rendered it (measured 2026-08-14: both navbars stop at depth 0, the Symfony
            //breadcrumb omits the group, the laminas one does not exist on publication pages, and
            //the sitemap's trailing-slash rule dropped it) — but a node whose href 404s is a trap
            //for whatever renders depth 1 next, and one heuristic in another component is not a
            //guard. So the group is not built.
            //
            //Its children are re-parented onto the literature root rather than dropped: they are
            //25 real published publications, they are what the sitemap walks, and losing them
            //here would withdraw 125 URLs from the index. They stay orphaned in the sense that no
            //page links to them — see BACKLOG, where giving them a real index page is the option
            //that was not taken.
            if ('' === (string) $languageCode) {
                foreach ($languagePages as $languagePage) {
                    $pages[] = $languagePage;
                }
                continue;
            }

            $languageName = $languageNames[$languageCode] ?? 'Other';
            $pages[]      = [
                'label'  => $languageName . ' Schoenstatt Literature',
                'route'  => 'publications/index',
                'params' => ['inLanguage' => $languageCode],
                'id'     => 'pub_lang_' . $languageCode,
                'pages'  => $languagePages,
            ];
        }
        $pages[] = [
            'label' => '150 preguntas sobre Schoenstatt',
            'route' => 'publications/one-fifty-preguntas',
            'id'    => 'pub_one_fifty_preguntas',
        ];

        return $pages;
    }

    /** @return array<string, mixed> the four sub-branches, keyed as onBootstrap attaches them */
    private function associationPages(string $locale): array
    {
        $pages = [
            'movement'        => [],
            'shrinesByRegion' => [],
            'shrinesWorld'    => [],
            'waysideShrines'  => [],
        ];

        /** @var SchoenstattTable $table */
        $table = ($this->services)(SchoenstattTable::class);

        foreach ($table->getObjects('association') as $object) {
            /*
             * `name` is the raw column; `nameByLocale` is what SchoenstattTable built for
             * this locale, honouring IsNameTranslateable and the kind's name format — the
             * same value every other screen shows. This branch is already salted per
             * locale for the slug, so the better label costs nothing, and it removes the
             * only reason a breadcrumb ever had to translate a name itself: the old label
             * was English and the crumb tried to render it, which for the thousands of
             * names that are proper nouns just filed a phrase per record.
             */
            $localizedName = $object['nameByLocale'][$locale] ?? $object['name'];
            $page          = [
                'label'  => $localizedName,
                'route'  => 'association',
                'params' => [
                    'sw_id' => $object['identifier'],
                    'slug'  => $object['slugByLocale'][$locale],
                ],
                /*
                 * Added 2026-08-13 for the sitemap, which is the only consumer: it needs to
                 * know which record a page is in order to drop the 248 associations an
                 * anonymous visitor cannot see and to date the rest from the change log.
                 * Every other branch here has always set an id; this one was the exception,
                 * so App\Sitemap could tell a publication from a composition but could not
                 * recognise an association at all. Deriving it from the URL instead was the
                 * alternative and is worse — the URL is a routing detail that has already
                 * changed once.
                 */
                'id'     => 'assoc_' . $object['associationId'],
            ];

            if ('sch-shrine' !== $object['kind'] && 'sch-wayside-shrine' !== $object['kind']) {
                //@todo this could be subdivided heirarchically
                $pages['movement'][] = $page;
            }
            if (! isset($object['countryRegion'])) {
                $pages['shrinesWorld'][] = $page;
                continue;
            }

            $key = $object['countryRegion'];
            if (! isset($pages['shrinesByRegion'][$key])) {
                $pages['shrinesByRegion'][$key] = [
                    'label'    => $key,
                    'route'    => 'shrines',
                    'fragment' => $key,
                    'pages'    => [],
                ];
            }
            $pages['shrinesByRegion'][$key]['pages'][] = $page;
        }

        return $pages;
    }

    /** @return list<array<string, mixed>> */
    private function libraryPages(): array
    {
        $pages = [];
        /** @var LibraryTable $table */
        $table = ($this->services)(LibraryTable::class);
        foreach ($table->getObjects('library') as $object) {
            if (! $object['isActive']) {
                continue;
            }
            $pages[] = [
                'label'  => $object['name'],
                'route'  => 'libraries/library',
                'params' => ['library_id' => $object['libraryId']],
                'id'     => 'lib_' . $object['libraryId'],
            ];
        }

        return $pages;
    }

    /** @return list<array<string, mixed>> */
    private function musicPages(): array
    {
        $pages = [];
        /** @var MusicTable $table */
        $table = ($this->services)(MusicTable::class);
        foreach ($table->getObjects('composition') as $object) {
            $pages[] = [
                'label'  => $object['name'],
                'route'  => 'composition',
                'params' => ['sw_id' => $object['identifier'], 'slug' => $object['slug']],
                'id'     => 'composition_' . $object['compositionId'],
            ];
        }

        return $pages;
    }

    /**
     * The locale the association branch is salted with, normalized to one the slug columns
     * exist for.
     */
    private function normalizeLocale(?string $locale): string
    {
        $locale ??= Locale::getDefault();

        return array_key_exists($locale, SchoenstattTable::LOCALES_TO_SLUG_COLUMN_NAME)
            ? $locale
            : self::FALLBACK_LOCALE;
    }

    /** The persistent cache, or null where there is none to speak of. */
    private function cache(): ?StorageInterface
    {
        $cache = ($this->services)('SionModel\PersistentCache');

        return $cache instanceof StorageInterface ? $cache : null;
    }

    /**
     * Persist one navigation branch, tolerating a cache that cannot accept the write.
     *
     * Laminas\Cache\Storage\Adapter\Apcu::internalSetItem() throws a RuntimeException when
     * apcu_store() returns false, which on a full segment is routine. Since this runs in
     * onBootstrap, an uncaught throw is a 500 on every single request until APCu drains, so a
     * failed write must degrade to "the navigation is simply not cached this request".
     *
     * @param array<int|string, mixed> $pages
     * @return bool whether the write succeeded
     */
    private function cacheBranch(StorageInterface $cache, string $cacheKey, array $pages): bool
    {
        try {
            if (false === $cache->setItem($cacheKey, $pages)) {
                $this->reportCacheFailure($cacheKey, null);

                return false;
            }
        } catch (Throwable $t) {
            $this->reportCacheFailure($cacheKey, $t);

            return false;
        }

        return true;
    }

    /** Log a navigation cache write failure if a logger happens to be reachable. */
    private function reportCacheFailure(string $cacheKey, ?Throwable $t): void
    {
        try {
            $logger = ($this->services)('SionModel\Logger');
            if (null === $logger || ! method_exists($logger, 'error')) {
                return;
            }
            $logger->error('Failed to cache a navigation branch.', [
                'cacheKey'  => $cacheKey,
                'exception' => null !== $t ? $t->getMessage() : 'setItem returned false',
            ]);
        } catch (Throwable $ignored) {
            //we're in onBootstrap; never let logging a cache miss take the site down
        }
    }
}
