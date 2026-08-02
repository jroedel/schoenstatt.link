<?php

namespace SchoenstattTest\Unit;

use PHPUnit\Framework\TestCase;
use SionModel\Text\Text;

/**
 * Characterization test for SionModel\Text\Text, the drop-in replacement for
 * Cake\Utility\Text.
 *
 * cakephp/utility declared `php >=5.6.0,<8.0.0` and so blocked every PHP 8
 * target while being used for nothing but four string functions. The fixture in
 * fixtures/text-expectations.json was generated from the real
 * Cake\Utility\Text 3.10.5 while it was still installed, so these assertions
 * pin the replacement to byte-identical output rather than to my reading of it.
 *
 * The class file is required directly: test/bootstrap.php deliberately avoids
 * vendor/autoload.php so the suite stays valid even when vendor/ is mid-migration.
 */
class TextTest extends TestCase
{
    /** @var array */
    private static $expectations;

    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../../module/SionModel/src/Text/Text.php';

        $path = __DIR__ . '/fixtures/text-expectations.json';
        self::$expectations = json_decode(file_get_contents($path), true);
        if (! is_array(self::$expectations)) {
            self::fail("Could not read expectations fixture at $path");
        }
    }

    public function testTruncateMatchesCakeOutput(): void
    {
        foreach (self::$expectations['truncate'] as $i => [$text, $length, $options, $expected]) {
            $this->assertSame(
                $expected,
                Text::truncate($text, $length, $options),
                "truncate case #$i (length $length) diverged from Cake's output"
            );
        }
    }

    public function testTruncateByWidthMatchesCakeOutput(): void
    {
        foreach (self::$expectations['truncateByWidth'] as $i => [$text, $length, $options, $expected]) {
            $this->assertSame(
                $expected,
                Text::truncateByWidth($text, $length, $options),
                "truncateByWidth case #$i (width $length) diverged from Cake's output"
            );
        }
    }

    public function testHighlightMatchesCakeOutput(): void
    {
        foreach (self::$expectations['highlight'] as $i => [$text, $phrase, $options, $expected]) {
            $this->assertSame(
                $expected,
                Text::highlight($text, $phrase, $options),
                "highlight case #$i diverged from Cake's output"
            );
        }
    }

    public function testExcerptMatchesCakeOutput(): void
    {
        foreach (self::$expectations['excerpt'] as $i => [$text, $phrase, $radius, $ellipsis, $expected]) {
            $this->assertSame(
                $expected,
                Text::excerpt($text, $phrase, $radius, $ellipsis),
                "excerpt case #$i diverged from Cake's output"
            );
        }
    }

    /**
     * The lengths the view scripts actually pass, asserted explicitly so a
     * regression names the caller rather than a fixture index.
     */
    public function testTruncationLengthsUsedByViewScripts(): void
    {
        // changes-table.phtml / view-changes.phtml truncate audit values at 150
        $this->assertSame(str_repeat('a', 147) . '...', Text::truncate(str_repeat('a', 200), 150));
        // j-translate/index.phtml truncates phrases and translations at 100
        $this->assertSame(str_repeat('b', 97) . '...', Text::truncate(str_repeat('b', 200), 100));
        // dictionary/in-language.phtml shortens link slugs at 50
        $this->assertSame(str_repeat('c', 47) . '...', Text::truncate(str_repeat('c', 60), 50));
        // Text shorter than the limit is returned untouched, with no ellipsis
        $this->assertSame('untouched', Text::truncate('untouched', 150));
    }

    /**
     * A regex metacharacter in a user's search term must not blow up the page:
     * the phrase is preg_quote'd before it reaches preg_replace.
     */
    public function testHighlightQuotesRegexMetacharactersInSearchTerms(): void
    {
        $this->assertSame(
            'a <mark>(b)</mark> c',
            Text::highlight('a (b) c', '(b)', ['format' => '<mark>\1</mark>'])
        );
        $this->assertSame(
            'pipes <mark>|</mark> survive',
            Text::highlight('pipes | survive', '|', ['format' => '<mark>\1</mark>'])
        );
    }

    /**
     * The `html` option was not ported. It must fail loudly rather than quietly
     * returning differently-shaped output than Cake would have.
     */
    public function testHtmlOptionThrowsRatherThanMisbehaving(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Text::truncate('<p>some markup here</p>', 10, ['html' => true]);
    }
}
