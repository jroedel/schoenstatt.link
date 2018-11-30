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

    public function __construct(Navigation $navigation, PublicationsTable $publicationsTable, SchoenstattTable $schoenstattTable, EventTextTable $eventTextTable)
    {
        $this->navigation = $navigation;
        $this->publicationsTable = $publicationsTable;
        $this->schoenstattTable = $schoenstattTable;
        $this->eventTextTable = $eventTextTable;
    }

    public function indexAction()
    {
        $changeCounts = $this->get6MonthsChanges();
        $blogPosts = $this->eventTextTable->getTexts(
            ['kind' => EventTextTable::TEXT_KIND_BLOG], 
            ['limit' => 5]
            );
        return new ViewModel([
            'changeCounts' => $changeCounts,
            'blogPosts' => $blogPosts,
        ]);
    }

    public function developersAction()
    {
        return new ViewModel([]);
    }
    
    public function acknowledgementsAction()
    {
        return new ViewModel([]);
    }
    
    public function privacyAction()
    {
        return new ViewModel([]);
    }

    public function sitemapAction()
    {
        $navigation = $this->navigation;
        $navigation->addPage([
            'label' => 'Home',
            'uri'   => $this->url()->fromRoute('welcome'),
            'order' => 0,
            'pages' => [
                [
                    'label' => 'Developers Center',
                    'uri' => $this->url()->fromRoute('developers'),
                ],
                [
                    'label' => 'Security research acknowledgements',
                    'uri' => $this->url()->fromRoute('acknowledgements'),
                ],
            ]
        ]);
        $publicationsPage = $navigation->findOneBy('route', 'publications');

        $pagesByLanguage = [];

        $table = $this->publicationsTable;
        $publications = $table->getUnlinkedPublications();
        foreach ($publications as $publicationId => $object) {
            if ($object['resourceId'] == 'publication_public' && true === $object['isRevisedWithBookInHand']) {
                $url = $this->url()->fromRoute('publications/publication', ['publication_id' => $publicationId]);
                if (!array_key_exists($object['inLanguage'], $pagesByLanguage)) {
                    $pagesByLanguage[$object['inLanguage']] = [];
                }
                $pagesByLanguage[$object['inLanguage']][] = [
                    'label' => $object['title'],
                    'uri' => $url,
                    'id'    => 'pub_'.$publicationId,
                ];
            }
        }

        foreach ($pagesByLanguage as $languageCode => $pages) {
            $url = $this->url()->fromRoute('publications/index', ['inLanguage' => $languageCode]);
            $publicationsPage->addPage([
                'label' => $languageCode.' Liturature',
                'uri' => $url,
                'id'    => 'pub_lang_'.$languageCode,
                'pages' => $pages,
            ]);
        }

        $shrinesPage = $navigation->findOneBy('route', 'shrines');

        $shrines = $this->schoenstattTable->getShrines();

        foreach ($shrines as $associationId => $object) {
            $url = $this->url()->fromRoute('associations/association', ['sw_id' => $object['identifier']]);
            $shrinesPage->addPage([
                'label' => $object['formattedName'],
                'uri' => $url,
                'id'    => 'assoc_'.$object['identifier'],
            ]);
        }

        // Explicitly set type to text/xml, otherwise it's text/html
        $this->getResponse()->getHeaders()->addHeaderLine(
            'Content-Type',
            'text/xml'
        );
        // Only render the sitemap helper, without any layout
        $viewModel = new ViewModel();
        $viewModel->setVariable('navigation', $navigation);
        $viewModel->setTerminal(true);
        return $viewModel;
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
