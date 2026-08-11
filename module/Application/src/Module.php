<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application;

use Laminas\Cache\Storage\StorageInterface;
use Laminas\Mvc\MvcEvent;
use Application\View\GdprStrategy;
use Laminas\Navigation\Navigation;
use Books\Model\DictionaryTable;
use Books\Model\PublicationsTable;
use Application\Navigation\FixNavigationPages;
use Psr\Container\ContainerInterface;
use Schoenstatt\Model\SchoenstattTable;
use Books\Model\LibraryTable;
use Books\Model\MusicTable;

class Module
{
    public const PAGES_CACHE_KEYS = [
        'dictionary-pages',
        'publication-pages',
        'association-pages',
        'library-pages',
        'music-pages',
    ];

    /**
     * Cache keys from PAGES_CACHE_KEYS whose *content* differs per locale and therefore may not
     * share a single cache item. Anything not listed here is cached once for all locales; adding
     * a key here multiplies its item count by the number of supported locales, which on a 32 MiB
     * APCu segment is the resource we are trying to conserve. See the audit in onBootstrap().
     */
    public const LOCALE_DEPENDENT_PAGES_CACHE_KEYS = [
        'association-pages',
    ];

    /**
     * Custom navigation-page property: this label is a record's own text, not language.
     *
     * Read by partial/breadcrumbs.phtml, which is the only thing that translates a page
     * label at a depth these branches reach.
     */
    public const LABEL_IS_DATA = 'labelIsData';

