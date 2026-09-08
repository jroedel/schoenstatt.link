<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use Carbon\Carbon;
use Laminas\Navigation\Navigation;
use Books\Model\PublicationsTable;
use Schoenstatt\Model\SchoenstattTable;
use Books\Model\DictionaryTable;
use Laminas\View\HelperPluginManager;
use Schoenstatt\Validator\SchoenstattLinkIdentifier;
use Schoenstatt\Filter\ToSchoenstattLinkIdentifier;

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
     * @var DictionaryTable $dictionaryTable
     */
    protected $dictionaryTable;

    protected $helperPluginManager;
    protected $config;

    public function __construct(
        Navigation $navigation,
        PublicationsTable $publicationsTable,
        SchoenstattTable $schoenstattTable,
        DictionaryTable $dictionaryTable,
        HelperPluginManager $helperPluginManager,
        array $config
    ) {
        $this->navigation = $navigation;
        $this->publicationsTable = $publicationsTable;
        $this->schoenstattTable = $schoenstattTable;
        $this->dictionaryTable = $dictionaryTable;
        $this->helperPluginManager = $helperPluginManager;
        $this->config = $config;
    }

    public function indexAction()
    {
//         $changeCounts = $this->get6MonthsChanges();
        //index.phtml renders neither of these; the blog query that used to sit here read
        //five rows nothing displayed, and went with the blog itself.
        return new ViewModel([
            'changeCounts' => [],
        ]);
    }

    public function redirectPreApril2020SlIdAction()
    {
        static $swValidator;
        static $swFilter;
        $id = $this->params()->fromRoute('sw_id');
        if (isset($id)) {
            if (! isset($swValidator)) {
                $swValidator = new SchoenstattLinkIdentifier(null, true);
            }
            if (! $swValidator->isValid($id)) {
                throw new \Exception('Invalid site-wide id');
            }
            if (! isset($swFilter)) {
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

    /**
     * Retired 2026-08-13: the sitemap is a static file at /sitemap.xml.
     *
     * This used to walk the navigation container and write `data/sitemap/sitemap.xml` on
     * every request. It is unreachable in normal traffic — the Symfony route matches
     * /sitemap.xml first, and both front controllers are SYMFONY_KERNEL=1 — but it stays
     * because a visitor holding the `sl_symfony_canary=0` cookie still reaches this
     * controller, and a 500 is a poor escape hatch.
     *
     * A permanent redirect rather than a re-implementation, because there is nothing left
     * to implement: `bin/console sitemap:build` writes the files and Apache serves them.
     * That is also why samdark/sitemap could be removed from composer.json — this action
     * was its last caller.
     */
    public function sitemapAction()
    {
        return $this->redirect()->toUrl('/sitemap.xml')->setStatusCode(301);
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
