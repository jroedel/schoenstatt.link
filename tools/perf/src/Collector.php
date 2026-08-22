<?php

declare(strict_types=1);

namespace SchoenstattPerf;

/**
 * One request's worth of measurements, written out as a single JSON line.
 *
 * Static because the two probes that feed it are built by the ServiceManager at unrelated
 * moments and there is exactly one request in flight per process. A profiler that had to be
 * threaded through the container would need the container to know about profiling, which is
 * precisely what this harness must not require: nothing in `src/`, `module/` or `config/`
 * refers to this class, and the application runs identically whether it is loaded or not.
 *
 * The output is JSON Lines rather than a report because the interesting questions are
 * cross-request — cold versus warm, one route versus another — and answering them in the
 * process that produced a single row would mean the harness deciding in advance which
 * questions get asked. {@see ../report.php} does the aggregating.
 */
final class Collector
{
    /**
     * The switch, and it is a directory rather than an environment variable or an ini
     * setting: `curl` cannot set a variable in the Apache worker that will serve the
     * request, and anything in php.ini needs an image rebuild. `tools/perf/run.sh` creates
     * this directory to arm the harness and the collector writes nothing when it is absent,
     * so a stray copy of the probe config costs one `is_dir()` per request.
     */
    private const OUTPUT_DIR = '/data/perf';

    private const OUTPUT_FILE = 'requests.jsonl';

    private static bool $booted = false;

    /** @var float */
    private static float $startedAt = 0.0;

    /** @var list<array{sql: string, ms: float}> */
    private static array $queries = [];

    /** @var array<string, array{store: string, op: string, hit: int, miss: int, ms: float, bytes: int}> */
    private static array $cache = [];

    /**
     * Arms the collector for this request. Idempotent; a second call is a no-op, which
     * matters because the config file that installs the probes is read once per container
     * build and a request may build more than one container (bin/console does).
     */
    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        //REQUEST_TIME_FLOAT, not microtime(): boot() runs whenever the container first
        //builds a probed service, which on a Symfony-served route can be most of the way
        //through the request. Timing from there reported 3 ms for a page curl measured at
        //92 ms — a number that is not wrong so much as answering a different question.
        self::$startedAt = (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));

        //Shutdown rather than an event listener: this has to fire under both front
        //controllers and under bin/console, and the three have no lifecycle hook in common.
        //It also fires on a fatal, which is when a measurement is most interesting.
        register_shutdown_function([self::class, 'flush']);
    }

    public static function recordQuery(string $sql, float $seconds): void
    {
        self::$queries[] = ['sql' => self::normalize($sql), 'ms' => round($seconds * 1000, 3)];
    }

    /**
     * A single cache operation. `$hit` is null for operations where hitting is not a
     * meaningful outcome (setItem, hasItem, removeItem).
     */
    public static function recordCache(
        string $store,
        string $op,
        string $key,
        ?bool $hit,
        float $seconds,
        int $bytes = 0
    ): void {
        $bucket = $store . '|' . $op . '|' . self::keyFamily($key);
        if (! isset(self::$cache[$bucket])) {
            self::$cache[$bucket] = [
                'store' => $store,
                'op'    => $op,
                'key'   => self::keyFamily($key),
                'hit'   => 0,
                'miss'  => 0,
                'ms'    => 0.0,
                'bytes' => 0,
            ];
        }
        if (true === $hit) {
            self::$cache[$bucket]['hit']++;
        } elseif (false === $hit) {
            self::$cache[$bucket]['miss']++;
        }
        self::$cache[$bucket]['ms']    = round(self::$cache[$bucket]['ms'] + $seconds * 1000, 3);
        self::$cache[$bucket]['bytes'] += $bytes;
    }

    public static function flush(): void
    {
        //tools/perf/src/Collector.php -> tools/perf/src -> tools/perf -> tools -> repo root
        $dir = dirname(__DIR__, 3) . self::OUTPUT_DIR;
        if (! is_dir($dir)) {
            return;
        }
        $path = $dir . '/' . self::OUTPUT_FILE;

        $queryMs = 0.0;
        foreach (self::$queries as $q) {
            $queryMs += $q['ms'];
        }

        //Queries are reported both in full and as a distinct-SQL histogram. The histogram is
        //the one that answers "what would caching remove": twelve executions of one statement
        //is one cacheable result, not twelve.
        $distinct = [];
        foreach (self::$queries as $q) {
            $distinct[$q['sql']] = ($distinct[$q['sql']] ?? 0) + 1;
        }
        arsort($distinct);

        $record = [
            //From the query string, because that is the only channel a curl caller shares
            //with the worker that answers it. `_perf` is inert: no route reads it.
            'label'        => is_string($_GET['_perf'] ?? null) ? $_GET['_perf'] : 'unlabelled',
            'uri'          => $_SERVER['REQUEST_URI'] ?? ($_SERVER['argv'][1] ?? 'cli'),
            'status'       => http_response_code() ?: 0,
            'wallMs'       => round((microtime(true) - self::$startedAt) * 1000, 3),
            'peakMemoryMb' => round(memory_get_peak_usage(true) / 1048576, 2),
            'queries'      => count(self::$queries),
            'distinct'     => count($distinct),
            'queryMs'      => round($queryMs, 3),
            'topQueries'   => array_slice($distinct, 0, 15, true),
            'cache'        => array_values(self::$cache),
        ];

        //LOCK_EX because six Apache workers write this file concurrently and a torn line is
        //an unparseable run, not a missing row.
        @file_put_contents($path, json_encode($record, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * Collapses a statement to its shape: literals and IN-lists become placeholders, so
     * "the same query with a different id" counts once. Without this every per-row lookup
     * looks unique and the histogram says nothing.
     */
    private static function normalize(string $sql): string
    {
        $sql = preg_replace('/\s+/', ' ', trim($sql)) ?? $sql;
        $sql = preg_replace("/'[^']*'/", "'?'", $sql) ?? $sql;
        $sql = preg_replace('/\b\d+\b/', '?', $sql) ?? $sql;
        $sql = preg_replace('/\(\s*\?(\s*,\s*\?)+\s*\)/', '(?...)', $sql) ?? $sql;

        return mb_substr($sql, 0, 400);
    }

    /**
     * Cache keys carry an id (`sion-table-books-book-12`), so raw keys would produce a
     * histogram as long as the database. The trailing numeric segment is folded away.
     */
    private static function keyFamily(string $key): string
    {
        return preg_replace('/-?\d+$/', '-{id}', $key) ?? $key;
    }
}
