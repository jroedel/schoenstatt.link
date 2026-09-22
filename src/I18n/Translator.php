<?php

declare(strict_types=1);

namespace App\I18n;

use JTranslate\I18n\Translator\Translator as JTranslateTranslator;
use SionModel\I18n\TranslatesMessages;

/**
 * Binds the application's translator to the contract SionModel states.
 *
 * `SionModel\I18n\TranslatesMessages` says what that package needs of a translator;
 * `JTranslate\I18n\Translator\Translator` is what this application has. The two cannot
 * know about each other — SionModel and JTranslate are peers, neither requires the other —
 * so the host is where they meet. This is the same shape as `App\JUser\Host\Session` and
 * `App\JTranslate\Host\*`: a library declares what it needs, the application supplies it.
 *
 * `Laminas\Translator\TranslatorInterface` was what made the introduction until 2026-09-21,
 * and being a package rather than a host class is the only thing it did differently.
 *
 * ## Which object a caller gets
 *
 * Both are registered, and the difference is deliberate:
 *
 * - **the library classes get this one**, under `TranslatesMessages::class` — SionModel's
 *   mailer, validators and view helpers, JUser's mailer, `SchoenstattTable`,
 *   `AssociationKindsService`, Books' publication-URL helper;
 * - **application code gets the translator itself**, under `MvcTranslator` or the class id,
 *   because some of it needs more than translation: `App\Twig\LaminasExtension` reads
 *   `getAllMessages()` to answer from the catalog directly, and
 *   `App\Laminas\TranslatorConfigurator` sets the locale, the fallback and the
 *   missing-phrase listener on it.
 *
 * There is still **one** translator, and this class's own identity does not matter. It
 * holds no state: the locale, the catalogs and the listeners all live in the object it
 * delegates to. So the container's instance and the one
 * {@see \App\Laminas\TranslatorConfigurator} constructs for
 * `SionModel\Validator\AbstractValidator::setDefaultTranslator()` — which cannot resolve
 * this from the container without recursing into the translator it is building — answer
 * identically and always will.
 */
final class Translator implements TranslatesMessages
{
    public function __construct(private readonly JTranslateTranslator $translator)
    {
    }

    public function translate(
        string $message,
        string $textDomain = self::DEFAULT_TEXT_DOMAIN,
        ?string $locale = null
    ): string {
        return $this->translator->translate($message, $textDomain, $locale);
    }
}
