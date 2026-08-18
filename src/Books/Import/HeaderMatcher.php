<?php

declare(strict_types=1);

namespace App\Books\Import;

use function array_values;
use function is_array;
use function is_string;
use function trim;

/**
 * Turns a spreadsheet's header row into a ColumnMap.
 *
 * Two sources, in order. A mapping **stored on the import row** wins wherever its
 * heading is still present in the file, so a re-run reads exactly what the previous
 * run read even if the vocabulary has since gained an alias that would decide
 * otherwise. Everything the stored mapping does not settle falls to alias matching
 * against ImportColumns.
 *
 * The old page did neither: `getColegioMayorLibraryFieldsMap()` was consulted for
 * every import of every library, and a spreadsheet whose headings differed produced
 * `Exception: Missing required fields for the excel file: withinLibraryId, title` —
 * a message naming internal field names, thrown out of a controller, rendered as a
 * 500.
 */
final class HeaderMatcher
{
    /**
     * @param list<string|null> $headers the header row, by column index
     * @param array<string, string>|null $stored field => heading, as
     *        `LibraryTable::getLibraryImport()` unserializes it. Pass it through
     *        LegacyColumnMapping first: rows written before 2018-11-02 name fields
     *        that no longer exist.
     */
    public function match(array $headers, ?array $stored = null): ColumnMap
    {
        $headers     = array_values($headers);
        $assignments = [];
        $taken       = [];

        if (is_array($stored)) {
            foreach ($stored as $field => $heading) {
                if (! is_string($heading) || null === ImportColumns::get($field)) {
                    continue;
                }
                $index = $this->indexOfHeading($headers, $heading, $taken);
                if (null !== $index) {
                    $assignments[$field] = $index;
                    $taken[$index]       = true;
                }
            }
        }

        foreach ($headers as $index => $heading) {
            if (isset($taken[$index]) || ! is_string($heading) || '' === trim($heading)) {
                continue;
            }
            $field = ImportColumns::fieldFor($heading);
            //**First column wins, and the second is reported rather than silently
            //dropped.** A spreadsheet with both `Autor` and `Author` would otherwise
            //have its answer decided by column order with nothing on screen saying so;
            //ColumnMap::unusedColumns() lists the loser, and the mapping screen lets a
            //librarian pick the other one.
            if (null === $field || isset($assignments[$field])) {
                continue;
            }
            $assignments[$field] = $index;
            $taken[$index]       = true;
        }

        return new ColumnMap($headers, $assignments);
    }

    /**
     * @param list<string|null> $headers
     * @param array<int, bool> $taken
     */
    private function indexOfHeading(array $headers, string $heading, array $taken): ?int
    {
        $wanted = ImportColumns::normalize($heading);
        if ('' === $wanted) {
            return null;
        }
        foreach ($headers as $index => $candidate) {
            if (isset($taken[$index]) || ! is_string($candidate)) {
                continue;
            }
            if (ImportColumns::normalize($candidate) === $wanted) {
                return $index;
            }
        }

        return null;
    }
}