    /**
     * @param MvcEvent $e
     */
    public function onBootstrap(MvcEvent $e)
    {
        $app = $e->getApplication();
        $sm = $app->getServiceManager();
        $strategy = $sm->get(GdprStrategy::class);
        $strategy->attach($app->getEventManager());

        /** @var StorageInterface $cache */
        $cache = $sm->get('SionModel\PersistentCache');
        $locale = \Locale::getDefault();
        if (! array_key_exists($locale, SchoenstattTable::LOCALES_TO_SLUG_COLUMN_NAME)) {
            $locale = 'en_US';
        }

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
        $cachedItems = $cache->getItems(array_values($cacheKeys));
        $pagesByCacheKey = [];
        foreach ($cacheKeys as $baseKey => $cacheKey) {
            if (isset($cachedItems[$cacheKey])) {
                $pagesByCacheKey[$baseKey] = $cachedItems[$cacheKey];
            }
        }


        if (! isset($pagesByCacheKey['dictionary-pages'])) {
            $dictionaryPages = [];
            /** @var DictionaryTable $dictionaryTable */
            $dictionaryTable = $sm->get(DictionaryTable::class);
            $dictionaries = $dictionaryTable->getAvailableDictionaryLanguages();
            foreach ($dictionaries as $object) {
                $dictionaryPages[] = [
                    'label' => sprintf('German to %s Dictionary', $object['inLanguageName']),
                    'route' => 'dictionary/inLanguage',
                    'params' => ['inLanguage' => $object['inLanguage']],
                    'id'    => 'dict_' . $object['inLanguage'],
                ];
            }
            $pagesByCacheKey['dictionary-pages'] = $dictionaryPages;
            $this->cacheNavigationPages($cache, $sm, $cacheKeys['dictionary-pages'], $dictionaryPages);
        }

        if (! isset($pagesByCacheKey['publication-pages'])) {
            $publicationPages = [];
            $pagesByLanguage = [];
            $table = $sm->get(PublicationsTable::class);
            //a narrow, 4-field projection restricted to publication_public rows; see
            //PublicationsTable::getPublicationNavigationData() for why we don't hydrate entities here
            $publications = $table->getPublicationNavigationData();
            foreach ($publications as $publicationId => $object) {
                $inLanguage = isset($object['inLanguage'])
                    && is_array($object['inLanguage'])
                    && isset($object['inLanguage'][0])
                    ? $object['inLanguage'][0]
                    : null;
                if (! array_key_exists($inLanguage, $pagesByLanguage)) {
                    $pagesByLanguage[$inLanguage] = [];
                }
                $pagesByLanguage[$inLanguage][] = [
                    'label' => $object['title'],
                    'route' => 'publication',
                    'params' => [
                        'sw_id' => $object['identifier'],
                        'slug' => $object['slug'],
                    ],
                    'id'    => 'pub_' . $publicationId,
                ];
            }

            if (! isset($dictionaryTable)) {
                /** @var DictionaryTable $dictionaryTable */
                $dictionaryTable = $sm->get(DictionaryTable::class);
            }
            $languageNames = $dictionaryTable->getLanguageNames();
            foreach ($pagesByLanguage as $languageCode => $pages) {
                $languageName = isset($languageNames[$languageCode]) ? $languageNames[$languageCode] : 'Other';
                $publicationPages[] = [
                    'label' => $languageName . ' Schoenstatt Literature',
                    'route' => 'publications/index',
                    'params' => ['inLanguage' => $languageCode],
                    'id'    => 'pub_lang_' . $languageCode,
                    'pages' => $pages,
                ];
            }
            $publicationPages[] = [
                'label' => '150 preguntas sobre Schoenstatt',
                'route' => 'publications/one-fifty-preguntas',
                'id'    => 'pub_one_fifty_preguntas',
            ];

            $pagesByCacheKey['publication-pages'] = $publicationPages;
            $this->cacheNavigationPages($cache, $sm, $cacheKeys['publication-pages'], $publicationPages);
        }

        if (! isset($pagesByCacheKey['association-pages'])) {
            $associationPages = [
                'movement' => [],
                'shrinesByRegion' => [],
                'shrinesWorld' => [],
                'waysideShrines' => [],
            ];

            /** @var SchoenstattTable $schoenstattTable */
            $schoenstattTable = $sm->get(SchoenstattTable::class);
            $associations = $schoenstattTable->getObjects('association');

            foreach ($associations as $object) {
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
                if ('sch-shrine' !== $object['kind'] && 'sch-wayside-shrine' !== $object['kind']) {
                    //@todo this could be subdivided heirarchically
                    $associationPages['movement'][] = [
                        'label' => $localizedName,
                        'route' => 'association',
                        'params' => [
                            'sw_id' => $object['identifier'],
                            'slug' => $object['slugByLocale'][$locale]
                        ],
                    ];
                }
                if (! isset($object['countryRegion'])) {
                    $associationPages['shrinesWorld'][] = [
                        'label' => $localizedName,
                        'route' => 'association',
                        'params' => [
                            'sw_id' => $object['identifier'],
                            'slug' => $object['slugByLocale'][$locale]
                        ],
                    ];
                } else {
                    $key = $object['countryRegion'];
                    if (! isset($associationPages['shrinesByRegion'][$key])) {
                        $associationPages['shrinesByRegion'][$key] = [
                            'label' => $key,
                            'route' => 'shrines',
                            'fragment' => $key,
                            'pages' => [],
                        ];
                    }
                    $associationPages['shrinesByRegion'][$key]['pages'][] = [
                        'label' => $localizedName,
                        'route' => 'association',
                        'params' => [
                            'sw_id' => $object['identifier'],
                            'slug' => $object['slugByLocale'][$locale]
                        ],
                    ];
                }
            }

            $pagesByCacheKey['association-pages'] = $associationPages;
            $this->cacheNavigationPages($cache, $sm, $cacheKeys['association-pages'], $associationPages);
        }

        if (! isset($pagesByCacheKey['library-pages'])) {
            $libraryPages = [];
            /** @var LibraryTable $libraryTable */
            $libraryTable = $sm->get(LibraryTable::class);
            $libraries = $libraryTable->getObjects('library');
            foreach ($libraries as $object) {
                if (! $object['isActive']) {
                    continue;
                }
                $libraryPages[] = [
                    'label' => $object['name'],
                    'route' => 'libraries/library',
                    'params' => ['library_id' => $object['libraryId']],
    //                 'resource' => $object['viewRole'],
                    'id'    => 'lib_' . $object['libraryId'],
                ];
            }
            $pagesByCacheKey['library-pages'] = $libraryPages;
            $this->cacheNavigationPages($cache, $sm, $cacheKeys['library-pages'], $libraryPages);
        }

        if (! isset($pagesByCacheKey['music-pages'])) {
            $musicPages = [];
            /** @var MusicTable $musicTable */
            $musicTable = $sm->get(MusicTable::class);
            $objects = $musicTable->getObjects('composition');
            foreach ($objects as $object) {
                $musicPages[] = [
                    'label' => $object['name'],
                    'route' => 'composition',
                    'params' => ['sw_id' => $object['identifier'], 'slug' => $object['slug']],
                    //                 'resource' => $object['viewRole'],
                    'id'    => 'composition_' . $object['compositionId'],
                ];
            }
            $pagesByCacheKey['music-pages'] = $musicPages;
            $this->cacheNavigationPages($cache, $sm, $cacheKeys['music-pages'], $musicPages);
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
        $pagesByCacheKey = self::markDataLabels($pagesByCacheKey);

        //build out the navigation a little
        /** @var Navigation $navigation */
        $navigation = $sm->get(Navigation::class);
        $dictionaryPage = $navigation->findOneBy('label', 'Dictionaries');
        $publicationsPage = $navigation->findOneBy('route', 'publications');
        $movement = $navigation->findOneBy('route', 'schoenstatt');
        $shrines = $navigation->findOneBy('label', 'Shrines');
        $world = $navigation->findOneBy('label', 'World');
        $libPage = $navigation->findOneBy('route', 'libraries');
        $musicPage = $navigation->findOneBy('route', 'music');

        $dictionaryPage->addPages($pagesByCacheKey['dictionary-pages']);
        $publicationsPage->addPages($pagesByCacheKey['publication-pages']);
        $movement->addPages($pagesByCacheKey['association-pages']['movement']);
        $shrines->addPages($pagesByCacheKey['association-pages']['shrinesByRegion']);
        $world->addPages($pagesByCacheKey['association-pages']['shrinesWorld']);
        $libPage->addPages($pagesByCacheKey['library-pages']);
        $musicPage->addPages($pagesByCacheKey['music-pages']);

        $navFixer = new FixNavigationPages();
        $navFixer->attach($app->getEventManager());

        $corsListener = new Listener\CorsListener();
        $corsListener->attach($app->getEventManager());
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
     * @param array<string, mixed> $pagesByCacheKey branches as onBootstrap() holds them
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
     * @param array<int|string, array<string, mixed>> $pages
     * @return array<int|string, array<string, mixed>>
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

    /**
     * Persist one navigation branch, tolerating a cache that cannot accept the write.
     *
     * Laminas\Cache\Storage\Adapter\Apcu::internalSetItem() throws a RuntimeException when
     * apcu_store() returns false, which on a full 32 MiB segment is routine. Since this runs in
     * onBootstrap, an uncaught throw is a 500 on every single request until APCu drains, so a
     * failed write must degrade to "the navigation is simply not cached this request".
     *
     * @param ContainerInterface $sm used only to reach a logger, lazily
     * @return bool whether the write succeeded
     */
    private function cacheNavigationPages(
        StorageInterface $cache,
        ContainerInterface $sm,
        string $cacheKey,
        array $pages
    ): bool {
        try {
            if (false === $cache->setItem($cacheKey, $pages)) {
                $this->reportNavigationCacheFailure($sm, $cacheKey, null);
                return false;
            }
        } catch (\Throwable $t) {
            $this->reportNavigationCacheFailure($sm, $cacheKey, $t);
            return false;
        }
        return true;
    }

    /**
     * Log a navigation cache write failure if a logger happens to be reachable.
     *
     * @param ContainerInterface $sm
     * @param string $cacheKey
     * @param \Throwable|null $t
     * @return void
     */
    private function reportNavigationCacheFailure($sm, $cacheKey, ?\Throwable $t = null)
    {
        try {
            if (! $sm->has('SionModel\Logger')) {
                return;
            }
            $logger = $sm->get('SionModel\Logger');
            $logger->error('Failed to cache a navigation branch.', [
                'cacheKey'  => $cacheKey,
                'exception' => isset($t) ? $t->getMessage() : 'setItem returned false',
            ]);
        } catch (\Throwable $ignored) {
            //we're in onBootstrap; never let logging a cache miss take the site down
        }
    }

    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }
}
