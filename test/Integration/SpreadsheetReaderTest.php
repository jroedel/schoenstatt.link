<?php

namespace SchoenstattTest\Integration;

use Books\Service\SpreadsheetReader;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Contract test for the PHPExcel -> PhpSpreadsheet migration of the library
 * import (docs/BACKLOG.md "phpoffice/phpexcel" advisory debt). Pins the two legacy
 * semantics importSpreadsheetFile() depends on, verified 2026-08-03 by a
 * golden-master diff of all 17 historical production imports (2.59M cells
 * identical, only numeric-to-string formatting differences):
 *
 *  - empty cells surface as null, never '' — the import's empty-row skip
 *    ($foundAValue) uses isset() and silently creates blank books otherwise;
 *  - numeric cells surface as formatted strings; every consumer goes through
 *    is_numeric() + (int).
 *
 * Needs vendor/ (PhpSpreadsheet), so it lives outside the vendor-free unit
 * suite and runs in the capsule: php composer.phar integration
 */
class SpreadsheetReaderTest extends TestCase
{
    private static string $file;

    public static function setUpBeforeClass(): void
    {
        $workbook = new Spreadsheet();
        $sheet = $workbook->getActiveSheet();
        $sheet->setTitle('Biblioteca CM');
        $sheet->fromArray([
            ['ID', 'Titulo', 'Año', 'Páginas'],
            [1, 'Hacia el Padre', 1999, 240],
            [2, 'Misterio de María', '', null],
            [null, null, null, null],
            [3, 'Nueva Visión', 2005.0, 128],
        ], null, 'A1');
        $headerOnly = $workbook->createSheet();
        $headerOnly->setTitle('LomoModelo');
        $headerOnly->setCellValue('A1', 'only-a-header');

        self::$file = tempnam(sys_get_temp_dir(), 'spreadsheet-reader-test') . '.xlsx';
        (new Xlsx($workbook))->save(self::$file);
        $workbook->disconnectWorksheets();
    }

    public static function tearDownAfterClass(): void
    {
        @unlink(self::$file);
    }

    public function testReadsHeaderAndRowsWithLegacySemantics(): void
    {
        $data = (new SpreadsheetReader())->read(self::$file, 'Biblioteca CM');

        $this->assertSame(['ID', 'Titulo', 'Año', 'Páginas'], $data['header']);
        $this->assertSame([
            // numerics come back as formatted strings, ints and floats alike
            ['1', 'Hacia el Padre', '1999', '240'],
            // '' cells are normalized to null so isset()-based row skipping works
            ['2', 'Misterio de María', null, null],
            // an all-empty row inside the data range stays all-null
            [null, null, null, null],
            ['3', 'Nueva Visión', '2005', '128'],
        ], $data['rows']);
    }

    public function testUnknownWorksheetThrowsInsteadOfFataling(): void
    {
        // legacy code fataled on null->getHighestDataRow() for a wrong name
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Available worksheets: Biblioteca CM, LomoModelo');
        (new SpreadsheetReader())->read(self::$file, 'Hoja1');
    }

    public function testHeaderOnlyWorksheetReportsNoData(): void
    {
        $this->expectExceptionMessage('No data contained in the spreadsheet.');
        (new SpreadsheetReader())->read(self::$file, 'LomoModelo');
    }
}
