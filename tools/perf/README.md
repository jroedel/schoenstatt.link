# Cache and query performance harness

Measures, per HTTP request: every database statement and what it cost, every cache operation
and whether it hit, wall time and peak memory. Drives a fixed URL set cold and warm and
aggregates. The findings it produced on 2026-08-22 are in
[`docs/caching-performance.md`](../../docs/caching-performance.md).

```
./tools/perf/run.sh                        # measure whichever front controller is live
PERF_TAG=laminas ./tools/perf/run.sh       # tag a run so its output survives the next one
php tools/perf/report.php data/perf-symfony.jsonl
```

Environment: `PERF_BASE` (default `http://localhost:8080`), `PERF_REPEATS` (default 3),
`PERF_API_KEY` (default `local-dev-api-key`, used only to flush the cache between phases),
`PERF_TAG`.

## How it attaches

`perf.local.php` is copied into `config/autoload/` by the runner and removed again on exit.
It registers **delegators only** — the DB adapter gets a `Laminas\Db\Adapter\Profiler\
ProfilerInterface`, each cache storage gets listeners on its own event manager. No
application service is replaced, no application file refers to this harness, and removing the
file restores the application exactly.

The harness classes live in `autoload-dev`, so a `--no-dev` install cannot load them; the
config file checks `class_exists()` and returns `[]` rather than fatalling if a copy is ever
left behind on such an install.

## Three things that will waste your afternoon

**The merged-config cache.** Adding or removing a file in `config/autoload/` changes the
merged config, and `data/config/` holds a serialized copy. Forget to clear it and the probes
are never installed — the run completes and reports zero cache activity, which reads exactly
like a finding. `run.sh` clears it on both install and removal.

**OPcache revalidation.** The capsule runs `revalidate_freq=2`, so a file written less than
two seconds before a request may not be seen by the worker serving it. `run.sh` sleeps three.

**A zero is a bug until proven otherwise.** The first version of the cache listener called
`array_key_exists()` on `$event->getParams()`, which answers an `ArrayObject`. That is a
`TypeError` — an `Error`, not an `Exception`, so laminas-cache's own `catch (\Exception)`
around `getItem()` did not stop it — and the harness reported a confident, uniform **zero
queries and zero cache operations on every route**. It looked like a finding. `run.sh` now
refuses to print a report when the total observation count is zero, and says so in those
words.

## What the numbers do and do not mean

**"Cache hit" means the storage returned something, not that the caller used it.**
`SionCacheTrait::mayServe()` refuses to serve an item whose dependency map entry is missing
and reports that refusal to its caller as a miss. That decision happens above the layer these
listeners watch, so every hit figure is an upper bound. Measuring refusals directly would need
a counter inside the trait.

**Statement counts are folded.** `report.php` collapses literals and IN-lists so that "the
same query with a different id" counts once — otherwise every per-row lookup looks unique and
the histogram says nothing. The raw per-request count in the `queries` field is not folded.

**Medians, not means.** The capsule shares a host, and one descheduled request skews a mean
badly.

**The `?_perf=` query parameter** carries the phase and route label, because `curl` cannot set
an environment variable in the Apache worker that will answer. No route reads it. It is
recorded as part of the URI, so it is visible in the raw JSONL.

## Output

`data/perf/requests.jsonl` while a run is in flight, then archived to
`data/perf-<tag>.jsonl`. The **presence of `data/perf/` is the switch**: the collector writes
nothing when that directory is absent, so a stray copy of the probe config costs one
`is_dir()` per request and nothing else.

One JSON object per request: `label`, `uri`, `status`, `wallMs`, `peakMemoryMb`, `queries`,
`distinct`, `queryMs`, `topQueries` (folded histogram), and `cache` (one row per store × op ×
key family, with hit/miss/ms/bytes).

`data/` is gitignored, so nothing here is ever committed.
