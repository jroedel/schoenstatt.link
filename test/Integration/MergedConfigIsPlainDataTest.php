<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use App\Modules\ModuleConfig;
use PHPUnit\Framework\TestCase;

use function is_array;
use function is_object;
use function var_export;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * The merged module configuration must hold nothing but arrays and scalars.
 *
 * `App\Modules\ModuleConfig` caches it with `var_export()`, which can write back an array
 * of scalars and nothing else. Until 2026-09-21 three module configs held **closures** —
 * `JUser\Host\SessionInterface`, `JTranslate\Cache` and `SionModel\Cache\EntityChangeListeners`
 * — and writing those out took `brick/varexporter` and its `nikic/php-parser`, which came
 * into this application through `laminas/laminas-modulemanager` and were the reason the
 * package could not simply be replaced by the thirty lines that merge the configs. All
 * three are now named factories in `module/Application/config/module.config.php`.
 *
 * Writing a closure back into a module config is an easy and natural thing to do, and its
 * consequence is remote from its cause: nothing fails in development, where the config
 * cache is off, and the first thing that happens in production is a failed cache write.
 * So the contract is asserted here rather than left to the next deploy.
 */
final class MergedConfigIsPlainDataTest extends TestCase
{
    /** @return array<string, mixed> */
    private function merged(): array
    {
        $appConfig = require __DIR__ . '/../../config/application.config.php';

        //No caching: this must read the configs as they are on disk, not a file some
        //earlier run left behind. It is also what keeps the test runnable on a CI box
        //with no writable data/config.
        return ModuleConfig::fromApplicationConfig($appConfig, false)->merged();
    }

    public function testTheMergedConfigContainsNoObjects(): void
    {
        $found = [];
        $walk  = static function (array $config, string $path) use (&$walk, &$found): void {
            foreach ($config as $key => $value) {
                if (is_array($value)) {
                    $walk($value, $path . '.' . $key);
                } elseif (is_object($value)) {
                    $found[] = $path . '.' . $key . ' (' . $value::class . ')';
                }
            }
        };
        $walk($this->merged(), 'config');

        self::assertSame(
            [],
            $found,
            "a module config holds an object, so the merged configuration can no longer be\n"
            . "cached by var_export() and production's first request will fail to write it.\n"
            . 'Register a named factory class instead — module/Application/src/Service/ has '
            . 'three written for exactly this reason.'
        );
    }

    /** The property that matters, asserted directly rather than inferred from the one above. */
    public function testTheMergedConfigSurvivesAVarExportRoundTrip(): void
    {
        $merged = $this->merged();

        /** @var mixed $restored */
        $restored = eval('return ' . var_export($merged, true) . ';');

        self::assertSame($merged, $restored);
    }
}
