<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use JTranslate\View\Escape as JTranslateEscape;
use SionModel\View\Escape;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * `SionModel\View\Escape` against what `Laminas\Escaper\Escaper` produced.
 *
 * A golden master. Every expected string below was captured from the running laminas
 * escaper on 2026-09-09, before step 4 of the laminas exit removes the package, over a
 * sweep of all 256 single bytes plus the cases here. The sweep found **zero** differences;
 * these are the ones worth keeping visible, because each stands for a rule that is easy to
 * get wrong and impossible to notice.
 *
 * ## What each case is really testing
 *
 * - **digits and the unescaped set** pass through untouched. laminas short-circuits on
 *   `ctype_digit()`, and `[a-z0-9,.\-_]` is the only class the attribute escaper leaves
 *   alone. Escaping them anyway would be safe but would change every attribute in the site.
 * - **entity width.** laminas wrote two hex digits below U+0100 and four above. Twig, which
 *   this delegates to, writes four always. The values are the same character to a browser,
 *   but this application's attributes are full of German, Spanish and Portuguese names, so
 *   without normalising, every one of them would change bytes on deploy.
 * - **the accented cases** are that rule in the data this site actually holds.
 * - **the emoji** is the other side of it: above U+FFFF, where four digits are correct.
 * - **control characters** become the replacement character, not the literal byte.
 *
 * Invalid UTF-8 is tested separately: laminas converted and returned a string, Twig raises,
 * and a page whose only fault is one bad byte in a database field must not die.
 *
 * ## Why this is an integration test and not a unit one
 *
 * `htmlAttr()` delegates to Twig's escaper runtime, so it needs `vendor/`. The unit suite
 * deliberately avoids `vendor/autoload.php` — see test/bootstrap.php — so that it stays
 * valid while vendor/ is mid-migration, which is exactly the state this step keeps it in.
 */
class EscapeTest extends TestCase
{
    /** @return array<string, array{string, string, string}> */
    public static function cases(): array
    {
        return [
            'empty' => ['', '', ''],
            'plain word' => ['plain', 'plain', 'plain'],
            'digits only' => ['123', '123', '123'],
            'the unescaped set' => ['a,b.c-d_e', 'a,b.c-d_e', 'a,b.c-d_e'],
            'a space' => ['a b', 'a b', 'a&#x20;b'],
            'html specials' => ['<b>&"\'', '&lt;b&gt;&amp;&quot;&#039;', '&lt;b&gt;&amp;&quot;&#x27;'],
            'an attribute break-out attempt' => ['" onmouseover="x', '&quot; onmouseover=&quot;x', '&quot;&#x20;onmouseover&#x3D;&quot;x'],
            'a tab and a newline' => ['tab	new
line', 'tab	new
line', 'tab&#x09;new&#x0A;line'],
            'a NUL' => ['' . "\0" . '', '' . "\0" . '', '&#xFFFD;'],
            'German umlaut' => ['Müller', 'Müller', 'M&#xFC;ller'],
            'Portuguese tilde' => ['São Paulo', 'São Paulo', 'S&#xE3;o&#x20;Paulo'],
            'German eszett' => ['Straße', 'Straße', 'Stra&#xDF;e'],
            'French diaeresis' => ['naïve', 'naïve', 'na&#xEF;ve'],
            'ampersand between words' => ['Kentenich & Söhne', 'Kentenich &amp; Söhne', 'Kentenich&#x20;&amp;&#x20;S&#xF6;hne'],
            'non-breaking space' => [' ', ' ', '&#xA0;'],
            'line separator' => [' ', ' ', '&#x2028;'],
            'an emoji, above U+FFFF' => ['😀', '😀', '&#x1F600;'],        ];
    }

    #[DataProvider('cases')]
    public function testHtmlMatchesWhatLaminasProduced(string $raw, string $html, string $attr): void
    {
        self::assertSame($html, Escape::html($raw));
    }

    #[DataProvider('cases')]
    public function testHtmlAttrMatchesWhatLaminasProduced(string $raw, string $html, string $attr): void
    {
        self::assertSame($attr, Escape::htmlAttr($raw));
    }

    /**
     * JTranslate ships its own copy, and it must not drift.
     *
     * That module does not require SionModel and must not start doing so for twelve lines
     * of escaping, so `JTranslate\View\Escape` is a deliberate duplicate. A duplicate that
     * is allowed to diverge is worse than a dependency: attribute escaping is where an
     * injection gets in, and nobody would notice one of the two copies losing the
     * entity-width normalisation or the invalid-UTF-8 guard.
     */
    #[DataProvider('cases')]
    public function testJTranslatesCopyProducesIdenticalOutput(string $raw, string $html, string $attr): void
    {
        self::assertSame($attr, JTranslateEscape::htmlAttr($raw), 'the two escaper copies have drifted');
    }

    /**
     * Null is the shape a database column hands over, and both must answer with a string.
     */
    public function testNullEscapesToAnEmptyString(): void
    {
        self::assertSame('', Escape::html(null));
        self::assertSame('', Escape::htmlAttr(null));
    }

    /**
     * The one place this deliberately does not reproduce laminas: it must not raise.
     *
     * Twig throws `RuntimeError` on a string that is not valid UTF-8. laminas converted and
     * returned something. A tooltip built from one badly encoded database field would
     * otherwise take the whole page down, which is the wrong trade for an attribute value.
     */
    public function testInvalidUtf8IsSubstitutedRatherThanRaised(): void
    {
        $escaped = Escape::htmlAttr("caf\xE9 bad");

        self::assertStringContainsString('bad', $escaped, 'the valid tail was lost');
        self::assertStringNotContainsString("\xE9", $escaped, 'the invalid byte survived escaping');
    }
}
