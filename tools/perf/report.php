<?php

/**
 * Aggregates data/perf/requests.jsonl into the four numbers the cache refactor needs:
 * how much database work a route does, how much of it the cache is being asked to remove,
 * how much it actually removes, and what that is worth in milliseconds.
 *
 * Medians rather than means throughout. A capsule shares a host with whatever else is
 * running on it and one descheduled request skews a mean badly; the question here is what a
 * typical request costs.
 */

$path = $argv[1] ?? (dirname(__DIR__, 2) . '/data/perf/requests.jsonl');
if (! is_readable($path)) {
    fwrite(STDERR, "no measurements at $path\n");
    exit(1);
}

/** @var list<array<string, mixed>> $records */
$records = [];
foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $row = json_decode($line, true);
    if (is_array($row)) {
        $records[] = $row;
    }
}
if ([] === $records) {
    fwrite(STDERR, "no parseable records in $path\n");
    exit(1);
}

function median(array $values): float
{
    if ([] === $values) {
        return 0.0;
    }
    sort($values);
    $mid = intdiv(count($values), 2);

    return 0 === count($values) % 2
        ? ($values[$mid - 1] + $values[$mid]) / 2
        : $values[$mid];
}

function bold(string $s): string
{
    return "\033[1m$s\033[0m";
}

// ------------------------------------------------------------------ group by label
$byLabel = [];
foreach ($records as $r) {
    $byLabel[(string) ($r['label'] ?? 'unlabelled')][] = $r;
}
ksort($byLabel);

echo bold("Per route, per phase") . "\n";
printf(
    "  %-22s %6s %8s %8s %9s %8s %7s %7s %7s\n",
    'label',
    'n',
    'wall ms',
    'query ms',
    'queries',
    'distinct',
    'gets',
    'hits',
    'writes'
);
echo '  ' . str_repeat('-', 92) . "\n";

$summary = [];
foreach ($byLabel as $label => $rows) {
    $gets = $hits = $writes = 0;
    foreach ($rows as $r) {
        foreach ($r['cache'] ?? [] as $c) {
            if (str_starts_with((string) $c['op'], 'getItem')) {
                $gets += (int) $c['hit'] + (int) $c['miss'];
                $hits += (int) $c['hit'];
            } elseif (str_starts_with((string) $c['op'], 'setItem')) {
                $writes += max(1, (int) $c['hit'] + (int) $c['miss']);
            }
        }
    }
    $n = count($rows);

    $summary[$label] = [
        'n'        => $n,
        'wallMs'   => median(array_column($rows, 'wallMs')),
        'queryMs'  => median(array_column($rows, 'queryMs')),
        'queries'  => median(array_column($rows, 'queries')),
        'distinct' => median(array_column($rows, 'distinct')),
        'gets'     => (int) round($gets / $n),
        'hits'     => (int) round($hits / $n),
        'writes'   => (int) round($writes / $n),
    ];

    printf(
        "  %-22s %6d %8.1f %8.1f %9d %8d %7d %7d %7d\n",
        $label,
        $n,
        $summary[$label]['wallMs'],
        $summary[$label]['queryMs'],
        $summary[$label]['queries'],
        $summary[$label]['distinct'],
        $summary[$label]['gets'],
        $summary[$label]['hits'],
        $summary[$label]['writes']
    );
}

// ------------------------------------------------------------------ cold vs warm
echo "\n" . bold("Cold versus warm — what the cache is worth today") . "\n";
printf("  %-18s %10s %10s %10s %10s %10s\n", 'route', 'cold ms', 'warm ms', 'saved', 'cold q', 'warm q');
echo '  ' . str_repeat('-', 74) . "\n";

$routes = [];
foreach (array_keys($summary) as $label) {
    if (str_contains($label, '.')) {
        [$phase, $route] = explode('.', $label, 2);
        $routes[$route][$phase] = $summary[$label];
    }
}
ksort($routes);

