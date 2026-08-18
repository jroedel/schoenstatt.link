<?php

declare(strict_types=1);

namespace App\Books\Import;

use Closure;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

use function array_map;
use function array_slice;
use function count;
use function implode;
use function is_array;
use function is_bool;
use function is_scalar;
use function preg_replace;
use function str_contains;
use function strlen;
use function strtolower;
use function trim;
use function vsprintf;

/**
 * The spreadsheet a librarian downloads, fills in, and uploads back.
 *
 * The reason this feature exists at all. Adding books one at a time through the web
 * form is slow enough that Colegio Mayor has always catalogued in a spreadsheet and
 * imported; every other library had no way in, because the import matched exactly one
 * library's Spanish column headings and the file had to already be on the server. A
 * blank template turns "guess what columns it wants" into "open this and type".
 *
 * ## Two sheets, and the second one is the documentation
 *
 * The data sheet carries only the core columns — see ImportColumn::$core — because a
 * column that is present and empty **erases** the stored value. Handing a librarian a
 * template with nineteen columns would hand them a way to blank the admin notes of
 * every book they touch.
 *
 * The instructions sheet is where the rules live, translated into the language the
 * page was requested in. Prose in the application that a librarian only needs while
 * looking at the file is prose they will not read; prose in cell A1 of the file is.
 *
 * ## Filled or blank
 *
 * `build()` with books produces the library's current catalogue in exactly the layout
 * the importer reads, which is how a librarian corrects two hundred records: export,
 * edit in Excel, upload. It is also what a *complete* import needs — the sweep
 * inactivates every active book the sheet does not mention, so the sheet has to start
 * from what is there.
 */
final class ImportTemplate
{
    /** The header row, in every generated file. */
    private const HEADER_ROW = 1;

    /** Beyond this many characters Excel refuses an inline list, so the dropdown is dropped. */
    private const MAX_VALIDATION_LENGTH = 200;

    /**
     * @param Closure(string): string $translate the page-aware translator every ported
     *        route renders through, i.e. App\Twig\LaminasExtension::translate()
     */
    public function __construct(private readonly Closure $translate)
    {
    }

