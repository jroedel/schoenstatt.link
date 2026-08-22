# Caching

> **[caching-performance.md](caching-performance.md) is the record of what this layer
> actually does at runtime**, and it is where the numbers live. Measured 2026-08-22: under
> the Symfony front controller — the one production runs — the persistent cache was writing
> no data items at all, because its only write path was an `MvcEvent::FINISH` listener that
> a Symfony-served route never reaches. **Fixed the same day** (see [The two flush
> points](#the-two-flush-points)); four of its five other findings are still open, so read
> that document before costing any work here.

The app caches query results in APCu through `SionModel\Db\Model\SionCacheTrait`
(mixed into `SionTable`, so every `*Table` model has it, plus JTranslate's
`TranslationsTable`). This document covers the two things about that layer that
are genuinely surprising — **an item too big to store is far worse than no item
at all**, and **an item whose dependency record is gone can never be
invalidated** — and what was done about each.

## The dependency map, and how it went missing (2026-08-09)

A cached item lives under its own key. What that item *depends on* lives
somewhere else: one map per table class, `<class>-cachedependencies`, naming
every key the class has cached and the entities behind it.
`removeDependentCacheItems()` reads the map, so **a key the map does not name is
a key nothing can remove.** It keeps answering with whatever the database said
when it was written, until its own TTL runs out.

That is what happened to the user list. An admin created an account, the row
landed in the database, and `/en/users` went on showing the world without it. No
race, no expunge, no full segment (`expunges: 0`, 5% of 256 MB used). The
sequence:

1. `JUser\Cache` stores with `ttl => 86400`, and both the data and the map live
   in it, so both expire.
2. A cache miss rewrote the *data* — pushing its expiry another day out — while
   the old code rewrote the *map* only when it learned a key it did not already
   have. A key already on record refreshed nothing.
3. So within about two days of ordinary traffic the map expired underneath a set
   of perfectly live cached items.
4. From then on `removeDependentCacheItems()` iterated an empty map and removed
   nothing, while `fetchCachedEntityObjects()` kept serving the items it could no
   longer invalidate.

Four properties now hold, each asserted by name in
`test/Unit/SionCacheDependencyMapTest`:

- **The map is refreshed whenever the data is.** `cacheEntityObjects()` persists
  the map on every call, so its TTL cannot fall behind the items it governs.
- **An item we cannot invalidate is not served.** A hit whose key is absent from
  the map is reported as a miss, and the caller's query re-registers it. If the
  map is ever lost anyway, the cache degrades to being slow rather than wrong.
- **The map is merged, never overwritten.** Two requests registering different
  keys at the same time would otherwise leave whichever wrote last as the only
  one on record — orphaning the other's item in milliseconds instead of a day.
- **A snapshot older than the last change is never written.** Items are written
  at the end of the request, long after they were read; a generation counter bumped
  on every invalidation (`<class>-cachegeneration`, advanced with the storage's
  atomic `incrementItem`) lets the writer recognise a snapshot a concurrent
  change has overtaken and drop it instead of putting it back on top of the
  removal.

Two related things were wrong in the same place and are fixed with it.
`SionTable`'s constructor injects `SionModel\PersistentCache` and JUser's factory
then replaces it with `JUser\Cache` — a different APCu namespace — so
`setPersistentCache()` now discards the map it read from the previous storage
rather than letting it vouch for keys in a namespace this instance no longer
touches. And the factory used to attach `onFinishWriteCache` a second time, which
is why every JUser key appeared twice in the application log; `wireOnFinishTrigger()`
is now idempotent. Both are pinned by `test/Integration/SionCacheWiringTest`.

## The two flush points

Nothing is written to APCu at the moment it is cached. `cacheEntityObjects()` puts the item
in memory and on a queue, and `onFinishWriteCache()` writes the queue out at the end of the
request — serializing a large result set mid-render would charge the visitor for it. So the
whole persistent cache depends on something calling that method, and **which something
depends on the front controller**:

| front controller | what calls `onFinishWriteCache()` |
|---|---|
| laminas (`SYMFONY_KERNEL=0`, and any bridged request) | a listener on `MvcEvent::FINISH`, attached by `SionTableWiring::wireFlushPoint()` |
| Symfony (production since 2026-08-11) | `App\Http\SionCacheFlushListener` on `KernelEvents::TERMINATE`, draining `SionModel\Cache\CacheFlushQueue` |

The queue is a per-request registry that tables **enrol themselves into from their factory**.
That is not indirection for its own sake: there is no way to ask a `ServiceManager` whether a
service was instantiated, so a listener that resolved tables by name would build all ten of
them, each with a database adapter, on a request that asked for `/_health`. Recording the
enrolment at the moment of use is the only way "only if it was used" can be known.

Three consequences worth knowing before changing any of it:

- **A host that registers no queue keeps the `MvcEvent` path unchanged.** That is patres,
  every un-strangled laminas host, and any console process. A process with neither gets no
  flush point at all, which is correct: an APCu segment belongs to the SAPI that created it,
  so anything a CLI run wrote would land where no web request can read it.
- **When a queue is present the MVC `Application` is deliberately not resolved.**
  `has('Application')` answers *true* under the Symfony front controller — laminas-mvc's
  module config defines the service whether or not anything bootstraps it — so the old
  unconditional code built an MVC application per table per request for an event manager
  whose event that request would never fire.
- **The queue is drained by the pass that writes it**, so calling the flush twice writes
  nothing the second time.
- **Both flush points run after the response has been sent.** `kernel.terminate` does by
  definition; the laminas listener does because it attaches at priority **-11000**, below
  `Laminas\Mvc\SendResponseListener`'s -10000. It sat at 100 until 2026-08-22, i.e. ahead
  of the send, which is the only reason the size of the flush was ever a visitor-facing
  question. Anything that passes an explicit priority to `wireOnFinishTrigger()` must stay
  below -10000.

## The other APCu tenant: the assembled ACL

`bjyauthorize:acl` is the second thing this application keeps in APCu, and it is not a
SionModel cache — it is BjyAuthorize's own, switched on here 2026-08-22 in
`config/autoload/acl.global.php`.

Assembling the ACL costs **~6.0 ms on every request**, anonymous ones included, and reading
it back costs **0.37 ms**. One document serves every visitor: `Authorize::load()` stores it
*before* calling `addRole($identity, $parentRoles)`, so no identity is ever part of what is
written.

Three things about it are easy to get wrong, and each fails quietly:

- **`cache_enabled` was `false`, not absent.** BjyAuthorize defaults it to *true* with a
  `memory` adapter, which caches nothing beyond the request. Turning the flag on without
  naming a real adapter looks like a change and does nothing.
- **The adapter options go at `cache_options.options`**, not `cache_options.adapter.options`
  — `BjyAuthorize\Service\CacheFactory` reads the former and ignores the latter. Every other
  cache block in this application, including the one above, is shaped the other way. Getting
  it wrong leaves the ACL cached with no namespace and no TTL.
- **A missed invalidation is a 500, not a stale page.**
  `Laminas\Permissions\Acl\Acl::addRole()` throws on a parent role it has never heard of, and
  the identity's roles are added as exactly that — so an ACL cached before a role was created
  breaks every request by whoever was granted it, until the 300-second TTL runs out.

Which is why invalidation is not a `clear()` call in each write path. `App\Acl\AclCacheInvalidator`
implements SionModel's `EntityChangeListenerInterface` and is driven from
`SionCacheTrait::removeDependentCacheItems()` — the one point every create, update and delete
in every module passes through. It expires on three entities and no others:

| entity | what it contributes to the ACL |
|---|---|
| `user-role` | the 45 roles and their hierarchy |
| `library` | one resource and three rules per library |
| `text` | one resource per distinct `texts.AclResourceId` |

`user-role-link` is deliberately **not** in that list: which roles an account holds is read
per request by the identity provider and never enters the stored document, so expiring on it
would throw the cache away every time anyone's roles changed, for nothing.

Deploys need no entry: config guards and rules only change with a release, and the deploy
flushes APCu as its last step.

## Why item size matters so much

Production APCu is a single fixed shared segment. `apc.shm_size` was **32M**
when this was written and is **256M** since the konsoleH ticket landed (verified
2026-08-11 on the 8.5 build, `/home/httpd/php85-ini/ourlink/php.ini` — the path
carries the PHP version, so it moves on every flip). `apc.ttl` is **still 0**,
which is why everything below still describes the failure mode rather than a
historical one: an eight-fold larger segment makes a failed allocation much less
likely, and does not change what happens when one occurs.

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

Each was individually larger than the whole 32M segment as it then was — both
would fit in today's 256M, which is exactly why the size raise was worth asking
for and exactly why it is not a fix on its own. The bloat is
structural, not content: books carry ~300 bytes/row of real data but serialize
to ~1,423 bytes/row, because every row repeats all 46 string keys and
`SionTable::filterDbDate()` turns date columns into `DateTime` objects
(~114 bytes each, five per book).

Both were built by callers that needed a fraction of the data — per-library
category counts, or four fields for a navigation label. Both are now narrow
projections.

## The size budget

`sion_model.max_cached_item_size` (bytes, default **4 MiB**, `0` disables)
bounds a single persistent cache item, and since 2026-08-22 it is the **only**
bound on a cache write. `onFinishWriteCache()` measures with
`strlen(serialize($value))` and skips anything over budget, logging a warning
with the key, the size and the budget. Neither a refusal nor a failed write
abandons the rest of the queue.

The count budget that used to sit beside it, `sion_model.max_items_to_cache`, is
**retired** — see [caching-performance.md](caching-performance.md) § Finding 4.
It is ignored rather than renamed, and `/sm/cache-status` reports both halves of
that under `sionModel`: `maxCachedItemSize` is the bound in force, and
`retiredConfigKeys` lists any retired key a server's config still names. That
field exists because `config/autoload/local.php` is gitignored, so a setting
that stopped doing anything can otherwise sit on a server indefinitely with
nothing to say so; `tools/smoke-prod.sh` warns when the list is non-empty.

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

Prefer `-H 'X-Api-Key: …'` over `?key=`; the query form still works, but it is
written verbatim to the access log and kept in shell history. A request with no
key, or a wrong one, is answered `302 → /en/user/login` by the laminas front
controller (production today) and `401` with a JSON body by the Symfony one (the
capsule) — this is one of the two routes ported to it, see
[strangler.md](strangler.md). The payload is the same either way, by construction:
both build it through `SionModel\Cache\CacheStatusPayload`.

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
  against the ceiling. Those alias keys are also where a release swap goes to
  hide: the script entry itself is filed under the resolved path, so it is the
  alias that still points at the previous release. See
  [the 2026-08-17 incident](incident-2026-08-17-stale-opcache.md) and
  `test/Deploy/opcache-swap-test.sh`.
- `validateTimestamps` is surfaced because it decides whether a deploy needs a
  pool restart. With it on (the current setting, `revalidate_freq=2`) changed
  files are noticed within seconds. With it off, a deploy is invisible to
  OPcache and would serve the previous release until `pkill -u ourlink -f php`.

### The interned-strings buffer reads differently from everything else here

Every other number on this page describes a cache that evicts. The interned
buffer does not: **nothing is ever removed from it**. Within one segment's life
its usage only rises, it stops rising when full, and a restart puts it back to
near zero. Three consequences, all of which have caught someone:

- **A percentage is meaningless without the segment's uptime beside it.** 84% at
  nine minutes and 84% at nine days are opposite findings. `internedPercentUsed`
  and `uptimeSeconds` are printed as one line by `tools/smoke-prod.sh` for that
  reason.
- **A reading taken after a deploy is worthless.** The deploy resets every
  segment, so the buffer starts cold and climbs for hours. The warmest reading
  that exists is the one taken immediately *before* a reset — which is why
  `tools/deploy.sh` now prints each pool's usage at the instant it wipes it, and
  why `tools/opcache-sample.sh` should be run before a deploy, not after.
- **When it fills, nothing happens.** No restart, no error, no counter. OPcache
  simply stops interning and stores duplicate strings per script. The only
  evidence is the percentage sitting at 100.

`internedBufferBytes` (allocated) and `internedBufferConfiguredMb` (what the ini
asked for) are both reported, added 2026-08-18. Until then the endpoint gave the
ratio alone, and **a ratio cannot show its own denominator changing** — so it
could not answer "did the raise take effect", which is the one question anyone
asks about this setting. `opcache.interned_strings_buffer` is `PHP_INI_SYSTEM`,
read once when a process starts, so the two can legitimately disagree while a
pool that has not recycled runs on the old buffer.

`startTimeUnix` is reported for a related reason: this host runs at least three
PHP pools, each with its own segment, and a request answers for whichever one
served it. One reading describes a third of production without saying which
third. It is the only per-segment identity OPcache exposes — `uptimeSeconds`
cannot serve, since it differs between two polls of the *same* segment.
`tools/opcache-sample.sh` polls and groups by it, and reports its segment count
as a lower bound rather than a count.

`tools/smoke-prod.sh` polls all of this and warns — never fails — at 80% memory
or keys, 90% interned strings, any restart, `cacheFull`, or timestamp validation
being off. First live reading after enabling OPcache: ~29% memory, ~15% of 16229
keys, 99.2% hit rate.

The arithmetic lives in `SionModel\Cache\OpcacheStatus` and, for the APCu half,
`SionModel\Cache\ApcuStatus` — both kept out of the controller so they are
unit-testable without a container, a request or even a loaded extension
(`test/Unit/OpcacheStatusTest.php`, `test/Unit/ApcuStatusTest.php`).
`SionModel\Cache\CacheStatusPayload` composes the two plus `phpVersion`, and is
the single thing both front controllers call: the key names are read by name by
`tools/smoke-prod.sh`, so a key present on one path and absent on the other would
silence a production warning rather than fail anything.

## Cautions when adding a cached call

- Cache what the caller needs, not the table. A full-table cache key is almost
  always a narrow projection or a `GROUP BY` in disguise.
- Declare the dependencies honestly. `cacheEntityObjects()`'s third argument is
  the list of entities whose change invalidates the item; if the payload embeds
  a related record, that entity belongs in the list or the item goes stale.
- Never write to the persistent cache around `SionCacheTrait` rather than
  through it. An item stored directly is an item the dependency map does not
  name, which is exactly the state the 2026-08-09 incident left the user list in
  — with the difference that this one would be deliberate.
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
- ~~`max_items_to_cache` is **1**~~ — **retired 2026-08-22**, and worth keeping as a
  worked example of a bound aimed at the wrong variable. It capped how many items a
  table wrote per request, on the theory that the rest would trickle in over later
  page loads; the theory held, and cost four extra passes of cold requests to reach
  a state one pass reaches without it. Its stated purpose was bounding memory, which
  it never did — `max_cached_item_size` does that, per item, which is the shape the
  hazard actually had. On a frequently-written table it never converged: one key
  accounted for 23,107 of the capsule log's 25,394 skips, re-queried forever while
  logging that it meant to do better. Production's value was never verified, and no
  longer needs to be — if the server still sets it, `/sm/cache-status` says so under
  `sionModel.retiredConfigKeys`.
- **Signed-in identity now resolves through the cache.** `SessionUser::read()` keeps
  only the user id in the session and re-reads the row every request, but it reads it
  with `UserTable::findById()`, which serves out of `all-linked-users` when that key is
  warm. Retiring `max_items_to_cache` made it reliably warm — it used to lose its write
  slot to a larger key on most passes. Nothing changes for a revocation performed in the
  application: unticking Active goes through `updateEntity()`, which invalidates
  `['user', 'user-role', 'user-role-link']`, and the very next request re-reads from the
  database. What *does* change is the out-of-band case — a `state` written by hand or by
  a migration (`database/db8.6.sql` was one) leaves an open session valid until something
  writes to a user through the application, or the 1-day TTL expires. That is an
  operational fact rather than a bug, and it is pinned by
  `AuthSmokeTest::testDeactivationEndsASessionThatIsAlreadyOpen`, which unticks the real
  box for exactly this reason.
- `JUser\Cache` (1 day) and `SionModel\PersistentCache` (5 days) have different
  TTLs for no recorded reason. Nothing depends on the difference now that the
  map is refreshed with the data, but two numbers where one would do is one
  number too many.
- Compression is unusually effective on these repetitive arrays (the book blob
  gzips 45.7 MiB → 3.7 MiB at level 1), but decode plus `unserialize()` costs
  ~540 ms, so it suits mid-sized items rather than the giant ones. Redis would
  remove the fixed-segment ceiling and the clear-everything failure mode
  entirely, but it is **no longer a free option**: the extension was installed
  in production and was deliberately **disabled in the hosting console on
  2026-08-04 to reduce attack surface**, nothing in `composer.json` requiring
  it. Reaching for it now means re-opening that surface on purpose, which is a
  security decision rather than a caching one. Both are strategic options,
  neither is needed now.
