<?php

declare(strict_types=1);

namespace SchoenstattTest\Fuzz;

use SionModel\Form\Fieldset;
use SionModel\Form\Form;
use Throwable;

/**
 * Drives the hostile corpus through every form and records what happened.
 *
 * ## The invariant being tested
 *
 * Not "the form rejects bad input" — that is the concurrent hardening work's job
 * and it is landing field by field. What this asserts is the weaker, absolute
 * property that has to hold before any of that matters:
 *
 *   **`isValid()` returns a boolean. It never throws.**
 *
 * A validator that throws is worse than a validator that says yes. Saying yes
 * produces a bad row; throwing produces an uncaught exception inside a controller
 * action, which in this application means a 500 — and, because of the fatal-200
 * bug's shape, sometimes an HTTP 200 with an empty body. Either way the user's
 * whole submission is gone and there is nothing in the response saying which field
 * did it. `SionModel\Filter\ToDateTime` is the known offender: it calls
 * `new \DateTime($value)` with no try/catch, so `'asdf'` in any date field takes
 * the request down.
 *
 * The second invariant is about bounds the form declares *about itself*:
 *
 *   **A field whose value the form accepted must not hold a value longer than the
 *   `StringLength` max that same form declares for it.**
 *
 * ## Why "accepted" means per-field, not `isValid() === true`
 *
 * Every form here carries a `Csrf` element, and no CLI harness can produce a token
 * that satisfies it, so `isValid()` is false for essentially every input and a
 * check gated on it would be vacuous — green, and testing nothing. Instead the
 * input filter is asked which fields produced messages: a field with no messages is
 * a field this form accepted, whatever the verdict on the form as a whole. That is
 * both non-vacuous and a strictly stronger statement. The literal `isValid() ===
 * true` case is a subset of it and is still covered.
 *
 * ## Three phases, and why the order matters
 *
 * 1. **Sweep** — every field set to the same corpus value, one `isValid()` per
 *    value per form. Cheap (43 forms x ~45 values), and finds every throw.
 * 2. **Attribution** — only for a form/value pair that threw in the sweep: re-run
 *    with one field hostile and the rest benign, to name the field. Without this a
 *    single throwing date field reports as "the whole form throws on 'asdf'" and
 *    masks the other forty fields for that value.
 * 3. **Combination** — `COMBINATION_ROUNDS` rounds per form with each field given a
 *    different corpus value, drawn from `mt_srand(HostileInputCorpus::SEED)`. This
 *    is the only phase that uses randomness, it is the same sequence on every
 *    machine and every run, and the seed is printed. Its purpose is cross-field
 *    interaction — a validator that reads a sibling field's value, which no
 *    single-field sweep reaches.
 *
 * Boundary values are generated per field from the tightest bound that field
 * declares, so they are part of the attribution phase rather than the sweep.
 *
 * ## Attribution needs a filler that is itself safe
 *
 * The first version filled the non-probed fields with a dull ASCII string and
 * reported 4,669 findings, most of them false: `'placebo'` in *any* date field
 * throws out of `ToDateTime`, so every single-field probe threw and every field
 * looked guilty. A control run therefore comes first — all fields set to the
 * filler, nothing hostile — and a filler that throws on its own is rejected before
 * any attribution happens. `''` works because `ToDateTime` short-circuits on it.
 * When no filler survives the control run, the form throws on *benign* input and
 * that is reported as such, rather than being smeared across every field.
 *
 * ## What the baseline records, and what it does not
 *
 * The baseline unit is `Class::field throws ExceptionClass` — no corpus label. The
 * label is diagnostic, not identity: `deathDate` throwing
 * `DateMalformedStringException` is one bug with one fix whether it was `'asdf'`
 * or `'2020-02-30'` that triggered it, and folding the label in would have made the
 * baseline 4,669 lines that all move the moment anyone adds a corpus entry. The
 * labels are kept and printed for whatever fails, so nothing is lost at the moment
 * it is needed.
 */
final class HostileInputDriver
{
    /** Fillers tried, in order, for the fields a probe is not targeting. */
    private const FILLER_CANDIDATES = ['', HostileInputCorpus::BENIGN];

    /** @var list<string> */
    private array $throwing = [];

    /** @var array<string, list<string>> finding => corpus labels that produced it */
    private array $throwingDetail = [];

    /** @var list<string> */
    private array $boundViolations = [];

    private int $isValidCalls = 0;

    private int $formsDriven = 0;

    private int $boundChecks = 0;

    private bool $driven = false;

    public function __construct(
        private readonly FormRepository $repository,
        private readonly FormGapCollector $collector,
    ) {
    }

