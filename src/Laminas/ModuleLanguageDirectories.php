<?php

declare(strict_types=1);

namespace App\Laminas;

use Laminas\ModuleManager\ModuleManager;
use Psr\Container\ContainerInterface;

use function array_key_exists;
use function getcwd;
use function glob;
use function is_array;
use function str_replace;

use const GLOB_ONLYDIR;

/**
 * `module/<M>/language` for every *loaded* module, keyed by module name.
 *
 * One computation with two consumers, and they are the two halves of the same fact:
 * `JTranslate\Model\TranslationsTable::setUserModules()` uses it to decide **where a text
 * domain's catalog is written**, and `App\Laminas\TranslatorConfigurator` uses it to decide
 * where catalogs are **read** from. A reader and a writer that disagreed about that would
 * produce a file nothing loads — see the class docblock on `TranslationsTable`'s export.
 *
 * ## The loaded-modules filter matters
 *
 * `module/` also holds directories for modules `config/modules.config.php` does not enable.
 * Registering a read pattern for one would let a disabled module's stale export translate a
 * live page; writing into one would put a live catalog somewhere nothing looks.
 *
 * `getcwd()` is safe here: both entry points chdir() to the project root
 * (`public/index.php`, `bin/console`).
 */
final class ModuleLanguageDirectories
{
    /** @return array<string, string> */
    public static function forContainer(ContainerInterface $container): array
    {
        /** @var ModuleManager $manager */
        $manager = $container->get(ModuleManager::class);

        return self::forLoadedModules($manager->getLoadedModules());
    }

    /**
     * @param array<string, mixed> $loadedModules keyed by module name, as
     *        `ModuleManager::getLoadedModules()` answers
     * @return array<string, string>
     */
    public static function forLoadedModules(array $loadedModules): array
    {
        $found = glob('module/*', GLOB_ONLYDIR);
        if (! is_array($found)) {
            return [];
        }

        $modules = [];
        foreach ($found as $dir) {
            $module = str_replace('module/', '', $dir);
            if (array_key_exists($module, $loadedModules)) {
                $modules[$module] = getcwd() . '/module/' . $module . '/language';
            }
        }

        return $modules;
    }
}
