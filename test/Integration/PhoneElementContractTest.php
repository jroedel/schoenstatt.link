<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use SionModel\Filter\StringTrim;
use SionModel\Filter\StripNewlines;
use SionModel\Filter\ToNull;
use SionModel\Form\Fieldset;
use PHPUnit\Framework\TestCase;
use SchoenstattTest\Fuzz\FormRepository;
use SionModel\Form\Element\Phone;
use SionModel\Form\Validation\FormSpecification;
use SionModel\Validator\Phone as PhoneValidator;

use function array_column;
use function implode;
use function in_array;
use function is_array;
use function sprintf;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Fuzz/FormRepository.php';

/**
 * A phone field is still held to the phone rules, now that its element states none.
 *
 * ## What changed under this test
 *
 * `SionModel\Form\Element\Phone` used to implement `InputProviderInterface` and hand the
 * input filter a `StringTrim`, a `StripNewlines`, a `ToNull` and `SionModel\Validator\Phone`.
 * It states nothing now — no element in the model does — so the question this file used to
 * answer ("is the element's input specification unchanged?") has no subject.
 *
 * The question worth answering is the one behind it: **are the seven phone fields on this
 * site still filtered and validated the way they were?** That is no longer a property of the
 * element class, so it cannot be tested on one. It is a property of every form that has a
 * phone field, and it is tested on all of them.
 *
 * ## Why this is not a duplicate of the fuzz harness
 *
 * `test/Fuzz/known-form-gaps.php` tracks `filteringSuppliedOnlyByElement` and
 * `validationSuppliedOnlyByElement`, and both were emptied of phone fields before the
 * element changed — that is what made the change safe. But those categories compare a form
 * against *its own elements*: once an element supplies nothing, they are satisfied by a
 * specification that also supplies nothing. This names the four rules and looks for them.
 */
final class PhoneElementContractTest extends TestCase
{
    /** What a phone field's filters have to be, in order. */
    private const FILTERS = [StringTrim::class, StripNewlines::class, ToNull::class];

    /**
     * A floor, because the assertions are inside a loop over discovered elements. Seven
     * today: four on a person, three on an association.
     */
    private const PHONE_FLOOR = 6;

    public function testEveryPhoneFieldStatesThePhoneRulesInItsFormSpecification(): void
    {
        $checked  = 0;
        $problems = [];

        foreach (FormRepository::instance()->forms() as $class => $form) {
            if (! $form instanceof Fieldset) {
                continue;
            }

            $spec = FormSpecification::of($form);

            foreach ($form->getElements() as $name => $element) {
                if (! $element instanceof Phone) {
                    continue;
                }

                $checked++;
                $path  = $class . '::' . (string) $name;
                $rules = $spec[(string) $name] ?? null;

                if (! is_array($rules)) {
                    $problems[] = sprintf('%s: the specification has no entry for it at all', $path);
                    continue;
                }

                $filters = array_column(
                    is_array($rules['filters'] ?? null) ? $rules['filters'] : [],
                    'name'
                );
                if (self::FILTERS !== $filters) {
                    $problems[] = sprintf(
                        '%s: filters are [%s], expected [%s]',
                        $path,
                        implode(', ', $filters),
                        implode(', ', self::FILTERS)
                    );
                }

                $validators = array_column(
                    is_array($rules['validators'] ?? null) ? $rules['validators'] : [],
                    'name'
                );
                if (! in_array(PhoneValidator::class, $validators, true)) {
                    $problems[] = sprintf(
                        '%s: no phone validator among [%s]',
                        $path,
                        implode(', ', $validators)
                    );
                }
            }
        }

        self::assertGreaterThanOrEqual(
            self::PHONE_FLOOR,
            $checked,
            'almost no phone field was found, so this test proves nothing'
        );

        self::assertSame(
            [],
            $problems,
            "A phone field lost a rule when the element stopped supplying one:\n  "
            . implode("\n  ", $problems)
        );
    }

    /** The one thing the element still says for itself. */
    public function testRendersAsATelephoneInput(): void
    {
        $element = new Phone('homePhone');

        self::assertSame('tel', $element->getAttribute('type'));
        self::assertSame('homePhone', $element->getAttribute('name'));
    }

    /**
     * End to end through the validator the specifications name, so those names are not
     * merely spelled right but actually reject a bad number.
     */
    public function testThePhoneValidatorAcceptsAndRejects(): void
    {
        $validator = new PhoneValidator();

        self::assertTrue($validator->isValid('+1 (555) 123-4567'));
        self::assertFalse($validator->isValid('555-1234'));
    }
}
