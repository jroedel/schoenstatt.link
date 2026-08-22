# What the caches are actually doing

Measured 2026-08-22 against the capsule with `tools/perf/run.sh`. This document is the
evidence base for the cache refactor. [caching.md](caching.md) describes how the caching
layer is *meant* to work and is still accurate as a description of the mechanism; this one
records what the mechanism does when you watch it.

**The headline: under the front controller production actually runs, the persistent cache
stores no data at all.** Not "a low hit rate" — zero. Across 49 measured requests there were
348 reads of data keys and **0 hits**, and **0 writes** of any data key. The only thing
written to APCu was the cache's own bookkeeping.

> **Findings 1, 2 and 4 were all fixed the same day.** Finding 1 —
> `SionModel\Cache\CacheFlushQueue` plus `App\Http\SionCacheFlushListener` on
> `kernel.terminate`. Finding 4 — `max_items_to_cache` retired outright. Finding 2 — the 294
> unused per-user ACL roles deleted and the assembled ACL cached in APCu. The measurements in
> this document are kept as the **before** picture, because they are the evidence for what is
> still open and because the shape of each bug is worth not forgetting. Every "today" below
> means 2026-08-22 before the fixes; the after-numbers are in each fixed finding's section.
>
> Finding 5 followed the same day: the shrine page's 259 ms of non-database time turned out
> to be a single redundant query inside `linkAssociations()`, not rendering.
>
> Finding 2 is worth reading even if the rest is not, because the finding as first written was
> **wrong about where the cost was** — it named six database statements that turned out to
> total 0.9 ms, while the layer cost 6 ms. A harness that counts statements cannot see a cost
> that is not a statement.

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

There is a second cost hidden in the same line of code. `SionTableWiring` reached for the MVC
`Application` to get that event manager, guarded by `has('Application')` — and under the
Symfony front controller `has('Application')` answers **true**, because laminas-mvc's own
module config defines the service whether or not anything ever bootstraps it. So every table
built an MVC application, per table, per request, to attach a listener to an event that
request would never fire.

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

### Finding 1 — fixed

Fixed 2026-08-22. `SionModel\Cache\CacheFlushQueue` is a per-request registry that tables
enrol themselves into from their factory; `App\Http\SionCacheFlushListener` drains it on
`KernelEvents::TERMINATE`. When a queue is present the MVC `Application` is not resolved at
all, which removes the second cost above. Same harness, same URL set, same capsule:

| | statements / request | data-key reads | data-key hits | data-key writes |
|---|---|---|---|---|
| Symfony, before | 11.4 | 348 | **0** | **0** |
| laminas (`SYMFONY_KERNEL=0`) | 6.7 | 415 | 345 (83%) | 35 |
| **Symfony, after** | **6.3** | 242 | **207 (86%)** | **16** |

Wall time, median per route and phase, before and after:

| route | before cold | after cold | before warm | after warm |
|---|---|---|---|---|
| developers | 67.2 ms | **12.4 ms** | 66.9 ms | **12.2 ms** |
| dictionary | 74.7 ms | **14.3 ms** | 66.9 ms | **12.1 ms** |
| home | 70.8 ms | 65.5 ms | 67.9 ms | **12.4 ms** |
| home-de | 71.8 ms | **17.8 ms** | 71.0 ms | **14.1 ms** |
| music | 93.2 ms | **34.7 ms** | 93.3 ms | **32.7 ms** |
| composition | 86.5 ms | **28.2 ms** | 85.1 ms | **29.4 ms** |
| publication | 275.1 ms | 220.3 ms | 273.3 ms | 227.3 ms |
| shrine | 536.4 ms | 513.1 ms | 566.7 ms | **334.7 ms** |
| **total** | **2,566.8 ms** | | **1,581.2 ms** | |

Two things about that table are worth reading carefully rather than celebrating.

