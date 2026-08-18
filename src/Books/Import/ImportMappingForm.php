<?php

declare(strict_types=1);

namespace App\Books\Import;

use Laminas\Form\Fieldset;
use Laminas\Form\Form;

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
 *
 * @extends Form<array<string, mixed>>
 */
final class ImportMappingForm extends Form
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

        //A Fieldset rather than nineteen top-level elements: `Form::prepare()` renames a
        //fieldset's children to `map[title]`, so the post arrives as one nested array and
        //`setData()` binds it without the controller unpicking a naming convention.
        $fields = new Fieldset('map');
        foreach (ImportColumns::all() as $column) {
            $index = $map->indexOf($column->field);
            $fields->add([
                'name'    => $column->field,
                'type'    => 'Select',
                'options' => [
                    'label'         => $column->heading . ($column->required ? ' *' : ''),
                    'value_options' => $columnOptions,
                    'help-block'    => $column->help,
                ],
            ]);
            $fields->get($column->field)->setValue(null === $index ? self::UNMAPPED : (string) $index);
        }
        $this->add($fields);

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
