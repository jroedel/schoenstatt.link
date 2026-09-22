<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Laminas\ServiceBridge;
use SionModel\Db\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionObject;
use SionModel\Cache\CacheFlushQueue;
use Schoenstatt\Model\SchoenstattTable;
use SionModel\Db\Model\SionTable;
use Throwable;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * What has to stay true now that SionTable's constructor no longer takes a container.
 *
 * It used to pull six services out of one: the entities service, the config, the persistent
 * cache, the MVC `Application` (for its event manager), the logger and the problem service.
 * That made the wiring automatic and unskippable. Explicit collaborators are better in every
 * other way, but they move three optional ones into each factory — so **a factory that
 * forgets `SionTableWiring::apply()` produces a table that reads and writes perfectly well
 * and simply never caches**. Nothing throws, no page breaks, and the only symptom is that
 * the site is slower than it was.
 *
 * These tests sweep every SionTable the application declares rather than naming any, so a
 * factory added later is covered without editing this file.
 *
 * Run in the capsule: php composer.phar integration
 */
final class SionTableWiringCompletenessTest extends TestCase
{
    private static ?ServiceBridge $bridge = null;

    private static ?CacheFlushQueue $queue = null;

    private function bridge(): ServiceBridge
    {
        if (null !== self::$bridge) {
            return self::$bridge;
        }

        $appConfig = require __DIR__ . '/../../config/application.config.php';
        $appConfig['module_listener_options']['config_cache_enabled']     = false;
        $appConfig['module_listener_options']['module_map_cache_enabled'] = false;

        //the end-of-request write queue a request registers, so that enrolment can be seen
        self::$queue = new CacheFlushQueue();

        return self::$bridge = new ServiceBridge($appConfig, null, self::$queue);
    }

    private function requireDatabase(): void
    {
        if (! is_readable(__DIR__ . '/../../config/autoload/local.php')) {
            self::markTestSkipped('no config/autoload/local.php, so no database configuration');
        }
        try {
            $adapter = $this->bridge()->get(Connection::class);
            $adapter->select('SELECT 1');
        } catch (Throwable $e) {
            self::markTestSkipped('no database: ' . $e->getMessage());
        }
    }

    /**
     * Every SionTable the application declares, from the entity specifications rather than a
     * hardcoded list — `sion_model_class` is what names them, and it is the same key
     * SionModel itself reads.
     *
     * @return array<string, SionTable>
     */
    private function tables(): array
    {
        $bridge = $this->bridge();
        $config = $bridge->get('SionModel\Config');

        $tables = [];
        foreach ($config['entities'] ?? [] as $spec) {
            $class = $spec['sion_model_class'] ?? null;
            if (! is_string($class) || isset($tables[$class]) || ! $bridge->has($class)) {
                continue;
            }
            $table = $bridge->get($class);
            if ($table instanceof SionTable) {
                $tables[$class] = $table;
            }
        }

        self::assertNotEmpty($tables, 'the entity specifications should name at least one SionTable');

        return $tables;
    }

    /**
     * The failure this whole change risks, and the reason it is worth a test rather than a
     * review: a table with no cache works. It answers every query correctly and is merely
     * slow, so a forgotten `SionTableWiring::apply()` ships and nobody notices for months.
     */
    public function testEveryDeclaredTableHasItsPersistentCache(): void
    {
        $this->requireDatabase();

        foreach ($this->tables() as $class => $table) {
            self::assertNotNull(
                $table->getPersistentCache(),
                "$class has no persistent cache — its factory is probably missing "
                . 'SionTableWiring::apply()'
            );
        }
    }

    /**
     * The other half of the same wiring. A cache whose write queue is never flushed collects
     * entries all request long and stores none of them, which is indistinguishable from a
     * cache that works, from anywhere except the cache itself.
     */
    public function testEveryDeclaredTableFlushesItsCacheQueue(): void
    {
        $this->requireDatabase();

        $tables = $this->tables();
        self::assertNotNull(self::$queue);
        foreach ($tables as $class => $table) {
            self::assertTrue(
                self::$queue->contains($table),
                "$class never enrolled in the CacheFlushQueue, so nothing it caches is ever written"
            );
        }
    }

    /**
     * The property the change exists to create. A container reachable from a table is what
     * let the data layer resolve `Application` and reach laminas-mvc; if one comes back,
     * this fails before it can grow call sites.
     */
    public function testNoTableHoldsAContainer(): void
    {
        $this->requireDatabase();

        foreach ($this->tables() as $class => $table) {
            foreach ((new ReflectionObject($table))->getProperties() as $property) {
                $value = $property->isInitialized($table) ? $property->getValue($table) : null;

                self::assertNotInstanceOf(
                    ContainerInterface::class,
                    $value,
                    "$class holds a container in \$" . $property->getName()
                    . ' — collaborators belong in the constructor, framework lookups in the factory'
                );
            }
        }
    }

    /**
     * `SchoenstattTable` loaded its country-name translations **in the constructor**, from
     * the persistent cache. The cache is attached after construction now, so that read would
     * miss every single time and the accompanying write would be discarded — a full ICU pass
     * over five locales on every build of the table, with nothing cached to show for it and
     * no error anywhere. It is lazy instead; this asserts the caching actually happens.
     */
    public function testSchoenstattCountryTranslationsAreCachedOnFirstUse(): void
    {
        $this->requireDatabase();

        $table  = $this->bridge()->get(SchoenstattTable::class);
        $method = (new ReflectionObject($table))->getMethod('getCountryNameTranslations');

        $first = $method->invoke($table);
        self::assertNotEmpty($first, 'country name translations should resolve');

        //Same instance answers from its own property; the point of the assertion below is
        //that the value also reached the shared cache, which is what a *second request*
        //would read and what the constructor-time version silently stopped doing.
        $cached = $table->fetchCachedEntityObjects('country-name-translations');
        self::assertSame(
            $first,
            $cached,
            'the translations were computed but never cached — the lazy getter must run '
            . 'after SionTableWiring has attached the cache, not before'
        );
    }
}
