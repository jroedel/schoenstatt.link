<?php

declare(strict_types=1);

namespace App\JTranslate\Phrase;

use JTranslate\Form\EditPhraseForm;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\TableGateway\Feature\GlobalAdapterFeature;
use Laminas\InputFilter\InputFilterInterface;

use function array_keys;

/**
 * The translation-edit rules as something an API request can run: one `InputFilter`,
 * built with no MVC, no session and no rendered form.
 *
 * The reasoning is {@see \App\Schoenstatt\Association\AssociationValidator}'s, applied
 * to the other form, and it is worth restating in one line because it is the whole
 * design of v3: the API takes **the form's own filter**, not a reimplementation of its
 * rules, so an agent is refused exactly what a translator is refused and parity is not
 * something anyone has to maintain. `test/Integration/PhraseValidationParityTest`
 * fails if that stops being true.
 *
 * ## Two seams, not one
 *
 * `AssociationValidator` had one thing to remove — the CSRF input, which presupposes a
 * browser session. This form has that and one more.
 *
 * **The static adapter.** `EditPhraseForm::getInputFilterSpecification()` reads
 * `GlobalAdapterFeature::getStaticAdapter()` while building the `RecordExists`
 * validator on `phraseId`, and the only thing that ever populates that static registry
 * is `JUser\Module::onBootstrap()` — which does not run on a Symfony-served route,
 * because nothing bootstraps laminas-mvc there. docs/strangler.md names this as the
 * blocker for porting the user and translation forms; it is reached here first. The
 * registry is populated from the bridge's own adapter before the form is touched,
 * which is the same adapter `onBootstrap()` would have put there.
 *
 * That is a global write, and it is worth being honest about: it affects the whole
 * process, not this filter. It is safe in the direction that matters — the value
 * written is the application's one configured adapter, so a bridged request that later
 * populates it writes the identical object — but it is the reason this is done in one
 * named place rather than scattered wherever a form is built.
 *
 * ## What the filter actually enforces
 *
 * Not much, and knowing that is the point of reading it rather than assuming: each
 * locale is optional, trimmed, and bounded at
 * {@see EditPhraseForm::TRANSLATION_MAX_LENGTH} characters to match the column. There
 * is no rule about placeholders, markup or length relative to the source string. An
 * agent that sends a 2001-character translation is refused with the translator's own
 * message; an agent that sends a plausible-looking mistranslation is not, and no
 * validator was ever going to catch that. What catches it is that the write is
 * attributable and the admin listing shows who touched each locale.
 */
final class PhraseValidator
{
    /**
     * The one input an API caller is not held to, because it presupposes a browser
     * session.
     */
    public const SESSION_ONLY_INPUT = 'security';

    /**
     * @param list<string> $locales the writable locale codes, from TranslationsTable::getLocales(true)
     */
    public function __construct(
        private readonly array $locales,
        private readonly string $phrasesTableName,
        private readonly string $translationsTableName,
        private readonly Adapter $adapter
    ) {
    }

    /**
     * Build from the application's services, the way the laminas factory would.
     *
     * @param callable(string): mixed $service
     */
    public static function fromServices(callable $service): self
    {
        /** @var \JTranslate\Model\TranslationsTable $table */
        $table = $service(\JTranslate\Model\TranslationsTable::class);
        /** @var array<string, mixed> $config */
        $config = $service('config');
        /** @var array<string, mixed> $jtranslate */
        $jtranslate = $config['jtranslate'] ?? [];
        /** @var Adapter $adapter */
        $adapter = $service(Adapter::class);

        return new self(
            array_keys($table->getLocales(true)),
            (string) ($jtranslate['phrases_table_name'] ?? 'trans_phrases'),
            (string) ($jtranslate['translations_table_name'] ?? 'trans_translations'),
            $adapter
        );
    }

    /**
     * The locale codes an API caller may write.
     *
     * `getLocales(true)` — the key locale included — because `updatePhrase()` iterates
     * exactly that set, so a locale this list omitted would be silently discarded
     * rather than refused.
     *
     * **Read it from the merged config, never from the module default.** JTranslate's
     * own module.config.php names three locales and this application's
     * config/autoload/jtranslate.global.php names `it_IT`; laminas merges the two by
     * appending, so the real answer here is five including the key locale, and
     * `it_IT` — which has only 17 rows and looks abandoned — is writable. Anything
     * that hardcodes the list is wrong on this site today.
     *
     * @return list<string>
     */
    public function writableLocales(): array
    {
        return $this->locales;
    }

    /**
     * A fresh input filter. Never shared: `InputFilter` holds the data and messages of
     * whatever was last validated through it, so handing the same instance to two
     * requests would leak one caller's submission into another's.
     *
     * @return InputFilterInterface<array<string, mixed>>
     */
    public function inputFilter(): InputFilterInterface
    {
        GlobalAdapterFeature::setStaticAdapter($this->adapter);

        $form   = new EditPhraseForm(
            $this->localeMap(),
            $this->phrasesTableName,
            $this->translationsTableName
        );
        $filter = $form->getInputFilter();

        if ($filter->has(self::SESSION_ONLY_INPUT)) {
            $filter->remove(self::SESSION_ONLY_INPUT);
        }

        return $filter;
    }

    /**
     * The form wants `[code => display name]` and reads only the keys for its filter
     * specification, so the display name is the code. Nothing renders this form here.
     *
     * @return array<string, string>
     */
    private function localeMap(): array
    {
        $map = [];
        foreach ($this->locales as $locale) {
            $map[$locale] = $locale;
        }

        return $map;
    }
}
