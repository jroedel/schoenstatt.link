<?php

declare(strict_types=1);

namespace App\Laminas;

use App\Acl\AclProvider;
use App\Acl\IsAllowed;
use JTranslate\Model\TranslationsTable;
use JTranslate\I18n\Translator\Translator as JTranslateTranslator;
use JTranslate\I18n\Translator\TranslatorFactory as JTranslateTranslatorFactory;
use Laminas\Translator\TranslatorInterface;
use App\Modules\ModuleConfig;
use Laminas\ServiceManager\ServiceManager;
use SionModel\Cache\CacheFlushQueue;

use function is_array;

/**
 * Builds the laminas `ServiceManager` this application still keeps for the module
 * configs, the tables, the forms and the translator — without laminas-mvc.
 *
 * This is what `Laminas\Mvc\Service\ServiceManagerConfig` plus the MVC service listener
 * used to do, cut down to the part that is not the MVC layer: the merged module
 * configuration, and the one step the service listener performed — feeding the merged
 * `service_manager` key into the container. The event-manager pair went on 2026-09-21
 * with laminas-session, which was the only thing that had wanted one. Every
 * plugin-manager key (`validators`, `filters`, `form_elements`,
 * `input_filters`, `hydrators`, `translator_plugins`, `route_manager`) is registered by
 * the laminas component that owns it, through its own `Module`/`ConfigProvider` in
 * `config/modules.config.php`. The keys laminas-mvc alone consumed — `controllers`,
 * `controller_plugins`, `view_manager` — are not read.
 *
 * The merging itself is {@see ModuleConfig}, which replaced laminas-modulemanager on
 * 2026-09-21; that class records what the module manager was and was not doing here.
 *
 * Two services laminas-mvc used to define are defined here under the same ids, because
 * ported code asks for them by those names:
 *
 * - `MvcTranslator` — an alias for `JTranslate\I18n\Translator\Translator`, the one
 *   translator; `jtranslate_translator` and `Laminas\Translator\TranslatorInterface` are
 *   the same object under other names;
 * - `config`, `Config`, `configuration` — the merged module configuration.
 *
 * And the two delegators the application needs on every code path: {@see TranslatorConfigurator}
 * on the translator's **class** id (aliases resolve before delegators are looked up, so a
 * delegator on an alias silently never runs) and {@see TranslationsTableConfigurator}.
 *
 * The one entry point for every caller — `ServiceBridge` per request, `bin/console`,
 * `tools/acl-table.php`, the fuzz harness and the integration tests — so that "the way
 * bin/console builds it" and "the way a request builds it" cannot drift.
 */
