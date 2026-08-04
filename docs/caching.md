# Caching

The app caches query results in APCu through `SionModel\Db\Model\SionCacheTrait`
(mixed into `SionTable`, so every `*Table` model has it, plus JTranslate's
`TranslationsTable`). This document covers the one thing about that layer that
is genuinely surprising: **an item too big to store is far worse than no item at
all**, and what was done about it.

## Why item size matters so much

Production APCu is a single fixed shared segment. `apc.shm_size` is **32M**
(`/home/httpd/php74-ini/ourlink/php.ini`, still open — see BACKLOG) and
`apc.ttl` is **0**.

With `apc.ttl = 0`, when APCu cannot allocate room for an item it does not evict
selectively — `apc_cache_default_expunge()` **clears the entire cache**. On top
of that, `Laminas\Cache\Storage\Adapter\Apcu::internalSetItem()` throws a
`RuntimeException` when `apcu_store()` returns false.

So a single oversized write used to do three things at once:

1. wipe every cached item, for every user, site-wide;
2. throw; and
3. via the old `catch` block in `onFinishWriteCache()`, abandon every *other*
   cache write queued in that request.

That is self-sustaining. A wipe leaves the navigation cache cold, so the next
request rebuilds the navigation, which queued another oversized write, which
wiped the cache again. Every visitor paid a full rebuild. It showed up as
unexplained APCu `expunges` and slow pages, not as an error.

## What the offenders were

Measured against production-scale data (2026-08-01):

| cache key | rows | size |
| --- | --- | --- |
| `unlinked-books` (all books, 46 fields each) | 33,690 | **45.7 MiB** |
| `query-objects-publication` (all publications, 80 fields each) | 10,166 | **29.2 MiB** |

Each was individually larger than the whole 32M segment. The bloat is
structural, not content: books carry ~300 bytes/row of real data but serialize
to ~1,423 bytes/row, because every row repeats all 46 string keys and
`SionTable::filterDbDate()` turns date columns into `DateTime` objects
(~114 bytes each, five per book).

Both were built by callers that needed a fraction of the data — per-library
category counts, or four fields for a navigation label. Both are now narrow
projections.

## The size budget

`sion_model.max_cached_item_size` (bytes, default **4 MiB**, `0` disables)
bounds a single persistent cache item. `onFinishWriteCache()` measures with
`strlen(serialize($value))` and skips anything over budget, logging a warning
with the key, the size and the budget. A refused item does not consume one of
the `max_items_to_cache` write slots, and neither a refusal nor a failed write
abandons the rest of the queue.

The default is not a guess. Warming the main routes against production-scale
data produces a long tail of legitimate items topping out around 2.5 MiB and
then one 29 MiB outlier; 4 MiB sits in the gap, so real items keep caching with
room to grow and only table-sized blobs are refused.

`strlen(serialize())` is a proxy — APCu serializes with its own routine — but a
close one: on the publication blob it read 30,626,536 bytes against APCu's own
30,626,760 byte allocation.

## Checking occupancy in production

    GET /sm/cache-status?key=<sion_model api_key>

Returns segment totals, hit/miss/expunge counters, and `largestEntries` — the
biggest entries with their real APCu `mem_size`, largest first. Aggregate
occupancy tells you the segment is full; `largestEntries` tells you *which key
filled it*, which is what decides whether the answer is a bigger segment or a
narrower query. It is also how `max_cached_item_size` should be re-tuned rather
than assumed.

Non-obvious: the web SAPI and CLI have **separate** APCu segments, so a CLI
probe cannot see what the site cached. Use the endpoint, not a shell one-liner.

## The other shared cache: OPcache

The same response carries an `opcache` block (added 2026-08-04, once OPcache was
enabled in production). It is reported here for the same reason APCu is: an
OPcache segment also belongs to the SAPI that created it — `opcache.enable_cli`
is off — so only a request into the web SAPI can say what the site has compiled.

