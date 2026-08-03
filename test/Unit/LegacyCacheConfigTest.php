<?php

namespace SchoenstattTest\Unit;

use PHPUnit\Framework\TestCase;
use SionModel\Cache\LegacyCacheConfig;

require_once __DIR__ . '/../../module/SionModel/src/Cache/LegacyCacheConfig.php';

/**
 * Pins the laminas-cache 2→3 config translation to the four legacy shapes that
 * actually occur in this repo's tracked and untracked config files, so a
 * production cache.local.php written for StorageFactory::factory() keeps
 * working after the rung-4a bump.
 */
class LegacyCacheConfigTest extends TestCase
{
    /** The juser.global.php / jtranslate.global.php shape: ttl beside the adapter name. */
    public function testAdapterWithTtlBesideName(): void
    {
        $translated = LegacyCacheConfig::translate([
            'adapter' => [
                'name' => 'apcu',
                'ttl' => 86400,
                'options' => ['namespace' => 'juser'],
            ],
        ]);

        $this->assertSame([
            'adapter' => 'apcu',
            'options' => ['namespace' => 'juser', 'ttl' => 86400],
            'plugins' => [],
        ], $translated);
    }

    /** The cache.local.php shape: options nested under adapter, plugin list of name/options entries. */
    public function testAdapterWithNestedOptionsAndPluginEntries(): void
    {
        $translated = LegacyCacheConfig::translate([
            'adapter' => [
                'name' => 'filesystem',
                'options' => ['ttl' => 86400],
            ],
            'plugins' => [
                ['name' => 'serializer', 'options' => ['serializer' => 'json']],
                ['name' => 'exception_handler', 'options' => ['throw_exceptions' => true]],
            ],
        ]);

        $this->assertSame([
            'adapter' => 'filesystem',
            'options' => ['ttl' => 86400],
            'plugins' => [
                ['name' => 'serializer', 'options' => ['serializer' => 'json']],
                ['name' => 'exception_handler', 'options' => ['throw_exceptions' => true]],
            ],
        ], $translated);
    }

    /** The commented-out SionModel shape: bare plugin-name strings. */
    public function testBarePluginNames(): void
    {
        $translated = LegacyCacheConfig::translate([
            'adapter' => ['name' => 'filesystem'],
            'plugins' => ['serializer'],
        ]);

        $this->assertSame(
            [['name' => 'serializer']],
            $translated['plugins']
        );
    }

    /** The cache.local.php.dist comment shape: plugin options keyed by plugin name. */
    public function testPluginOptionsKeyedByName(): void
    {
        $translated = LegacyCacheConfig::translate([
            'adapter' => ['name' => 'filesystem'],
            'plugins' => ['exception_handler' => ['throw_exceptions' => false]],
        ]);

        $this->assertSame(
            [['name' => 'exception_handler', 'options' => ['throw_exceptions' => false]]],
            $translated['plugins']
        );
    }

    /** Config already in the new shape passes through unchanged. */
    public function testNewShapePassesThrough(): void
    {
        $translated = LegacyCacheConfig::translate([
            'adapter' => 'apcu',
            'options' => ['ttl' => 300],
        ]);

        $this->assertSame([
            'adapter' => 'apcu',
            'options' => ['ttl' => 300],
            'plugins' => [],
        ], $translated);
    }
}