final class ContainerFactory
{
    /**
     * @param array<string, mixed> $appConfig `config/application.config.php`
     * @param bool $configCaches whether the merged config may be read from and written to
     *        `data/config/`. Only a web request wants that: a console run or a test writing
     *        there leaves a file owned by the wrong user next to a real deployment, and CI
     *        has no such directory
     * @param PhraseFlush|null $phraseFlush the request's end-of-request phrase flush, armed
     *        by TranslatorConfigurator; a console process passes none
     * @param CacheFlushQueue|null $cacheFlushQueue the request's persistent-cache write
     *        queue, which SionTableWiring enrols tables into; a console process passes none
     *        — and must: an APCu segment belongs to the SAPI that created it
     */
    public static function build(
        array $appConfig,
        bool $configCaches = false,
        ?PhraseFlush $phraseFlush = null,
        ?CacheFlushQueue $cacheFlushQueue = null
    ): ServiceManager {
        $appConfig['module_listener_options'] ??= [];
        if (! $configCaches) {
            $appConfig['module_listener_options']['config_cache_enabled'] = false;
        }

        $moduleConfig = ModuleConfig::fromApplicationConfig($appConfig, $configCaches);
        $merged       = $moduleConfig->merged();

        $services = new ServiceManager();
        $services->setAllowOverride(true);
        $services->configure(self::bootstrapConfig($merged, $appConfig));
        $services->configure(is_array($appConfig['service_manager'] ?? null) ? $appConfig['service_manager'] : []);
        $services->setService('ApplicationConfig', $appConfig);
        $services->setService(ServiceManager::class, $services);
        $services->setService(ModuleConfig::class, $moduleConfig);

        //What `Laminas\ModuleManager\Listener\ServiceListener` did on loadModules.post,
        //and the only thing it did here: no module implements `ServiceProviderInterface`
        //— every `Module` class in this application has `getConfig()` and nothing else —
        //so the merged `service_manager` key is the whole of its contribution. It ran with
        //override allowed, as this still does.
        $services->configure(is_array($merged['service_manager'] ?? null) ? $merged['service_manager'] : []);
        $services->setAllowOverride(false);

        //After the module configuration: what follows shadows or decorates services the
        //module configs defined, and there is nothing to shadow before that. Before anything
        //asks for them, which nothing has yet — a delegator on an already-built service
        //would throw.
        //There is one translator and every name for it is an alias, which is what makes
        //the delegator below reach all of them. `MvcTranslator` is historical — laminas-mvc
        //named it and 27 config entries still do — and `jtranslate_translator` is
        //JTranslate's own name for the same object.
        //
        //Since 2026-09 that object is `JTranslate\I18n\Translator\Translator`, which
        //implements the `Laminas\Translator` interface — one file, no implementation, and
        //what `SionModel\Validator\AbstractValidator::setDefaultTranslator()` type-hints —
        //so validators take it directly, with no adapter in between.
        $services->configure([
            'factories'  => [
                JTranslateTranslator::class => JTranslateTranslatorFactory::class,
                //The ambient authorization question, shared for the request. Registered on
                //the container rather than on a view-helper manager (where BjyAuthorize put
                //it, and where it stayed until laminas-view was removed) so that the one
                //instance serves the ported controllers, App\Sion\* and App\Laminas\ViewHelpers
                //alike — two AclProviders in a request assemble the ACL twice.
                IsAllowed::class            => static fn (ServiceManager $container): IsAllowed
                    => new IsAllowed(new AclProvider(new ContainerServices($container))),
            ],
            'aliases'    => [
                TranslatorInterface::class => JTranslateTranslator::class,
                'MvcTranslator'            => JTranslateTranslator::class,
                'jtranslate_translator'    => JTranslateTranslator::class,
            ],
            'delegators' => [
                JTranslateTranslator::class => [TranslatorConfigurator::class],
                TranslationsTable::class    => [TranslationsTableConfigurator::class],
            ],
        ]);

        if (null !== $phraseFlush) {
            $services->setService(PhraseFlush::class, $phraseFlush);
        }
        if (null !== $cacheFlushQueue) {
            $services->setService(CacheFlushQueue::class, $cacheFlushQueue);
        }

        return $services;
    }

    /**
     * The services that must exist before the module configuration is applied.
     *
     * @param array<string, mixed> $merged the merged module configuration
     * @param array<string, mixed> $appConfig
     * @return array<string, mixed>
     */
    private static function bootstrapConfig(array $merged, array $appConfig): array
    {
        /** @var array<string, mixed> $listenerOptions */
        $listenerOptions = is_array($appConfig['module_listener_options'] ?? null)
            ? $appConfig['module_listener_options']
            : [];

        return [
            'aliases'   => [
                'Config'        => 'config',
                'configuration' => 'config',
                'Configuration' => 'config',
            ],
            'services'  => [
                //Already built: the merged `service_manager` key is applied from it a few
                //lines above, so there is nothing to defer and no factory to write.
                'config'           => $merged,
                //The files `cache:clear-config` removes. A plain array rather than a
                //reachable object so that SionModel, which owns the command, needs no
                //App\ class and no second copy of the naming rule.
                'ConfigCacheFiles' => ModuleConfig::cacheFiles($listenerOptions),
            ],
        ];
    }
}
