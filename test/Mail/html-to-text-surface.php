<?php

/**
 * What `voku/html2text` answered, recorded on 2026-09-21 while the package was still
 * installed, and now the contract for `SionModel\Mailing\HtmlToText`.
 *
 * Each entry is `label => [html, text]`. Read by `SchoenstattTest\Unit\HtmlToTextTest`.
 *
 * ## What is in here, and what deliberately is not
 *
 * One shape per rule — every tag the corpus or the mail templates use, plus the whitespace,
 * entity and link cases. It is **not** the 2,756 stored documents: that comparison was run
 * once and belongs in the pull request, and committing it would put the database in the
 * repository.
 *
 * For the record, that run said: **2,748 of 2,756 identical**, six differing only in
 * leading whitespace and two by a blank line, all of them inside the footnote lists of two
 * very long documents. Both mail templates and all of the shapes below matched byte for
 * byte, which is the part that reaches a recipient.
 *
 * A changed line here is a real answer moving. The package that produced these answers no
 * longer exists, so there is nothing to regenerate them from — read the diff.
 */

declare(strict_types=1);

return [
    "shape 01" => [
        "<p>One.</p><p>Two.</p>",
        "One.\n\nTwo.",
    ],
    "shape 02" => [
        "<h1>Heading</h1><p>Body</p>",
        "HEADING\n\nBody",
    ],
    "shape 03" => [
        "<h2>Sub</h2>",
        "SUB",
    ],
    "0" => [
        "<h4>Four</h4>",
        "FOUR",
    ],
    "shape 04" => [
        "<div>a</div><div>b</div>",
        "a\nb",
    ],
    "shape 05" => [
        "<table><tr><td>one</td><td>two</td></tr><tr><th>head</th></tr></table>",
        "one\ntwo\n\nHEAD",
    ],
    "shape 06" => [
        "<a href=\"https://schoenstatt.link/x\">Click here</a>",
        "Click here [https://schoenstatt.link/x]",
    ],
    "shape 07" => [
        "<a href=\"https://schoenstatt.link/x\">https://schoenstatt.link/x</a>",
        "https://schoenstatt.link/x",
    ],
    "shape 08" => [
        "<a href=\"mailto:someone@example.com\">someone@example.com</a>",
        "someone@example.com",
    ],
    "shape 09" => [
        "<a href=\"#anchor\">anchor</a>",
        "anchor",
    ],
    "shape 10" => [
        "<a href=\"javascript:void(0)\">js</a>",
        "js",
    ],
    "shape 11" => [
        "<strong>bold</strong> and <b>also bold</b>",
        "BOLD and ALSO BOLD",
    ],
    "shape 12" => [
        "<em>emphasis</em> and <i>italic</i>",
        "_emphasis_ and _italic_",
    ],
    "shape 13" => [
        "<ul><li>one</li><li>two</li></ul>",
        "  * one\n  * two",
    ],
    "shape 14" => [
        "<ol><li>first</li><li>second</li></ol>",
        "  * first\n  * second",
    ],
    "shape 15" => [
        "<li></li>",
        "*",
    ],
    "shape 16" => [
        "<hr>",
        "-------------------------",
    ],
    "1" => [
        "<hr />",
        "-------------------------",
    ],
    "shape 17" => [
        "<br>a<br />b",
        "a\nb",
    ],
    "shape 18" => [
        "<dl><dt>term</dt><dd>definition</dd></dl>",
        "  TERM\n  * definition",
    ],
    "shape 19" => [
        "<code>\$x = 1;</code>",
        "```\$x = 1;```",
    ],
    "shape 20" => [
        "<ins>added</ins> <del>removed</del>",
        "_added_~~removed~~",
    ],
    "shape 21" => [
        "<img alt=\"Logo\" src=\"https://example.com/l.png\">",
        "Image: \"Logo\" [https://example.com/l.png]",
    ],
    "shape 22" => [
        "<img src=\"https://example.com/l.png\" alt=\"Logo\">",
        "Image: \"Logo\" [https://example.com/l.png]",
    ],
    "shape 23" => [
        "<img src=\"cid:inline\" alt=\"Inline\">",
        "Image: \"Inline\"",
    ],
    "shape 24" => [
        "<img src=\"https://example.com/l.png\">",
        "",
    ],
    "shape 25" => [
        "<head><title>t</title></head><p>after head</p>",
        "after head",
    ],
    "shape 26" => [
        "<script type=\"application/ld+json\">{\"@type\":\"EmailMessage\"}</script><p>after script</p>",
        "after script",
    ],
    "shape 27" => [
        "<style>p { color: red }</style><p>after style</p>",
        "after style",
    ],
    "shape 28" => [
        "<p>a&nbsp;&nbsp;&nbsp;b</p>",
        "a   b",
    ],
    "shape 29" => [
        "<p>&amp; &lt; &gt; &quot; &#39; &#153; &#151;</p>",
        "& < > \" ' ™ —",
    ],
    "shape 30" => [
        "<p>runs    of     spaces</p>",
        "runs of spaces",
    ],
    "shape 31" => [
        "<p>line\nbreak\tand tab</p>",
        "line break and tab",
    ],
    "shape 32" => [
        "<p>windows\r\nnewline</p>",
        "windows newline",
    ],
    "shape 33" => [
        "<p>   leading and trailing   </p>",
        "leading and trailing",
    ],
    "shape 34" => [
        "<span>span text</span>",
        "span text",
    ],
    "shape 35" => [
        "<section><p>in a section</p></section>",
        "in a section",
    ],
    "shape 36" => [
        "<sup>2</sup>",
        "2",
    ],
    "shape 37" => [
        "<p>unicode: Schönstatt — “quoted” ¡olé!</p>",
        "unicode: Schönstatt — “quoted” ¡olé!",
    ],
    "shape 38" => [
        "<p><strong>bold <em>inner</em> text</strong></p>",
        "BOLD _INNER_ TEXT",
    ],
    "shape 39" => [
        "<p>empty next</p><p></p><p>after empty</p>",
        "empty next\n\nafter empty",
    ],
    "shape 40" => [
        "<!-- a comment --><p>after comment</p>",
        "after comment",
    ],
    "shape 41" => [
        "",
        "",
    ],
    "shape 42" => [
        "plain text with no tags at all",
        "plain text with no tags at all",
    ],
];
