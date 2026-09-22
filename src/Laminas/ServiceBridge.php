<?php

declare(strict_types=1);

namespace App\Laminas;

use App\Services\Container;
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
 * Built the way bin/console builds it: configure a container, load the
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
 * never both, so at most one container is ever built per request.
 */
final class ServiceBridge implements LaminasServices
{
    private ?Container $services = null;

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

    /**
     * A bridge over a container somebody else built — the fuzz harness, which needs the
     * raw container to swap a session config in and attach a query profiler before
     * anything resolves.
     */
    public static function around(Container $services): self
    {
        $bridge           = new self([]);
        $bridge->services = $services;

        return $bridge;
    }

    private function services(): Container
    {
        //Config caches on: this is the per-request path, and merging every module's
        //config on every request is what the cache exists to avoid. Console runs and
        //tests build through ContainerFactory directly, with them off.
        return $this->services ??= ContainerFactory::build(
            $this->appConfig,
            true,
            $this->phraseFlush,
            $this->cacheFlushQueue
        );
    }
}