    /**
     * @param string $libraryName shown on the instructions sheet
     * @param list<string> $collections the library's collection names, for the dropdown
     * @param list<array<string, mixed>> $books book entities to pre-fill, or none
     */
    public function build(string $libraryName, array $collections, array $books = []): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setTitle($this->t('Library import'))
            ->setSubject($libraryName)
            ->setDescription($this->t('Fill this in and upload it on the library imports page.'));

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->t('Books'));
        $this->writeHeader($sheet);
        $this->writeBooks($sheet, $books);
        $this->applyValidation($sheet, $collections, count($books));

        $this->writeInstructions($spreadsheet, $libraryName, $collections);
        $spreadsheet->setActiveSheetIndex(0);
        $sheet->setSelectedCell('A2');

        return $spreadsheet;
    }

    /**
     * A filename that says which library and, for a filled export, which day.
     *
     * Slugged rather than passed through: a library called `Schoenstatt University Men
     * - Austin, TX` in a Content-Disposition header is a quoting problem nobody needs.
     */
    public static function filename(string $libraryName, bool $filled, string $today): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $libraryName), '-'));
        if ('' === $slug) {
            $slug = 'library';
        }

        return $filled
            ? $slug . '-books-' . $today . '.xlsx'
            : $slug . '-import-template.xlsx';
    }

    private function writeHeader(Worksheet $sheet): void
    {
        $column = 1;
        foreach (ImportColumns::core() as $spec) {
            $cell = Coordinate::stringFromColumnIndex($column) . self::HEADER_ROW;
            //Written as an explicit string: a heading like `ISBN` is harmless, but a
            //library that renames one to something Excel reads as a formula or a date
            //would corrupt the very row the importer matches on.
            $sheet->getCell($cell)->setValueExplicit($spec->heading, DataType::TYPE_STRING);
            $sheet->getComment($cell)->getText()->createTextRun($this->t($spec->help));
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setWidth($spec->width);
            $column++;
        }

        $last  = Coordinate::stringFromColumnIndex($column - 1);
        $style = $sheet->getStyle('A' . self::HEADER_ROW . ':' . $last . self::HEADER_ROW);
        $style->getFont()->setBold(true);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DDEBF7');
        $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);

        //Required columns are marked in the file itself, not only in the instructions.
        $column = 1;
        foreach (ImportColumns::core() as $spec) {
            if ($spec->required) {
                $cell = Coordinate::stringFromColumnIndex($column) . self::HEADER_ROW;
                $sheet->getStyle($cell)->getFont()->getColor()->setRGB('9C0006');
            }
            $column++;
        }

        $sheet->freezePane('A' . (self::HEADER_ROW + 1));
    }

    /** @param list<array<string, mixed>> $books */
    private function writeBooks(Worksheet $sheet, array $books): void
    {
        $row = self::HEADER_ROW + 1;
        foreach ($books as $book) {
            $column = 1;
            foreach (ImportColumns::core() as $spec) {
                $value = $this->cellValue($spec->field, $book);
                if ('' !== $value) {
                    //Explicit strings throughout, including the barcode. Excel turns a
                    //call number like `1944_Std. 2.1` into text anyway, but it turns
                    //`1-2` into a date, and a call number is exactly the sort of field
                    //that looks like one.
                    $sheet->getCell(Coordinate::stringFromColumnIndex($column) . $row)
                        ->setValueExplicit($value, DataType::TYPE_STRING);
                }
                $column++;
            }
            $row++;
        }
    }

    /** @param array<string, mixed> $book */
    private function cellValue(string $field, array $book): string
    {
        //`collection` is the importer's name for what the entity calls collectionName:
        //the import writes a name and resolves it to an id, so the export has to give
        //back the name it would accept.
        $value = 'collection' === $field
            ? ($book['collectionName'] ?? null)
            : ($book[$field] ?? null);

        if (is_array($value)) {
            return implode('|', array_map('strval', $value));
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /** @param list<string> $collections */
    private function applyValidation(Worksheet $sheet, array $collections, int $bookCount): void
    {
        //Enough rows for the export plus room to add to it. Excel stores one validation
        //rule per range, not per cell, so the range costs nothing to make generous.
        $lastRow = self::HEADER_ROW + 1 + ($bookCount > 0 ? $bookCount + 500 : 2000);

        $this->listValidation($sheet, 'collection', $collections, $lastRow);
        $this->listValidation($sheet, 'inLanguage', ['en', 'es', 'de', 'pt', 'it', 'fr', 'la', 'pl'], $lastRow);
    }

    /** @param list<string> $options */
    private function listValidation(Worksheet $sheet, string $field, array $options, int $lastRow): void
    {
        if ([] === $options) {
            return;
        }
        $joined = implode(',', $options);
        //A comma in a collection name would end the list early, and a long list is
        //refused outright. Either way the instructions sheet still lists the values, so
        //dropping the dropdown loses convenience and not information.
        if (strlen($joined) > self::MAX_VALIDATION_LENGTH || str_contains($joined, '"')) {
            return;
        }

        $index = $this->columnIndexOf($field);
        if (null === $index) {
            return;
        }
        $letter = Coordinate::stringFromColumnIndex($index);

        $validation = $sheet->getCell($letter . (self::HEADER_ROW + 1))->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        //Not STYLE_STOP: the importer accepts a collection name that does not exist yet
        //and creates it, so refusing one in the spreadsheet would remove a feature.
        $validation->setErrorStyle(DataValidation::STYLE_WARNING);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setShowErrorMessage(true);
        $validation->setErrorTitle($this->t('Not in the list'));
        $validation->setError($this->t('This value is not one of the existing ones. It will be created.'));
        $validation->setFormula1('"' . $joined . '"');

        $sheet->setDataValidation(
            $letter . (self::HEADER_ROW + 1) . ':' . $letter . $lastRow,
            clone $validation
        );
    }

    private function columnIndexOf(string $field): ?int
    {
        $index = 1;
        foreach (ImportColumns::core() as $spec) {
            if ($spec->field === $field) {
                return $index;
            }
            $index++;
        }

        return null;
    }

    /** @param list<string> $collections */
    private function writeInstructions(Spreadsheet $spreadsheet, string $libraryName, array $collections): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($this->t('Instructions'));
        $sheet->getColumnDimension('A')->setWidth(26);
        $sheet->getColumnDimension('B')->setWidth(12);
        $sheet->getColumnDimension('C')->setWidth(96);

        $row = 1;
        $row = $this->heading($sheet, $row, $this->t('Importing books into %s', [$libraryName]));
        $row = $this->paragraph($sheet, $row, $this->t(
            'Fill in the Books sheet, then upload this file on the library\'s Imports page. '
            . 'Nothing is changed until you have seen a summary of what the import would do and confirmed it.'
        ));
        $row++;

        $row = $this->heading($sheet, $row, $this->t('Three rules worth knowing'));
        $row = $this->bullet($sheet, $row, $this->t(
            'The Barcode column identifies the copy. A barcode already in the library updates that book; '
            . 'a barcode that is new creates one.'
        ));
        $row = $this->bullet($sheet, $row, $this->t(
            'An empty cell erases what is stored. To leave a field alone, delete the whole column '
            . 'from this sheet instead of clearing its cells.'
        ));
        $row = $this->bullet($sheet, $row, $this->t(
            'A complete import marks every book NOT listed here as inactive. Use it only when this sheet '
            . 'is the whole library.'
        ));
        $row++;

        $row = $this->heading($sheet, $row, $this->t('The columns'));
        $sheet->fromArray(
            [$this->t('Column'), $this->t('Required'), $this->t('Meaning')],
            null,
            'A' . $row
        );
        $sheet->getStyle('A' . $row . ':C' . $row)->getFont()->setBold(true);
        $row++;
        foreach (ImportColumns::core() as $spec) {
            $sheet->setCellValue('A' . $row, $spec->heading);
            $sheet->setCellValue('B' . $row, $spec->required ? $this->t('Yes') : '');
            $sheet->setCellValue('C' . $row, $this->t($spec->help));
            $sheet->getStyle('C' . $row)->getAlignment()->setWrapText(true);
            $row++;
        }
        $row++;

        $extra = [];
        foreach (ImportColumns::all() as $spec) {
            if (! $spec->core) {
                $extra[] = $spec->heading;
            }
        }
        $row = $this->heading($sheet, $row, $this->t('Columns you may add'));
        $row = $this->paragraph($sheet, $row, $this->t(
            'These are not in this template, because a column left empty erases what is stored. '
            . 'Add one only if you mean to fill it in: %s.',
            [implode(', ', $extra)]
        ));
        $row++;

        $row = $this->heading($sheet, $row, $this->t('Other headings that are recognised'));
        $row = $this->paragraph($sheet, $row, $this->t(
            'A spreadsheet you already have does not need renaming. Each of these is understood as the '
            . 'column beside it, ignoring capitals and accents.'
        ));
        foreach (ImportColumns::all() as $spec) {
            if ([] === $spec->aliases) {
                continue;
            }
            $sheet->setCellValue('A' . $row, $spec->heading);
            $sheet->setCellValue('C' . $row, implode(', ', array_slice($spec->aliases, 0, 12)));
            $sheet->getStyle('C' . $row)->getAlignment()->setWrapText(true);
            $row++;
        }
        $row++;

        if ([] !== $collections) {
            $row = $this->heading($sheet, $row, $this->t('Collections in this library'));
            $row = $this->paragraph($sheet, $row, implode(', ', $collections));
            $this->paragraph($sheet, $row, $this->t(
                'A collection name that does not exist yet is created by the import.'
            ));
        }
    }

    private function heading(Worksheet $sheet, int $row, string $text): int
    {
        $sheet->setCellValue('A' . $row, $text);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(13);

        return $row + 1;
    }

    private function paragraph(Worksheet $sheet, int $row, string $text): int
    {
        $sheet->setCellValue('A' . $row, $text);
        $sheet->mergeCells('A' . $row . ':C' . $row);
        $sheet->getStyle('A' . $row)->getAlignment()->setWrapText(true);
        $sheet->getRowDimension($row)->setRowHeight(-1);

        return $row + 1;
    }

    private function bullet(Worksheet $sheet, int $row, string $text): int
    {
        return $this->paragraph($sheet, $row, '• ' . $text);
    }

    /**
     * Translate, then interpolate — never the other way round.
     *
     * The phrase looked up is the template with its `%s` still in it. Interpolating
     * first would make every library name its own phrase, which is exactly the defect
     * JTranslate\I18n\TranslatableMessage exists to avoid and which
     * `LibraryImportsController::importSpreadsheetFile()` had with its duplicate-barcode
     * message until 2026.
     *
     * @param array<int, string> $params
     */
    private function t(string $message, array $params = []): string
    {
        if ('' === $message) {
            return '';
        }
        $translated = ($this->translate)($message);

        return [] === $params ? $translated : vsprintf($translated, $params);
    }
}
