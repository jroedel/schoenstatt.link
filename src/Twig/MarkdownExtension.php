<?php

declare(strict_types=1);

namespace App\Twig;

use Parsedown;
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
    private ?Parsedown $safeParser = null;

    /** @return list<TwigFilter> */
    public function getFilters(): array
    {
        return [
            new TwigFilter('markdown', $this->markdown(...), ['is_safe' => ['html']]),
            new TwigFilter('markdown_safe', $this->markdownSafe(...), ['is_safe' => ['html']]),
        ];
    }

    public function markdown(?string $text): string
    {
        if (null === $text || '' === $text) {
            return '';
        }

        return ($this->parser ??= new ParsedownExtra())->text($text);
    }

    /**
     * `\Parsedown` with safe mode **on** — not ParsedownExtra, and not the filter above.
     *
     * This exists because the blog does it, and because the blog does it *inconsistently*:
     * `books/blog/show.phtml` builds `new \Parsedown()` and calls `setSafeMode(true)`,
     * while `books/blog/index.phtml` renders the same column through the `markdown` view
     * helper, which is a ParsedownExtra with safe mode off. So the identical post is
     * sanitized on its own page and not on the index that excerpts it.
     *
     * Both are reproduced exactly, because reproducing the *rendering* is the contract for
     * this port and a post containing inline HTML renders differently under the two. It is
     * worth knowing which way round the risk runs: `markdown` above is documented as being
     * for template literals only, and the blog index is the first caller to hand it a
     * database column. The column is written by `blog_contributor`, which is a trusted
     * role — so this is a pre-existing trust assumption being carried across unchanged,
     * not a new hole. Tightening it is a content decision for the site owner, and it
     * belongs in a change that says so rather than inside a porting batch.
     */
    public function markdownSafe(?string $text): string
    {
        if (null === $text || '' === $text) {
            return '';
        }

        if (null === $this->safeParser) {
            $this->safeParser = new Parsedown();
            $this->safeParser->setSafeMode(true);
        }

        return $this->safeParser->text($text);
    }
}
