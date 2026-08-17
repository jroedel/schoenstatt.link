# Incident, 2026-08-17 — a destructive migration met a stale opcode cache

**Impact.** Roughly 12:06–12:45 CEST. Every page that built the site navigation
answered HTTP 200 with an empty body. 449 recorded fatals, mostly crawler traffic
(Googlebot, PetalBot). The homepage kept working, which is why it did not look like
an outage. No data was lost or corrupted at any point.

**One-line cause.** A release symlink swap is invisible to OPcache, so a migration
that dropped five columns ran against code that had already been replaced but was
still executing — and the automatic rollback then restored the release that could
not run against the new schema.

---

## What was deployed

`db8.1` drops five `SchemaOrgJsonMd5V1*` columns from `sch_associations`, together
with the code that wrote them. The columns had no reader; the change was correct and
had been rehearsed against the capsule, where the full suite passed after applying
the migration.

The migration was declared `@phase: post` deliberately, and that reasoning was
right: during the `pre` phase the *previous* release is still serving, and its
`getSelectPrototype()` names all five columns in every association `SELECT`.
Dropping them before the swap would have broken the site for the length of the
composer install.

What was never asked was what happens **after** the swap if the swap does not take
effect, and what a rollback means once the columns are gone.

## Timeline

| time | event |
|---|---|
| 11:35 | release `20260817-113537-43261cd` built and warmed |
| ~12:05:5x | symlink swapped to the new release |
| 12:06:00 | `db8.1` applied — five columns dropped, `@verify` clean, 666 ms |
| 12:06:09 | first fatal. **The smoke test's own request**, reporting `Revision: b07d7c36d1ff` — the *previous* release |
| 12:06–12:07 | 5 smoke checks fail as "cold page broken (status 200, 0 bytes)" |
| ~12:07 | automatic rollback to `20260817-021926-b07d7c3` — the release that cannot run against the new schema |
| 12:07–12:25 | every navigation-building request fatals; ~330 occurrences |
| 12:25 | manual roll *forward* to the new release + `pkill` of PHP workers |
| 12:26 | `--rollback --to <new>` re-run: swap, APCu flush, smoke — **21 failures** |
| ~12:36 | one-off `opcache_reset()` over HTTP. One pool recovers |
| 12:36–12:45 | remaining pools reset / recycle |
| 12:45 | all pages verified healthy |

## Why the swap did not take effect

`opcache.revalidate_path` defaults to `0`. OPcache keys compiled scripts on the
path it resolved when it first saw them and never re-resolves the symlink;
`validate_timestamps=On` then checks the **old** target's mtime, which never
changes. The old release therefore keeps serving indefinitely, with nothing in any
response to say so. This does not self-heal.

And there is more than one cache. Polling `/en/sm/cache-status` ten times returned
three distinct uptimes:

```
  148 s / 1053 scripts
  349 s / 1150 scripts
  485 s / 1228 scripts
```

At least three PHP pools, each with its own OPcache segment. A request resets only
the segment that serves it, so a single `opcache_reset()` fixes a fraction of
traffic — which presented as "some pages load, some don't" and looked random.

`ps -u ourlink` shows no long-lived PHP processes, yet segment uptime reached
1259 s: the shared memory outlives the request processes.

## The diagnosis that was wrong, and why it looked right

The first explanation offered was PHP's `realpath_cache` (120 s TTL). It fit the
ten-second gap between swap and smoke, and it is a real hazard in symlink deploys.
It was **wrong**: `realpath_cache` is per-process, and there are no persistent
processes here. It was refuted by `ps`, and by the failure persisting for 20 minutes
rather than 120 seconds.

The datum that actually settled it was `.revision`. Each release carries its own,
the exception reporter prints it, and reports at 12:29 still named the *old*
release while the symlink had pointed at the new one since 12:26. **A live process
reporting an older revision than the live symlink is the signature of this bug**,
and no amount of reasoning about caches substitutes for that one field.

## Why the rollback made it worse

The automatic rollback is the correct response to a failing release. It is exactly
the wrong response to a *successful* release whose migration has already run: the
restored code is from before the schema change, so it cannot work. The deploy
printed "No database migration was undone by this rollback" — accurate, and far too
mild for what it meant.

