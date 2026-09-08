<?php

declare(strict_types=1);

namespace App\Laminas;

use JTranslate\Model\TranslationsTable;
use Laminas\Mvc\I18n\Translator as MvcI18nTranslator;
use Laminas\Mvc\Service\ServiceManagerConfig;
use Laminas\ServiceManager\ServiceManager;
use SionModel\Cache\CacheFlushQueue;

/**
 * Read access to the laminas service manager from a Symfony-served route.
 *
 * A ported route does not go through App\Http\LegacyBridge, so nothing has built
 * a laminas application for it: no merged config, no services, no database
 * adapter. Most of what gets ported still needs some of that — the maintenance
 * endpoints need `sion_model.api_keys` and the persistent cache — and rewriting
 * each dependency at the moment its route moves would turn one migration into
 * many. This class is the seam that defers that choice.
 *
 * Built the way bin/console builds it: configure a ServiceManager, load the
 * modules, and **never** call bootstrap(). Bootstrapping is what attaches the MVC
 * listeners, resolves a route and dispatches; none of that is wanted here, and
 * running it would put a second laminas application in front of a request Symfony
 * has already routed.
 *
 * Two things it deliberately does *not* copy from
 * test/Integration/AclGuardRouteDriftTest, which uses the same technique:
 *
 * 1. The config and module-map caches stay **on**. The test disables them so a
 *    CI runner never writes data/config/; at runtime those caches are the whole
 *    reason module loading is affordable per request.
 * 2. Nothing is built in the constructor. This is the property App\Container's
 *    docblock is about: a Symfony-served route costs no module loading and no
 *    config merge. Handing a controller a ServiceBridge keeps that true —
 *    /_health still touches none of this, and a ported controller pays only if it
 *    actually asks. Which is also why the bridge is not itself the container:
 *    App\Container must be able to resolve LegacyBridge, the thing that *builds*
 *    the laminas application, without any laminas involvement at all.
 *
 * There is no sharing with LegacyBridge's application, and there does not need to
 * be: a request is either routed to a ported controller or handed to the bridge,
 * never both, so at most one ServiceManager is ever built per request.
 */
final class ServiceBridge implements LaminasServices
{
    private ?ServiceManager $services = null;

    /**
     * @param array<string, mixed> $appConfig the merged config/application.config.php
     * @param PhraseFlush|null $phraseFlush registered as a service so
     *        TranslatorConfigurator can arm it when it builds the translator. Null
     *        outside a request — a test or a console process has no end-of-request
     *        hook to flush from, and TranslatorConfigurator skips the arming.
     * @param CacheFlushQueue|null $cacheFlushQueue registered as a service so every
     *        SionTable factory can enrol its table for the end-of-request cache
     *        write. Null for the same reason and with the same effect: without it
     *        the tables fall back to the `MvcEvent::EVENT_FINISH` listener, which
     *        outside a laminas request simply never fires. A console process must
     *        not be given one — an APCu segment belongs to the SAPI that created
     *        it, so a CLI write lands where no web request can read it.
     */
    public function __construct(
        private readonly array $appConfig,
        private readonly ?PhraseFlush $phraseFlush = null,
        private readonly ?CacheFlushQueue $cacheFlushQueue = null
    ) {
    }

    /**
     * The merged module configuration — the same array `$container->get('config')`
     * returns inside the laminas application.
     *
     * @return array<string, mixed>
     */
    public function config(): array
    {
        /** @var array<string, mixed> $config */
        $config = $this->services()->get('config');

        return $config;
    }

    public function has(string $id): bool
    {
        return $this->services()->has($id);
    }

    public function get(string $id): mixed
    {
        return $this->services()->get($id);
    }

    private function services(): ServiceManager
    {
        if (null !== $this->services) {
            return $this->services;
        }

        $services = new ServiceManager();
        (new ServiceManagerConfig($this->serviceManagerConfig()))->configureServiceManager($services);
        $services->setService('ApplicationConfig', $this->appConfig);
        $services->get('ModuleManager')->loadModules();

        //After loadModules(), because MvcTranslator is defined by the merged module
        //config and there is nothing to decorate before that. Before anything asks for
        //it, which nothing has yet — the delegator would throw if the instance already
        //existed.
        //
        //This is the translator half of JTranslate\Module::onBootstrap(), which a
        //Symfony-served route never runs: without it the translator has no sources and
        //every translated string on every ported page falls back to its English source.
        //Measured on production. See App\Laminas\TranslatorConfigurator.
        //Keyed on the **canonical** class, not on `MvcTranslator`: that name is an
        //alias for it, and laminas-servicemanager resolves an alias before it looks for
        //delegators, so one registered under the alias never runs. Measured — the first
        //attempt used 'MvcTranslator' and silently did nothing. Registering the class
        //covers every alias pointing at it, `MvcTranslator` and `jtranslate_translator`
        //included.
        //`TranslationsTable` is decorated too, and for a reason the translator delegator
        //cannot cover: `setUserModules()` decides **where an exported catalog is written**
        //— `module/<M>/language` for a loaded module, `language/<M>` for anything else —
        //and until 2026-09-08 the only thing that called it was the delegator above.
        //
        //So the map was set exactly when something asked for a *translator*, which is not
        //the same as when something asks for the *table*. The translation GUI's save is the
        //case that breaks: a successful write redirects, so nothing renders, nothing builds
        //a translator, and `writePhpTranslationArrays()` ran with an empty map — putting
        //every module domain's catalog under `language/<M>/` instead of in the module.
        //
        //It reads as harmless because both directories are registered as *read* paths, and
        //`language/*` is registered last so the misplaced file even wins. The hazard is the
        //pair: `bin/console jtranslate:export-catalogs` writes the module copy, the GUI
        //wrote the other, and a phrase deleted through the GUI would go on being served
        //from whichever copy the console did not rewrite.
        $services->configure([
            'delegators' => [
                MvcI18nTranslator::class => [TranslatorConfigurator::class],
                TranslationsTable::class => [TranslationsTableConfigurator::class],
            ],
        ]);

        //The other half of onBootstrap's translator wiring: laminas writes discovered
        //phrases on MvcEvent::EVENT_FINISH, which a Symfony-served route never
        //reaches. Registered as an instance rather than a factory because the object
        //is the Kernel's — its listener has to flush the same one the delegator armed.
        if (null !== $this->phraseFlush) {
            $services->setService(PhraseFlush::class, $this->phraseFlush);
        }

        //Same problem, same shape of fix, a different subsystem: SionTable also
        //defers its writes to MvcEvent::EVENT_FINISH. Registered as an instance
        //because SionTableWiring enrols tables into *this* object as it builds
        //them, and App\Http\SionCacheFlushListener has to drain the same one.
        if (null !== $this->cacheFlushQueue) {
            $services->setService(CacheFlushQueue::class, $this->cacheFlushQueue);
        }

        return $this->services = $services;
    }

    /**
     * @return array<string, mixed>
     */
    private function serviceManagerConfig(): array
    {
        $config = $this->appConfig['service_manager'] ?? [];

        return is_array($config) ? $config : [];
    }
}
