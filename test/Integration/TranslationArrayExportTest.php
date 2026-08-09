<?php

namespace SchoenstattTest\Integration;

use JTranslate\Model\TranslationsTable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_merge;
use function glob;
use ReflectionClass;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Proves that TranslationsTable's own array exporter reproduces the format
 * Laminas\Code\Generator\ValueGenerator used to emit.
 *
 * writePhpTranslationArrays() imported laminas-code, but laminas-code is not
 * installed — so the JTranslate admin action that rewrites the language files
 * fatalled with "Class ...ValueGenerator not found" the moment it was reached.
 * Rather than add the dependency, the generator was replaced with a local
 * exporter (docs/BACKLOG.md, PHPStan repair).
 *
 * The check is a round-trip: read the array back out of a `.lang.php`, re-export
 * it, and require the result to match the file byte for byte. Files the old
 * generator produced are the strongest available evidence of fidelity, and matching
 * them guarantees regenerating the catalogs yields no spurious diff.
 *
 * ## The corpus is a fixture now, not the live catalogs
 *
 * This used to glob `module/*​/language/` and compare against the 24 catalogs
 * committed there. That worked only by accident: those files were doing two
 * unrelated jobs at once — build output the site renders from, and frozen evidence
 * of the old generator's format.
 *
 * When the catalogs stopped being tracked both jobs vanished together. A fresh
 * checkout had none, the data provider returned an empty set, and PHPUnit errored —
 * correctly, because the test's premise was gone. `fixtures/lang-export/` holds three
 * of those files verbatim so the guarantee survives independently of whether anyone
 * has run `jtranslate:export-catalogs`; see the README beside them.
 *
 * Any catalogs that *do* exist on disk are still swept in on top, so a local run also
 * checks the real corpus. That half is a bonus and is empty on CI.
 *
 * Needs vendor/ for autoloading, so it runs in the capsule:
 * php composer.phar integration
 */
class TranslationArrayExportTest extends TestCase
{
    /**
     * The fixture corpus, plus any live catalogs this checkout happens to have.
     *
     * The fixtures come first and are never empty, which is the point: a data provider
     * that yields nothing is a PHPUnit *error*, not a skipped test, and it takes the
     * whole class down. That is exactly what happened when the catalogs stopped being
     * tracked.
     *
     * @return array<string, array{string}>
     */
    public static function languageFiles(): array
    {
        $root = dirname(__DIR__, 2);

        $files = glob(__DIR__ . '/fixtures/lang-export/*.lang.php') ?: [];
        //Whatever the working tree has, if anything. Gitignored and generated, so
        //present in a developer's capsule and absent on CI.
        $files = array_merge($files, glob($root . '/module/*/language/*.lang.php') ?: []);

        $cases = [];
        foreach ($files as $file) {
            $cases[substr($file, strlen($root) + 1)] = [$file];
        }

        return $cases;
    }

    /**
     * @return callable(array<array-key, mixed>): string
     */
    private function exporter(): callable
    {
        // The table's constructor wants a database adapter; the exporter is a
        // pure function of its argument, so build the object without running it.
        $table  = (new ReflectionClass(TranslationsTable::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(TranslationsTable::class, 'exportArray');

        return static fn (array $translations): string => $method->invoke($table, $translations, 1);
    }

    /**
     * Trailing newlines are compared loosely. The original 24-file corpus disagreed
     * with itself — 12 of them ended without one — so no single expectation could
     * match them all. The exporter emits one, which is both POSIX-correct and what
     * FileGenerator did, and everything up to it must be identical.
     */
    #[DataProvider('languageFiles')]
    public function testLanguageFilesRoundTripExactly(string $file): void
    {
        $translations = include $file;
        self::assertIsArray($translations, "$file should return an array");

        $expected = file_get_contents($file);
        $actual   = "<?php\n\nreturn " . ($this->exporter())($translations) . ";\n";

        self::assertSame(
            rtrim($expected, "\n"),
            rtrim($actual, "\n"),
            "re-exporting $file changed it"
        );
        self::assertStringEndsWith("];\n", $actual);
    }

    /**
     * The shapes the round-trip corpus may not happen to contain.
     */
    public function testEmptyArray(): void
    {
        self::assertSame('[]', ($this->exporter())([]));
    }

    public function testSequentialIntegerKeysAreWrittenPositionally(): void
    {
        self::assertSame(
            "[\n    'a',\n    'b',\n]",
            ($this->exporter())(['a', 'b'])
        );
    }

    public function testNonSequentialIntegerKeysKeepTheirKey(): void
    {
        self::assertSame(
            "[\n    2007 => '2007',\n]",
            ($this->exporter())([2007 => '2007'])
        );
    }

    public function testQuotesAndBackslashesAreEscaped(): void
    {
        self::assertSame(
            "[\n    'it\\'s' => 'a\\\\b',\n]",
            ($this->exporter())(["it's" => 'a\\b'])
        );
    }

    /**
     * Multi-line phrases are stored literally, as the committed files show.
     */
    public function testNewlinesAreKeptLiteral(): void
    {
        self::assertSame(
            "[\n    'one\ntwo' => 'x',\n]",
            ($this->exporter())(["one\ntwo" => 'x'])
        );
    }

    public function testNestedArraysAreIndented(): void
    {
        self::assertSame(
            "[\n    'outer' => [\n        'inner' => 'v',\n    ],\n]",
            ($this->exporter())(['outer' => ['inner' => 'v']])
        );
    }

    public function testNullIsLowercase(): void
    {
        self::assertSame("[\n    'k' => null,\n]", ($this->exporter())(['k' => null]));
    }

    /**
     * The exported source has to actually parse back to the same array.
     */
    public function testExportedSourceEvaluatesBackToTheInput(): void
    {
        $input = ['a' => "quote ' and \\ backslash", 2007 => '2007', 'n' => null, 'list' => ['x', 'y']];

        $evaluated = eval('return ' . ($this->exporter())($input) . ';');

        self::assertSame($input, $evaluated);
    }
}
