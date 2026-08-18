<?php

namespace Books\Service;

use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Reads a worksheet into a header row + data rows for the library import.
 *
 * Extracted from LibraryImportsController::importSpreadsheetFile() during the
 * PHPExcel -> PhpSpreadsheet migration; the array shape preserves the legacy
 * PHPExcel semantics the import logic was written against. In particular,
 * empty cells always come back as null — never '' — because the import's
 * empty-row detection relies on isset().
 */
class SpreadsheetReader
{
    /**
     * The worksheets a file contains, in the order the file lists them.
     *
     * Exists because the import used to ask a librarian to *type* the worksheet name,
     * with no indication of what the file held and no error until the whole page 500'd
     * on `Worksheet "Hoja 1" not found`. The names come from the reader rather than the
     * controller because loading a spreadsheet is this class's job and nobody else's.
     *
     * @return list<string>
     * @throws \Exception if the file cannot be read as a spreadsheet at all
     */
    public function worksheetNames(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        try {
            return array_values($spreadsheet->getSheetNames());
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    /**
     * @return array{header: array<int, string|null>, rows: array<int, array<int, string|null>>}
     * @throws InvalidArgumentException if the worksheet doesn't exist
     * @throws \Exception if the worksheet holds no data
     */
    public function read(string $filePath, string $worksheetName): array
    {
        $spreadsheet = IOFactory::load($filePath);
        try {
            $sheet = $spreadsheet->getSheetByName($worksheetName);
            if (null === $sheet) {
                throw new InvalidArgumentException(sprintf(
                    'Worksheet "%s" not found in %s. Available worksheets: %s',
                    $worksheetName,
                    basename($filePath),
                    implode(', ', $spreadsheet->getSheetNames())
                ));
            }
            $highestRow = $sheet->getHighestDataRow();
            $highestColumn = $sheet->getHighestDataColumn();
            if ('A' === $highestColumn || 1 === $highestRow) {
                throw new \Exception('No data contained in the spreadsheet.');
            }
            $header = $sheet->rangeToArray('A1:' . $highestColumn . '1')[0];
            $rows = $sheet->rangeToArray('A2:' . $highestColumn . $highestRow);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }

        return [
            'header' => $this->normalizeCells($header),
            'rows' => array_map([$this, 'normalizeCells'], $rows),
        ];
    }

    /**
     * PhpSpreadsheet (>= 1.28) formats cell values as strings, so an empty
     * cell can surface as '' where PHPExcel produced null.
     *
     * @param array<int, mixed> $cells
     * @return array<int, string|null>
     */
    private function normalizeCells(array $cells): array
    {
        return array_map(
            static fn ($value) => ('' === $value || null === $value) ? null : (string) $value,
            $cells
        );
    }
}
