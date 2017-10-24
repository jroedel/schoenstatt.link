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

class IndexController extends AbstractActionController
{
    public function indexAction()
    {
        $sm = $this->serviceLocator;
        $changeCounts = $this->get6MonthsChanges();
        return new ViewModel([
            'changeCounts' => $changeCounts,
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

    public function sitemapAction()
    {
        $sm = $this->getServiceLocator();

        /** @var \Zend\Navigation\Navigation $navigation */
        $navigation = $sm->get('navigation');
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

        /** @var \Books\Model\PublicationsTable $table */
        $table = $sm->get('Books\Model\PublicationsTable');
        $publications = $table->getUnlinkedPublications();
        foreach ($publications as $publicationId => $object) {
            if ($object['resourceId'] == 'publication_public') {
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


        // Explicitly set type to text/xml, otherwise it's text/html
        $this->getResponse()->getHeaders()->addHeaderLine(
            'Content-Type', 'text/xml'
        );
        // Only render the sitemap helper, without any layout
        $viewModel = new ViewModel();
        $viewModel->setVariable('navigation', $navigation);
        $viewModel->setTerminal(true);
        return $viewModel;
    }

    public function get6MonthsChanges()
    {
        $sm = $this->serviceLocator;
        $schTable = $sm->get('Schoenstatt\Model\SchoenstattTable');
        $pubTable = $sm->get('Books\Model\PublicationsTable');
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
