<?php

declare(strict_types=1);

namespace App\Books\Import;

use function array_diff;
use function array_keys;
use function array_search;
use function array_values;
use function count;
use function in_array;
use function is_string;
use function ksort;
use function trim;

/**
 * Which spreadsheet column feeds which book field, for one import.
 *
 * Built by HeaderMatcher from the file's own header row, correctable on the mapping
 * screen, and stored on the import row so that re-running it years later reads the
 * same columns. It carries the header row itself, because a mapping is only
 * meaningful beside the headings it was made against — that is what lets the review
 * page say "column `Lomo` → Call number" instead of "column 3".
 */
final class ColumnMap
{
    /**
     * @param list<string|null> $headers the header row as read, by column index
     * @param array<string, int> $assignments book field => column index
     */
    public function __construct(
        public readonly array $headers,
        private readonly array $assignments
    ) {
    }

    public function has(string $field): bool
    {
        return isset($this->assignments[$field]);
    }

    public function indexOf(string $field): ?int
    {
        return $this->assignments[$field] ?? null;
    }

    public function headingOf(string $field): ?string
    {
        $index = $this->indexOf($field);

        return null === $index ? null : ($this->headers[$index] ?? null);
    }

    /** @return array<string, int> book field => column index */
    public function assignments(): array
    {
        $assignments = $this->assignments;
        ksort($assignments);

        return $assignments;
    }

    public function isEmpty(): bool
    {
        return [] === $this->assignments;
    }

    /**
     * The required fields no column feeds.
     *
     * @return list<string>
     */
    public function missingRequired(): array
    {
        $missing = [];
        foreach (ImportColumns::REQUIRED_FIELDS as $field) {
            if (! $this->has($field)) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * Columns the import will not read, by index.
     *
     * Reported rather than ignored: a heading the vocabulary does not recognise is the
     * single most likely reason a librarian's data does not arrive, and nothing in the
     * old page said a word about it.
     *
     * @return array<int, string>
     */
    public function unusedColumns(): array
    {
        $used   = array_values($this->assignments);
        $unused = [];
        foreach ($this->headers as $index => $heading) {
            if (in_array($index, $used, true)) {
                continue;
            }
            if (! is_string($heading) || '' === trim($heading)) {
                continue;
            }
            $unused[$index] = $heading;
        }

        return $unused;
    }

    /**
     * The form stored on the import row: field => heading, never field => index.
     *
     * A heading survives someone inserting a column into the spreadsheet before the
     * next run; an index does not.
     *
     * @return array<string, string>
     */
    public function toStorage(): array
    {
        $stored = [];
        foreach ($this->assignments() as $field => $index) {
            $heading = $this->headers[$index] ?? null;
            if (is_string($heading) && '' !== trim($heading)) {
                $stored[$field] = $heading;
            }
        }

        return $stored;
    }

    /**
     * The same map with one field pointed at another column, or unmapped for a null
     * index. Any field already holding that column is unmapped first — one column
     * cannot feed two fields, and the mapping screen posts one select per field.
     */
    public function with(string $field, ?int $index): self
    {
        $assignments = $this->assignments;
        unset($assignments[$field]);

        if (null !== $index && isset($this->headers[$index])) {
            $taken = array_search($index, $assignments, true);
            if (false !== $taken) {
                unset($assignments[$taken]);
            }
            $assignments[$field] = $index;
        }

        return new self($this->headers, $assignments);
    }

    public function count(): int
    {
        return count($this->assignments);
    }

    /** @return list<string> the fields this map does not feed, in vocabulary order */
    public function unmappedFields(): array
    {
        return array_values(array_diff(ImportColumns::fields(), array_keys($this->assignments)));
    }
}
