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
use Books\Model\EventTextTable;
use Books\Model\MusicTable;

class Module
{
    public const PAGES_CACHE_KEYS = [
        'dictionary-pages',
        'publication-pages',
        'association-pages',
        'library-pages',
        'blog-pages',
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
         *  - association-pages: embeds $object['slugByLocale'][$locale]. LOCALE-DEPENDENT.
         *  - library-pages: label is the raw LibraryName column, param is libraryId.
         *      Locale-independent.
         *  - blog-pages: label is the raw text title, params are identifier and the single Slug
         *      column. Locale-independent.
         *  - music-pages: label is the raw composition name, params are identifier and the single
         *      Slug column. Locale-independent.
         * Labels are translated at render time by the navigation view helper, so an English label
         * in the cache is not itself a reason to salt.
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
                if ('sch-shrine' !== $object['kind'] && 'sch-wayside-shrine' !== $object['kind']) {
                    //@todo this could be subdivided heirarchically
                    $associationPages['movement'][] = [
                        'label' => $object['name'], //@todo replace this with something translatable
                        'route' => 'association',
                        'params' => [
                            'sw_id' => $object['identifier'],
                            'slug' => $object['slugByLocale'][$locale]
                        ],
                    ];
                }
                if (! isset($object['countryRegion'])) {
                    $associationPages['shrinesWorld'][] = [
                        'label' => $object['name'],
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
                        'label' => $object['name'],
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

        if (! isset($pagesByCacheKey['blog-pages'])) {
            $blogPages = [];
            /** @var EventTextTable $eventTextTable */
            $eventTextTable = $sm->get(EventTextTable::class);
            $texts = $eventTextTable->getObjects('text', ['kind' => EventTextTable::TEXT_KIND_BLOG]);
            foreach ($texts as $object) {
                $blogPages[] = [
                    'label' => $object['title'],
                    'route' => 'blog/blog-post',
                    'params' => ['sw_id' => $object['identifier'], 'slug' => $object['slug']],
                    //                 'resource' => $object['viewRole'],
                    'id'    => 'blog_' . $object['textId'],
                ];
            }
            $pagesByCacheKey['blog-pages'] = $blogPages;
            $this->cacheNavigationPages($cache, $sm, $cacheKeys['blog-pages'], $blogPages);
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

        //build out the navigation a little
        /** @var Navigation $navigation */
        $navigation = $sm->get(Navigation::class);
        $dictionaryPage = $navigation->findOneBy('label', 'Dictionaries');
        $publicationsPage = $navigation->findOneBy('route', 'publications');
        $movement = $navigation->findOneBy('route', 'schoenstatt');
        $shrines = $navigation->findOneBy('label', 'Shrines');
        $world = $navigation->findOneBy('label', 'World');
        $libPage = $navigation->findOneBy('route', 'libraries');
        $blogPage = $navigation->findOneBy('route', 'blog');
        $musicPage = $navigation->findOneBy('route', 'music');

        $dictionaryPage->addPages($pagesByCacheKey['dictionary-pages']);
        $publicationsPage->addPages($pagesByCacheKey['publication-pages']);
        $movement->addPages($pagesByCacheKey['association-pages']['movement']);
        $shrines->addPages($pagesByCacheKey['association-pages']['shrinesByRegion']);
        $world->addPages($pagesByCacheKey['association-pages']['shrinesWorld']);
        $libPage->addPages($pagesByCacheKey['library-pages']);
        $blogPage->addPages($pagesByCacheKey['blog-pages']);
        $musicPage->addPages($pagesByCacheKey['music-pages']);

        $navFixer = new FixNavigationPages();
        $navFixer->attach($app->getEventManager());

        $corsListener = new Listener\CorsListener();
        $corsListener->attach($app->getEventManager());
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
