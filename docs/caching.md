# Caching

Two shared-memory caches, both belonging to the web SAPI: **APCu** for query results and
JTranslate's phrase index, **OPcache** for compiled scripts. Query results are cached through
`SionModel\Db\Model\SionCacheTrait` (mixed into `SionTable`, so every `*Table` has it, plus
JTranslate's `TranslationsTable`) into `SionModel\PersistentCache` (APCu, TTL 5 days,
`sion_model.persistent_cache_config`); JUser's tables use `JUser\Cache`, a separate APCu
namespace with a 1-day TTL (verify). Related: [DEPLOY.md](DEPLOY.md) for the flush the
deploy performs, [BACKLOG.md](BACKLOG.md) for the open items.

**Rule zero: an APCu or OPcache segment belongs to the SAPI that created it.** A CLI
process sees its own empty segment — `opcache.enable_cli` is off and APCu is per process
family — so a CLI `apcu_clear_cache()` flushes nothing the site reads, a CLI measurement of a
cached layer reports misses on everything, and anything a console command caches lands where
no request can find it. Measure and flush **over HTTP** (`/en/sm/cache-status`,
`/en/sm/clear-persistent-cache`, `bin/console cache:flush-persistent`, which goes over HTTP
for exactly this reason). Never from `php -r`, `bin/console` directly, or `php -i`.

## The dependency map

A cached item lives under its own key. What it depends on lives in one map per table class,
`<class>-cachedependencies`, naming every key the class has cached and the entities behind
it. `removeDependentCacheItems($entity)` reads that map, so **a key the map does not name is
a key nothing can remove** — it answers with whatever the database said when it was written
until its own TTL runs out. Four invariants hold, each pinned by
`test/Unit/SionCacheDependencyMapTest`:

- **The map is refreshed whenever the data is.** `cacheEntityObjects()` persists the map on
  every call, so its TTL cannot fall behind the items it governs.
- **An item we cannot invalidate is not served.** A hit whose key is absent from the map is
  reported as a miss and the caller's query re-registers it. Losing the map makes the cache
  slow, not wrong.
- **The map is merged, never overwritten**, so two requests registering different keys at
  once do not orphan each other's item.
- **A snapshot older than the last change is never written.** Items are written at the end
  of the request, long after they were read; a generation counter
  (`<class>-cachegeneration`, advanced with the storage's atomic `incrementItem` on every
  invalidation) lets the writer drop a snapshot a concurrent change has overtaken.

Two wiring facts pinned by `test/Integration/SionCacheWiringTest`: `setPersistentCache()`
discards the map read from a previous storage (JUser's factory swaps `PersistentCache` for
`JUser\Cache`, a different namespace), and `wireOnFinishTrigger()` is idempotent.

## The storage

`SionModel\Cache\Storage` is the cache surface, `ApcuStorage` and `FilesystemStorage` the
two implementations, and `StorageFactory` builds one from the configuration files already on
each deployment target. The interface is ours rather than PSR-16 for two reasons the trait
depends on: `getItem()` reports the hit through a by-reference flag, so a cached `null` is a
hit and not a miss, and `incrementItem()` is atomic, which is what makes the generation
counter above worth having. Both implementations also implement
`Psr\SimpleCache\CacheInterface`, which is how JTranslate takes one without depending on
this package.

Two consequences of dropping laminas-cache, both intended:

- **A write that fails is `false`, not an exception.** The laminas adapter threw from
  `internalSetItem()`; a cache is an optimisation and must not be able to take a page down.
- **Keys carry no `laminascache:` prefix any more.** That was laminas's default namespace,
  applied to every cache configuring none — the persistent cache, the application-wide one,
  Books'. Only the name changed; the first post-swap `cache:flush-persistent` clears the
  orphans, since it empties the whole segment.

## The flush point