**The saving is not evenly distributed and the biggest pages moved least.** `developers` is a
near-static page and it dropped 55 ms; `publication` is the second-slowest page and dropped
~50 ms out of 275. The flat ~55 ms every route gained is the four full-table statements from
Finding 2 becoming cache hits — a per-request constant, not a proportional speed-up. Finding 3
already said the entity cache is worth little on the expensive pages, and the after-numbers
agree with it.

**`home` cold is the one route that barely moved (70.8 → 65.5 ms) while its warm time went
67.9 → 12.4 ms.** That is what a working cache is supposed to look like and it had no
cold-warm difference at all before.

Guarded by three tests: `SionCacheWiringTest` pins that a table built for a Symfony request
enrols in the queue and does *not* also attach the MVC listener (and that one built without a
queue still does); `SionCacheDependencyMapTest::testFlushingTwiceWritesEachItemOnce` pins that
the queue is drained by the pass that writes it; and
`SionModelSmokeTest::testAPortedPageLeavesDataInThePersistentCache` is the end-to-end form —
clear the cache, request a ported page, assert APCu holds a **data** key and not only a
dependency map. That last assertion is deliberately about key names: entry *count* grew all
along, because the dependency map was written faithfully throughout, so "the cache has
entries" is exactly the check that would have passed for eleven days.

---

## Finding 2 — fixed

*Was: six full-table statements run on every page, whatever the page is.* Kept as written,
then what the statements turned out to be worth.

### What was found

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

**Four of the six are cached as of the Finding 1 fix** — the `user` row-set, `lib_libraries`,
`lib_collections` and the `texts` ACL resources all went through `SionTable` and were being
queued and discarded. What survived was the pair BjyAuthorize issues directly,
`SELECT user_id FROM user` and `SELECT user_role.*`, which never touch SionCacheTrait.

### The statements were never the cost

This finding called the authorization layer "the largest cacheable target on the site" and
pointed at its queries. The queries are **0.9 ms combined**, measured. Naming them as the
target was the mistake: the harness counts statements, so a per-request cost that is not a
statement is invisible to it, and six of them looked like the whole story.

The layer costs **~6.0 ms on every request**, anonymous ones included — measured in the web
SAPI, in-request, with a delegator around `Authorize`, on 2026-08-22. On `/en/developers`,
whose entire render is 12 ms, that is half the page.

Do not measure this from the CLI. A CLI run has its own APCu segment, so the ACL's dynamic
providers miss on everything they read: the same measurement reports 58 ms and blames
`LibraryTable::getResources()`, which in a real request costs 0.13 ms because the row-set it
reads is cached. The first version of this investigation did exactly that.

| stage | ms |
|---|---|
| `getRoles UserIdRoles` (294 per-user roles) | 1.5 |
| constructing the resource providers | 2.2 |
| `getRoles LaminasDb` (45 real roles) | 0.35 |
| `getResources` LibraryTable + EventTextTable | 0.14 |
| the rest of `loadAcl()`, plus the identity | 2.2 |

### The fix

Two changes, and the first is a prerequisite rather than an optimisation.

**The 294 `user_<id>` roles are gone.** `JUser\Bridge\Laminas\UserIdRoles` registered one ACL
role per account so that a rule could name an individual, and in the life of this database not
one ever did: no rule in any config named one, `user_role` held no such row, and the two
columns that store a role name by hand (`lib_libraries.ViewRole` / `.CheckoutBooksRole`) never
held one either. Their real cost was not the 1.5 ms — it was that the ACL then depended on the
`user` table, and an account is created on **every first-time sign-in**, which makes the
assembled ACL uncacheable. `ZfcUserZendDbPlusSelfAsRole` stopped returning `user_<id>` in the
same commit, because what it returns is handed to `Acl::addRole()` as parent roles and that
throws on a parent the ACL does not know.