The two caches fail *differently*, and that is what the fields are chosen for.
APCu degrades to misses as it fills. OPcache does not degrade at all: when it
runs out of memory or hash slots it **restarts**, discarding every compiled
script, and the only lasting evidence is a counter. So:

- `oomRestarts` / `hashRestarts` are the OPcache analogue of APCu's `expunges` —
  non-zero means the cache has already been thrown away at least once.
- `cacheFull` is OPcache saying outright that it has no room for new scripts.
- `memoryPercentUsed` counts wasted memory as used, because stale entries occupy
  the segment and cannot be handed to a new script.
- `keysPercentUsed` is measured against `maxCachedKeys`, **not** against
  `opcache.max_accelerated_files`. PHP rounds the script hash table up to the
  next prime, so a configured 10000 reports a real ceiling of 16229. Measuring
  against the configured number understates the headroom — the wrong direction
  to be wrong about a cache that flushes when it fills. Both numbers are
  reported (`maxCachedKeys`, `maxAcceleratedFilesConfigured`) so the difference
  is visible rather than surprising.
- `cachedKeys` exceeds `cachedScripts` because one script can occupy several
  keys (the include path plus the resolved realpath). Compare keys, not scripts,
  against the ceiling.
- `validateTimestamps` is surfaced because it decides whether a deploy needs a
  pool restart. With it on (the current setting, `revalidate_freq=2`) changed
  files are noticed within seconds. With it off, a deploy is invisible to
  OPcache and would serve the previous release until `pkill -u ourlink -f php`.

`tools/smoke-prod.sh` polls all of this and warns — never fails — at 80% memory
or keys, 90% interned strings, any restart, `cacheFull`, or timestamp validation
being off. First live reading after enabling OPcache: ~29% memory, ~15% of 16229
keys, 99.2% hit rate.

The arithmetic lives in `SionModel\Cache\OpcacheStatus`, kept out of the
controller so it is unit-testable without a container
(`test/Unit/OpcacheStatusTest.php`).

## Cautions when adding a cached call

- Cache what the caller needs, not the table. A full-table cache key is almost
  always a narrow projection or a `GROUP BY` in disguise.
- Declare the dependencies honestly. `cacheEntityObjects()`'s third argument is
  the list of entities whose change invalidates the item; if the payload embeds
  a related record, that entity belongs in the list or the item goes stale.
- Read and write the *same* key. A key built from `$this->getLocale()` on read
  and a bare literal on write is a permanent miss that rebuilds and rewrites
  every request — this happened in `SchoenstattTable::getAssignments()`.
- Salt by locale only when the cached content really varies by locale. Salting
  multiplies the item count, which is the resource being conserved.
- Nothing in `Application\Module::onBootstrap()` may let a cache failure escape;
  an uncaught throw there is a 500 on every request until APCu drains.

## Still open

- `apc.ttl = 0` means a failed allocation clears everything. Setting it above 0
  would make APCu evict stale entries instead. Defense in depth behind the size
  budget, and a one-line ini change — but the ini is not editable by us
  (see BACKLOG).
- The full-entity `query-objects-publication` blob is still *built* by the
  literature routes (`PublicationsController`, `LibrariesController`). It is now
  refused rather than destructive, so those routes simply run uncached. A narrow
  projection there is the next win.
- The navigation cache keys written in `onBootstrap()` are never invalidated
  when the underlying data changes; `removeDependentCacheItems()` only clears
  keys registered through `SionCacheTrait`. They go stale until the TTL.
- Compression is unusually effective on these repetitive arrays (the book blob
  gzips 45.7 MiB → 3.7 MiB at level 1), but decode plus `unserialize()` costs
  ~540 ms, so it suits mid-sized items rather than the giant ones. Redis — the
  extension is already installed in production — would remove the fixed-segment
  ceiling and the clear-everything failure mode entirely. Both are strategic
  options, neither is needed now.
