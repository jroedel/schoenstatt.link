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
use samdark\sitemap\Sitemap;
use Laminas\View\HelperPluginManager;
use Schoenstatt\Validator\SchoenstattLinkIdentifier;
use Schoenstatt\Filter\ToSchoenstattLinkIdentifier;
use App\Http\KernelCanary;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;
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
     * Toggle the Symfony-kernel canary cookie for the administrator who asked.
     *
     * Lives here, on the laminas side, because it has to work under **both** front
     * controllers: you turn the canary on while laminas is serving you and off while
     * Symfony is. The Symfony kernel bridges every unported path back to this
     * application, so one laminas action covers both directions; a Symfony-side route
     * could only ever switch it off.
     *
     * ## Consent is checked first, and that is not a formality
     *
     * Both GDPR strategies call `header_remove('Set-Cookie')` for a visitor who has not
     * accepted cookies — Application\View\GdprStrategy::onFinish() and, on the ported
     * side, App\Http\GdprCookieListener. So without consent this action *cannot* work:
     * the Set-Cookie is stripped after it returns and the admin is left clicking a menu
     * item that does nothing, with no clue why. Saying so is the whole reason the
     * branch exists.
     *
     * ## GET, deliberately
     *
     * A GET that changes state invites CSRF, and here the worst outcome is that an
     * administrator renders pages through the other front controller — no privilege
     * changes, because both consult the same ACL, and the next click undoes it. A CSRF
     * token would mean a form and a POST for a debugging toggle whose entire value is
     * being one click away. The trade is deliberate rather than overlooked.
     */
    public function kernelSwitchAction()
    {
        $cookies = $this->getRequest()->getCookie();

        if (! KernelCanary::hasConsent($cookies)) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)->addMessage(
                'Accept cookies first — without consent the site strips every Set-Cookie, '
                . 'so the kernel switch cannot take effect.'
            );

            return $this->redirect()->toUrl($this->kernelSwitchReturnUrl());
        }

        if (KernelCanary::isActive($cookies)) {
            //expire it: back to laminas-mvc, which is what every other visitor gets
            setcookie(KernelCanary::COOKIE, '', $_SERVER['REQUEST_TIME'] - 42000, '/');
            $message = 'Legacy kernel: pages now render through Laminas\Mvc\Application, '
                . 'the same as for every other visitor.';
        } else {
            //a session cookie, with no expiry on purpose: closing the browser reverts to
            //laminas, so an admin cannot leave themselves on the Symfony kernel for weeks
            //without noticing
            setcookie(KernelCanary::COOKIE, KernelCanary::VALUE, 0, '/');
            $message = 'Symfony kernel: pages now render through App\Kernel for you only. '
                . 'Check /_health — it answers 200 only on the Symfony side.';
        }

        $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_SUCCESS)->addMessage($message);

        return $this->redirect()->toUrl($this->kernelSwitchReturnUrl());
    }

    /**
     * Where to send the admin back to: the page they came from, but only when it is
     * ours.
     *
     * The Referer is attacker-controllable, so an unchecked redirect back to it is an
     * open redirect on a route administrators are expected to click. Same scheme and
     * host as the current request, or the home page.
     */
    protected function kernelSwitchReturnUrl()
    {
        $request  = $this->getRequest();
        $referer  = $request->getHeader('Referer');
        $fallback = $this->url()->fromRoute('welcome');

        if (! $referer) {
            return $fallback;
        }

        $target = parse_url((string) $referer->getFieldValue());
        $here   = $request->getUri();
        if (! is_array($target) || ! isset($target['host'])) {
            return $fallback;
        }
        if ($target['host'] !== $here->getHost() || ($target['scheme'] ?? null) !== $here->getScheme()) {
            return $fallback;
        }

        return (string) $referer->getFieldValue();
    }

    public function signInNoCookiesAction()
    {
        return new ViewModel();
    }

    public function sitemapAction()
    {
        $navigation = $this->navigation;
        $plugins = $this->helperPluginManager;
        /** @var \Laminas\View\Helper\Navigation\Sitemap $sitemapHelper */
        $sitemapHelper = $plugins->get('navigation')->sitemap();
        $sitemapFile = 'data/sitemap/sitemap.xml';
        $sitemap = new Sitemap($sitemapFile, true);
        $sitemap->setUseGzip(true);
        $iterator = new \RecursiveIteratorIterator($navigation, \RecursiveIteratorIterator::SELF_FIRST);
        $serverUrl = $sitemapHelper->getServerUrl();
        $languageSiteBases = [];
        $languages = array_keys($this->config['slm_locale']['aliases']);
        foreach ($languages as $lang) {
            $languageSiteBases[$lang] = $serverUrl . "/$lang/";
        }
        $firstCharToGrabFromUrl = strlen($languageSiteBases['en']);
        // iterate container
        foreach ($iterator as $page) {
            $url = $sitemapHelper->url($page);
            if (isset($url)) {
                $urlLocales = [];
                foreach ($languageSiteBases as $lang => $urlBase) {
                    $urlLocales[$lang] = $urlBase . substr($url, $firstCharToGrabFromUrl);
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
