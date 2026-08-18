<?php

declare(strict_types=1);

namespace App\Books\Import;

use function array_filter;
use function array_values;
use function count;

/**
 * Everything an import would do, decided but not done.
 *
 * The old engine produced this and the finished import from one method, told apart by
 * a by-reference `$simulate` flag it also wrote to — so "what would happen" and "make
 * it happen" were the same code path with the same failure modes, and the only way to
 * see a plan was to render eleven thousand table rows into a browser. Here `plan()`
 * returns this and touches nothing; `apply()` takes it.
 */
final class ImportPlan
{
    /**
     * @param list<PlannedRow> $rows
     * @param list<array{reason: string, params: list<string>}> $blockers reasons the
     *        plan may not be applied at all. A plan with any of these still renders —
     *        seeing what *would* have happened is how a librarian fixes the file.
     */
    public function __construct(
        public readonly ColumnMap $map,
        public readonly array $rows,
        public readonly array $blockers = [],
        public readonly bool $isCompleteImport = false,
        public readonly int $sheetRowCount = 0
    ) {
    }

    public function canApply(): bool
    {
        return [] === $this->blockers;
    }

    /**
     * Counts by action, plus the two the old page could not tell apart.
     *
     * `update` counts every row matched to an existing book, which is what the old
     * page's "Records updated" meant; `update-unchanged` is the part of it that would
     * write nothing. On import 3 that split is 10,091 into 342 and 9,749 — the
     * difference between a review page worth reading and one that is not.
     *
     * @return array<string, int>
     */
    public function statistics(): array
    {
        $stats = [
            PlannedRow::CREATE            => 0,
            PlannedRow::UPDATE            => 0,
            'update-unchanged'            => 0,
            PlannedRow::INACTIVATE        => 0,
            PlannedRow::ERROR             => 0,
            PlannedRow::CREATE_COLLECTION => 0,
        ];
        foreach ($this->rows as $row) {
            $stats[$row->action] = ($stats[$row->action] ?? 0) + 1;
            if ($row->isUnchanged()) {
                $stats['update-unchanged']++;
            }
        }

        return $stats;
    }

    /** @return list<PlannedRow> */
    public function withAction(string $action): array
    {
        return array_values(array_filter(
            $this->rows,
            static fn (PlannedRow $row): bool => $action === $row->action
        ));
    }

    /** @return list<PlannedRow> */
    public function errors(): array
    {
        return $this->withAction(PlannedRow::ERROR);
    }

    /**
     * The rows a librarian needs to look at: everything except matched books that
     * change nothing.
     *
     * @return list<PlannedRow>
     */
    public function notable(): array
    {
        return array_values(array_filter(
            $this->rows,
            static fn (PlannedRow $row): bool => ! $row->isUnchanged()
        ));
    }

    public function writeCount(): int
    {
        return count(array_filter($this->rows, static fn (PlannedRow $row): bool => $row->writes()));
    }
}