Nothing is written to APCu at the moment it is cached. `cacheEntityObjects()` puts the item
in memory and on a queue; the queue is written out **after the response has been sent**, so
serializing a large result set never charges the visitor. Under `App\Kernel` the queue is
`SionModel\Cache\CacheFlushQueue`, drained by `App\Http\SionCacheFlushListener` on
`KernelEvents::TERMINATE`. Tables **enrol themselves into the queue from their factory** —
there is no way to ask a `ServiceManager` whether a service was instantiated, so a listener
that resolved tables by name would build all of them, each with a database adapter, on
`/_health`. (Fixed 2026-08-22; before that a Symfony-served route wrote nothing, because the
only flush point was a laminas `MvcEvent::FINISH` listener, and nobody noticed because
wall time went *down*.)

- **The queue is the only flush point.** A host that registers none has a persistent cache
  that stores nothing; the laminas `MvcEvent::FINISH` fallback is gone with laminas-mvc.
- **A process with neither gets no flush point**, which is correct (rule zero).
- **The queue is drained by the pass that writes it**; a second flush writes nothing.
- When a queue is present the MVC `Application` service is deliberately not resolved.

## The ACL is not cached

`App\Acl\AclProvider` assembles the ACL per request from plain arrays (~0.45 ms, as cheap as
reading a cached one back), memoized for the request only. There is **no cross-request ACL
cache**, so a role, library or text change is simply picked up on the next request.
`App\Acl\AclCacheInvalidator` is gone with it, and the `bjyauthorize.cache_*` keys still in
`config/autoload/acl.global.php` are inert — nothing resolves BjyAuthorize's `Authorize`
service any more.

If a cross-request ACL cache is ever reintroduced, three things about it, each learned while
BjyAuthorize's was live (2026-08-22 to 2026-09-09): it must expire on `user-role`, `library`
and `text` and **not** on `user-role-link` (an account's roles are read per request and never
enter the document); hang the invalidation off `SionCacheTrait::removeDependentCacheItems()`
via SionModel's `EntityChangeListenerInterface`, the one point every write passes through;
and know the two failure faces — a cached ACL that predates a **role** is a **500** for
everyone granted it (`addRole()` throws on an unknown parent), while one that predates a
**resource** answers **403** and self-heals at the TTL. "The new admin page 403s for
everyone" and "every request by one account is a 500" would be the same bug.

## Item size: an item too big to store is worse than no item

Production APCu is one fixed shared segment: `apc.shm_size` **256M**, `apc.ttl` **0**,
APCu 5.1.27, in `/home/httpd/php85-ini/ourlink/php.ini` (root-owned; the path carries the
PHP version, so every konsoleH version flip reverts it — diff after any switch).

With `apc.ttl = 0`, a failed allocation does not evict selectively:
`apc_cache_default_expunge()` **clears the entire cache**, site-wide, for every user.
Unbounded, that is self-sustaining: the wipe leaves the navigation cold, the next request
rebuilds it and queues the same oversized write, which wipes again. It shows up as APCu
`expunges` and slow pages, never as an error.

**`sion_model.max_cached_item_size`** (bytes, default **4 MiB** = 4194304, `0` disables) is
the one bound on a persistent cache write. `onFinishWriteCache()` measures
`strlen(serialize($value))` — a close proxy for APCu's own allocation (30,626,536 measured
against 30,626,760) — skips anything over budget and logs a warning with key, size and
budget. **Neither a refusal nor a failed write abandons the rest of the queue.** Calibration:
legitimate items top out around 2.5 MiB; the two structural offenders were `unlinked-books`
(33,690 rows, 45.7 MiB) and `query-objects-publication` (10,166 rows, 29.2 MiB), both built
by callers needing a fraction of the data — books carry ~300 bytes/row of data but serialize
to ~1,423 because every row repeats 46 keys and `filterDbDate()` makes five `DateTime`
objects per row. Both would fit in 256M, which is exactly why the raise is not a fix on its
own.

`sion_model.max_items_to_cache` is **retired** (it capped how many items a table wrote per
request, which bounded convergence time rather than memory). It is ignored, not renamed;
`/en/sm/cache-status` lists any retired key a server still names under
`sionModel.retiredConfigKeys` — because `config/autoload/local.php` is gitignored, a dead
setting can otherwise sit on a server indefinitely — and `tools/smoke-prod.sh` warns when
that list is non-empty.

