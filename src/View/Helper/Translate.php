<?php

declare(strict_types=1);

namespace App\View\Helper;

use Laminas\Translator\TranslatorInterface;
use Laminas\View\Helper\AbstractHelper;

/**
 * The `translate` view helper, formerly `Laminas\I18n\View\Helper\Translate`.
 *
 * ## Who calls it, and why the text domain is set from outside
 *
 * Not the templates. Twig asks `App\Twig\LaminasExtension` instead, which passes a domain
 * with every call. This helper exists for the *other* laminas view helpers, which reach it
 * as `$this->view->translate($string)` with **no domain** — `Books\View\Helper\FormatField`
 * is the case that forced it into existence. Whatever domain was last set is what they get,
 * and {@see \App\Laminas\ViewHelpers::useTextDomain()} sets it per request from the route.
 *
 * The failure that makes this worth a class rather than a closure is invisible in English:
 * an unset domain means the lookup lands in `default`, misses, and returns the source
 * string — which *is* the English text. `/es/SL202186L` rendered its whole bibliographic
 * panel in English against a laminas page that rendered it in Spanish.
 *
 * ## No translator is not an error
 *
 * The original threw when asked to translate with none set. A console process legitimately
 * has one and a missing translation must never be the thing that takes a page down, so this
 * returns the message unchanged, which is what a total catalog miss does anyway.
 */
final class Translate extends AbstractHelper
{
    private ?TranslatorInterface $translator = null;

    private string $textDomain = 'default';

    public function __invoke(string $message, ?string $textDomain = null, ?string $locale = null): string
    {
        if (null === $this->translator) {
            return $message;
        }

        return $this->translator->translate($message, $textDomain ?? $this->textDomain, $locale);
    }

    public function setTranslator(?TranslatorInterface $translator = null, ?string $textDomain = null): self
    {
        $this->translator = $translator;
        if (null !== $textDomain) {
            $this->textDomain = $textDomain;
        }

        return $this;
    }

    public function getTranslator(): ?TranslatorInterface
    {
        return $this->translator;
    }

    public function hasTranslator(): bool
    {
        return null !== $this->translator;
    }

    public function setTranslatorTextDomain(string $textDomain = 'default'): self
    {
        $this->textDomain = $textDomain;

        return $this;
    }

    public function getTranslatorTextDomain(): string
    {
        return $this->textDomain;
    }
}