The 21 failures on the 12:26 re-run were also misread at first. Every
`should 404`/`should 410` check reported "got 200", because a PHP fatal with
`display_errors=Off` **is** an HTTP 200 with an empty body. The suite had the body
length and never looked at it, so one fault was reported as 21 unrelated ones and
named none of them correctly.

## What changed as a result

Four protections, all in this repository:

1. **`/_health` reports the running release** when given the maintenance key, and
   `tools/deploy.sh` polls it 12 times after the swap. If any probe disagrees with
   the release just deployed, the deploy **stops before running any post-deploy
   migration** — the point in the timeline where this incident became irreversible.
   The endpoint is served by the Symfony kernel without booting laminas, so it
   answers even while the legacy bootstrap is fatalling, which is precisely the
   state it must detect.
2. **The deploy resets opcode caches after every swap**, including rollbacks, by
   writing a single-use randomly-named PHP file into the release and requesting it
   until several consecutive hits report an already-fresh segment. A new path has no
   cache entry anywhere, so it always compiles from disk. An in-application endpoint
   cannot do this: a pool serving the previous release resolves routes against that
   release's code, which need not contain the endpoint.
3. **Rollback refuses a target that predates a destructive migration.** A migration
   may now declare `@destructive: yes`, meaning older code cannot run once it has
   been applied. Both the manual and the automatic rollback paths check it; the
   automatic one now *declines to roll back* and says why. `--allow-incompatible`
   overrides, deliberately verbosely.
4. **`tools/smoke-prod.sh` treats a zero-byte HTTP 200 as its own failure**, named
   as a fatal, reported once per URL and summarised separately from the failure
   count — because N such failures are almost always one fault seen N times.

A fifth, forced by the third: `tools/migrate.sh reseal` re-records the ledger hash
of an applied migration whose **comments** changed, after proving via git history
that no statement moved. Without it `@destructive` could never be added to `db8.1`,
since an applied migration's bytes are frozen.

## The first deploy of the protections, and what it proved

The very next deploy (`b317f7a`, 13:56) **stopped at the new gate**, which is the
system working: it aborted at step 13 with no post-deploy migration run and the
site healthy. But it stopped for a reason worth recording.

- Step 12's cache reset **missed** — `opcache reset helper did not answer as
  expected on attempt 1` — and then printed `✓ opcode caches reset`. It reported
  success having done nothing. That is the same "looks like it worked" failure as
  the 30 requests to a 500 during the incident itself, and it is now fixed: the
  helper is retried three times and prints the actual HTTP status and body when it
  does not get a reset. (It was briefly base64-encoded over the wire too, on the
  suspicion that shell quoting was corrupting it. That was measured and found
  false, and the encoding was removed on the same day: the one thing a deploy
  writes into a production docroot has to be legible to whoever reads the script
  later, and "it's only base64" is how a deploy stops being trusted.)
- Step 13 then correctly refused, because a pool was still serving the *pre*-#120
  `HealthController`, which has no `revision` field at all. Diagnosis took four
  rounds and produced two wrong hypotheses of mine — a heredoc quoting bug
  (disproved: the generated PHP lints clean) and a memory limit (disproved: 8 MiB
  peak). The actual proof came from running the controller in the web SAPI, where
  it worked, versus over HTTP, where it did not.
- A single `opcache_reset()` from an unrelated probe fixed it: `/_health` went
  from 0/20 reporting a revision to **12/12**.

Two lessons beyond the code. **A diagnostic script must reproduce the real
environment or it will manufacture its own failures** — mine reported a
`Could not create temporary file in directory "data/config"` throwable that was
purely an artifact of not `chdir`-ing, since laminas resolves that path relative
to the CWD that `public/index.php` sets. And **the gate can deadlock**: if the
reset misses, the gate refuses, and re-running the deploy hits the same wall. The
gate now retries the reset once itself, and its failure message spells out the
manual recovery rather than saying "re-run the deploy".

## The second deploy, and the diagnosis that finally held

`a707b3d` (14:33) also stopped at the gate — correctly, again, with nothing
irreversible done. This time the improved reporting said what happened:

```
! reset helper answered HTTP 404 on attempt 1 (miss 1/3), not a reset:
      No input file specified.|
