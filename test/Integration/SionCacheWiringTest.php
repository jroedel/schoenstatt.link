<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Laminas\ServiceBridge;
use JUser\Model\UserTable;
use SionModel\Cache\ApcuStorage;
use Laminas\Db\Adapter\Adapter;
use PHPUnit\Framework\TestCase;
use SionModel\Cache\CacheFlushQueue;
use ReflectionProperty;
use Throwable;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * How a SionTable ends up attached to the cache and to the request lifecycle.
 *
 * Both halves went wrong at once and neither announced itself. SionTable's
 * constructor used to inject the application-wide `SionModel\PersistentCache` and
 * attach its end-of-request writer; JUser's factory then swapped in its own
 * `JUser\Cache` — a different APCu namespace with a different TTL — and attached
 * the writer a *second* time. The visible symptom was every JUser cache key appearing
 * twice in the application log, "Writing cache" for the same key back to back. The
 * invisible one was a dependency map read from one namespace vouching for keys stored
 * in another. The end-of-request writer is `SionModel\Cache\CacheFlushQueue` now, the
 * only flush point there is.
 *
 * The unit suite pins what the trait does with a storage it is handed
 * (test/Unit/SionCacheDependencyMapTest). This pins what the container actually
 * hands it, which no amount of unit testing can reach.
 *
 * Run in the capsule: php composer.phar integration
 */
final class SionCacheWiringTest extends TestCase
{
    private static ?ServiceBridge $bridge = null;
    private static ?ServiceBridge $queueBridge = null;
    private static ?CacheFlushQueue $queue = null;

    /**
     * The laminas services, built the way bin/console builds them — no
     * bootstrap(), so no MVC listeners, no route stack, no dispatch.
     *
     * Config caching is off here for the same reason as in the sibling parity
     * test: a test run must not write data/config/.
     */
    private function bridge(): ServiceBridge
    {
        if (null !== self::$bridge) {
            return self::$bridge;
        }

        $appConfig = require __DIR__ . '/../../config/application.config.php';
        $appConfig['module_listener_options']['config_cache_enabled']     = false;
        $appConfig['module_listener_options']['module_map_cache_enabled'] = false;

        return self::$bridge = new ServiceBridge($appConfig);
    }

    /**
     * Building a UserTable builds a database adapter, which CI has no
     * configuration for. Checks local.php before asking the container, because
     * asking without it raises a warning and failOnWarning makes that a failure
     * no later catch can undo.
     */
    private function requireDatabase(): void
    {
        if (! is_readable(__DIR__ . '/../../config/autoload/local.php')) {
            self::markTestSkipped('no config/autoload/local.php, so no database configuration');
        }
        try {
            $adapter = $this->bridge()->get(Adapter::class);
            $adapter->getDriver()->getConnection()->connect();
        } catch (Throwable $e) {
            self::markTestSkipped('no database: ' . $e->getMessage());
        }
    }

    /**
     * A second bridge, given the end-of-request queue a Symfony request supplies.
     *
     * Separate from bridge() rather than a parameter on it because the two answer
     * different questions and each caches its ServiceManager: this one is "what a
     * request builds", the other is "what a console process builds".
     */
    private function queueBridge(): ServiceBridge
    {
        if (null !== self::$queueBridge) {
            return self::$queueBridge;
        }

        $appConfig = require __DIR__ . '/../../config/application.config.php';
        $appConfig['module_listener_options']['config_cache_enabled']     = false;
        $appConfig['module_listener_options']['module_map_cache_enabled'] = false;

        self::$queue = new CacheFlushQueue();

        return self::$queueBridge = new ServiceBridge($appConfig, null, self::$queue);
    }

    /**
     * The bug the queue exists for: a Symfony-served route reached no laminas
     * `MvcEvent::EVENT_FINISH`, so the listener that wrote the persistent cache never
     * ran and every ported page queued its items and threw them away — measured at 0
     * writes and 0 hits across 49 requests, with nothing logged and nothing failing. Registration happens in the factory, so this asserts the
     * property at the only place it can be seen before a request ends.
     */
    public function testATableBuiltForASymfonyRequestEnrolsInTheFlushQueue(): void
    {
        $this->requireDatabase();
        $bridge = $this->queueBridge();
        if (! $bridge->has(UserTable::class)) {
            $this->markTestSkipped('JUser is not enabled in this configuration');
        }

        $bridge->get(UserTable::class);

        $this->assertNotNull(self::$queue);
        $this->assertFalse(
            self::$queue->isEmpty(),
            'building a SionTable must enrol it for the end-of-request cache write'
        );
    }

    /**
     * JUser stores in its own namespace with its own TTL, so the table has to end
     * up holding `JUser\Cache` and not the application-wide storage its parent
     * constructor injected first. Asserted through the namespace rather than
     * object identity, because that is the property the cache keys depend on.
     */
    public function testTheUserTableCachesInTheJUserNamespace(): void
    {
        $this->requireDatabase();
        $bridge = $this->bridge();
        if (! $bridge->has(UserTable::class)) {
            $this->markTestSkipped('JUser is not enabled in this configuration');
        }

        $table = $bridge->get(UserTable::class);
        $cache = $table->getPersistentCache();

        $this->assertInstanceOf(ApcuStorage::class, $cache);
        $this->assertSame(
            'juser',
            $cache->namespace(),
            'UserTable must end up on JUser\Cache, not the SionModel-wide storage'
        );
    }

    /**
     * The map the table carries has to describe the storage it is holding. When
     * the factory replaced the storage, anything read from the previous one
     * named keys in a namespace this instance can no longer read or remove — so
     * the swap must leave the map empty rather than misleading.
     */
    public function testSwappingTheStorageLeavesNoMapFromTheOldNamespace(): void
    {
        $this->requireDatabase();
        $bridge = $this->bridge();
        if (! $bridge->has(UserTable::class)) {
            $this->markTestSkipped('JUser is not enabled in this configuration');
        }

        $table = $bridge->get(UserTable::class);

        $property = new ReflectionProperty($table, 'cacheDependencies');
        $map = $property->getValue($table);

        $this->assertIsArray($map);
        foreach (array_keys($map) as $key) {
            $this->assertStringStartsWith(
                'jusermodelusertable-',
                (string) $key,
                'the map must only name keys this table stores in its own namespace'
            );
        }
    }
}
