<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application;

use Laminas\Mvc\MvcEvent;
use Application\View\GdprStrategy;
use Laminas\Navigation\Navigation;
use Application\Navigation\FixNavigationPages;
use Application\Navigation\PageBuilder;

class Module
{
    /**
     * The navigation constants, kept here as the names everything already refers to.
     *
     * The values, and the code that builds the branches they key, live in
     * Application\Navigation\PageBuilder since 2026-08-13 — the Symfony side needs the
     * same tree and two implementations of it would drift silently. These are aliases so
     * that partial/breadcrumbs.phtml, test/Unit/NavigationDataLabelsTest and everything
     * else keep working unchanged; PageBuilder is where to read what they mean.
     */
    public const PAGES_CACHE_KEYS = PageBuilder::PAGES_CACHE_KEYS;

    public const LOCALE_DEPENDENT_PAGES_CACHE_KEYS = PageBuilder::LOCALE_DEPENDENT_PAGES_CACHE_KEYS;

    public const LABEL_IS_DATA = PageBuilder::LABEL_IS_DATA;

    /**
     * @param MvcEvent $e
     */
    public function onBootstrap(MvcEvent $e)
    {
        $app = $e->getApplication();
        $sm = $app->getServiceManager();
        $strategy = $sm->get(GdprStrategy::class);
        $strategy->attach($app->getEventManager());

        //Every database-derived branch, cached exactly as it always was; see
        //Application\Navigation\PageBuilder, which the Symfony side calls too.
        $pageBuilder = new PageBuilder(static fn (string $id) => $sm->has($id) ? $sm->get($id) : null);
        $pagesByCacheKey = $pageBuilder->branches();

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

        //Listener\CorsListener was attached here until 2026-08-14. It served the v1
        //API alone: routes opted in with a `'cors' => true` default, and every route
        //that carried one was in the Books api-v1 tree. With those gone no route can
        //opt in, so its onFinish() half could never fire again, and its preflight half
        //would have answered a cheerful 204 to a browser about to receive a 404. The
        //v3 API has never sent CORS headers and does not want them — its callers are
        //server-side agents — and if that ever changes it belongs on the Symfony side,
        //where ported routes actually run, not in an MvcEvent listener they bypass.
    }

    /**
     * Flag every navigation label that is record content.
     *
     * Delegates to PageBuilder, which is where the rule and its reasoning live.
     * Kept as a static on this class because test/Unit/NavigationDataLabelsTest drives it
     * by name and partial/breadcrumbs.phtml reads the flag it sets.
     *
     * @param array<string, mixed> $pagesByCacheKey
     * @return array<string, mixed>
     */
    public static function markDataLabels(array $pagesByCacheKey): array
    {
        return PageBuilder::markDataLabels($pagesByCacheKey);
    }

    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }
}