    /**
     * Places where `isValid()` threw instead of answering, as
     * `Class::field throws ExceptionClass`. Deliberately carries the exception
     * class but neither its message nor the corpus label — see the class docblock.
     *
     * @return list<string>
     */
    public function throwingInputs(): array
    {
        $this->drive();

        return $this->throwing;
    }

    /**
     * The corpus labels behind each finding, for printing when a test fails.
     *
     * @return array<string, list<string>>
     */
    public function throwingDetail(): array
    {
        $this->drive();

        return $this->throwingDetail;
    }

    /**
     * Fields the form accepted holding a value longer than the form's own declared
     * maximum. A non-empty result means the declared bound is decoration.
     *
     * @return list<string>
     */
    public function boundViolations(): array
    {
        $this->drive();

        return $this->boundViolations;
    }

    public function isValidCalls(): int
    {
        $this->drive();

        return $this->isValidCalls;
    }

    public function formsDriven(): int
    {
        $this->drive();

        return $this->formsDriven;
    }

    /**
     * How many times a field the form accepted was actually measured against a
     * declared max.
     *
     * Exists because `boundViolations()` returning `[]` has two very different
     * meanings — every bound held, or no bound was ever examined — and a test that
     * cannot tell them apart is a test that passes when the harness breaks. It was
     * 0 for the first three iterations of this file (see `attempt()` on sticky
     * messages), which is precisely why it is asserted on.
     */
    public function boundChecksPerformed(): int
    {
        $this->drive();

        return $this->boundChecks;
    }

    // ------------------------------------------------------------------ driving

    private function drive(): void
    {
        if ($this->driven) {
            return;
        }
        $this->driven = true;

        mt_srand(HostileInputCorpus::SEED);

        $corpus = HostileInputCorpus::values();

        foreach ($this->repository->forms() as $class => $form) {
            $this->formsDriven++;

            $subject = $this->validatableSubject($form);
            $fields  = array_keys($this->collector->dataElements($form));
            if ([] === $fields) {
                continue;
            }

            // Read once per form, not once per isValid(): several of these specs
            // are rebuilt from scratch on every call.
            $spec   = $this->collector->specificationOf($form);
            $filler = $this->workingFiller($subject, $fields);

            $this->sweep($class, $subject, $fields, $corpus, $filler, $spec);
            $this->probeBoundaries($class, $subject, $fields, $filler, $spec);
            $this->combine($class, $subject, $fields, $corpus, $spec);
        }

        sort($this->throwing);
        sort($this->boundViolations);

        $this->throwing                = array_values(array_unique($this->throwing));
        $this->boundViolations         = array_values(array_unique($this->boundViolations));

        foreach ($this->throwingDetail as &$labels) {
            sort($labels);
            $labels = array_values(array_unique($labels));
        }
        ksort($this->throwingDetail);
    }

    /**
     * A `Form` that can be validated, for a subject that may be a bare `Fieldset`.
     *
     * `Fieldset` has no `isValid()` and no `getInputFilter()`, so four of the
     * discovered classes cannot be driven directly — and skipping them is not an
     * option, since `SimpleDiocesanMovementFieldset` and `MassCheckoutFieldset` are
     * exactly the kind of place a hole hides. A **clone** goes into a throwaway
     * wrapper form so laminas builds the nested input filter the same way it does
     * in production; the clone matters because the original instance is shared with
     * `FormGapCollector` and adding it to a form would reparent it.
     */
    private function validatableSubject(Fieldset $subject): Form
    {
        if ($subject instanceof Form) {
            return $subject;
        }

        $wrapper = new Form('fuzz-wrapper');
        $this->repository->quietly(static function () use ($wrapper, $subject): void {
            $wrapper->add(clone $subject);
        });

        $this->wrapperTargets[spl_object_id($wrapper)] = (string) $subject->getName();

        return $wrapper;
    }

