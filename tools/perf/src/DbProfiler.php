<?php

declare(strict_types=1);

namespace SchoenstattPerf;

use Laminas\Db\Adapter\Profiler\ProfilerInterface;
use Laminas\Db\Adapter\StatementContainerInterface;

/**
 * Times every statement the laminas-db driver executes and hands it to {@see Collector}.
 *
 * laminas-db ships `Laminas\Db\Adapter\Profiler\Profiler`, which accumulates every statement
 * in an array and hands it back at the end. That is the wrong shape here for one reason: a
 * page rendering a few thousand rows produces a few thousand entries and the array outweighs
 * the thing being measured. This keeps a running total and a folded histogram instead, so the
 * profiler's own footprint does not distort what it reports.
 */
final class DbProfiler implements ProfilerInterface
{
    private ?string $sql = null;

    private float $startedAt = 0.0;

    /**
     * @param string|StatementContainerInterface $target
     * @return $this
     */
    public function profilerStart($target)
    {
        //`is_string()` rather than a bare cast: the interface documents `string|
        //StatementContainerInterface`, but laminas-db hands a plain object through in at
        //least one path and a profiler that fatals is worse than one that says "(unknown)".
        if ($target instanceof StatementContainerInterface) {
            $this->sql = (string) $target->getSql();
        } else {
            /** @phpstan-ignore-next-line the docblock says string; reality is looser */
            $this->sql = is_string($target) ? $target : '(unknown)';
        }
        $this->startedAt = microtime(true);

        return $this;
    }

    /**
     * @return $this
     */
    public function profilerFinish()
    {
        if (null === $this->sql) {
            //A finish with no start means someone else called us out of order. Silently
            //dropping the sample is right: a profiler that throws turns a measurement run
            //into an outage report about the profiler.
            return $this;
        }

        Collector::recordQuery($this->sql, microtime(true) - $this->startedAt);
        $this->sql = null;

        return $this;
    }
}
