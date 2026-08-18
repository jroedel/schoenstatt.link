<?php

declare(strict_types=1);

namespace SchoenstattTest\Unit;

use App\Books\Import\ImportStorage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function str_ends_with;
use function str_repeat;
use function str_starts_with;
use function strlen;

require_once __DIR__ . '/../../src/Books/Import/ImportStorage.php';

/**
 * Which paths an import may open, and what an uploaded file is allowed to be called.
 *
 * ## Why `owns()` exists at all
 *
 * Because an import row can name any path a 2017 text box accepted, and two of the
 * fourteen in the database do:
 *
 *     C:\Users\Ramon Vergara\Desktop\intento.xlsx
 *     data/import/2019-09-26.xlsx
 *
 * The first is somebody's own computer. Neither was chosen by this application. Both the
 * configure page and `bin/console books:import` take an import id and open whatever its
 * row names, and the console runs as the deploy account — so "only paths under
 * `data/import/`" is the difference between a feature and a file-read primitive.
 */
class ImportStorageTest extends TestCase
{
    /** @return array<string, array{0: string, 1: bool, 2: string}> */
    public static function paths(): array
    {
        return [
            'a file this class stored'   => ['data/import/3/2026-08-18-a1b2c3d4-books.xlsx', true, ''],
            'a legacy relative path'     => ['data/import/2019-09-26.xlsx', true, 'the 2017-2019 imports'],
            'the directory itself'       => ['data/import', false, 'not a file under it'],
            'somebody\'s desktop'        => ['C:\\Users\\Ramon Vergara\\Desktop\\intento.xlsx', false, 'import 14'],
            'an absolute path'           => ['/etc/passwd', false, ''],
            'a traversal'                => ['data/import/../../config/autoload/local.php', false, 'credentials'],
            'a traversal inside a name'  => ['data/import/3/../../../.deploy.local', false, 'credentials'],
            'a lookalike prefix'         => ['data/importsomething/x.xlsx', false, 'the slash is load-bearing'],
            'empty'                      => ['', false, ''],
        ];
    }

    #[DataProvider('paths')]
    public function testOnlyPathsUnderTheImportDirectoryAreOpened(string $path, bool $owned, string $note): void
    {
        self::assertSame($owned, (new ImportStorage())->owns($path), $note);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function names(): array
    {
        return [
            'kept as-is'          => ['biblioteca_2017-12-26.xlsx', 'biblioteca_2017-12-26.xlsx'],
            'spaces and accents'  => ['Biblioteca Colegio Mayor.xlsx', 'Biblioteca-Colegio-Mayor.xlsx'],
            //`pathinfo()` takes the basename, so a POSIX path loses its directories
            //outright; a Windows one does not, because a backslash is an ordinary
            //character here — which is why the substitution has to run as well.
            'a path, not a name'  => ['../../etc/passwd.xlsx', 'passwd.xlsx'],
            'a windows path'      => ['C:\\Users\\Ramon\\intento.xlsx', 'C-Users-Ramon-intento.xlsx'],
            'extension lowered'   => ['BOOKS.XLSX', 'BOOKS.xlsx'],
            'nothing left'        => ['....xlsx', 'import.xlsx'],
            'no extension'        => ['books', 'books'],
        ];
    }

    #[DataProvider('names')]
    public function testAnUploadedFilenameIsReducedToSomethingSafe(string $original, string $expected): void
    {
        self::assertSame($expected, ImportStorage::safeName($original));
    }

    public function testALongFilenameIsCutButKeepsItsExtension(): void
    {
        $long = str_repeat('a', 300) . '.xlsx';
        $safe = ImportStorage::safeName($long);

        self::assertTrue(str_ends_with($safe, '.xlsx'));
        self::assertLessThanOrEqual(66, strlen($safe));
    }

    public function testTheDirectoryIsPerLibrary(): void
    {
        //Per library, not one flat directory: the pre-2026 files sit directly in
        //`data/import/` and a library id in the path keeps a new upload from colliding
        //with one of them by name.
        self::assertSame('data/import/3', (new ImportStorage())->directory(3));
        self::assertTrue(str_starts_with((new ImportStorage())->directory(7) . '/x', 'data/import/7/'));
    }

    public function testTheAcceptedFormatsAreOnesWithNamedWorksheets(): void
    {
        //`csv` is deliberately absent. PhpSpreadsheet reads one, but a CSV has a single
        //unnamed sheet and no cell types, so the worksheet step would be a question with
        //one meaningless answer and every barcode would arrive as a string.
        self::assertSame(['xlsx', 'xls', 'ods'], ImportStorage::ACCEPTED_EXTENSIONS);
        self::assertNotContains('csv', ImportStorage::ACCEPTED_EXTENSIONS);
    }
}
