<?php

declare(strict_types=1);

namespace SchoenstattTest\Unit;

use App\Laminas\ModuleLanguageDirectories;
use PHPUnit\Framework\TestCase;

use function chdir;
use function getcwd;

//test/bootstrap.php deliberately avoids vendor/autoload.php; the class under test needs
//nothing but laminas' ModuleManager type hint in a docblock, so requiring it is enough.
require_once __DIR__ . '/../../src/Laminas/ModuleLanguageDirectories.php';

/**
 * Where an exported translation catalog goes, which is one `glob()` and one filter.
 *
 * Small, and worth pinning because both halves are load-bearing and neither fails loudly:
 *
 * - **the module path**, because `TranslationsTable::writePhpTranslationArrays()` uses this
 *   map to decide between `module/<M>/language/` and `language/<M>/`, and both are
 *   registered as *read* paths — so a catalog written to the wrong one is still loaded and
 *   the page looks right. What breaks is the pair of copies: the console export rewrites
 *   one, a GUI or API write the other, and `language/*` patterns are registered last, so a
 *   translation deleted through the GUI goes on being served from the copy nothing
 *   rewrote. Measured 2026-09-08 — every module domain had two.
 * - **the loaded-modules filter**, because `module/` also holds directories for modules
 *   `config/modules.config.php` does not enable. Registering one would let a disabled
 *   module's stale export translate a live page.
 */
final class ModuleLanguageDirectoriesTest extends TestCase
{
    private string $cwd = '';

    protected function setUp(): void
    {
        //The class globs `module/*` relative to the working directory, which is what both
        //entry points chdir() to. A test has no such guarantee.
        $this->cwd = (string) getcwd();
        chdir(__DIR__ . '/../..');
    }

    protected function tearDown(): void
    {
        if ('' !== $this->cwd) {
            chdir($this->cwd);
        }
    }

    public function testALoadedModuleGetsItsOwnLanguageDirectory(): void
    {
        $modules = ModuleLanguageDirectories::forLoadedModules([
            'JTranslate' => 'irrelevant, only the key is read',
            'SionModel'  => 'irrelevant',
        ]);

        $this->assertSame(
            [
                'JTranslate' => getcwd() . '/module/JTranslate/language',
                'SionModel'  => getcwd() . '/module/SionModel/language',
            ],
            $modules
        );
    }

    /**
     * A module directory that is not loaded is left out — even though it exists on disk.
     *
     * Asserted with a real directory rather than an invented name, so that the test is
     * about the *filter* and not about `glob()`: `module/Books` is there in every checkout.
     */
    public function testAnUnloadedModuleDirectoryIsExcluded(): void
    {
        $this->assertDirectoryExists(getcwd() . '/module/Books');

        $modules = ModuleLanguageDirectories::forLoadedModules(['JTranslate' => 'loaded']);

        $this->assertArrayNotHasKey('Books', $modules);
        $this->assertArrayHasKey('JTranslate', $modules);
    }

    /** A name that is not a directory under module/ cannot conjure a path. */
    public function testANameWithNoDirectoryIsNotInvented(): void
    {
        $modules = ModuleLanguageDirectories::forLoadedModules([
            'Laminas\Form' => 'a vendor module, loaded, with no directory here',
        ]);

        $this->assertSame([], $modules);
    }
}