$totalCold = $totalWarm = 0.0;
foreach ($routes as $route => $phases) {
    if (! isset($phases['cold'], $phases['warm'])) {
        continue;
    }
    $cold = $phases['cold']['wallMs'];
    $warm = $phases['warm']['wallMs'];
    $totalCold += $cold;
    $totalWarm += $warm;
    $saved = $cold - $warm;

    printf(
        "  %-18s %10.1f %10.1f %9.1f%s %10d %10d\n",
        $route,
        $cold,
        $warm,
        $saved,
        //A negative saving is not noise to hide: it means the warm path costs more than the
        //cold one, which is what a cache that is written but never read looks like.
        $saved < 0 ? '!' : ' ',
        $phases['cold']['queries'],
        $phases['warm']['queries']
    );
}
printf("  %-18s %10.1f %10.1f %10.1f\n", bold('total'), $totalCold, $totalWarm, $totalCold - $totalWarm);

// ------------------------------------------------------------------ cache detail
echo "\n" . bold("Cache activity by store and key family") . "\n";
printf("  %-26s %-9s %-34s %6s %6s %6s\n", 'store', 'op', 'key family', 'hit', 'miss', 'ms');
echo '  ' . str_repeat('-', 94) . "\n";

$byKey = [];
foreach ($records as $r) {
    foreach ($r['cache'] ?? [] as $c) {
        $id = $c['store'] . '|' . $c['op'] . '|' . $c['key'];
        if (! isset($byKey[$id])) {
            $byKey[$id] = [
                'store' => $c['store'],
                'op'    => $c['op'],
                'key'   => $c['key'],
                'hit'   => 0,
                'miss'  => 0,
                'ms'    => 0.0,
            ];
        }
        $byKey[$id]['hit']  += (int) $c['hit'];
        $byKey[$id]['miss'] += (int) $c['miss'];
        $byKey[$id]['ms']   += (float) $c['ms'];
    }
}
uasort($byKey, static fn(array $a, array $b): int => ($b['hit'] + $b['miss']) <=> ($a['hit'] + $a['miss']));

foreach (array_slice($byKey, 0, 30) as $c) {
    printf(
        "  %-26s %-9s %-34s %6d %6d %6.1f\n",
        substr((string) $c['store'], 0, 26),
        $c['op'],
        substr((string) $c['key'], 0, 34),
        $c['hit'],
        $c['miss'],
        $c['ms']
    );
}
if ([] === $byKey) {
    echo "  (none — no cache storage was consulted in any measured request)\n";
}

// ------------------------------------------------------------------ query histogram
echo "\n" . bold("Most-executed statements across the run (folded)") . "\n";
$statements = [];
foreach ($records as $r) {
    foreach ($r['topQueries'] ?? [] as $sql => $count) {
        $statements[$sql] = ($statements[$sql] ?? 0) + $count;
    }
}
arsort($statements);
$i = 0;
foreach ($statements as $sql => $count) {
    printf("  %6d x  %s\n", $count, substr((string) $sql, 0, 150));
    if (++$i >= 12) {
        break;
    }
}

// ------------------------------------------------------------------ the headline
echo "\n" . bold("Totals") . "\n";
$allQueries = array_sum(array_column($records, 'queries'));
$allWrites = $allHits = $allGets = 0;
foreach ($records as $r) {
    foreach ($r['cache'] ?? [] as $c) {
        if (str_starts_with((string) $c['op'], 'setItem')) {
            $allWrites++;
        } else {
            $allGets += (int) $c['hit'] + (int) $c['miss'];
            $allHits += (int) $c['hit'];
        }
    }
}
printf("  requests measured        %d\n", count($records));
printf("  database statements      %d\n", $allQueries);
printf(
    "  cache reads              %d  (%d hit, %.0f%%)\n",
    $allGets,
    $allHits,
    $allGets > 0 ? 100 * $allHits / $allGets : 0
);
printf("  cache writes             %d\n", $allWrites);
if (0 === $allWrites && $allGets > 0) {
    echo "\n  " . bold('Nothing was written to any cache during this run.') . "\n";
    echo "  Reads happened, so the storages are wired. A read-only cache is a cache whose\n";
    echo "  contents predate the run — see docs/caching.md.\n";
}
