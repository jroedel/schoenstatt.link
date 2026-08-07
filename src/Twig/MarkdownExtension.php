<?php

declare(strict_types=1);

namespace App\Twig;

use ParsedownExtra;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * The `markdown` filter, which is what the ported content pages are made of.
 *
 * Five of the pages on this site are a Markdown heredoc in a .phtml handed to one
 * view helper — `/`, /developers, /acknowledgements, /privacy and
 * /shrines/submitting-photos are literally `echo $this->markdown($text['en'])`. So
 * porting them needs Markdown, and nothing else.
 *
 * **Not routed through App\Laminas\ViewHelpers**, unlike `flag` or `telephone`. The
 * allowlist there exists for helpers with real logic worth reusing — a libphonenumber
 * formatter, a translated country table. Books\View\Helper\Markdown has none: it
 * constructs a ParsedownExtra in its constructor and forwards one call to `text()`.
 * Reaching it through laminas-view would mean priming a PhpRenderer and a
 * HelperPluginManager to reach a `new ParsedownExtra()` this class can make itself,
 * on pages that need nothing else from laminas-view at all. Byte-identical output
 * either way, because it is the same parser with the same default settings.
 *
 * **is_safe: html, and the input must be a template literal.** ParsedownExtra runs
 * with safe mode off and markup escaping off — its defaults, and what the laminas
 * pages already rely on, since their Markdown contains inline HTML. So the filter
 * emits whatever HTML its input asks for, and its input must come from a template,
 * never from a request parameter or a database column. Every current caller passes a
 * literal defined in the same file. Should a page ever need to render *stored*
 * Markdown, that caller needs `setSafeMode(true)`, which is a different filter and
 * should be named like one rather than added as an argument here.
 *
 * The parser is built on first use: a page that renders no Markdown — every ported
 * page before this one — pays nothing for the extension being registered.
 */
final class MarkdownExtension extends AbstractExtension
{
    private ?ParsedownExtra $parser = null;

    /** @return list<TwigFilter> */
    public function getFilters(): array
    {
        return [
            new TwigFilter('markdown', $this->markdown(...), ['is_safe' => ['html']]),
        ];
    }

    public function markdown(?string $text): string
    {
        if (null === $text || '' === $text) {
            return '';
        }

        return ($this->parser ??= new ParsedownExtra())->text($text);
    }
}
