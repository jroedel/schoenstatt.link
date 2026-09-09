<?php

declare(strict_types=1);

namespace App\View;

use Closure;
use Laminas\Escaper\Escaper;

use function preg_match;
use function sprintf;

/**
 * A Bootstrap 3 label: `<span class="label-info label">Text</span>`.
 *
 * Replaces TwbBundle's `label` view helper, byte for byte — including the order of the
 * classes (the caller's first, `label` appended unless present) and the attribute
 * escaping, which turns the space between them into `&#x20;`. The text is translated in
 * the given domain, as the helper's own translator did, and then attribute-safe escaped
 * as the helper's `createAttributesString()` left it.
 */
final class Label
{
    private readonly Escaper $escaper;

    /**
     * @param Closure(string, string): string $translate `fn (text, textDomain)`
     */
    public function __construct(private readonly Closure $translate)
    {
        $this->escaper = new Escaper('utf-8');
    }

    public function render(string $text, string $class, string $textDomain = 'default'): string
    {
        if (! preg_match('/(\s|^)label(\s|$)/', $class)) {
            $class .= ' label';
        }

        return sprintf(
            '<span class="%s">%s</span>',
            $this->escaper->escapeHtmlAttr($class),
            ($this->translate)($text, $textDomain)
        );
    }
}
