<?php

declare(strict_types=1);

namespace App\Books\Import;

use SionModel\Form\Form;
use SionModel\Form\InputFilterProviderInterface;
use SionModel\Form\ChoiceDomain;
use SionModel\Form\CsrfSpec;

use function chr;
use function count;
use function is_string;
use function sprintf;
use function trim;

/**
 * Which worksheet, and which column feeds which field.
 *
 * The screen the old import never had. `getColegioMayorLibraryFieldsMap()` decided
 * both questions for every library in the application, and a spreadsheet that did not
 * match answered `Missing required fields for the excel file: withinLibraryId, title`
 * — an uncaught exception, rendered as a 500, naming two internal field names.
 *
 * The selects are built from the file that was actually uploaded, so the options are
 * that file's own worksheets and that file's own column headings. HeaderMatcher has
 * already guessed; this is the chance to disagree with it.
 */
final class ImportMappingForm extends Form implements InputFilterProviderInterface
{
    /** The option meaning "no column feeds this field". */
    public const UNMAPPED = '';

    /**
     * @param list<string> $worksheets the file's own sheet names
     * @param list<string|null> $headers the chosen sheet's header row
     */
    public function __construct(array $worksheets, array $headers, ColumnMap $map, string $worksheet)
    {
        parent::__construct('import_mapping');

        $this->setAttribute('method', 'POST');

        $this->add([
            'name'    => 'security',
            'type'    => 'csrf',
            'options' => ['csrf_options' => ['timeout' => 900]],
        ]);

        $sheetOptions = [];
        foreach ($worksheets as $name) {
            $sheetOptions[$name] = $name;
        }
        $this->add([
            'name'    => 'worksheet',
            'type'    => 'Select',
            'options' => [
                'label'         => 'Worksheet',
                'value_options' => $sheetOptions,
                'help-block'    => 1 === count($worksheets)
                    ? 'This file has one sheet.'
                    : 'This file has several sheets. Choose the one holding the books.',
            ],
        ]);
        $this->get('worksheet')->setValue($worksheet);

        $columnOptions = [self::UNMAPPED => '— not imported —'];
        foreach ($headers as $index => $heading) {
            $label = is_string($heading) && '' !== trim($heading) ? trim($heading) : '(no heading)';
            //The column letter is in the label because that is how a librarian will find
            //it in Excel; two columns can share a heading, and the letter tells them apart.
            $columnOptions[(string) $index] = sprintf('%s — %s', self::letter($index), $label);
        }

        //A fieldset rather than nineteen top-level elements: `Form::prepare()` renames a
        //fieldset's children to `map[title]`, so the post arrives as one nested array and
        //`setData()` binds it without the controller unpicking a naming convention.
        $this->add(new ImportMappingFieldset($columnOptions, $map));

        $this->add([
            'name'       => 'submit',
            'type'       => 'Submit',
            'attributes' => [
                'value' => 'Save and preview',
                'id'    => 'submit',
                'class' => 'btn-primary',
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getInputFilterSpecification(): array
    {
        return [
            //Restated rather than left to the element — see SionModel\Form\CsrfSpec.
            'security'  => CsrfSpec::forElement($this->get('security')),
            'worksheet' => [
                //Both restate what the element already supplies: `Select::getInputSpecification()`
                //declares `required => true` and an `InArray` over its own options. Written
                //out because the domain is this file's own sheet names, so it can only be
                //read off the element, and because the engine that replaces
                //`Laminas\InputFilter` reads the specification and nothing else.
                //
                //`known-form-gaps.php` lists `worksheet` as element-validated anyway, and
                //that entry is a property of the harness rather than of this form: nothing
                //registers this form, so `FormRepository` builds it by shape with an empty
                //`$worksheets`, and `ChoiceDomain` declines to constrain a field whose
                //options it cannot see. In every request there is an uploaded file and the
                //domain is its sheets. `Books\Form\SearchForm::collectionId` has the same
                //shape, and was a named exception in the parity tests that measured the
                //engine against `Laminas\InputFilter` before both left.
                'required'   => true,
                'validators' => ChoiceDomain::validators($this->get('worksheet')),
            ],
        ];
    }

    /** Excel's own column name for a zero-based index: 0 => A, 26 => AA. */
    public static function letter(int $index): string
    {
        $letter = '';
        $index++;
        while ($index > 0) {
            $remainder = ($index - 1) % 26;
            $letter     = chr(65 + $remainder) . $letter;
            $index      = (int) (($index - $remainder - 1) / 26);
        }

        return $letter;
    }
}