! giving up after 3 bad reads. The file on the server was:
  -rw-r--r-- 1 ourlink ourlink 419 Aug 17 14:35 .../a707b3d/public/zz-opcache-….php
```

The file existed and the web could not find it. Six minutes later, by hand, the
**identical file at the identical path** answered all 25 requests, every one
reporting `from=…/a707b3d/public` — the new release.

So: **the 404 was purely timing.** A freshly written file is not instantly visible
to the web server here, and three retries spanning four seconds were far too eager.
Apache resolves the docroot symlink correctly; there is no stale-docroot problem.
`/_health` then went from `b317f7a` on 8/8 probes to `a707b3d` on 8/8, and the full
production smoke suite passed.

Four hypotheses died on the way to that, and they are listed because the pattern
matters more than any one of them: `realpath_cache` (no persistent processes),
heredoc corruption (the generated PHP lints clean), a memory limit (8 MiB peak),
and a stale docroot resolution (`from=` disproved it). Each fit the symptom before
there was data to separate it from the others. **The rule this earns: when a
mechanism is proposed for a failure that costs an outage, get the datum that
distinguishes it from its neighbours before acting on it.**

### The one structural finding that survived

`public/index.php` had **eight hard links and an mtime of 2026-08-07**. rsync
`--link-dest` hardlinks every unchanged file to the previous release, and that file
had not changed in ten days — so one inode, one mtime, shared by every release. It
is also the only file reached through a path that never changes, since the docroot
symlink is what moves underneath it. If OPcache caches it under that stable path,
`validate_timestamps` compares an mtime that is identical in every release and can
never fire.

That does not explain the 404, but it is a coherent account of why the stale state
*persists* rather than clearing on the next request. The deploy now breaks that
hardlink after upload — `cp -p` then `mv -f`, giving a new inode and a current
mtime, leaving the previous release's copy untouched (a plain `touch` would move
the mtime on the shared inode, i.e. on every release at once). Whether OPcache here
keys on the symlink path or the resolved one was never established; if it is the
resolved path this changes nothing, and it costs one copy of a 3 KB file.

## The deploy that worked, and the last thing it exposed

`a8f9909` (15:03) completed: swap, reset, gate 12/12, no pending migrations, **all**
production smoke checks, housekeeping, 202 s total. The hardlink change also did
its job — the new release's `index.php` has one link and its own mtime, where the
four older releases still share one inode dated 2026-08-07.

Two things it exposed, both now fixed:

**The reset loop warned on success.** It stopped on OPcache segment ages and never
saw six consecutive fresh reads in forty hits, so it warned — on a deploy where the
very next step passed 12/12. It now stops on eight consecutive probes reporting the
expected revision instead. Segment age was always a proxy; the revision is the
observable, and a warning that fires on success teaches people to ignore the one
mechanism guarding the migration.

**Agreement is momentary.** Sampling `/_health` after that deploy:

| time | new release | previous release |
|---|---|---|
| during deploy | 12/12 | 0 |
| ~2 min later | 0 | **6/6** |
| ~3 min later | 11/20 | 9/20 |
| 15:13 onward | 20/20 | 0 — held for 4 rounds |

Pools the reset never reached kept serving old code for about four minutes, then
recycled. Harmless there — both releases ran against the same schema — but for a
destructive migration it is this incident again with a delay on it. So a pending
`@destructive: yes` migration now requires the agreement to *hold*: three rounds,
45 s apart. Ordinary deploys are unaffected, deliberately; a three-minute tax on
every release is a check people would route around.

## What is still true and worth knowing

- **The capsule cannot reproduce this.** It runs one PHP pool and serves from a
  plain directory, not a release symlink. The capsule suite passed against the
  post-drop schema both before and after the incident. Local green does not predict
  swap coherence, and nothing in this repository can test it.
- **Pools recycle on their own** (observed 148/349/485 s), so this class of failure
  eventually self-heals. Slowly, unpredictably, and never fast enough.
- The rehearsal that would have caught it is a production deploy of a
  backward-incompatible change — which is the thing itself. Protection 1 is
  therefore a check, not a test, and it is checked on every deploy from now on.
