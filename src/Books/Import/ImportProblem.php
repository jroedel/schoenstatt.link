<?php

declare(strict_types=1);

namespace App\Books\Import;

/**
 * Why a row cannot be imported, or why an import cannot be run.
 *
 * Keys rather than sentences, so the review page can translate them and the console
 * command can print them without the engine knowing about either. Each is also a
 * heading in docs/library-imports.md, which is where the librarian is sent.
 */
final class ImportProblem
{
    // Row-level: the row is skipped, the rest of the import proceeds.

    /** No barcode at all, or one that is not a whole number. */
    public const BARCODE_MISSING = 'barcode-missing';

    /** The same barcode appears on an earlier row of the same sheet. */
    public const BARCODE_DUPLICATED = 'barcode-duplicated';

    /** No title, and no Literature ID to take one from. */
    public const TITLE_MISSING = 'title-missing';

    /**
     * A Literature ID that names no record, on a row with no title of its own.
     *
     * Distinguished from TITLE_MISSING because it is the case the create page
     * documents as *supported* — "a row with a publicationId need not have a title" —
     * and the case six of import 3's eight errors actually were. The instruction is
     * true only when the record exists, which nothing said.
     */
    public const LITERATURE_ID_UNKNOWN = 'literature-id-unknown';

    // Import-level: nothing is applied until it is resolved.

    /** Required columns that no spreadsheet heading feeds. */
    public const COLUMNS_MISSING = 'columns-missing';

    /** At least one barcode appears twice. The old engine refused the whole file too. */
    public const BARCODES_DUPLICATED = 'barcodes-duplicated';

    /** The worksheet named on the import row is not in the file. */
    public const WORKSHEET_MISSING = 'worksheet-missing';

    /** The uploaded file is gone from disk. */
    public const FILE_MISSING = 'file-missing';

    /** The sheet has a header row and nothing under it. */
    public const NO_ROWS = 'no-rows';
}
