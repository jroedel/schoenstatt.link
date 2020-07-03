<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Carbon\Carbon;
use Zend\Navigation\Navigation;
use Books\Model\PublicationsTable;
use Schoenstatt\Model\SchoenstattTable;
use Books\Model\EventTextTable;
use Books\Model\DictionaryTable;
use samdark\sitemap\Sitemap;
use Zend\View\HelperPluginManager;
use Schoenstatt\Validator\SchoenstattLinkIdentifier;
use Schoenstatt\Filter\ToSchoenstattLinkIdentifier;
use function False\true;

class IndexController extends AbstractActionController
{
    /**
     * @var Navigation $navigation
     */
    protected $navigation;

    /**
     * @var PublicationsTable $publicationsTable
     */
    protected $publicationsTable;

    /**
     * @var SchoenstattTable $schoenstattTable
     */
    protected $schoenstattTable;
    
    /**
     * @var EventTextTable $eventTextTable
     */
    protected $eventTextTable;
    
    /**
     * @var DictionaryTable $dictionaryTable
     */
    protected $dictionaryTable;
    
    protected $helperPluginManager;
    protected $config;

    public function __construct(
        Navigation $navigation, 
        PublicationsTable $publicationsTable, 
        SchoenstattTable $schoenstattTable, 
        EventTextTable $eventTextTable, 
        DictionaryTable $dictionaryTable,
        HelperPluginManager $helperPluginManager,
        array $config
        )
    {
        $this->navigation = $navigation;
        $this->publicationsTable = $publicationsTable;
        $this->schoenstattTable = $schoenstattTable;
        $this->eventTextTable = $eventTextTable;
        $this->dictionaryTable = $dictionaryTable;
        $this->helperPluginManager = $helperPluginManager;
        $this->config = $config;
    }

    public function indexAction()
    {
//         $changeCounts = $this->get6MonthsChanges();
        $blogPosts = $this->eventTextTable->getObjects(
            'text',
            ['kind' => EventTextTable::TEXT_KIND_BLOG],
            ['limit' => 5]
        );
        return new ViewModel([
            'changeCounts' => [],
            'blogPosts' => $blogPosts,
        ]);
    }
    
    public function redirectPreApril2020SlIdAction()
    {
        static $swValidator;
        static $swFilter;
        $id = $this->params()->fromRoute('sw_id');
        if (isset($id)) {
            if (!isset($swValidator)) {
                $swValidator = new SchoenstattLinkIdentifier(null, true);
            }
            if (!$swValidator->isValid($id)) {
                throw new \Exception('Invalid site-wide id');
            }
            if (!isset($swFilter)) {
                $swFilter = new \Schoenstatt\Filter\SchoenstattLinkIdentifier(null, true);
            }
            $id = $swFilter->filter($id);
            $entityType = $swFilter->getLastEntityType();
        } else {
            throw new \Exception('Invalid id');
        }
        
        $toIdFilter = new ToSchoenstattLinkIdentifier($entityType);
        $newId = $toIdFilter->filter($id);
        $route = SchoenstattLinkIdentifier::ENTITY_TYPE_ROUTES[$entityType];
        $response = $this->redirect()->toRoute(
            $route,
            [
                'sw_id' => $newId,
            ],
            [],
            true //reusing params should pass on our slug
            );
        
        $response->setStatusCode(301);
        return $response;
    }

    public function developersAction()
    {
        return new ViewModel();
    }
    
    public function acknowledgementsAction()
    {
        return new ViewModel();
    }
    
    public function privacyAction()
    {
        return new ViewModel();
    }
    
    public function signInNoCookiesAction()
    {
        return new ViewModel();
    }

    public function sitemapAction()
    {
        $navigation = $this->navigation;
        $plugins = $this->helperPluginManager;
        /** @var \Zend\View\Helper\Navigation\Sitemap $sitemapHelper */
        $sitemapHelper = $plugins->get('navigation')->sitemap();
        $sitemapFile = 'data/sitemap/sitemap.xml';
        $sitemap = new Sitemap($sitemapFile, true);
        $sitemap->setUseGzip(true);
        $iterator = new \RecursiveIteratorIterator($navigation, \RecursiveIteratorIterator::SELF_FIRST);
        $serverUrl = $sitemapHelper->getServerUrl();
        $languageSiteBases = [];
        $languages = array_keys($this->config['slm_locale']['aliases']);
        foreach ($languages as $lang) {
            $languageSiteBases[$lang] = $serverUrl."/$lang/";
        }
        $firstCharToGrabFromUrl = strlen($languageSiteBases['en']);
        // iterate container
        foreach ($iterator as $page) {
            $url = $sitemapHelper->url($page);
            if (isset($url)) {
                $urlLocales = [];
                foreach ($languageSiteBases as $lang => $urlBase) {
                    $urlLocales[$lang] = $urlBase.substr($url, $firstCharToGrabFromUrl);
                }
                $sitemap->addItem($urlLocales);
            }
        }
        $sitemap->write();
        // Explicitly set type to text/xml, otherwise it's text/html
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $headers->addHeaderLine(
            'Content-Type',
            'text/xml'
            )
            ->addHeaderLine('Content-Encoding', 'gzip');
        $response->setContent(file_get_contents($sitemapFile));
        return $response;
        // Only render the sitemap helper, without any layout
//         $viewModel = new ViewModel();
//         $viewModel->setVariable('navigation', $navigation);
//         $viewModel->setTerminal(true);
//         return $viewModel;
    }

    public function get6MonthsChanges()
    {
        $schTable = $this->schoenstattTable;
        $pubTable = $this->publicationsTable;
        $cMonth = new Carbon();
        $cMonth->startOfMonth()
            ->subMonths(5);
        $changeCounts = [
            'schoenstatt' => $schTable->getChangesCountPerMonth(),
            'publications' => $pubTable->getChangesCountPerMonth(),
            'translations' => $schTable->getTranslationChangesCountPerMonth(),
        ];
        $changes = [
            'schoenstatt' => [],
            'publications' => [],
            'translations' => [],
        ];
        for ($i = 0; $i < 6; $i++) {
            $monthKey = $cMonth->format('Ym');
            foreach ($changeCounts as $key => $changeCountArray) {
                $value = key_exists($monthKey, $changeCountArray) ?
                    $changeCountArray[$monthKey] : 0;
                $changes[$key][$cMonth->format('F')] = $value;
            }
            $cMonth->addMonth();
        }
        return $changes;
    }
}