**The assembled ACL is cached in APCu**, which BjyAuthorize has always supported —
`cache_enabled` was explicitly `false` here because the package's default adapter is `memory`,
which caches nothing across requests. One document serves every visitor: `Authorize::load()`
stores it *before* adding the identity's roles.

| | before | after |
|---|---|---|
| authorization layer, per request | **6.0 ms** | **3.2 ms** |
| serialized ACL | 127,565 B | 80,560 B |
| `unserialize` on a hit | — | 0.37 ms |

Invalidation is `App\Acl\AclCacheInvalidator`, driven from
`SionCacheTrait::removeDependentCacheItems()` through SionModel's new
`EntityChangeListeners` — the one point every write in every module passes through, rather
than the four write paths that exist today across two repositories. That is correctness, not
tidiness: a missed role invalidation is a **500 on every request** by whoever was granted the
role, because of the `addRole()` throw above. The 300-second TTL is a backstop, not the plan.
Guarded end-to-end by
`JUserAdminSmokeTest::testAnAccountGrantedABrandNewRoleStillGetsAPage`, which was confirmed to
fail — with a real 500 — when `user-role` is taken out of the invalidator's list.

### What is left, and it is bigger than what was just saved

Of the remaining 3.2 ms, **1.9 ms is `$container->get(JUser\Model\UserTable::class)`**. The
cache hit itself is 0.4 ms and the identity provider 0.2 ms; the rest is
`AuthenticationServiceFactory` eagerly building a full SionTable — DB adapter, entity spec,
cache, logger, user directory — so that `SessionUser` can hold it in case the session turns
out to contain an identity. On an anonymous request it never reads it: `getIdentityRoles()`
returns the default role in **0.01 ms** without touching the table. Making that dependency
lazy is a JUser constructor change on the authentication path and wants its own review; it is
in [BACKLOG.md](BACKLOG.md).

This was the largest cacheable target on the site and none of it was cached under the live
front controller. It is also the one finding the capsule's data skew touches: the first statement
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

## Finding 4 — fixed

*Was: the write budget is set to 1, and it is throttling constantly.* Kept as written, then
the measurement that retired it.

### What was found

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
  fixed when the refactor lands. *(Fixed — the line now records the retirement.)*
* **Production's value is unverified.** `config/autoload/local.php` is gitignored, so the only
  evidence is the `.dist` and the capsule, both of which say 1. Read the server's copy before
  assuming.

The number was chosen for a 32 MB APCu segment. The segment has been 256 MB since
2026-08-11.

### What it was actually bounding

It was introduced to stop the end-of-request flush exhausting `memory_limit`, with the
explicit intent that the items it dropped would trickle into the cache over subsequent page
loads. Both halves are worth separating, because the first is wrong and the second is right.

**It bounded count; the hazard was size.** Serializing many items is not what exhausted
memory — one huge item was, and the historical offenders serialized to 29.2 and 45.7 MiB.
`max_cached_item_size` bounds exactly that, per item, and has since 2026-08. Measured over
the whole working set of this URL set: the heaviest single write spikes **1.29 MiB** of PHP
memory against a 512 MB limit, and the 16 items together come to 8.22 MiB of segment.

**The trickle did work, and that is why the cap had to go.** Warming eight pages from a
cleared cache, counting per pass:

| budget | pass 1 | 2 | 3 | 4 | 5 | 6+ |
|---|---|---|---|---|---|---|
| 1 | 12 written, 6 skipped | 1 | 1 | 1 | 1 | 0 |
| unbounded | **16 written, 0 skipped** | 0 | 0 | 0 | 0 | 0 |

Same 16 items, same 24 APCu entries, same 8.22 MiB at the end. The cap changed nothing about
the destination and four passes about the journey — and the entire unbounded flush costs
**36 ms across the pass**, 17 ms in its heaviest single request, spent after the response has
been sent. What those four extra passes cost, cold:

| route | budget 1 | unbounded |
|---|---|---|
| home | 67.8 ms wall / 49.6 ms query | **16.4 / 0.5** |
| shrine | 480.2 / 81.0, 14 queries | **308.0 / 82.0, 10 queries** |

Cold is not a rare state. Any entity edit bumps the generation and invalidates, and every
deploy resets the segment.

**And on a frequently-written table it never converged at all.** Of the 25,394 skip records
in the capsule log, 23,107 are one key — `jusermodelusertable-usernames`. Invalidation
outran a one-item-per-request refill, so that item was re-queried forever while logging that
it meant to do better.

### The fix

`max_items_to_cache` is **retired**, not renamed: `SionCacheTrait` no longer reads it,
`SionTable` no longer honours it, and it is gone from SionModel's `module.config.php`, this
application's `local.php.dist` and `docker/local.docker.php`. A host whose config still names
it is not broken, only ignored — and because `local.php` is gitignored, `/sm/cache-status`
now reports `sionModel.retiredConfigKeys` so that leftover is findable over HTTPS instead of
over SSH. `tools/smoke-prod.sh` warns when the list is non-empty; the same block reports
`sionModel.maxCachedItemSize`, the one bound left.

The laminas flush point moved with it. `wireOnFinishTrigger()` attaches at priority
**-11000** rather than 100 — below `SendResponseListener`'s -10000 — so a pure laminas host
also writes after the response has gone out. Under Symfony `kernel.terminate` already did.
That is what makes an unbounded queue cost a visitor nothing on either front controller, and
it matters beyond this repository: patres is a laminas host and would otherwise have picked
up the 17 ms in front of the send.

### Left behind, and separate

`booksmodellibrarytable-checkouts` serializes to **4,608,092 bytes** against the 4,194,304
budget, so it is refused on every request, permanently, on a hot path. That is
`max_cached_item_size` working exactly as designed and pointing at a query that needs
narrowing — the same shape as the `query-objects-publication` blob in
[caching.md](caching.md). Filed in [BACKLOG.md](BACKLOG.md).

---

## Finding 5 — fixed

*Was: the slowest page is not database-bound.* Kept as written, then what the
non-database time turned out to be.

### What was found

`/en/SL100320A/…`, a shrine page, is the slowest route measured: 536 ms median wall under
Symfony, of which **117 ms is query time**. The other ~420 ms is PHP — row processing,
translation, rendering. `publication` is the opposite: 273 ms wall, 183 ms of it in the
database.

A cache refactor scoped to database results cannot touch the shrine page. Whatever is spending
420 ms there needs its own measurement pass; the harness records `peakMemoryMb` and wall time
per request and would support it with a profiler attached.

### The measurement pass, 2026-08-22

By the time it was taken, Findings 1, 2 and 4 had landed and the page was 324.5 ms wall with
65 ms of query — **259 ms of PHP**, and 52 MB of peak memory to render one record. Only
27.8 ms of that was inside the cache, so the whole-table cache items were not the story.

Instrumenting `SchoenstattTable` in-request found all of it in one place:

| stage | ms |
|---|---|
| `getObjects('association')` — 498 rows, from APCu | 9–12 |
| **the related-associations query** | **276–333** |
| link parents (loop over 498) | 1.2 |
| link children (loop over 431) | 0.25 |
| `connectEntityRolesAndAssignments()` | 12–30 |
| **`getAssociation()` total** | **261–371** |

`getAssociation($id)` — one record — goes through `getAssociations()`, which loads the whole
table and calls `linkAssociations()`. That method then asked the database for the parents and
children of everything it had just been handed: one `orCombination` query over
`parentId IN (…) OR associationId IN (…)`, returning **431 rows, every one of which was
already in the array**. Verified rather than assumed — 0 rows absent from the loaded set, 0
rows differing from it.

It was a batching optimisation, and a sound one when `$objects` came from the database
anyway. It became the most expensive thing on the site when the whole table started arriving
from cache in 10 ms.