## Checking occupancy: `/en/sm/cache-status`

    curl -H 'X-Api-Key: <sion_model api_key>' https://schoenstatt.link/en/sm/cache-status

Prefer the header over `?key=`, which is written to the access log. No key or a wrong one is
a `401` with a JSON body (`App\Http\MaintenanceKey`). The payload is built by
`SionModel\Cache\CacheStatusPayload` from `ApcuStatus` and `OpcacheStatus` (both unit-tested
without an extension loaded: `test/Unit/{ApcuStatus,OpcacheStatus}Test`); its key names are
read by name by `tools/smoke-prod.sh`.

The `apcu` block carries segment totals, hit/miss/expunge counters, and `largestEntries`
with real APCu `mem_size`, largest first. Aggregate occupancy says the segment is full;
`largestEntries` says **which key** filled it, which decides between a bigger segment and a
narrower query, and is how `max_cached_item_size` should be re-tuned rather than assumed.

The deploy flushes APCu as its last step (`tools/deploy.sh` → `bin/console
cache:flush-persistent`, over HTTP into the web SAPI). **That step is a `warn`, not a hard
failure**: if it does not answer, the deploy completes and the operator re-runs it.

## OPcache

Same endpoint, `opcache` block. `validate_timestamps=On`, `revalidate_freq=2`, 128 MB,
`max_accelerated_files=10000`, interned-strings buffer 32 MB. The two caches fail differently
and the fields are chosen for that: APCu degrades to misses as it fills; OPcache **restarts**
when out of memory or hash slots, discarding every compiled script, and leaves only a
counter behind.

- `oomRestarts` / `hashRestarts` are the analogue of APCu's `expunges` — non-zero means the
  cache has already been thrown away. `cacheFull` is OPcache saying it has no room.
- `memoryPercentUsed` counts wasted memory as used; stale entries cannot be handed out.
- `keysPercentUsed` is measured against `maxCachedKeys` (**16229** — PHP rounds the hash
  table up to the next prime), not against the configured 10000; both are reported.
- `cachedKeys` exceeds `cachedScripts` because a script occupies several keys (include path
  plus resolved realpath). Compare keys against the ceiling. Those alias keys are also where
  a release swap hides: the script is filed under the resolved path, so the alias keeps
  pointing at the previous release — which is why the deploy resets every pool and polls
  `/_health` for its own `.revision` (`test/Deploy/opcache-swap-test.sh` is the repro;
  `opcache.revalidate_path=1` does **not** fix it, measured).
- `validateTimestamps` decides whether a deploy needs a pool restart. On (current): changed
  files are noticed within seconds. Off: every deploy needs `pkill -u ourlink -f php`.
- `startTimeUnix` is the only per-segment identity OPcache exposes. This host runs **at
  least three** PHP pools with a segment each, and a request answers for whichever served
  it; `uptimeSeconds` cannot serve, since it differs between two polls of the same segment.
  `tools/opcache-sample.sh` polls and groups by it, reporting the count as a lower bound.

### Reading the interned-strings buffer

Every other number here describes a cache that evicts. The interned buffer is
**append-only**: within a segment's life it only rises, stops rising when full, and a
restart puts it near zero. So:

- **A percentage means nothing without the segment's uptime.** 84% at nine minutes and 84%
  at nine days are opposite findings; `tools/smoke-prod.sh` prints `internedPercentUsed`
  and `uptimeSeconds` on one line for that reason.
- **A reading taken after a deploy is cold and always looks healthy.** The warmest reading
  is the one taken immediately *before* a reset — `tools/deploy.sh` prints each pool's usage
  at the instant it wipes it; run `tools/opcache-sample.sh` before a deploy, not after.
- **When it fills, nothing happens** — no restart, no counter. OPcache stops interning and
  stores duplicates per script. The only evidence is the percentage sitting at 100.
- `internedBufferBytes` (allocated) and `internedBufferConfiguredMb` (the ini) are both
  reported because a ratio cannot show its own denominator changing.
  `opcache.interned_strings_buffer` is `PHP_INI_SYSTEM`, read once per process start, so a
  pool that has not recycled legitimately runs on the old buffer. Confirm a system ini from
  `/en/sm/phpinfo`, never from CLI `php -i`.