    /**
     * The first filler whose all-fields control run does not throw, or null when
     * none of them survive. See the class docblock: attribution is meaningless
     * without this.
     *
     * @param list<string> $fields
     */
    private function workingFiller(Form $subject, array $fields): ?string
    {
        foreach (self::FILLER_CANDIDATES as $candidate) {
            $result = $this->attempt($subject, array_fill_keys($fields, $candidate));
            if (null === $result['throwable']) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Phase 1 and 2. Every field gets $value; if that throws, find out which field.
     *
     * @param list<string>         $fields
     * @param array<string, mixed> $corpus
     * @param array<string, mixed> $spec
     */
    private function sweep(
        string $class,
        Form $subject,
        array $fields,
        array $corpus,
        ?string $filler,
        array $spec
    ): void {
        foreach ($corpus as $label => $value) {
            $data   = array_fill_keys($fields, $value);
            $result = $this->attempt($subject, $data);

            if (null !== $result['throwable']) {
                $this->attribute($class, $subject, $fields, $label, $value, $filler, $result['throwable'], $spec);
                continue;
            }

            $this->inspectAccepted($class, $spec, $result['messages'], $result['values'], $label);
        }
    }

    /**
     * Phase 2. One hostile field, the rest filled with a value proven harmless.
     *
     * @param list<string>         $fields
     * @param array<string, mixed> $spec
     */
    private function attribute(
        string $class,
        Form $subject,
        array $fields,
        string $label,
        mixed $value,
        ?string $filler,
        string $sweepThrowable,
        array $spec
    ): void {
        if (null === $filler) {
            // Even an all-benign submission throws, so no single field can be
            // blamed and the form is broken for every input, not just hostile ones.
            $this->record(
                sprintf('%s::<any input> throws %s even with no hostile value present', $class, $sweepThrowable),
                $label
            );
            return;
        }

        $named = false;

        foreach ($fields as $field) {
            $data         = array_fill_keys($fields, $filler);
            $data[$field] = $value;

            $result = $this->attempt($subject, $data);

            if (null !== $result['throwable']) {
                $named = true;
                $this->record(
                    sprintf('%s::%s throws %s', $class, $field, $result['throwable']),
                    $label
                );
                continue;
            }

            $this->inspectAccepted($class, $spec, $result['messages'], $result['values'], $label);
        }

        if (! $named) {
            // The whole-form value threw but no single field reproduces it: an
            // interaction, and worth saying so rather than dropping the finding.
            $this->record(
                sprintf('%s::<cross-field> throws %s only when several fields carry it', $class, $sweepThrowable),
                $label
            );
        }
    }

    /**
     * Boundary probing: for each field with a declared bound, push exactly-at,
     * one-over and multibyte-at values through it alone.
     *
     * @param list<string>         $fields
     * @param array<string, mixed> $spec
     */
    private function probeBoundaries(
        string $class,
        Form $subject,
        array $fields,
        ?string $filler,
        array $spec
    ): void {
        if (null === $filler) {
            return;
        }

        foreach ($fields as $field) {
            $max = FormGapCollector::declaredMaxLength($spec[$field] ?? null);
            foreach (HostileInputCorpus::boundaryValues($max) as $label => $value) {
                $data         = array_fill_keys($fields, $filler);
                $data[$field] = $value;

                $result = $this->attempt($subject, $data);

                if (null !== $result['throwable']) {
                    $this->record(
                        sprintf('%s::%s throws %s', $class, $field, $result['throwable']),
                        $label
                    );
                    continue;
                }

                $this->inspectAccepted($class, $spec, $result['messages'], $result['values'], $label);
            }
        }
    }

    /**
     * Phase 3. Seeded cross-field combinations.
     *
     * @param list<string>         $fields
     * @param array<string, mixed> $corpus
     * @param array<string, mixed> $spec
     */
    private function combine(string $class, Form $subject, array $fields, array $corpus, array $spec): void
    {
        $labels = array_keys($corpus);

        for ($round = 0; $round < HostileInputCorpus::COMBINATION_ROUNDS; $round++) {
            $data = [];
            foreach ($fields as $field) {
                $label        = $labels[mt_rand(0, count($labels) - 1)];
                $data[$field] = $corpus[$label];
            }

            $result = $this->attempt($subject, $data);

            if (null !== $result['throwable']) {
                $this->record(
                    sprintf('%s::<combination> throws %s', $class, $result['throwable']),
                    'seeded round ' . $round
                );
                continue;
            }

            $this->inspectAccepted($class, $spec, $result['messages'], $result['values'], 'combination');
        }
    }

    private function record(string $finding, string $label): void
    {
        $this->throwing[]                 = $finding;
        $this->throwingDetail[$finding][] = $label;
    }

    /**
     * One setData()/isValid() cycle, with PHP diagnostics swallowed and any
     * Throwable captured.
     *
     * `Form::getData()` throws `DomainException` unless the form validated, and the
     * form never validates here because of the CSRF element — so the answer has to
     * be per-field, and both halves of it come from the last validation rather than
     * from the elements. See the comment inside on why.
     *
     * @param array<string, mixed> $data
     * @return array{throwable: ?string, messages: array<string, mixed>, values: array<string, mixed>}
     */
    private function attempt(Form $form, array $data): array
    {
        $this->isValidCalls++;
        $envelope = $this->envelopeFor($form, $data);

        $raw = $this->repository->quietly(static function () use ($form, $envelope): array {
            try {
                $form->setData($envelope);

                $valid = $form->isValid();

                if (! is_bool($valid)) {
                    // The invariant is stated as "returns a boolean", so a
                    // non-boolean is a finding in its own right, not a cast.
                    return [
                        'throwable' => 'NON-BOOLEAN-RETURN(' . get_debug_type($valid) . ')',
                        'messages'  => [],
                        'values'    => [],
                    ];
                }

                // Never `Form::getMessages()`. `Fieldset::setMessages()` only touches
                // the elements named in the set it is given, so an element that failed
                // on an earlier call and passes on this one **keeps its stale messages
                // forever**. Driving one shared form instance through 45 corpus values
                // therefore accumulates messages until every field looks rejected, and
                // the bound check silently measures nothing (0 checks performed, 0
                // violations, green — which is what testTheBoundCheckActuallyMeasures
                // Something exists to catch).
                //
                // This used to read `getInputFilter()->getMessages()` for that reason,
                // because BaseInputFilter rebuilds its invalid-input list on every call.
                // `SionModel\Form\Form` builds a fresh engine per validation and keeps
                // its result, which is the same guarantee from the filter the application
                // actually runs — and the laminas filter stopped being that filter when
                // the engine was cut over, at which point this returned nothing at all.
                return [
                    'throwable' => null,
                    'messages'  => $form->validationMessages(),
                    'values'    => $form->getData(),
                ];
            } catch (Throwable $e) {
                return ['throwable' => $e::class, 'messages' => [], 'values' => []];
            }
        });

        return [
            'throwable' => $raw['throwable'],
            'messages'  => $this->unwrap($form, $raw['messages']),
            'values'    => $this->unwrap($form, $raw['values']),
        ];
    }

    /**
     * The fieldset a wrapper form was built around, so data can be nested under its
     * name and the results unnested again.
     *
     * @var array<int, string> wrapper form object id => fieldset name
     */
    private array $wrapperTargets = [];

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function envelopeFor(Form $form, array $data): array
    {
        $target = $this->wrapperTargets[spl_object_id($form)] ?? null;

        return null === $target ? $data : [$target => $data];
    }

    /**
     * Undo `envelopeFor()` on a result array.
     *
     * Explicitly keyed off the recorded wrapper name rather than "unwrap any array
     * that happens to have one key", which is what this method used to do and which
     * was wrong in a way that manufactured false findings: whenever exactly one
     * field had messages, `['inLanguage' => [...]]` was unwrapped to
     * `['stringLengthTooLong' => '…']`, the field name vanished, and the bound check
     * concluded that a field it had just watched be rejected had been accepted.
     * `Books\Form\SearchForm::inLanguage` was duly reported as accepting three
     * characters against a max of two; in isolation it rejects them correctly.
     *
     * @param array<array-key, mixed> $result
     * @return array<string, mixed>
     */
    private function unwrap(Form $form, array $result): array
    {
        $target = $this->wrapperTargets[spl_object_id($form)] ?? null;

        if (null !== $target && is_array($result[$target] ?? null)) {
            /** @var array<string, mixed> */
            return $result[$target];
        }

        /** @var array<string, mixed> */
        return $result;
    }

    /**
     * For every field the form raised no message about, check the value it kept
     * against the bounds the form and the schema declare.
     *
     * @param array<string, mixed> $spec
     * @param array<string, mixed> $messages
     * @param array<string, mixed> $values
     */
    private function inspectAccepted(
        string $class,
        array $spec,
        array $messages,
        array $values,
        string $label
    ): void {
        foreach ($values as $field => $value) {
            if (! is_string($field) || isset($messages[$field])) {
                continue;
            }
            if (! is_string($value) || '' === $value) {
                continue;
            }

            $max = FormGapCollector::declaredMaxLength($spec[$field] ?? null);
            if (null === $max) {
                continue;
            }

            $this->boundChecks++;

            $length = self::characterLength($value);
            if ($length > $max) {
                $this->boundViolations[] = sprintf(
                    '%s::%s accepted %d characters against its own declared max of %d (input %s)',
                    $class,
                    $field,
                    $length,
                    $max,
                    $label
                );
            }
        }
    }

    /**
     * Character count, falling back to byte count for input that is not valid
     * UTF-8 — which several corpus entries deliberately are not, and where
     * `mb_strlen` returns a number that means nothing.
     */
    private static function characterLength(string $value): int
    {
        if (! mb_check_encoding($value, 'UTF-8')) {
            return strlen($value);
        }

        return mb_strlen($value, 'UTF-8');
    }
}
