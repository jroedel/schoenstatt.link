<?php

declare(strict_types=1);

namespace App\Laminas;

use Laminas\Mvc\Service\ServiceManagerConfig;
use Laminas\ServiceManager\ServiceManager;

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
final class ServiceBridge
{
    private ?ServiceManager $services = null;

    /** @param array<string, mixed> $appConfig the merged config/application.config.php */
    public function __construct(private readonly array $appConfig)
    {
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
