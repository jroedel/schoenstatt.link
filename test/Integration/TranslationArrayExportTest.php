<?php

namespace SchoenstattTest\Integration;

use JTranslate\Model\TranslationsTable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
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
 * The check is a round-trip against the 24 committed *.lang.php files: read the
 * array back out of each one, re-export it, and require the result to match the
 * file byte for byte. Those files are exactly what the old generator produced,
 * so matching them is the strongest available evidence of fidelity — and it
 * guarantees regenerating the language files yields no spurious diff.
 *
 * Needs vendor/ for autoloading, so it runs in the capsule:
 * php composer.phar integration
 */
class TranslationArrayExportTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function languageFiles(): array
    {
        $root  = dirname(__DIR__, 2);
        $files = glob($root . '/module/*/language/*.lang.php') ?: [];

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
        $method->setAccessible(true);

        return static fn (array $translations): string => $method->invoke($table, $translations, 1);
    }

    /**
     * Trailing newlines are compared loosely: exactly half the committed corpus
     * (12 of 24 files) ends without one, so the files disagree with each other
     * and cannot all be matched. The generator emits one — which is both
     * POSIX-correct and what FileGenerator did — and everything up to it must
     * be identical.
     */
    #[DataProvider('languageFiles')]
    public function testCommittedLanguageFilesRoundTripExactly(string $file): void
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
