<?php

declare(strict_types=1);

namespace App\Books\Import;

use function count;
use function is_string;

/**
 * One thing the import will do, or refuse to do, for one spreadsheet row.
 *
 * The old engine returned a bare array per row whose only decoration was
 * `['action' => 'error']` — no reason, no row number, no notion of what would
 * actually change. So the review page showed eleven thousand rows of every mapped
 * column and eight of them were coloured yellow with nothing to say why. Six of those
 * eight, measured on import 3, were rows carrying a Literature ID for a record that
 * does not exist, which the page's own instructions describe as a supported case.
 */
final class PlannedRow
{
    public const CREATE            = 'create';
    public const UPDATE            = 'update';
    public const INACTIVATE        = 'inactivate';
    public const ERROR             = 'error';
    public const CREATE_COLLECTION = 'create-collection';

    /**
     * @param int|null $rowNumber the row's own number in the worksheet, header
     *        included, so it matches what the librarian sees in the margin of Excel.
     *        Null for the collection and inactivation rows, which no spreadsheet row
     *        produced.
     * @param array<string, mixed> $values the fields as they would be written
     * @param array<string, array{0: mixed, 1: mixed}> $changes field => [before, after],
     *        for an update only. Empty means the row matches a book and changes nothing
     *        about it — 9,749 of import 3's 10,091 "updates", which the old page
     *        reported indistinguishably from the 342 that did change something.
     * @param string|null $reason a key from ImportProblem, not a sentence: the review
     *        page translates it, and the console command prints it
     * @param list<string> $reasonParams
     */
    public function __construct(
        public readonly string $action,
        public readonly ?int $rowNumber = null,
        public readonly ?int $bookId = null,
        public readonly ?int $withinLibraryId = null,
        public readonly array $values = [],
        public readonly array $changes = [],
        public readonly ?string $reason = null,
        public readonly array $reasonParams = [],
        public readonly ?string $collection = null
    ) {
    }

    /** An update that would write nothing. */
    public function isUnchanged(): bool
    {
        return self::UPDATE === $this->action && 0 === count($this->changes);
    }

    /** Whether this row would write to the database if the plan were applied. */
    public function writes(): bool
    {
        if (self::ERROR === $this->action) {
            return false;
        }

        return ! $this->isUnchanged();
    }

    public function title(): string
    {
        $title = $this->values['title'] ?? null;

        return is_string($title) ? $title : '';
    }
}
