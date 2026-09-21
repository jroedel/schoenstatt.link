<?php

declare(strict_types=1);

namespace SchoenstattTest\Unit;

use App\Modules\ModuleConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Modules/ModuleConfig.php';

/**
 * The two rules `App\Modules\ModuleConfig` took over from laminas-modulemanager on
 * 2026-09-21, both of which are silent when wrong.
 *
 * The merge rule is `Laminas\Stdlib\ArrayUtils::merge()`. The clause that matters is the
 * integer one — a list under a key two configs both declare **appends** rather than
 * replaces — and it is what makes `SionModel`'s and `Books`' entries in a shared key
 * combine instead of one erasing the other.
 *
 * The glob order **is** the configuration precedence: `local` must land after `global`,
 * or every `*.local.php` override in `config/autoload/` stops overriding. The cases below
 * were taken from `Laminas\Stdlib\Glob`'s brace fallback, and at the cutover the whole
 * real pattern was compared against it directly with an identical result.
 */
final class ModuleConfigTest extends TestCase
{
    /** @return iterable<string, array{array<array-key, mixed>, array<array-key, mixed>, array<array-key, mixed>}> */
    public static function mergeCases(): iterable
    {
        yield 'a key only one side has is kept' => [
            ['a' => 1],
            ['b' => 2],
            ['a' => 1, 'b' => 2],
        ];

        yield 'a scalar under a shared string key is overwritten' => [
            ['a' => 1],
            ['a' => 2],
            ['a' => 2],
        ];

        yield 'arrays under a shared string key recurse' => [
            ['factories' => ['x' => 'X', 'y' => 'Y']],
            ['factories' => ['y' => 'Y2', 'z' => 'Z']],
            ['factories' => ['x' => 'X', 'y' => 'Y2', 'z' => 'Z']],
        ];

        //The clause everything turns on.
        yield 'lists append rather than replace' => [
            ['listeners' => ['first']],
            ['listeners' => ['second']],
            ['listeners' => ['first', 'second']],
        ];

        yield 'appending renumbers rather than colliding' => [
            [['a'], ['b']],
            [['c']],
            [['a'], ['b'], ['c']],
        ];

        yield 'an array replaces a scalar' => [
            ['a' => 1],
            ['a' => ['deep' => true]],
            ['a' => ['deep' => true]],
        ];

        yield 'a scalar replaces an array' => [
            ['a' => ['deep' => true]],
            ['a' => 1],
            ['a' => 1],
        ];

        //isset() would answer false here and treat the key as absent. It makes no
        //difference to the result, but ArrayUtils uses array_key_exists and so does this.
        yield 'an explicit null overwrites' => [
            ['a' => 'set'],
            ['a' => null],
            ['a' => null],
        ];

        yield 'nesting is unbounded' => [
            ['one' => ['two' => ['three' => ['keep' => 1, 'replace' => 1]]]],
            ['one' => ['two' => ['three' => ['replace' => 2]]]],
            ['one' => ['two' => ['three' => ['keep' => 1, 'replace' => 2]]]],
        ];

        yield 'an empty right-hand side changes nothing' => [
            ['a' => ['b' => 1]],
            [],
            ['a' => ['b' => 1]],
        ];
    }

    /**
     * @param array<array-key, mixed> $a
     * @param array<array-key, mixed> $b
     * @param array<array-key, mixed> $expected
     */
    #[DataProvider('mergeCases')]
    public function testTheMergeRuleIsArrayUtilsMerge(array $a, array $b, array $expected): void
    {
        self::assertSame($expected, ModuleConfig::merge($a, $b));
    }

    /**
     * The real pattern from `config/application.config.php`, over a directory built to
     * look like `config/autoload/`.
     */
    public function testTheGlobOrderIsGlobalThenLocal(): void
    {
        $directory = $this->makeDirectory([
            'global.php',
            'acl.global.php',
            'sionmodel.global.php',
            'local.php',
            'cache.local.php',
            //Neither arm matches this, exactly as in the real directory.
            'local.php.dist',
            'README.md',
        ]);

        $found = ModuleConfig::expand($directory . '/{{,*.}global,{,*.}local}.php');

        self::assertSame(
            [
                $directory . '/global.php',
                $directory . '/acl.global.php',
                $directory . '/sionmodel.global.php',
                $directory . '/local.php',
                $directory . '/cache.local.php',
            ],
            $found,
            'the alternatives must come out in brace order, each sorted — a *.local.php '
            . 'file that lands before global.php stops overriding anything'
        );
    }

    public function testAPatternWithNoBracesIsAPlainGlob(): void
    {
        $directory = $this->makeDirectory(['one.php', 'two.php', 'three.txt']);

        self::assertSame(
            [$directory . '/one.php', $directory . '/two.php'],
            ModuleConfig::expand($directory . '/*.php')
        );
    }

    public function testAMatchlessPatternIsEmptyRatherThanFalse(): void
    {
        self::assertSame([], ModuleConfig::expand($this->makeDirectory([]) . '/{a,b}.php'));
    }

    /** Both cache names follow laminas-modulemanager's, so an existing tree's file is the same file. */
    public function testTheCacheFileNamesAreTheOnesLaminasWrote(): void
    {
        $options = [
            'cache_dir'            => 'data/config/',
            'config_cache_key'     => 'sch_config',
            'module_map_cache_key' => 'sch_module_map',
        ];

        self::assertSame('data/config/module-config-cache.sch_config.php', ModuleConfig::cacheFile($options));
        self::assertSame(
            [
                'data/config/module-config-cache.sch_config.php',
                'data/config/module-classmap-cache.sch_module_map.php',
            ],
            ModuleConfig::cacheFiles($options)
        );
    }

    /**
     * With no `cache_dir`, laminas built its names against an empty directory — i.e. paths
     * at the filesystem root. Nothing was ever cached there, so there is nothing to clear
     * and certainly nothing to unlink outside the application.
     */
    public function testNoCacheDirectoryMeansNoCacheFiles(): void
    {
        self::assertNull(ModuleConfig::cacheFile([]));
        self::assertSame([], ModuleConfig::cacheFiles([]));
    }

    /** @param list<string> $names */
    private function makeDirectory(array $names): string
    {
        $directory = sys_get_temp_dir() . '/module-config-' . bin2hex(random_bytes(6));
        mkdir($directory);
        foreach ($names as $name) {
            touch($directory . '/' . $name);
        }

        return $directory;
    }
}
