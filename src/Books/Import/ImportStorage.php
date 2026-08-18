<?php

declare(strict_types=1);

namespace App\Books\Import;

use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function bin2hex;
use function is_dir;
use function is_file;
use function mkdir;
use function pathinfo;
use function preg_replace;
use function random_bytes;
use function str_contains;
use function str_starts_with;
use function strtolower;
use function substr;
use function trim;

use const PATHINFO_EXTENSION;
use const PATHINFO_FILENAME;

/**
 * Where an uploaded library spreadsheet goes, and what is allowed to be one.
 *
 * ## The field this replaces
 *
 * `filePath` was a text box, and it meant "path on the server". The clearest evidence
 * of how that went is import 14, created 2021-08-10 and still `pending`:
 *
 *     C:\Users\Ramon Vergara\Desktop\intento.xlsx
 *
 * A librarian typed the path to the file on their own computer, because that is the
 * only path a person has. Nothing on the page said otherwise, nothing validated it,
 * and the import has sat unrunnable for five years.
 *
 * ## Why `data/import/` and not somewhere new
 *
 * It is the directory the working imports of 2017–2019 used, and it is already listed
 * in `tools/deploy-bootstrap.sh`'s `SHARED_DATA`, so on the server it is
 * `shared/data/import/` symlinked into every release. That matters more than it
 * sounds: a release swap deletes anything inside the release tree, so an upload
 * directory that was *not* shared would lose every file at the next deploy, and the
 * loss would show up as imports whose file "went missing" weeks later.
 *
 * Files are kept after the import runs, deliberately — an import row names its file
 * and the detail page shows it, so a librarian can see what was actually loaded. There
 * is no pruning yet; see docs/BACKLOG.md.
 */
final class ImportStorage
{
    /** Formats PhpSpreadsheet reads that also have named worksheets. */
    public const ACCEPTED_EXTENSIONS = ['xlsx', 'xls', 'ods'];

    /** Ten times the largest spreadsheet any library has ever imported (816 KB). */
    public const MAX_BYTES = 10485760;

    public function __construct(private readonly string $root = 'data/import')
    {
    }

    public function directory(int $libraryId): string
    {
        return $this->root . '/' . $libraryId;
    }

    /**
     * Move an upload into place and return its path, relative to the application root.
     *
     * The name keeps the librarian's own filename so the imports list is readable, and
     * prefixes a date and four random bytes so that uploading `books.xlsx` twice does
     * not overwrite the first one — an import row points at its file for years, and
     * silently replacing it would rewrite history.
     *
     * @throws RuntimeException if the directory cannot be created or the move fails
     */
    public function store(UploadedFile $file, int $libraryId, string $today): string
    {
        $directory = $this->directory($libraryId);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Could not create the import directory: ' . $directory);
        }

        $name = $today . '-' . bin2hex(random_bytes(4)) . '-' . self::safeName($file->getClientOriginalName());
        $file->move($directory, $name);

        return $directory . '/' . $name;
    }

    /**
     * The stored name for an upload: the librarian's filename with everything that is
     * not a letter, digit, dot or dash removed.
     */
    public static function safeName(string $original): string
    {
        $extension = strtolower((string) pathinfo($original, PATHINFO_EXTENSION));
        $stem      = (string) pathinfo($original, PATHINFO_FILENAME);
        $stem      = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $stem), '-.');
        if ('' === $stem) {
            $stem = 'import';
        }
        //A 200-character filename is legal and useless; the random prefix is what makes
        //the name unique, so the rest can be cut without risking a collision.
        $stem = substr($stem, 0, 60);

        return '' === $extension ? $stem : $stem . '.' . $extension;
    }

    /**
     * Whether a path is one this class produced — the guard on anything that opens a
     * file named by an import row.
     *
     * The rows written before 2026 name paths this class did not choose, including one
     * on somebody's Windows desktop, and the console command takes an import id from an
     * operator. Neither is a reason to open an arbitrary path off the filesystem.
     */
    public function owns(string $path): bool
    {
        return str_starts_with($path, $this->root . '/') && ! str_contains($path, '..');
    }

    public function exists(string $path): bool
    {
        return '' !== $path && is_file($path);
    }
}
