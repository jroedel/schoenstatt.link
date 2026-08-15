<?php

declare(strict_types=1);

namespace App\Twig;

use JShrink\Minifier;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

use function is_string;

/**
 * `minify_js`, the Twig counterpart of SionModel's `jshrink` view helper.
 *
 * ## Why one filter for one page
 *
 * Exactly one view script in the application minifies its inline JavaScript:
 * `module/Schoenstatt/view/schoenstatt/assignments/create.phtml`, which wraps its
 * association-to-role cascade in `$this->jshrink($script)`. Every other page — including
 * that page's own *edit* twin — emits the script as written.
 *
 * That one exception has to be reproduced rather than ignored, and the reason is the
 * verification rather than the page: `tools/port-baseline.php` compares the laminas and
 * Symfony renderings after collapsing whitespace *runs*, which is not the same as removing
 * whitespace. Minified `var $x=$("…")` and raw `var $x = $("…")` normalize to different
 * strings, so a create page that skipped the minifier would report drift on every locale
 * and identity — drift that says nothing about the port.
 *
 * The laminas helper is a two-line wrapper over `JShrink\Minifier::minify()`, so this calls
 * the library directly instead of bridging the helper: there is no laminas behaviour in
 * between to preserve, and reaching through `ViewHelperManager` for it would need an entry
 * in `App\Laminas\ViewHelpers` that no other template would ever use.
 *
 * Used as a block filter, which is how the original reads too — a heredoc, then the
 * minifier:
 *
 *     {% apply minify_js %}
 *     $(function () { … });
 *     {% endapply %}
 */
final class ScriptExtension extends AbstractExtension
{
    /** @return list<TwigFilter> */
    public function getFilters(): array
    {
        return [
            //`is_safe: html` because the output is script source going inside a <script>
            //element the template already opened; autoescaping it would turn every quote
            //into an entity and the browser would parse none of it. The input is written
            //in the template rather than supplied by a request — the one interpolated
            //value, the roles map, arrives as JSON from Laminas\Json\Json::encode().
            new TwigFilter('minify_js', $this->minify(...), ['is_safe' => ['html']]),
        ];
    }

    public function minify(string $script): string
    {
        /** @var mixed $minified */
        $minified = Minifier::minify($script);

        //JShrink returns false on a parse failure rather than throwing. Falling back to the
        //unminified source is the right answer: a larger page beats a blank one, and this
        //runs on a moderator-only form where the script is what makes the role picker work.
        return is_string($minified) ? $minified : $script;
    }
}
