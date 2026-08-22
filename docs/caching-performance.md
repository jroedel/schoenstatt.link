# What the caches are actually doing

Measured 2026-08-22 against the capsule with `tools/perf/run.sh`. This document is the
evidence base for the cache refactor. [caching.md](caching.md) describes how the caching
layer is *meant* to work and is still accurate as a description of the mechanism; this one
records what the mechanism does when you watch it.

**The headline: under the front controller production actually runs, the persistent cache
stores no data at all.** Not "a low hit rate" — zero. Across 49 measured requests there were
348 reads of data keys and **0 hits**, and **0 writes** of any data key. The only thing
written to APCu was the cache's own bookkeeping.

---

## How the measurement works

`tools/perf/run.sh` copies a delegator-only config into `config/autoload/`, drives a fixed
URL set through the running capsule cold and warm, removes the config again, and aggregates.
Nothing in `src/`, `module/` or `config/` refers to the harness, and no application service
is replaced — the DB adapter gets a `ProfilerInterface`, each cache storage gets listeners on
its own event manager. See [`tools/perf/README.md`](../tools/perf/README.md).

Two properties of the numbers below are worth stating before reading them.

**"Cache hit" means APCu returned something, not that the caller used it.**
`SionCacheTrait::mayServe()` refuses to serve an item its dependency map does not name and
reports the refusal to its caller as a miss. That happens above the layer the probe watches,
so every hit figure here is an **upper bound**. It does not soften the headline: the upper
bound is zero.

**The capsule's `user` table is not production-shaped.** 6,303 rows against roughly 292 real
accounts, the rest `@example.com` leakage from the smoke suites. Every other table is
current production data (see CLAUDE.md). This matters for exactly one of the findings below
and is called out there.

---

## Finding 1 — the persistent cache is write-only-never on Symfony routes

The same nine pages, measured through both front controllers:

| | statements / request | data-key reads | data-key hits | data-key writes |
|---|---|---|---|---|
| **Symfony** (live in production since 2026-08-11) | **11.2** | 348 | **0** | **0** |
| **laminas** (`SYMFONY_KERNEL=0`) | **6.6** | 419 | 349 (83%) | 35 |

Per route, statements executed, cold and warm:

| route | sym cold | sym warm | lam cold | lam warm |
|---|---|---|---|---|
| home | 6 | 6 | 3 | **2** |
| home-de | 6 | 6 | 2 | 2 |
| dictionary | 6 | 6 | 2 | 2 |
| developers | 6 | 6 | 2 | 2 |
| music | 7 | 7 | 3 | **2** |
| composition | 16 | 16 | 10 | 10 |
| shrine | 20 | 20 | 16 | **12** |
| publication | 24 | 24 | 20 | **19** |

Under Symfony, cold and warm are **identical on every route**. That is the signature of a
cache that is read and never filled.

### Why

`SionCacheTrait::onFinishWriteCache()` — the only place a data item is ever handed to APCu —
is attached to `MvcEvent::FINISH`. A Symfony-served route never runs
`Laminas\Mvc\Application`, so that event never fires and the write queue is discarded at the
end of every request.

The dependency map is written by `persistCacheDependencies()`, called inline, which is why
`*-cachedependencies` keys write 180 times and hit 178 times while every data key writes zero.
**The cache currently persists only the metadata describing what it would invalidate if it
had anything to invalidate.**

This is a known shape in this codebase. JTranslate hit exactly the same wall and built
`src/Http/PhraseFlushListener.php` as the Symfony counterpart of its `EVENT_FINISH` listener;
`src/Laminas/PhraseFlush.php` and `TranslatorConfigurator` both carry comments explaining it.
SionModel never got the equivalent, and nothing failed when it didn't.

### Why nobody noticed

Wall-clock time went **down**, not up, when production moved to the Symfony front controller:

| route | Symfony (no cache) | laminas (cache working) |
|---|---|---|
| dictionary | **67 ms** | 175 ms |
| home | **68 ms** | 178 ms |
| developers | **67 ms** | 180 ms |
| music | **93 ms** | 192 ms |
| publication | **273 ms** | 900 ms |
| shrine | 567 ms | **488 ms** |

Not booting laminas-mvc, its view layer and its listener stack saves more than the cache was
returning. The move was a net win *and* it silently disabled the cache; the two cancelled out
in the only number anyone was watching.

---

## Finding 2 — six full-table statements run on every page, whatever the page is

Every single measured request, including the near-static `/en/developers`, executed these
exactly once:

```
SELECT user_id, username, email, display_name, … FROM `user`
SELECT `user`.`user_id` FROM `user`
SELECT `user_role`.* FROM `user_role`
SELECT … FROM `lib_libraries`
SELECT … FROM `lib_collections`
SELECT DISTINCT `AclResourceId` FROM `texts` ORDER BY AclResourceId
```

That is the authorization layer: BjyAuthorize building roles, the per-user role provider, and
the library/text ACL resources. Six statements, on an **anonymous** request, on a page with no
user-specific content.

This is the largest cacheable target on the site and none of it is cached under the live front
controller. It is also the one finding the capsule's data skew touches: the first statement
reads the whole `user` table, 6,303 rows here against ~292 in production, so its *cost* here is
inflated — but it runs once per request either way, and in production `getUsers()` has been
measured at 0.55 MiB of cached payload, so it is not free there either.

---

## Finding 3 — a working cache would only remove 14% of query time

This is the finding that should shape the refactor, and it argues against simply fixing
Finding 1 and stopping.

Measured on the laminas front controller, where the cache does work, median query time per
route cold versus warm:

