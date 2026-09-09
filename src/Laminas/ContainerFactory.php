<?php

declare(strict_types=1);

namespace App\Laminas;

use JTranslate\Model\TranslationsTable;
use Laminas\EventManager\EventManager;
use Laminas\EventManager\EventManagerAwareInterface;
use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\SharedEventManager;
use Laminas\EventManager\SharedEventManagerInterface;
use JTranslate\I18n\Translator\Translator as JTranslateTranslator;
use JTranslate\I18n\Translator\TranslatorFactory as JTranslateTranslatorFactory;
use Laminas\Translator\TranslatorInterface;
use Laminas\ModuleManager\Feature\ServiceProviderInterface;
use Laminas\ModuleManager\Feature\ViewHelperProviderInterface;
use Laminas\ModuleManager\Listener\ConfigListener;
use Laminas\ModuleManager\Listener\DefaultListenerAggregate;
use Laminas\ModuleManager\Listener\ListenerOptions;
use Laminas\ModuleManager\Listener\ServiceListener;
use Laminas\ModuleManager\ModuleEvent;
use Laminas\ModuleManager\ModuleManager;
use Laminas\ServiceManager\ServiceManager;
use SionModel\Cache\CacheFlushQueue;

use function is_array;

/**
 * Builds the laminas `ServiceManager` this application still keeps for the module
 * configs, the tables, the forms and the translator — without laminas-mvc.
 *
 * This is what `Laminas\Mvc\Service\ServiceManagerConfig` plus the MVC service listener
 * used to do, cut down to the part that is not the MVC layer: an event manager pair for
 * the module manager, the module manager itself with the default listeners (config
 * merging and caching) and the service listener that turns each module's
 * `service_manager` and `view_helpers` keys into container configuration. Every other
 * plugin-manager key (`validators`, `filters`, `form_elements`, `input_filters`,
 * `hydrators`, `translator_plugins`, `route_manager`) is registered by the laminas
 * component that owns it, through its own `Module`/`ConfigProvider` in
 * `config/modules.config.php`. The keys laminas-mvc alone consumed — `controllers`,
 * `controller_plugins`, `view_manager` — are not read.
 *
 * Three services laminas-mvc used to define are defined here under the same ids, because
 * ported code asks for them by those names:
 *
 * - `ViewHelperManager` — {@see ViewHelperManagerFactory};
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
     * @param bool $configCaches whether the merged config and the module map may be read
     *        from and written to `data/config/`. Only a web request wants that: a console
     *        run or a test writing there leaves a file owned by the wrong user next to a
     *        real deployment, and CI has no such directory
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
            $appConfig['module_listener_options']['config_cache_enabled']     = false;
            $appConfig['module_listener_options']['module_map_cache_enabled'] = false;
        }

        $services = new ServiceManager();
        $services->setAllowOverride(true);
        $services->configure(self::bootstrapConfig($appConfig));
        $services->configure(is_array($appConfig['service_manager'] ?? null) ? $appConfig['service_manager'] : []);
        $services->setService('ApplicationConfig', $appConfig);
        $services->setService(ServiceManager::class, $services);
        $services->setAllowOverride(false);

        $services->get('ModuleManager')->loadModules();

        //After loadModules(): what follows shadows or decorates services the module
        //configs defined, and there is nothing to shadow before that. Before anything
        //asks for them, which nothing has yet — a delegator on an already-built service
        //would throw.
        //There is one translator and every name for it is an alias, which is what makes
        //the delegator below reach all of them. `MvcTranslator` is historical — laminas-mvc
        //named it and 27 config entries still do — and `jtranslate_translator` is
        //JTranslate's own name for the same object.
        //
        //Since 2026-09 that object is `JTranslate\I18n\Translator\Translator`, which
        //implements both the `Laminas\Translator` interface (what laminas-validator 3 will
        //want) and laminas-validator 2's own deprecated one, so validators take it
        //directly. The `Laminas\Validator\Translator\Translator` adapter that used to sit
        //in between is gone with laminas-i18n.
        $services->configure([
            'factories'  => [
                'ViewHelperManager'         => ViewHelperManagerFactory::class,
                JTranslateTranslator::class => JTranslateTranslatorFactory::class,
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
     * The services the module manager needs to exist before any module is loaded.
     *
     * @param array<string, mixed> $appConfig
     * @return array<string, mixed>
     */
    private static function bootstrapConfig(array $appConfig): array
    {
        return [
            'aliases'   => [
                'Config'                           => 'config',
                'configuration'                    => 'config',
                'Configuration'                    => 'config',
                EventManagerInterface::class       => 'EventManager',
                SharedEventManager::class          => 'SharedEventManager',
                SharedEventManagerInterface::class => 'SharedEventManager',
                ModuleManager::class               => 'ModuleManager',
                ServiceListener::class             => 'ServiceListener',
            ],
            'factories' => [
                //the merged module configuration, read off the module manager's config
                //listener once the modules are loaded (Laminas\Mvc\Service\ConfigFactory did
                //the same)
                'config'             => static function (ServiceManager $container): array {
                    /** @var ModuleManager $moduleManager */
                    $moduleManager = $container->get('ModuleManager');
                    $moduleManager->loadModules();
                    $listener = $moduleManager->getEvent()->getConfigListener();
                    if (! $listener instanceof ConfigListener) {
                        return [];
                    }
                    /** @var array<string, mixed> $merged */
                    $merged = $listener->getMergedConfig(false);

                    return $merged;
                },
                'SharedEventManager' => static fn (): SharedEventManager => new SharedEventManager(),
                'EventManager'       => static function (ServiceManager $container): EventManager {
                    /** @var SharedEventManager $shared */
                    $shared = $container->get('SharedEventManager');

                    return new EventManager($shared);
                },
                'ServiceListener'    => static fn (ServiceManager $container): ServiceListener
                    => new ServiceListener($container),
                'ModuleManager'      => static function (ServiceManager $container) use ($appConfig): ModuleManager {
                    $listenerOptions  = new ListenerOptions($appConfig['module_listener_options']);
                    $defaultListeners = new DefaultListenerAggregate($listenerOptions);

                    /** @var ServiceListener $serviceListener */
                    $serviceListener = $container->get('ServiceListener');
                    $serviceListener->addServiceManager(
                        $container,
                        'service_manager',
                        ServiceProviderInterface::class,
                        'getServiceConfig'
                    );
                    $serviceListener->addServiceManager(
                        'ViewHelperManager',
                        'view_helpers',
                        ViewHelperProviderInterface::class,
                        'getViewHelperConfig'
                    );

                    /** @var EventManager $events */
                    $events = $container->get('EventManager');
                    $defaultListeners->attach($events);
                    $serviceListener->attach($events);

                    $moduleEvent = new ModuleEvent();
                    $moduleEvent->setParam('ServiceManager', $container);

                    $moduleManager = new ModuleManager($appConfig['modules'], $events);
                    $moduleManager->setEvent($moduleEvent);

                    return $moduleManager;
                },
            ],
            'shared'    => [
                'EventManager' => false,
            ],
            //what ServiceManagerConfig registered: an EventManagerAware service built by
            //the container gets the container's event manager, shared manager attached
            'initializers' => [
                static function (ServiceManager $container, mixed $instance): void {
                    if (! $instance instanceof EventManagerAwareInterface) {
                        return;
                    }
                    $events = $instance->getEventManager();
                    if (
                        $events instanceof EventManagerInterface
                        && $events->getSharedManager() instanceof SharedEventManagerInterface
                    ) {
                        return;
                    }
                    /** @var EventManagerInterface $shared */
                    $shared = $container->get('EventManager');
                    $instance->setEventManager($shared);
                },
            ],
        ];
    }
}
