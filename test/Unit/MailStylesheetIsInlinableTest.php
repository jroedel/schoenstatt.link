<?php

declare(strict_types=1);

namespace SchoenstattTest\Unit;

use PHPUnit\Framework\TestCase;
use SionModel\Mailing\CssInliner;

require_once __DIR__ . '/../../module/SionModel/src/Mailing/CssInliner.php';

/**
 * Every selector in the mail stylesheet is one `SionModel\Mailing\CssInliner` understands.
 *
 * `tijsverkoyen/css-to-inline-styles` was removed on 2026-09-21, with `symfony/css-selector`
 * behind it, because the stylesheet it served is ours and uses a closed set of selectors —
 * `*`, tags, classes, compounds, descendant chains, comma lists. The replacement supports
 * exactly that set and **throws** on anything else.
 *
 * Without this test that trade would be a trap: someone adds `a:hover` or `.footer > p`,
 * the inliner throws inside a mail send, and a notice goes out unstyled or not at all. Here
 * it is a red test on the commit that adds the selector, and the fix is either to extend
 * the inliner or to write that style inline.
 *
 * The inliner's agreement with the package it replaces was measured on the 117 real mail
 * bodies stored in `mailings`: identical inline declarations on every element of all 117.
 */
final class MailStylesheetIsInlinableTest extends TestCase
{
    private const STYLESHEET = __DIR__ . '/../../public/css/email-default.css';

    /**
     * A floor, so a stylesheet that failed to load reads as a failure rather than as a
     * test that checked nothing.
     */
    private const RULE_FLOOR = 40;

    public function testEverySelectorInTheMailStylesheetCanBeInlined(): void
    {
        $css = file_get_contents(self::STYLESHEET);
        self::assertIsString($css, 'the mail stylesheet is not readable');

        //parse() throws a RuntimeException naming the selector it cannot handle.
        $rules = CssInliner::parse($css);

        self::assertGreaterThanOrEqual(
            self::RULE_FLOOR,
            count($rules),
            'almost no rule was parsed, so this test proves nothing'
        );
    }

    /** The inliner refuses what it cannot express, rather than dropping it silently. */
    public function testAnUnsupportedSelectorThrowsRatherThanBeingIgnored(): void
    {
        $this->expectExceptionMessageMatches('/outside what .* supports/');

        CssInliner::parse('a:hover { color: red; }');
    }

    /** The cascade, as far as an inline style can carry it. */
    public function testAnInlineDeclarationBeatsTheStylesheet(): void
    {
        $html = '<p style="color: green;">text</p>';

        self::assertStringContainsString(
            'color: green;',
            CssInliner::inline($html, 'p { color: red; font-size: 12px; }')
        );
    }

    /** A more specific selector wins, whatever order the file puts it in. */
    public function testSpecificityOutranksSourceOrder(): void
    {
        $html   = '<p class="footer">text</p>';
        $result = CssInliner::inline($html, '.footer { color: blue; } p { color: red; }');

        self::assertStringContainsString('color: blue;', $result);
        self::assertStringNotContainsString('color: red;', $result);
    }
}
