<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application;

use Zend\Mvc\MvcEvent;
use Application\View\GdprStrategy;
use Zend\Navigation\Navigation;
use Books\Model\DictionaryTable;
use Books\Model\PublicationsTable;
use Application\Navigation\FixNavigationPages;
use Schoenstatt\Model\SchoenstattTable;
use Books\Model\LibraryTable;
use Books\Model\EventTextTable;
use Zend\Cache\Storage\StorageInterface;

class Module
{
    const PAGES_CACHE_KEYS = [
        'dictionary-pages',
        'publication-pages',
        'association-pages',
        'library-pages',
        'blog-pages',
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
        $cache = $sm->get(StorageInterface::class);
        $locale = \Locale::getDefault();
        if (!array_key_exists($locale, SchoenstattTable::LOCALES_TO_SLUG_COLUMN_NAME)) {
            $locale = 'en_US';
        }
        $pagesByCacheKey = $cache->getItems(self::PAGES_CACHE_KEYS);
        
        
        if (!isset($pagesByCacheKey['dictionary-pages'])) {
            $dictionaryPages = [];
            /** @var DictionaryTable $dictionaryTable */
            $dictionaryTable = $sm->get(DictionaryTable::class);
            $dictionaries = $dictionaryTable->getAvailableDictionaryLanguages();
            foreach ($dictionaries as $object) {
                $dictionaryPages[] = [
                    'label' => sprintf('German to %s Dictionary', $object['inLanguageName']),
                    'route' => 'dictionary/inLanguage',
                    'params' => ['inLanguage' => $object['inLanguage']],
                    'id'    => 'dict_'.$object['inLanguage'],
                ];
            }
            $pagesByCacheKey['dictionary-pages'] = $dictionaryPages;
            $cache->setItem('dictionary-pages', $dictionaryPages);
        }
        
        if (!isset($pagesByCacheKey['publication-pages'])) {
            $publicationPages = [];
            $pagesByLanguage = [];
            $table = $sm->get(PublicationsTable::class);
            $publications = $table->getUnlinkedPublications();
            foreach ($publications as $publicationId => $object) {
                if ($object['resourceId'] === 'publication_public' && true === $object['isRevisedWithBookInHand']) {
                    if (!array_key_exists($object['inLanguage'], $pagesByLanguage)) {
                        $pagesByLanguage[$object['inLanguage']] = [];
                    }
                    $pagesByLanguage[$object['inLanguage']][] = [
                        'label' => $object['title'],
                        'route' => 'publications/publication',
                        'params' => ['publication_id' => $publicationId],
                        'id'    => 'pub_'.$publicationId,
                    ];
                }
            }
            
            if (!isset($dictionaryTable)) {
                /** @var DictionaryTable $dictionaryTable */
                $dictionaryTable = $sm->get(DictionaryTable::class);
            }
            $languageNames = $dictionaryTable->getLanguageNames();
            foreach ($pagesByLanguage as $languageCode => $pages) {
                $publicationPages[] = [
                    'label' => $languageNames[$languageCode].' Schoenstatt Literature',
                    'route' => 'publications/index',
                    'params' => ['inLanguage' => $languageCode],
                    'id'    => 'pub_lang_'.$languageCode,
                    'pages' => $pages,
                ];
            }
            
            $pagesByCacheKey['publication-pages'] = $publicationPages;
            $cache->setItem('publication-pages', $publicationPages);
        }
        
        if (!isset($pagesByCacheKey['association-pages'])) {
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
                if (!isset($object['countryRegion'])) {
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
                    if (!isset($associationPages['shrinesByRegion'][$key])) {
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
            $cache->setItem('association-pages', $associationPages);
        }
        
        if (!isset($pagesByCacheKey['library-pages'])) {
            $libraryPages = [];
            /** @var LibraryTable $libraryTable */
            $libraryTable = $sm->get(LibraryTable::class);
            $libraries = $libraryTable->getObjects('library');
            foreach ($libraries as $object) {
                if (!$object['isActive']) {
                    continue;
                }
                $libraryPages[] = [
                    'label' => $object['name'],
                    'route' => 'libraries/library',
                    'params' => ['library_id' => $object['libraryId']],
    //                 'resource' => $object['viewRole'],
                    'id'    => 'lib_'.$object['libraryId'],
                ];
            }
            $pagesByCacheKey['library-pages'] = $libraryPages;
            $cache->setItem('library-pages', $libraryPages);
        }
        
        if (!isset($pagesByCacheKey['blog-pages'])) {
            $blogPages = [];
            /** @var EventTextTable $eventTextTable */
            $eventTextTable = $sm->get(EventTextTable::class);
            $texts = $eventTextTable->getObjects('text');
            foreach ($texts as $object) {
                $blogPages[] = [
                    'label' => $object['title'],
                    'route' => 'blog/blog-post',
                    'params' => ['text_id' => $object['textId'], 'slug' => $object['slug']],
                    //                 'resource' => $object['viewRole'],
                    'id'    => 'blog_'.$object['textId'],
                ];
            }
            $pagesByCacheKey['blog-pages'] = $blogPages;
            $cache->setItem('blog-pages', $blogPages);
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
        
        $dictionaryPage->addPages($pagesByCacheKey['dictionary-pages']);
        $publicationsPage->addPages($pagesByCacheKey['publication-pages']);
        $movement->addPages($pagesByCacheKey['association-pages']['movement']);
        $shrines->addPages($pagesByCacheKey['association-pages']['shrinesByRegion']);
        $world->addPages($pagesByCacheKey['association-pages']['shrinesWorld']);
        $libPage->addPages($pagesByCacheKey['library-pages']);
        $blogPage->addPages($pagesByCacheKey['blog-pages']);
        
        $navFixer = new FixNavigationPages();
        $navFixer->attach($app->getEventManager());
    }
    
    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }
}
