<?php

declare(strict_types=1);

namespace App\Books\Import;

use SionModel\Form\Fieldset;
use SionModel\Form\InputFilterProviderInterface;
use SionModel\Form\ChoiceDomain;

/**
 * One select per importable field, each offering the uploaded file's own columns.
 *
 * ## Why a class rather than the `new Fieldset('map')` this replaced
 *
 * `Form::prepare()` renames a fieldset's children to `map[title]`, so the post arrives as
 * one nested array and `setData()` binds it without the controller unpicking a naming
 * convention. That much was true of the anonymous fieldset too, and is why it existed.
 *
 * What an anonymous `Fieldset` cannot do is carry a specification. It is not an
 * `InputFilterProviderInterface`, so `Form::attachInputFilterDefaults()` builds its nested
 * filter out of the *elements* alone — nineteen selects, each contributing its own
 * `InArray` and nothing else. That works exactly as long as `Laminas\InputFilter` is what
 * assembles the filter, and stops the day `SionModel\Form\Validation\InputFilter` does:
 * the engine reads specifications, and a fieldset with no specification is a fieldset with
 * no rules. Naming the class is what gives it somewhere to say them.
 *
 * ## What the domain is, and why it is not written out here
 *
 * The options are that file's own columns — `0`, `1`, `2`… for the sheet's column indices,
 * plus {@see ImportMappingForm::UNMAPPED} for "no column feeds this field" — so the
 * legitimate domain is different for every upload and cannot be a literal. `ChoiceDomain`
 * reads it back off the element at validation time, which is the case that class exists
 * for.
 */
final class ImportMappingFieldset extends Fieldset implements InputFilterProviderInterface
{
    /** The fieldset's name, which is also the key the post arrives under. */
    public const NAME = 'map';

    /**
     * @param array<string, string> $columnOptions value options shared by every select:
     *        the uploaded sheet's columns, keyed by column index as a string
     */
    public function __construct(array $columnOptions, ColumnMap $map)
    {
        parent::__construct(self::NAME);

        foreach (ImportColumns::all() as $column) {
            $index = $map->indexOf($column->field);
            $this->add([
                'name'    => $column->field,
                'type'    => 'Select',
                'options' => [
                    'label'         => $column->heading . ($column->required ? ' *' : ''),
                    'value_options' => $columnOptions,
                    'help-block'    => $column->help,
                ],
            ]);
            $this->get($column->field)->setValue(
                null === $index ? ImportMappingForm::UNMAPPED : (string) $index
            );
        }
    }

    /**
     * Every field optional and every field constrained to the columns it was offered.
     *
     * ## `required => false` is a fix, not parity
     *
     * `Laminas\Form\Element\Select::getInputSpecification()` declared `required => true`,
     * and an anonymous fieldset has nothing to say otherwise — so every one of these
     * nineteen selects was required, while {@see ImportMappingForm::UNMAPPED} is the empty
     * string. A required field with an empty value gets an injected `NotEmpty` and fails.
     *
     * The consequence, reproduced before this class was written: **leaving any field
     * "— not imported —" made the mapping screen unsavable.** A spreadsheet feeds three or
     * four of these fields, so in practice every submission failed, and the controller
     * answered "Error in form submission, please review." with the per-field messages
     * buried in a fieldset the template does not render. Nothing in the page said which
     * field, or that the answer it was rejecting is the correct one.
     *
     * Optional is what the screen means: "no column feeds this field" is a legitimate
     * answer for all but the required ones, and the required ones are not enforced here.
     * `LibraryImporter::plan()` checks `ColumnMap::missingRequired()` and returns a blocker
     * naming the missing *headings*, which is what the preview page shows. A per-field
     * `required` would report the same fact one field at a time, on the wrong page, in
     * internal field names.
     *
     * @return array<string, mixed>
     */
    public function getInputFilterSpecification(): array
    {
        $spec = [];
        foreach (ImportColumns::all() as $column) {
            $spec[$column->field] = [
                'required'   => false,
                'validators' => ChoiceDomain::validators($this->get($column->field)),
            ];
        }

        return $spec;
    }
}
