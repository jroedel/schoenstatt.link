<?php

declare(strict_types=1);

namespace SchoenstattTest\Form;

use App\Locale\Locales;
use Laminas\Form\Fieldset;
use Locale;
use SchoenstattTest\Fuzz\FormRepository;

use function date;
use function get_debug_type;
use function is_array;
use function is_scalar;
use function ksort;
use function preg_replace;
use function restore_error_handler;
use function set_error_handler;
use function str_replace;

require_once __DIR__ . '/../Fuzz/FormRepository.php';
require_once __DIR__ . '/Engine.php';
require_once __DIR__ . '/FormData.php';

/**
 * What the validation engine answers for every form, as data rather than as markup.
 *
 * ## Why this exists, and why now
 *
 * `test/Form/form-markup.php` records what a form *renders*, which is the contract of the
 * form model. This records what the engine *returns*, which is the contract of the rules
 * underneath it — and the two do not overlap where it matters most. A rendered value is a
 * string in an attribute: `value=""` is what both `''` and `null` produce, so the 96
 * references to `ToNull`, the `ToInt`s and the `ToDateTime`s are invisible in markup and
 * decisive in the database. `getValues()` is the array a controller hands to
 * `SionTable::updateEntity()`.
 *
 * It is recorded now because the instruments that measure this today —
 * `WholeFormEngineParityTest`, `EngineMatchesAssembledFilterTest` and
 * `InputFilterEngineParityTest` — all work by running laminas' assembled input filter
 * beside the engine and comparing. They stop existing when `Laminas\Form\Form` does, which
 * is this iteration. A recording outlives its subject; a parity test does not.
 *
 * ## What is recorded
 *
 * Every form and fieldset `test/Fuzz/FormRepository` builds, under the two datasets
 * `SchoenstattTest\Form\FormData` produces — the same ones the markup baseline submits, so
 * a finding can be read across both files:
 *
 * - **accepted** — a value of the right shape in every field. What a good submission does.
 * - **rejected** — a value every validator should refuse. What a bad one does.
 *
 * and for each: the verdict, the values the engine returns, and the messages it produces.
 * The CSRF rule is dropped from the specification first: no test process holds the
 * session's token, so leaving it in would make every `accepted` verdict false and record
 * one fact 43 times instead of what each form does.
 *
 * ## Two normalisations, and no more
 *
 * A CSRF token and today's date, exactly as the markup baseline normalises them and for
 * the same reason: both differ between two runs of the same code. Not shared with that
 * file, because the two walk different things — one rewrites markup, this one rewrites
 * scalars inside a nested array — and a shared normaliser would have to be told which.
 *
 * Objects are recorded as their type. A `DateTime` from `ToDateTime` is a real answer and
 * its identity is not stable across runs; the type moving is the finding.
 */
final class EngineSurface
{
    private const TOKEN = '<csrf-token>';
    private const TODAY = '<today>';

    /** The datasets, and whether each one is meant to be accepted. */
    public const STATES = ['accepted' => true, 'rejected' => false];

    /**
     * `Form\Class` => state => `['valid' => bool, 'values' => …, 'messages' => …]`.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public static function collect(): array
    {
        $surface = [];

        foreach (self::forms() as $class => $form) {
            foreach (self::STATES as $state => $valid) {
                $surface[(string) $class][$state] = self::run($form, $valid);
            }
        }

        ksort($surface);

        return $surface;
    }

    /**
     * One dataset through one form's engine.
     *
     * @return array<string, mixed>
     */
    private static function run(Fieldset $form, bool $valid): array
    {
        //The same error handler FormMarkup installs, for the same reason: reading a Csrf
        //element's value regenerates its token, which starts a session, and a CLI process
        //that has written a byte of output cannot. FormData asks for that value.
        set_error_handler(static fn (): bool => true);

        try {
            $engine = Engine::of($form, ['security']);
            $engine->setData(FormData::forFieldset($form, $valid));

            $verdict = $engine->isValid();

            return [
                'valid'    => $verdict,
                'values'   => self::normalise($engine->getValues()),
                'messages' => self::normalise($engine->getMessages()),
            ];
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @return array<class-string, Fieldset>
     */
    private static function forms(): array
    {
        //The locale, for the reason `SchoenstattTest\Form\FormMarkup::build()` gives: a
        //form factory reads `\Locale::getDefault()` to label 136 selects, and a CLI process
        //inherits a locale that is not one of the five keys. It decides an `InArray`
        //haystack's *labels* rather than its keys, so it changes no verdict here — pinned
        //anyway, so that a value option read from the database is the same one both
        //baselines saw.
        $previous = Locale::getDefault();
        Locale::setDefault(Locales::DEFAULT_LOCALE);

        try {
            return FormRepository::fresh()->forms();
        } finally {
            Locale::setDefault($previous);
        }
    }

    /**
     * @param array<array-key, mixed> $values
     * @return array<string, mixed>
     */
    private static function normalise(array $values): array
    {
        $out = [];
        foreach ($values as $key => $value) {
            $out[(string) $key] = is_array($value) ? self::normalise($value) : self::scalar($value);
        }
        ksort($out);

        return $out;
    }

    private static function scalar(mixed $value): mixed
    {
        if (null === $value || ! is_scalar($value)) {
            return null === $value ? null : '<' . get_debug_type($value) . '>';
        }

        if (! is_string($value)) {
            return $value;
        }

        $normalised = (string) preg_replace('/\b[0-9a-f]{32}-[0-9a-f]{32}\b/', self::TOKEN, $value);

        return str_replace(date('Y-m-d'), self::TODAY, $normalised);
    }
}