| route | cold | warm | saved |
|---|---|---|---|
| home | 45.5 ms | 0.4 ms | 45.1 ms |
| shrine | 102.5 ms | 83.9 ms | 18.6 ms |
| publication | 330.7 ms | 316.7 ms | 14.0 ms |
| music | 1.2 ms | 0.5 ms | 0.7 ms |
| composition | 32.6 ms | 36.8 ms | −4.2 ms |
| dictionary / developers / home-de | 0.4 ms | 0.4 ms | 0.0 ms |
| **total** | **514.3 ms** | **440.0 ms** | **74.3 ms (14%)** |

The cache empties the cheap pages completely and barely touches the expensive ones. 330 ms of
the 514 ms is a single publication query that stays 317 ms warm — it is not cached, because
`getEditionValueOptions()`-shaped work is per-request and per-record.

So: fixing the cache recovers about 74 ms per full sweep of these nine pages. Rewriting what
the publication page asks the database for is worth four times that on one route.

---

## Finding 4 — the write budget is set to 1, and it is throttling constantly

`sion_model.max_items_to_cache` is **1** in `config/autoload/local.php` and in
`local.php.dist`. `onFinishWriteCache()` writes that many items **per table instance per
request** and drops the rest, logging each one.

The current capsule log holds **24,945** `Cache writes skipped: max_items_to_cache reached`
lines. A typical entry:

```
{"maxItemsToCache":1,"skipped":[
  "booksmodelpublicationstable-publication-categories",
  "booksmodelpublicationstable-query-objects-publication",
  "booksmodelpublicationstable-publication-edition-value-options"]}
```

So even on the laminas path, where writes reach APCu, a page touching four cached queries
persists one and re-queries three — forever, since the same thing happens next request.

Two corrections fall out of this:

* [caching.md](caching.md) says the value is **2**. Two is the class default in
  `SionCacheTrait`; the application config overrides it to 1. That line is wrong and should be
  fixed when the refactor lands.
* **Production's value is unverified.** `config/autoload/local.php` is gitignored, so the only
  evidence is the `.dist` and the capsule, both of which say 1. Read the server's copy before
  assuming.

The number was chosen for a 32 MB APCu segment. The segment has been 256 MB since
2026-08-11.

---

## Finding 5 — the slowest page is not database-bound

`/en/SL100320A/…`, a shrine page, is the slowest route measured: 536 ms median wall under
Symfony, of which **117 ms is query time**. The other ~420 ms is PHP — row processing,
translation, rendering. `publication` is the opposite: 273 ms wall, 183 ms of it in the
database.

A cache refactor scoped to database results cannot touch the shrine page. Whatever is spending
420 ms there needs its own measurement pass; the harness records `peakMemoryMb` and wall time
per request and would support it with a profiler attached.

---

## Finding 6 — the generation counter has never been read successfully

`currentGeneration()` was called 772 times across both runs. **0 hits, 772 misses, 0 writes.**

That is not a bug: `bumpGeneration()` only writes on invalidation, and a read-only measurement
run performs none. But it means `generationMovedOn()` — the guard that discards a cache write
overtaken by a concurrent change — was inert throughout, so these runs say nothing about
whether it works. It also costs one APCu read per write attempt to learn nothing.

---

## Finding 7 — a write on every entity page view

`INSERT INTO sch_visits (…)` ran 18 times across 49 requests — once per entity show page.
Every anonymous view of a shrine, publication or composition performs a database write. That
is by design (`registerVisit()`), but it is worth naming in a caching document: it is why
these pages can never be served entirely from cache without changing what "a visit" means.

---

## What this says about the refactor

In rough order of value per unit of risk:

1. **Give the persistent cache a Symfony flush point.** The mechanism works, is already
   tested, and is simply unreachable — `src/Http/PhraseFlushListener.php` is the pattern and
   `kernel.terminate` is the hook. This is the smallest change with the largest correctness
   improvement, and it is a prerequisite for measuring anything else honestly.
2. **Raise or remove `max_items_to_cache`.** It exists to bound memory on a 32 MB segment that
   is now 256 MB, and `max_cached_item_size` already bounds the thing that actually hurt.
   Verify production's value first.
3. **Cache the authorization layer.** Six full-table statements per request, identical for
   every anonymous visitor, is the biggest single win available and does not depend on
   SionCacheTrait at all.
4. **Then re-measure before touching SionCacheTrait's ~120 inline `fetchCachedEntityObjects()`
   call sites.** Finding 3 says the entity cache is worth 14% of query time; that number will
   change once 1–3 land, and the decision about whether to replace the trait with a decorator
   should be taken against the new number, not this one.
5. **Profile the shrine page separately.** 420 ms of non-database time on the slowest route is
   not a caching problem and should not be folded into one.

## Reproducing

```
./tools/perf/run.sh                    # against whichever front controller is live
PERF_TAG=laminas ./tools/perf/run.sh   # after flipping SYMFONY_KERNEL, to compare
php tools/perf/report.php data/perf-symfony.jsonl
```

Flipping the capsule's front controller needs the vhost edited **inside the container** —
`docker/apache-vhost.conf` is `COPY`d into the image, so editing the host file does nothing
without a rebuild:

```
docker compose exec -T app sh -c \
  'sed -i "s/SYMFONY_KERNEL \"1\"/SYMFONY_KERNEL \"0\"/" /etc/apache2/sites-available/000-default.conf && apachectl graceful'
```

`curl -s localhost:8080/_health` confirms which one answers; under laminas that route does not
exist, which is itself the signal.
