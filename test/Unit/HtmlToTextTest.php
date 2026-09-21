<?php

declare(strict_types=1);

namespace SchoenstattTest\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SionModel\Mailing\HtmlToText;

require_once __DIR__ . '/../../module/SionModel/src/Mailing/HtmlToText.php';

/**
 * `SionModel\Mailing\HtmlToText` against what `voku/html2text` answered.
 *
 * The package was removed on 2026-09-21, together with the four it pulled in, and its
 * answers were recorded first — `test/Mail/html-to-text-surface.php`, one shape per rule.
 *
 * The wider measurement, which belongs in the pull request rather than the repository: over
 * all 2,756 stored HTML documents the two agree on 2,748; six differ only in leading
 * whitespace and two by a blank line, all inside the footnote lists of two very long
 * documents. Both mail templates and every shape below matched byte for byte.
 *
 * What this output is: the `text/plain` alternative of every mail this application sends —
 * a magic link, an overdue-book notice — and the `PlainText` column that feeds search
 * excerpts. The first of those is what a recipient reads on a phone.
 *
 * A unit test: no database, no container, no `ext-intl`. See `php composer.phar unit`.
 */
final class HtmlToTextTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function recordedShapes(): iterable
    {
        /** @var array<string, array{string, string}> $recording */
        $recording = require __DIR__ . '/../Mail/html-to-text-surface.php';

        foreach ($recording as $label => [$html, $text]) {
            yield $label => [$html, $text];
        }
    }

    #[DataProvider('recordedShapes')]
    public function testEveryShapeConvertsAsRecorded(string $html, string $expected): void
    {
        self::assertSame($expected, HtmlToText::convert($html));
    }

    /**
     * The rule that must never regress, stated on its own rather than left to a shape.
     *
     * `module/SionModel/templates/mailing/action-email.html.twig` embeds a JSON-LD
     * `<script>`. If the block survived into the text part, every mail this application
     * sends would open with a blob of JSON.
     */
    public function testHeadScriptAndStyleAreRemovedWholesale(): void
    {
        $html = '<head><title>ignored</title></head>'
            . '<style>p { color: red }</style>'
            . '<script type="application/ld+json">{"@type":"EmailMessage","name":"secret"}</script>'
            . '<p>The body.</p>';

        self::assertSame('The body.', HtmlToText::convert($html));
    }

    /**
     * The other rule a recipient depends on: a link keeps its URL, so a magic link is
     * still usable from the plain-text part.
     */
    public function testALinkKeepsItsUrl(): void
    {
        $url = 'https://schoenstatt.link/library/my-books?t=abc123';

        self::assertStringContainsString(
            $url,
            HtmlToText::convert(sprintf('<p><a href="%s">See my books</a></p>', $url))
        );
    }
}
