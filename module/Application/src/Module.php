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

class Module
{
    /**
     * @todo CACHE ME PLZZZ!
     * @param MvcEvent $e
     */
    public function onBootstrap(MvcEvent $e)
    {
        $app = $e->getApplication();
        $sm = $app->getServiceManager();
        $strategy = $sm->get(GdprStrategy::class);
        $strategy->attach($app->getEventManager());
        
        //build out the navigation a little
        /** @var Navigation $navigation */
        $navigation = $sm->get(Navigation::class);
        /** @var DictionaryTable $dictionaryTable */
        $dictionaryTable = $sm->get(DictionaryTable::class);
//         $navigation->addPage(['label' => 'hi', 'route' => 'associations/do-work']);
        $dictionaryPage = $navigation->findOneBy('route', 'dictionary');
        $dictionaries = $dictionaryTable->getAvailableDictionaryLanguages();
        foreach ($dictionaries as $object) {
            $dictionaryPage->addPage([
                'label' => sprintf('German to %s Dictionary', $object['inLanguageName']),
                'route' => 'dictionary/inLanguage',
                'params' => ['inLanguage' => $object['inLanguage']],
                'id'    => 'dict_'.$object['inLanguage'],
            ]);
        }
        
        $publicationsPage = $navigation->findOneBy('route', 'publications');
        $pagesByLanguage = [];
        $table = $sm->get(PublicationsTable::class);
        $publications = $table->getUnlinkedPublications();
        foreach ($publications as $publicationId => $object) {
            if ($object['resourceId'] == 'publication_public' && true === $object['isRevisedWithBookInHand']) {
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
        
        $languageNames = $dictionaryTable->getLanguageNames();
        foreach ($pagesByLanguage as $languageCode => $pages) {
            $publicationsPage->addPage([
                'label' => $languageNames[$languageCode].' Schoenstatt Literature',
                'route' => 'publications/index',
                'params' => ['inLanguage' => $languageCode],
                'id'    => 'pub_lang_'.$languageCode,
                'pages' => $pages,
            ]);
        }
        
        /** @var SchoenstattTable $schoenstattTable */
        $schoenstattTable = $sm->get(SchoenstattTable::class);
        $associations = $schoenstattTable->getUnlinkedAssociations();
        
        $movement = $navigation->findOneBy('route', 'schoenstatt');
        $pages = $navigation->findAllBy('route', 'shrines');
        $shrinePages = [];
        $shrinePageCounts = [];
        foreach ($pages as $page) {
            $shrinePages[$page->getLabel()] = $page;
            if (isset($shrinePageCounts[$page->getLabel()])) {
                $shrinePageCounts[$page->getLabel()]++;
            } else {
                $shrinePageCounts[$page->getLabel()] = 1;
            }
        }
        foreach ($associations as $object) {
            if ('sch-shrine' !== $object['kind'] && 'sch-wayside-shrine' !== $object['kind']) {
                //@todo this could be subdivided heirarchically
                $movement->addPage([
                    'label' => $object['name'], //@todo replace this with something translatable
                    'route' => 'associations/association',
                    'params' => ['sw_id' => $object['identifier']],
                ]);
            }
            if (!isset($object['countryRegion']) || !isset($shrinePages[$object['countryRegion']])) {
                $key = 'Shrines';
            } else {
                $key = $object['countryRegion'];
            }
            $shrinePages[$key]->addPage([
                'label' => $object['name'], //@todo replace this with something translatable
                'route' => 'associations/association',
                'params' => ['sw_id' => $object['identifier']],
            ]);
        }
        
        /** @var LibraryTable $libraryTable */
        $libraryTable = $sm->get(LibraryTable::class);
        $libraries = $libraryTable->getObjects('library');
        $libPage = $navigation->findOneBy('route', 'libraries');
        foreach ($libraries as $object) {
            if (!$object['isActive']) {
                continue;
            }
            $libPage->addPage([
                'label' => $object['name'],
                'route' => 'libraries/library',
                'params' => ['library_id' => $object['libraryId']],
//                 'resource' => $object['viewRole'],
                'id'    => 'lib_'.$object['libraryId'],
            ]);
        }
        
        $navFixer = new FixNavigationPages();
        $navFixer->attach($app->getEventManager());
    }
    
    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }
}