Note where the cost actually sits: the page's **total** SQL is 61–65 ms, so ~230 ms of that
query is PHP — building a ~900-term OR predicate and re-hydrating 431 rows through the entity
processor.

### The fix

`linkAssociations()` takes a second argument saying that `$objects` is the complete table, and
`getAssociations()` — the only caller that can honestly say so — passes it. The subset callers
(`searchAssociations()`, `getShrines()`, `getWaysideShrines()`) still query, because a filtered
match's parent genuinely can lie outside the set.

Measured A/B under identical conditions, five samples each:

| | before | after |
|---|---|---|
| `getAssociation()` | 344–399 ms | **21–36 ms** |
| peak memory | 40.6 MB | **25.4 MB** |

The related rows stay in a **separate snapshot** even when they are a copy of `$objects`,
which is load-bearing rather than wasteful: linking `$objects` to rows that are themselves
being linked would make the graph cyclic where this keeps it two levels deep and finite.
(Until [Finding 6](#finding-6--fixed) the links into that snapshot were PHP *references*.
They are plain copies now, and the snapshot survives for the reason above.)

This reaches every association page, `/movement`, the edit form, the v3 API's list and detail
endpoints, both laminas controllers, and `getNationalAssociations()` /
`getAssociationProblems()` internally.

Proved by `tools/port-baseline.php`: **1,272 of 1,272 responses identical** — 106 paths × 6
locale forms × 2 identities — plus `test/Integration/AssociationLinkingTest`, which pins the
two properties the saving rests on rather than the timing.

## Finding 6 — fixed

**The links into that snapshot were PHP references, and the rows they pointed at were
taken before the roles were attached.**

Two defects in the same six lines, found on 2026-08-22 by re-reading the reference
assignments rather than by measuring anything.

### The references bought nothing

`$objects[$id]['parent'] = &$related[$parentId]` was written to avoid copying rows. It does
not avoid anything: a plain array assignment in PHP is copy-on-write, so the two forms cost
the same live memory until something writes, and nothing writes. Measured like-for-like on
`getAssociation()`, peak memory moved 56 MB → 58 MB — and that 2 MB is the roles fix below,
not the de-referencing.

What the references did buy is aliasing: one row reachable by two paths, where a write
through either changes both, in a structure `getShrines()` hands straight to
`cacheEntityObjects()`.

They also buy a smaller `serialize()`, which is not the same claim and is the only reason
one of them survives elsewhere — see below.

### The attached rows had no roles, no assignments and no leader

`connectEntityRolesAndAssignments()` ran **last**, and only on `$objects`. Everything
attached as a `parent` or a `childAssociations` entry was a copy taken before it, so it
carried `roles => []`, `assignments => []`, `mainPerson => null` — the keys are declared in
`processAssociationRow()`, so nothing was missing and nothing errored; the values were just
empty.

Measured over the full set: **791 of 792 linked rows** differed from the same row read
under its own id.

| key | linked rows where it differs |
|---|---|
| `roles` | 396 parents, 395 children |
| `assignments` | 389 parents, 61 children |
| `mainRole` | 250 parents, 215 children |
| `mainAssignment` / `mainPerson` | 187 parents, 6 children |
| `mainContactAssignment` / `mainContactPerson` | 60 parents, 2 children |

The visible half is `mainPerson`. `_associations-table.html.twig` — and the `.phtml` it
replaced — renders `entity.mainPerson` for every row, so the leader column of "Associated
organizations" was blank for every child that has one, on a page whose other tables fill the
same column in. Six links in the current database are affected, all under associations that
answer 404 to an anonymous visitor, which is why the byte comparison below is clean.

The fix is ordering: connect first, snapshot second, link third. For the filtered callers
(`searchAssociations()`, `getShrines()`, `getWaysideShrines()`) the snapshot is
`$objects + $related`, union with `$objects` winning, so rows already in the set keep their
roles and only rows genuinely outside it arrive unconnected — as they always have.

### Verification

`tools/port-baseline.php`: **1,272 of 1,272 responses identical**. That is the expected
result, not a disappointment — no sampled path renders a linked row's leader — and it is
the assurance that reordering the connect call broke nothing else.

The regression guard is `AssociationLinkingTest::testAnAttachedRowCarriesTheSameDataAsItsOwnRow`.
Note why the existing test could not catch this: it compares the two linking paths against
*each other*, and both were wrong in the same way. The new one compares an attached row
against the same row under its own id. It fails on the previous code (association 466,
attached to 1) and passes on this one.

### What was not changed, and why

`LibraryTable::getCheckouts()` keeps its reference. There the memory argument is wrong for
the same reason but the serialization argument is real: that array is the `checkouts` cache
item, `exceedsItemSizeBudget()` measures it with `serialize()`, and a reference is stored
once and back-referenced. Over 1,953 rows it is **4,605,258 bytes with the reference and
7,943,438 without**. The item is already refused at the smaller figure by the 4 MiB budget,
so a copy would foreclose ever fitting rather than merely cost something. The real fix is
not to embed a whole library row in every checkout (BACKLOG).

`PublicationsTable::linkPublications()` and `linkPublication()` have the same shape and are
untouched, with the redundant query below.

Two references that cost nothing to remove went with this: the shrine and wayside-shrine
`$regions` grouping in `SchoenstattController`, and `$libraryBooks[…]['library']` in
`PublicationsController`. Both aliased two view variables to each other for no gain.

A third was dead outright: `processLibraryRow()` contained
`$collections[$collectionId] = &$collections[$collectionId];` — an element of a `static`
array assigned to itself, changing no value and only converting the element into a reference
for the rest of the request. It appears to be a mistyped attempt to fill
`$processedRow['collections']`, which is declared there and which nothing has ever read.

### The same shape lives in PublicationsTable

`PublicationsTable::linkPublications()` issues the same kind of `orCombination` re-query, and
the publication page is the other slow route (208 ms wall, 129 ms query). It was left alone
deliberately: its relations are a different shape (`mainPublication` / `translatedFrom` rather
than parent / children), so it is a separate correctness argument. Note the right shape already
exists beside it — `linkPublication()`, singular, resolves one record's relations directly.

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

1. ~~**Give the persistent cache a Symfony flush point.**~~ **Done 2026-08-22** — see
   [Finding 1 — fixed](#finding-1--fixed). Statements per request 11.4 → 6.3, the measured
   page set 2,567 ms → 1,581 ms. Everything below should be re-measured against the new
   baseline before it is costed, which was the point of doing this one first.
2. ~~**Raise or remove `max_items_to_cache`.**~~ **Done 2026-08-22** — removed, see
   [Finding 4 — fixed](#finding-4--fixed). Production's value never was verified and no
   longer needs to be; `/sm/cache-status` reports whether a server still sets it.
3. ~~**Cache the authorization layer.**~~ **Done 2026-08-22** — see
   [Finding 2 — fixed](#finding-2--fixed). 6.0 → 3.2 ms per request. Note the premise of this
   line was wrong: the six statements cost 0.9 ms, and the saving was in assembly, not in the
   database. What remains is 1.9 ms of eagerly-built `UserTable` on anonymous requests.
4. **Then re-measure before touching SionCacheTrait's ~120 inline `fetchCachedEntityObjects()`
   call sites.** Finding 3 says the entity cache is worth 14% of query time; that number will
   change once 1–3 land, and the decision about whether to replace the trait with a decorator
   should be taken against the new number, not this one.
5. ~~**Profile the shrine page separately.**~~ **Done 2026-08-22** — see
   [Finding 5 — fixed](#finding-5--fixed). It was not a caching problem and it was not
   rendering either: `getAssociation()` re-queried 431 association rows it already held.
   344–399 ms → 21–36 ms.

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
