<?php

declare(strict_types=1);

namespace App\Console\Command;

use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use App\Sitemap\ChangeLog;
use App\Sitemap\GuestAccess;
use App\Sitemap\SitemapGenerator;
use App\View\NavigationTree;
use App\View\PreferredUrls;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

use function dirname;
use function is_array;
use function is_string;

/**
 * Builds `sitemap:build`, and does as little as possible while doing it.
 *
 * ## Two subtleties, both load-bearing
 *
 * **The ServiceBridge is constructed from this container's `ApplicationConfig`, not from the
 * config file.** `bin/console` disables the config and module-map caches *before* it registers
 * `ApplicationConfig`, so the array reached through the container already has them off, and a
 * bridge built from it inherits that. Reading `config/application.config.php` here instead
 * would silently re-enable them and let a cron run write `data/config/` as the deploy account
 * — a file the web SAPI can then never overwrite, which pins the site to whatever config that
 * process happened to see. That is the exact failure the comment in `bin/console` is about.
 *
 * **No PhraseFlush is passed**, which is what keeps JTranslate out of this. The second
 * constructor argument is the end-of-request phrase writer; with it null,
 * `TranslatorConfigurator` skips arming the flush, and `TranslationsTable::flush()` — which
 * needs a session and fatals in any console process, leaving half-written phrase rows behind —
 * is never reached. A cron job that builds the navigation container is exactly the process
 * that would otherwise trip it.
 *
 * The bridge is built inside the closure rather than here so that a run with nothing to do
 * loads no laminas modules at all: the staleness check needs only the database adapter, which
 * `ChangeLog` takes from the bridge lazily, and `isStale()` is reached without ever touching
 * the navigation.
 */
final class BuildSitemapCommandFactory implements FactoryInterface
{
    /**
     * @param string $requestedName
     * @param array<mixed>|null $options
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        ?array $options = null
    ): BuildSitemapCommand {
        /** @var array<string, mixed> $appConfig */
        $appConfig = $container->get('ApplicationConfig');

        /** @var array<string, mixed> $config */
        $config    = $container->get('config');
        $sionModel = $config['sion_model'] ?? [];
        $baseUrl   = is_array($sionModel) && is_string($sionModel['canonical_base_url'] ?? null)
            ? $sionModel['canonical_base_url']
            : '';

        //src/Console/Command/ -> the application root
        $root = dirname(__DIR__, 3);

        return new BuildSitemapCommand(
            static function () use ($appConfig, $root): SitemapGenerator {
                $laminas = new ServiceBridge($appConfig);
                $urls    = new RouteUrl($laminas, '');

                return new SitemapGenerator(
                    $laminas,
                    //The base URL is '' because the tree only ever produces paths here; the
                    //writer prepends the host. RouteUrl needs no MVC bootstrap — the laminas
                    //router assembles happily out of a bare ServiceManager, which is the
                    //property the whole strangler rests on.
                    new NavigationTree($laminas, $urls),
                    new ChangeLog($laminas),
                    new GuestAccess($laminas),
                    //the same builder the record pages use, so the URLs the sitemap
                    //advertises are the ones those pages call canonical
                    new PreferredUrls($urls),
                    $root . '/public'
                );
            },
            $baseUrl
        );
    }
}
