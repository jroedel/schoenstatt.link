<?php

declare(strict_types=1);

namespace App\Laminas;

use function array_values;
use function is_array;

/**
 * What a SionModel read actually returns, as opposed to what it says it returns.
 *
 * `SionTable::getObject()`, `getObjects()`, `queryObjects()`, `getLibraryImport()` and
 * their neighbours are annotated `mixed[]` and return **null** on paths their docblocks
 * do not admit — a missing row, a projection that filters everything, a query with no
 * results. So the `is_array()` guard every caller needs reads to PHPStan as dead code,
 * and level 8 reports the else branch as unreachable.
 *
 * Suppressing that per call site would mean trusting the annotation, which is the thing
 * that is wrong. Funnelling the value through one `mixed` parameter keeps the guard —
 * the guard is the runtime behaviour these pages depend on — and keeps the analysis
 * honest about everything else.
 *
 * The alternative is fixing the annotations in SionModel, which is the right end state
 * and is a change to a shared library used by other applications. Filed rather than done
 * inside a porting batch.
 */
final class SionResult
{
    /**
     * The value as a keyed array, or null when the read answered nothing.
     *
     * @return array<int|string, mixed>|null
     */
    public static function rowOrNull(mixed $value): ?array
    {
        return is_array($value) && [] !== $value ? $value : null;
    }

    /**
     * The value as a keyed array, empty when the read answered nothing.
     *
     * @return array<int|string, mixed>
     */
    public static function rows(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * The value as a list, keys discarded — for the places that `array_merge()` two
     * result sets, which reindexes them anyway.
     *
     * @return list<mixed>
     */
    public static function listOf(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }
}
