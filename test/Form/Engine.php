<?php

declare(strict_types=1);

namespace SchoenstattTest\Form;

use Laminas\Form\Fieldset;
use SionModel\Form\Validation\FormSpecification;
use SionModel\Form\Validation\InputFilter;

use function array_key_exists;
use function is_array;

/**
 * The thing that decides whether a submission is valid, for a test that wants to ask it.
 *
 * ## Why this exists
 *
 * Until #239 the answer was `Laminas\Form\Form::getInputFilter()`, and seven test classes
 * still asked it: is this field required, what haystack constrains it, what does the form
 * do with an empty string, which keys does it write. **Production stopped consulting that
 * object at #239** — `SionModel\Form\Form::isValid()` runs
 * {@see \SionModel\Form\Validation\InputFilter} over
 * {@see \SionModel\Form\Validation\FormSpecification} — so a test reading the assembled
 * filter has been measuring something no request reaches.
 *
 * That is not only stale, it is misleading in a specific direction: laminas merges each
 * element's own input specification into the assembled filter, and the engine reads the
 * form's specification and nothing else. So the assembled filter answers "required" and
 * "constrained" for fields the engine does not, which is exactly the set
 * `test/Fuzz/known-form-gaps.php` records as accepted gaps. A test asking the old object
 * reports the stricter answer and passes.
 *
 * Iteration A forces the issue — `getInputFilter()` leaves with `Laminas\Form\Form` — but
 * the reason to move is that the engine is what runs.
 *
 * ## Why a helper rather than two lines per test
 *
 * Both lines are easy to get subtly wrong in the same direction. A spec taken from a
 * *fieldset* rather than the form, or an engine built with a rule set other than
 * `withLaminasRules()`, answers plausibly and differently; and a test that builds its own
 * pair is a test that can drift from what `SionModel\Form\Form::isValid()` does. One seam,
 * named after what it is.
 */
final class Engine
{
    /**
     * The engine `SionModel\Form\Form::isValid()` would build for this form.
     *
     * @param list<string> $without specification keys to drop first. `security` is the
     *        only one any caller drops: a test driving one field through the engine would
     *        otherwise fail every case on the CSRF token, which no test process holds.
     */
    public static function of(Fieldset $form, array $without = []): InputFilter
    {
        return InputFilter::withLaminasRules(self::specificationOf($form, $without));
    }

    /**
     * The specification behind that engine, as plain data.
     *
     * Where the old object answered `has($name)`, `get($name)->isRequired()` and
     * `getValidatorChain()->getValidators()`, this answers `array_key_exists($name, $spec)`,
     * `$spec[$name]['required']` and `$spec[$name]['validators']` — the same facts, in the
     * form the engine actually holds them.
     *
     * @param list<string> $without
     * @return array<string, mixed>
     */
    public static function specificationOf(Fieldset $form, array $without = []): array
    {
        $spec = FormSpecification::of($form);
        foreach ($without as $name) {
            unset($spec[$name]);
        }

        return $spec;
    }

    /**
     * Whether the specification requires this field.
     *
     * A key with no `required` is not required: `FormSpecification::of()` writes
     * `['required' => false]` for an element the specification does not name, and the
     * engine reads a missing key the same way.
     *
     * @param array<string, mixed> $spec
     */
    public static function requires(array $spec, string $name): bool
    {
        $rules = $spec[$name] ?? null;

        return is_array($rules) && array_key_exists('required', $rules) && true === $rules['required'];
    }

    /**
     * The validators the specification names for one field, by short name.
     *
     * @param array<string, mixed> $spec
     * @return list<array<string, mixed>> each entry as the specification writes it
     */
    public static function validatorsFor(array $spec, string $name): array
    {
        $rules = $spec[$name] ?? null;
        if (! is_array($rules) || ! isset($rules['validators']) || ! is_array($rules['validators'])) {
            return [];
        }

        $validators = [];
        foreach ($rules['validators'] as $validator) {
            if (is_array($validator)) {
                $validators[] = $validator;
            }
        }

        return $validators;
    }
}
