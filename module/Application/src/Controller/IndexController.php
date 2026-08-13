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
     *
     * ## It offers the kernel you are not on, and never needs to know the default
     *
     * The cookie has three states — absent, `1`, `0` — because public/.htaccess has a
     * site-wide default plus an override each way. So this is a toggle between "what
     * everyone else gets" and "the other one", which is the only pair an administrator
     * ever wants, and it is built from two questions that are both answerable:
     *
     * - **Am I overriding anything?** From the cookie. If so, clear it and land back on
     *   the default, whatever that currently is.
     * - **Which kernel is serving me?** From `getenv('SYMFONY_KERNEL')`, which Apache
     *   exports to a bridged request too. If nothing is overridden, set the cookie to
     *   the *opposite* of that.
     *
     * What deliberately does not appear anywhere here is the site default itself. PHP
     * cannot read .htaccess, and inferring it would be guessing — so the day
     * `SYMFONY_KERNEL=1` becomes the default for everyone, this action keeps working
     * with no edit, and the two branches simply swap which kernel they hand out.
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

        if (KernelCanary::isOverriding($cookies)) {
            //expire it: back to whatever public/.htaccess makes the default, which this
            //action cannot see and so does not claim to know. Naming the kernel here
            //would be a guess that reads as fact the moment the default flips.
            setcookie(KernelCanary::COOKIE, '', $_SERVER['REQUEST_TIME'] - 42000, '/');
            $message = 'Kernel override cleared: you now get the same front controller as '
                . 'every other visitor. Check /_health — it answers 200 only on the Symfony side.';
        } else {
            //a session cookie, with no expiry on purpose: closing the browser drops the
            //override, so an admin cannot leave themselves off the site default for weeks
            //without noticing
            $toLaminas = KernelCanary::symfonyKernelIsLive();
            setcookie(
                KernelCanary::COOKIE,
                $toLaminas ? KernelCanary::FORCE_LAMINAS : KernelCanary::FORCE_SYMFONY,
                0,
                '/'
            );
            $message = $toLaminas
                ? 'Legacy kernel: pages now render through Laminas\Mvc\Application for you only. '
                    . 'Check /_health — it stops answering 200 on the laminas side.'
                : 'Symfony kernel: pages now render through App\Kernel for you only. '
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
