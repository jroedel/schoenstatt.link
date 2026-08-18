<?php

declare(strict_types=1);

namespace App\Books\Import;

use Books\Service\SpreadsheetReader;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

use function in_array;
use function strtolower;
use function unlink;

use const UPLOAD_ERR_INI_SIZE;
use const UPLOAD_ERR_NO_FILE;
use const UPLOAD_ERR_OK;

/**
 * Accepting a spreadsheet from a browser: what may be one, and where it lands.
 *
 * The check that matters is the last one. Extension and size are cheap and catch the
 * obvious, but the question a librarian actually needs answered is "can this file be
 * read at all", and the only honest way to answer it is to open it. So the file is
 * stored, opened, and **deleted again** if PhpSpreadsheet cannot make a workbook out of
 * it — the alternative is an import row pointing at a file that fails hours later, on a
 * page whose whole job is to be the one that explains things.
 */
final class SpreadsheetUpload
{
    public const NO_FILE     = 'upload-no-file';
    public const TOO_LARGE   = 'upload-too-large';
    public const WRONG_TYPE  = 'upload-wrong-type';
    public const FAILED      = 'upload-failed';
    public const UNREADABLE  = 'upload-unreadable';

    public function __construct(
        private readonly ImportStorage $storage,
        private readonly SpreadsheetReader $reader
    ) {
    }

    /**
     * @return array{0: string|null, 1: list<string>} an error key and its parameters,
     *         or [null, []] when the upload is acceptable
     */
    public function problem(?UploadedFile $file): array
    {
        if (null === $file) {
            return [self::NO_FILE, []];
        }
        $error = $file->getError();
        if (UPLOAD_ERR_NO_FILE === $error) {
            return [self::NO_FILE, []];
        }
        if (UPLOAD_ERR_INI_SIZE === $error) {
            return [self::TOO_LARGE, []];
        }
        if (UPLOAD_ERR_OK !== $error) {
            return [self::FAILED, [$file->getErrorMessage()]];
        }
        if ($file->getSize() > ImportStorage::MAX_BYTES) {
            return [self::TOO_LARGE, []];
        }
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ImportStorage::ACCEPTED_EXTENSIONS, true)) {
            return [self::WRONG_TYPE, [$extension]];
        }

        return [null, []];
    }

    /**
     * Store the upload and prove it opens.
     *
     * @return array{path: string|null, worksheets: list<string>, error: string|null, params: list<string>}
     */
    public function accept(UploadedFile $file, int $libraryId, string $today): array
    {
        try {
            $path = $this->storage->store($file, $libraryId, $today);
        } catch (Throwable $e) {
            return ['path' => null, 'worksheets' => [], 'error' => self::FAILED, 'params' => [$e->getMessage()]];
        }

        try {
            $worksheets = $this->reader->worksheetNames($path);
        } catch (Throwable $e) {
            @unlink($path);

            return ['path' => null, 'worksheets' => [], 'error' => self::UNREADABLE, 'params' => [$e->getMessage()]];
        }

        return ['path' => $path, 'worksheets' => $worksheets, 'error' => null, 'params' => []];
    }
}
