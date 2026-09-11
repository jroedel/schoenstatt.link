<?php

declare(strict_types=1);

namespace SchoenstattTest\Form;

use Laminas\Form\Element\Collection;
use Laminas\Form\ElementInterface;
use Laminas\Form\Fieldset;
use SionModel\Form\Element\Button;
use SionModel\Form\Element\Checkbox;
use SionModel\Form\Element\Csrf;
use SionModel\Form\Element\Date;
use SionModel\Form\Element\DateSelect;
use SionModel\Form\Element\Email;
use SionModel\Form\Element\File;
use SionModel\Form\Element\Number;
use SionModel\Form\Element\Phone;
use SionModel\Form\Element\Select;
use SionModel\Form\Element\Submit;
use SionModel\Form\Element\Textarea;
use SionModel\Form\Element\Url;

use function array_keys;

/**
 * One value per field, chosen by element type, for the two states that carry data.
 *
 * ## Why it is by type rather than per form
 *
 * 477 surfaces across 43 forms, and the point of the baseline is that a form added next
 * month is recorded without anyone remembering to add it — the same reason
 * `test/Fuzz/FormRepository` discovers by filesystem scan and never by a list. A table of
 * field names would be correct on the day it was written and silently wrong after.
 *
 * ## The valid half exercises escaping; the invalid half exercises the validators
 *
 * `populated` wants a value that **renders**: something with `<`, `&`, a quote and an
 * apostrophe in it, so that a change in how a value reaches an attribute or a textarea's
 * body shows up as bytes. It does not want to be valid in any deeper sense — nothing
 * validates that state.
 *
 * `invalid` wants a value every validator **rejects**, per type, so that the message
 * `BootstrapFormRenderer::errors()` renders is the validator's own: an address that is not
 * one for `EmailAddress`, a word where `Digits` wants digits, a key that is in no haystack
 * for `InArray`, a token that is not the session's for `Csrf`. Empty strings would produce
 * one message shape — "value is required and can't be empty" — from the handful of fields
 * that are required, and would record nothing at all about the twenty validator classes
 * that leave with laminas-validator.
 *
 * The strings are literals rather than anything derived from the element, deliberately: a
 * value built out of a field's own name would make every recorded value unique and the
 * file unreadable, and a diff across the swap would be 477 distinct strings instead of one
 * repeated one.
 */
final class FormData
{
    /** Carries `<`, `&`, a double quote and an apostrophe: everything the escapers touch. */
    public const VALID_TEXT = 'Bäseline <b>value</b> & "quoted" \'apostrophe\'';

    /** Two lines, so a textarea's body is not a single-line case. */
    public const VALID_TEXTAREA = "Bäseline <b>value</b> & \"quoted\"\nsecond line";

    /** Short on purpose: it is echoed back into the re-rendered control in every invalid row. */
    public const INVALID_TEXT = '<script>alert(1)</script>';

    /**
     * Data shaped the way `setData()` wants it, for one fieldset and everything under it.
     *
     * @return array<string, mixed>
     */
    public static function forFieldset(Fieldset $fieldset, bool $valid): array
    {
        $data = [];

        foreach ($fieldset->getElements() as $name => $element) {
            $value = self::forElement($element, $valid);
            if (null !== $value) {
                $data[(string) $name] = $value;
            }
        }

        foreach ($fieldset->getFieldsets() as $name => $child) {
            if ($child instanceof Collection) {
                $data[(string) $name] = self::forCollection($child, $valid);
                continue;
            }

            $data[(string) $name] = self::forFieldset($child, $valid);
        }

        return $data;
    }

    /**
     * A collection gets **one** item, built from its target element.
     *
     * One rather than none because the mass-checkout form's rows only exist once data
     * arrives — `library-mass-checkout.html.twig` iterates `form.get('checkout')`, so with
     * no item the loop renders nothing and the collection's three fields would be recorded
     * only as the `<target>` template they are built from. One rather than several because
     * the second row's markup is the first's with an index incremented.
     *
     * @return list<array<string, mixed>>
     */
    private static function forCollection(Collection $collection, bool $valid): array
    {
        $target = $collection->getTargetElement();
        if (! $target instanceof Fieldset) {
            return [];
        }

        return [self::forFieldset($target, $valid)];
    }

    /**
     * The value for one element, or null when the element takes none.
     *
     * A submit, a button and a file take none: the first two carry their caption in the
     * markup and a browser posts a file part rather than a string, and inventing either
     * would record a control the application never renders.
     */
    private static function forElement(ElementInterface $element, bool $valid): mixed
    {
        return match (true) {
            $element instanceof Submit, $element instanceof Button, $element instanceof File => null,
            //The element's own token, which the session validator accepts and
            //FormMarkup normalises away; anything else for the state that wants the
            //`Csrf` validator's message.
            $element instanceof Csrf => $valid ? $element->getValue() : 'not-this-sessions-token',
            $element instanceof Checkbox => $valid
                ? $element->getCheckedValue()
                : 'neither-checked-nor-unchecked',
            $element instanceof Select => self::forSelect($element, $valid),
            $element instanceof DateSelect => $valid
                ? ['day' => '02', 'month' => '01', 'year' => '2020']
                : ['day' => '99', 'month' => '99', 'year' => 'not-a-year'],
            $element instanceof Date => $valid ? '2020-01-02' : 'not-a-date',
            $element instanceof Number => $valid ? '42' : 'forty-two',
            $element instanceof Email => $valid ? 'baseline@example.com' : 'not-an-email',
            $element instanceof Url => $valid ? 'https://example.com/baseline' : 'not a url',
            $element instanceof Phone => $valid ? '+1 202 555 0100' : 'not a phone number',
            $element instanceof Textarea => $valid
                ? self::VALID_TEXTAREA
                : self::INVALID_TEXT,
            default => $valid ? self::VALID_TEXT : self::INVALID_TEXT,
        };
    }

    /**
     * The first real option for the valid state, a key no haystack holds for the invalid
     * one, and an array of either when the select is multiple.
     *
     * The first option is skipped when it is the empty one: an `empty_option` is the
     * "please choose" placeholder, so selecting it is the same as selecting nothing and
     * the state would record no `selected` attribute at all — which is the one thing this
     * state exists to render.
     */
    private static function forSelect(Select $element, bool $valid): mixed
    {
        $value = $valid ? self::firstRealOption($element) : 'no-such-option';

        return $element->isMultiple() ? [$value] : $value;
    }

    private static function firstRealOption(Select $element): string
    {
        foreach (array_keys($element->getValueOptions()) as $key) {
            if ('' !== (string) $key) {
                return (string) $key;
            }
        }

        return '';
    }
}