`tools/smoke-prod.sh` warns — never fails — at 80% memory or keys, 90% interned strings, any
restart, `cacheFull`, or timestamp validation off.

## Cautions when adding a cached call

- Cache what the caller needs, not the table. A full-table key is almost always a narrow
  projection or a `GROUP BY` in disguise.
- Declare dependencies honestly: `cacheEntityObjects()`'s third argument is the list of
  entities whose change invalidates the item. A payload embedding a related record must
  name that entity or it goes stale.
- Never write to APCu around `SionCacheTrait`. An item stored directly is one the dependency
  map does not name — the un-invalidatable state by construction.
- Read and write the **same** key. A key salted by `$this->getLocale()` on read and a bare
  literal on write is a permanent miss that rebuilds and rewrites every request
  (`SchoenstattTable::getAssignments()` did this).
- Salt by locale only when the content varies by locale — and **cache before the sort**.
  `getShrines()` cached a locale-sorted array under a locale-less key and served four
  languages English's order for years.
- `SionTable`'s `link*` methods must not re-query rows the caller already holds when the
  source is a whole-table cache (see `SchoenstattTable::linkAssociations()`); and linked
  rows stay a separate snapshot, never PHP references — references save no live memory
  (copy-on-write) and only shrink `serialize()`.
- **Signed-in identity resolves through the cache.** `JUser\Authentication\SessionIdentity`
  keeps only the user id in the session and re-reads the row every request via
  `UserTable::findById()`,
  which serves from `all-linked-users` when warm. A revocation made in the application
  (unticking Active) invalidates `['user', 'user-role', 'user-role-link']` and takes effect
  next request; a `state` written straight to the database (a migration, a DBA) is
  **invisible to an open session** until something writes to a user through the app or the
  1-day TTL expires. Pinned by `AuthSmokeTest::testDeactivationEndsASessionThatIsAlreadyOpen`.

## What the cache is worth, and what it cannot do

Measured cold vs warm over nine routes: a working entity cache removes about **14% of query
time**. It empties the cheap pages (home 45 → 0.4 ms) and barely touches the expensive ones,
because the 300 ms publication query is per-record and per-request and is not cached.
Rewriting what an expensive page asks the database for is worth more than any cache tuning;
re-measure with `tools/perf/run.sh` before touching the ~120 inline
`fetchCachedEntityObjects()` call sites. Two structural limits: every entity show page
**writes** (`registerVisit()` inserts into `sch_visits`), so it can never be served entirely
from cache without redefining a visit; and a read-only measurement run never exercises the
generation-counter guard (`bumpGeneration()` only writes on invalidation), so such runs say
nothing about whether it works. Nine tables over 200 rows carry only a primary key and that
is correct — 150 of 215 reads are PK lookups and the rest are whole-table loads feeding an
APCu item; selectivity decides (the same `Kind` column is indexed-and-used at 9%, ignored at
42%). Use `COUNT(*)`, not `information_schema.TABLE_ROWS`, when a decision rests on a count.

## Still open

- `apc.ttl = 0` — setting it above 0 would make APCu evict instead of wiping. A konsoleH
  ticket, not ours to edit.
- `query-objects-publication` is still *built* by the literature routes and refused rather
  than stored, so those run uncached; a narrow projection is the next win.
- Navigation branch caches (`Application\Navigation\PageBuilder`) are written outside
  `SionCacheTrait` and never invalidated when the data changes; they go stale until the TTL.
- `JUser\Cache` (1 day) and `SionModel\PersistentCache` (5 days) differ for no recorded
  reason; nothing depends on the difference.
- Compression (the book blob gzips 45.7 → 3.7 MiB at level 1) costs ~540 ms to decode plus
  `unserialize()`, so it suits mid-sized items only. Redis would remove the fixed-segment
  ceiling and the clear-everything mode, but the extension was **disabled in the hosting
  console 2026-08-04 to reduce attack surface**; re-enabling it is a security decision.
