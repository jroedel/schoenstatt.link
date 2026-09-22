<?php

declare(strict_types=1);

namespace SchoenstattPerf;

/**
 * Times every statement the connection executes and hands it to {@see Collector}.
 *
 * `Laminas\Db\Adapter\Profiler\ProfilerInterface` until 2026-09-22: a start/finish pair
 * where the listener had to narrow a `StatementContainerInterface` before it could read the
 * SQL, and where a finish arriving without a start had to be tolerated. `Connection::watch()`
 * hands over the statement and its elapsed time in one call, after the fact, so neither
 * problem exists any more and the class is the one line that was ever the point.
 *
 * laminas-db also shipped a `Profiler` that accumulated every statement in an array. That was
 * the wrong shape here: a page rendering a few thousand rows produces a few thousand entries
 * and the array outweighs the thing being measured. `Collector` keeps a running total and a
 * folded histogram, so the measurement\'s own footprint does not distort what it reports.
 */
final class DbProfiler
{
    /** @param list<mixed> $values */
    public function __invoke(string $sql, array $values, float $seconds): void
    {
        Collector::recordQuery($sql, $seconds);
    }
}
