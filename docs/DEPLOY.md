# Deploying schoenstatt.link

```bash
make prod-deploy
```

`make prod-deploy` builds the release in **a checkout of its own**, proves it is the tree
you think it is, and hands over to `./tools/deploy.sh`, which is the whole thing:
preflight, build, migrate, warm, swap, verify. That runs `rsync` over your own shell
account on **port 222** and needs no local PHP — only `git`, `rsync`, `ssh`, `curl` and
(strongly recommended) `timeout`/`gtimeout`. **Your working tree is only ever read**, so a
deploy and an afternoon's work can share a machine.

**Deployment is atomic.** A release is built, composer-installed and warmed in a
directory nothing is serving, and goes live when one symlink is replaced by a
single `rename(2)`. No visitor ever meets a half-deployed tree. **phploy was
deleted 2026-08-16; do not reintroduce it or reason from it.**

Configuration lives in **`.deploy.local`** (gitignored, mode 0600), seeded from
`.deploy.local.dist` by `./config.sh`: the SSH target, `DEPLOY_API_KEY` (any
`sion_model.api_keys` value — sent as an `X-Api-Key` header, never a query string)
and the full-DDL database credentials. **Nothing in it is ever written to the
server, committed or printed.** An unreplaced `TODO` aborts the preflight —
a bogus API key would fail the smoke run and roll back a release that was fine.

There is now one other place those values exist: the GitHub secrets that
[Deploying from GitHub Actions](#deploying-from-github-actions) reads. That is a real
widening — the whole point of full-DDL credentials being local was that compromising the
web application could not reach them — and the things holding it are named in that
section. `DEPLOY_CONFIG` exists so the file the runner writes never lands in the
working tree.

## The entry point: `make prod-deploy`

```bash
make prod-deploy                # checks, then hands over to tools/deploy.sh
make prod-deploy DRY_RUN=1      # checks, then deploy.sh --dry-run (server untouched)
make prod-deploy CI=1           # full ci-local first, and refuse if it fails
make prod-deploy CHECKS_ONLY=1  # run the checks and stop; never reaches deploy.sh
make prod-deploy DEPLOY_TREE=…  # put the deploy checkout somewhere else
```

`tools/deploy.sh` is careful about *how* it ships a tree. What it cannot check is whether
the tree it is about to package is the tree you think it is. That is `tools/prod-deploy.sh`,
and it refuses rather than repairs — every repair here is a judgement a script should not
make on its own.

### The deploy checkout

A release is built from a **working tree** (see below), so this script used to take yours
over: `git checkout master`, `git merge --ff-only`, and a refusal whenever you had
uncommitted work. A deploy and a working day could not share a machine, and a branch
switch *during* a deploy corrupted the deploy — the script is read incrementally by bash,
so rewriting the file underneath it resumes execution inside the other branch's copy.

Since 2026-09-11 it maintains a second checkout instead:

```
${XDG_CACHE_HOME:-~/.cache}/schoenstatt.link-deploy      # override with DEPLOY_TREE
```

cloned from your repository once (hardlinked objects, so it costs neither network nor
disk), then on every run fetched and hard-reset to `origin/master`. That tree is
what `tools/deploy.sh` packages. Two things are **linked** back to your tree rather than
duplicated: `.deploy.local`, so the DDL password exists once on disk, and `data/deploy/`,
so the table snapshots a migration takes stay in one place across deploys. Both links are
excluded per-checkout in its `.git/info/exclude`, because `deploy.sh` refuses to package a
tree with anything untracked in it.

Consequences worth knowing:

- Everything from `tools/deploy.sh` onwards is **master's copy**, read from the deploy
  checkout. Only `tools/prod-deploy.sh` comes from your working tree, so a change to the
  deploy machinery is exercised by `make prod-deploy` only once it is merged.
- `tools/ci-local.sh` **cannot** run there — see "Verification" below.

### The checks

1. **Is `.deploy.local` present?** Asked before anything slow.
2. **Is the deploy checkout at `origin/master`?** By construction: fetched and hard-reset
   every run. Two old refusals disappear with it — the tree cannot be dirty, and master
   cannot be *ahead* of origin, so a local-only commit can no longer reach production even
   by accident.
3. **Is the deploy checkout clean?** The post-condition `deploy.sh` will insist on, asked
   where the answer is still comprehensible rather than three steps into a deploy.
4. **Which open pull requests are not in this tree?** A warning, never a refusal.
5. **Can this build be verified at all?** See below.

`CHECKS_ONLY=1` exists because the checks are the part worth exercising, and every other
way of doing that ends one step away from a live deploy. Piping the script through `head`
or `sed` truncates what you see and does **not** stop it running. Without `CI=1` it stops
before verification, so it costs a fetch and nothing else.

### Verification: why it stays in your tree

`ci-local` is the fuller check of the two — CI now runs on every push and has a database,
but it still runs only unit and integration, because smoke, fuzz and smoke-prod need a
running application: 3,768 tests of 4,487, 8,410 assertions of ~89,000. It runs in the capsule, and
`docker-compose.yml` bind-mounts **your**
tree into the capsule: running it from the deploy checkout would test your tree while
reporting on the release. So it runs where the capsule is, under one condition.

| your tree | what happens |
|---|---|
| clean and on the deployed commit | `ci-local` runs here; `deploy.sh` is told the revision is proven and does not repeat it |
| anything else | nothing can verify this build; `deploy.sh` is told that instead, counts it an unusual deploy and asks before the swap |

`CI=1` refuses rather than warns, and runs the **full** `ci-local` (smoke, fuzz and
`smoke-prod` as well as the seven CI jobs) rather than the `--ci` subset — which it no
longer runs twice, because `deploy.sh` now honours the handover.

That handover is three environment variables — `DEPLOY_VERIFIED_SHA` (which revision was
proven green), `DEPLOY_VERIFIED_BY` (what proved it, defaulting to ci-local) and
`DEPLOY_UNVERIFIED_REASON` — set by nothing but `tools/prod-deploy.sh` and
`.github/workflows/deploy.yml`. Getting it wrong in
the lenient direction ships an unverified build without the prompt that exists for exactly
that, and the deploy succeeds either way — so `test/Deploy/deploy-checkout-test.sh` drives
the real block out of `tools/deploy.sh` through all six of its paths, and checks that
every mutating `git` command in `tools/prod-deploy.sh` names the deploy checkout.

### Why the deploy checkout exists

**A release is `git ls-files` over the working tree**, not over HEAD. What ships is
whatever is checked out, so a deploy run from a tree that is mid-edit, on the wrong branch,
or carrying a commit that exists nowhere else ships exactly that. The deploy checkout makes
all three impossible rather than merely detected, and leaves your tree free to carry on
working during a deploy.

## Flags

```
./tools/deploy.sh                 # the whole thing
./tools/deploy.sh --dry-run       # preflight + plan, server untouched
./tools/deploy.sh --rollback      # re-point the symlink at the previous release
./tools/deploy.sh --rollback --to <release> [--allow-incompatible]
./tools/deploy.sh --releases      # what is on the server, and what is live
./tools/deploy.sh --reset-caches  # force every opcode-cache segment onto the live release, prove it; no swap
./tools/deploy.sh --migrations    # = tools/migrate.sh status
  --stash        deploy HEAD with local changes stashed, restored afterwards
  --skip-tests   skip tools/ci-local.sh --ci in the preflight
  --ref REF      deploy something other than master (says so, loudly)
  --bootstrap    first swap only: convert a flat tree to the release layout (done 2026-08-16; never again)
  -y, --yes      force past the prompt an unusual run raises
```

**A routine deploy asks nothing** — the preflight, the revision gate and the smoke
rollback protect it, not a `y`. It prompts only for an unusual run (`--ref`,
`--skip-tests`, `--stash`, a first swap, `.deploy.local` not mode 600/400, or a build
nothing could verify). `make prod-deploy` needs no `git` of its own at all, so the
unattended form is:

```bash
gh pr merge <n> --merge && make prod-deploy
```

That deploys `origin/master` whatever branch you happen to be on. Being on master
yourself, clean, is what lets `ci-local` verify it first.

## Deploying from GitHub Actions

`.github/workflows/deploy.yml` runs the same `tools/deploy.sh` from a runner. Since
2026-09-24 it is **push-to-deploy**: `workflow_run` fires when CI completes, and a commit
that landed on master and went green ships itself with no approval step.
`workflow_dispatch` is kept — Actions → Deploy → Run workflow, with a `dry_run` checkbox
that defaults to **on** — for the deploys that are still a decision: a dry run, or a
re-deploy of an unchanged tip.

It is deliberately **not** `push: branches: [master]`. A push trigger fires the instant the
merge lands, while CI is still queued, so the CI gate below would ask whether CI passed on
a commit CI has not finished and refuse every time. `workflow_run` fires *after* CI, which
is the only ordering in which that gate can be satisfied.

What this makes unattended is **migrations**: `tools/deploy.sh` runs the pending
`database/*.sql` through the ledger with nobody watching. That path has every guard it
needs — phases, per-file sha256, a server-side snapshot, an `@verify` that must return zero
rows — and as of this change it has still never run in anger. Watch the first merge that
carries one.

Five things stand between a stranger and production:

- **No `pull_request` trigger, and no `pull_request_target`.** On a public repository a
  fork's pull request cannot reach a secret through a trigger that does not exist. Do not
  add one.
- **The three-condition `if:` on the deploy job.** This is the guard that `workflow_run`
  specifically needs, and the branch restriction below does *not* cover it: a `workflow_run`
  job runs in the **default branch's** context, so the Environment sees `refs/heads/master`
  and passes no matter whose commit CI was testing — and CI *does* run on a fork's pull
  request. The job therefore demands that the run succeeded, that its head branch was
  `master`, and that its head repository was this one. Drop any of the three and a fork's
  green CI run reaches the SSH key. `test/Deploy/workflow-triggers-test.sh` holds all three,
  and holds `workflows: [CI]` against `ci.yml`'s `name:` — renaming CI would otherwise stop
  every automatic deploy silently.
- **The `production` Environment, restricted to `master`.** Settings → Environments →
  `production` → *Deployment branches and tags* → **Selected branches**, `master`. This is
  the one that is doing the work today, and it is not optional: `workflow_dispatch` can run
  a workflow **from any branch**, so without it, a branch carrying an edited `deploy.yml`
  is a branch that can read these secrets and print them. Restricted to `master`, the only
  `deploy.yml` that can reach them is the reviewed one. Keep the secrets on the environment
  rather than on the repository for the same reason.

  *Required reviewers* became available when the repository went public on 2026-09-24 and
  are deliberately **not** used: the decision was push-to-deploy without an approval step.
  With a single maintainer a required reviewer is a keystroke rather than a review — GitHub
  permits self-approval unless *Prevent self-review* is ticked — so it would add friction
  without adding a second pair of eyes. Revisit when someone else has write access.
- **`concurrency: deploy-production`** with `cancel-in-progress: false`, plus the
  server-side lock in `$SHARED/deploy.lock`, which is what catches the case GitHub cannot
  see: someone running `make prod-deploy` from a laptop at the same moment.
- **A pinned host key.** `DEPLOY_KNOWN_HOSTS`, never `StrictHostKeyChecking=no`.

`ci-local` cannot run on a runner — it drives the capsule — so the gate is this
repository's own CI. The workflow **checks that**, rather than asserting it: its first step
asks the Actions API for a successful `ci.yml` run on the exact SHA being deployed and
refuses if there is none. That SHA is `workflow_run.head_sha`, never `github.sha`: for a
`workflow_run` event the latter is master's tip at fire time, which is a *different* commit
whenever a second merge landed during CI's ~7 minutes, and deploying that pairing would
credit an unverified commit with a green run. A green run on the branch is a different claim and does not
count. It then hands `tools/deploy.sh` `DEPLOY_VERIFIED_SHA` and `DEPLOY_VERIFIED_BY`
instead of `--skip-tests`, so the script reports which run proved the commit and still
warns if `master` moved in between. CI runs 3,768 of 4,487 tests — everything except the
three suites that need a running application — which is why the branch restriction above is
not decoration.

### The secrets

```bash
./tools/gh-secrets.sh          # set them all from .deploy.local
./tools/gh-secrets.sh --list   # names and dates, never values
```

Reading the same file `tools/deploy.sh` reads, so the two cannot drift, and idempotent —
run it again after a rotation. It prints names only, passes each value on stdin (a `--body`
argument is visible in `ps`), generates the deploy key if it is missing, and tells you
whether the server accepts it yet. It deliberately will not create the environment or set
the branch restriction: a protection a script can switch on is one a script can switch off.

What it sets, which is `.deploy.local` field for field plus two:

| secret | notes |
| --- | --- |
| `DEPLOY_SSH_USER` | the shell account, not the retired SFTP one |
| `DEPLOY_SSH_HOST` | |
| `DEPLOY_SSH_PORT` | `222`; the workflow defaults to it if unset |
| `DEPLOY_APP_PATH` | relative to the account's home |
| `DEPLOY_BASE_URL` | |
| `DEPLOY_API_KEY` | any `sion_model.api_keys` entry |
| `DEPLOY_CANARY_COOKIE` | may be empty |
| `DEPLOY_DB_NAME`, `DEPLOY_DB_USER`, `DEPLOY_DB_PASS` | used only through the SSH tunnel. This does not have to be the full-DDL account: a user with DDL on this application's tables and no `GRANT` is enough for every migration the ledger has ever applied, and is the credential worth putting here. Left unset, the deploy reports migrations as unmanaged and a person runs `tools/migrate.sh` — which splits `@phase: pre` from `@phase: post` across two operators, and that ordering exists because of the 2026-08-17 outage. |
| `DEPLOY_DB_TUNNEL_PORT` | |
| `DEPLOY_SSH_KEY` | generated by the script at `~/.ssh/schoenstatt-deploy-ci` if absent: ed25519, **no passphrase** (no agent on a runner, nobody to type one), its own key so revoking CD is not rotating yours. Install the public half with the `ssh-copy-id` line the script prints. |
| `DEPLOY_KNOWN_HOSTS` | `ssh-keyscan -p 222 <host>`. Check the fingerprints against your own `~/.ssh/known_hosts` before trusting a scan — a scan taken from a compromised path pins the attacker's key just as happily. |

The first dispatch doubles as the spike for #267 — whether a GitHub runner can reach the
host on port 222 at all. Leave `dry_run` checked for it: the preflight opens the
connection and plans the release without touching the server.

## The steps

| # | step | where | notes |
|---|---|---|---|
| 1 | Preflight | local | on `master`; tree clean; `pull --ff-only`; local master not ahead of origin. Under `make prod-deploy` all of this is true by construction of the deploy checkout |
| 2 | Verification | local | `tools/ci-local.sh --ci` (lint, `composer --no-dev` rehearsal, PHPStan, PSR-12, unit, integration, `test/Deploy/*`) — skipped here when `make prod-deploy` already ran it in the capsule's tree |
| 3 | Back up `public/.htaccess` | server | to `shared/data/htaccess-backups/` |
| 4 | Build | server | `rsync` into `releases/<ts>-<sha>/`, `--link-dest` hardlinked against the previous release; `.revision` written; `public/index.php` broken out of its hardlink (`cp -p` + `mv -f`) |
| 5 | Link shared | server | `shared/data/*`, `shared/public/*`, `shared/config-autoload/*` symlinked in; `data/config` and `data/cache` created empty, per-release |
| 6 | composer install | server | `--no-dev --optimize-autoloader`, vendor seeded from the previous release |
| 7 | Pre-migrations | local → tunnel | `@phase: pre` while the OLD code still serves; a failure aborts before the swap |
| 8 | Warm | server | `jtranslate:export-catalogs`, `sitemap:build --force` — in a tree nothing serves |
| 9 | Census | local | count OPcache segments before the swap (lower bound for step 12) |
| 10 | Swap | server | `ln -sfn` + `mv -Tf`: one `rename(2)` |
| 11 | Post-swap | server | `cache:flush-persistent` (APCu, over HTTP — see below) |
| 12 | Make the swap visible | local | reset **every** opcode-cache segment; not optional |
| 13 | Confirm the live release | local | `/_health` polled 12×; any disagreement **aborts before any migration runs** |
| 13b | Sustained check | local | only when a `@destructive` migration is pending: **three consecutive** agreeing rounds, 45 s apart, waiting out drift for up to 480 s |
| 14 | Post-migrations | local → tunnel | `@phase: post`, now that the new code is provably running |
| 15 | Smoke | local | `tools/smoke-prod.sh`; a failure rolls back automatically unless that would be worse |
| 16 | Housekeeping | local / server | tag `deploy/<ts>` locally (never pushed; `git tag -l 'deploy/*'` answers "what shipped?"); prune to `DEPLOY_KEEP_RELEASES` (5), never the live release or its predecessor |

Any failure before the swap leaves production untouched; nothing irreversible
happens before step 13 passes.

### Step 13b waits, because drift is normal

A `@destructive` migration drops something older code still reads, so it must not run
while any pool is still serving the previous release. Step 13 asks once; 13b asks
repeatedly, and requires **three consecutive** agreements rather than three attempts.

It used to abort the first time a round disagreed, which made its real patience one
interval — shorter than the four minutes its own message said pool drift takes to clear.
The first destructive migration to meet it (#310, dropping eight IP-address columns on
2026-09-24) aborted after 56 s on an otherwise correct deploy, leaving the migration to be
run by hand. Under push-to-deploy that is the wrong trade: the abort is benign by its own
admission — the symlink is already swapped and nothing has run — so waiting costs only
time, while giving up removes the automation from exactly the deploys nobody is watching.

Two things are deliberately **not** waited out:

- **A revision that cannot be read at all.** A wrong `DEPLOY_API_KEY` or an unreachable
  `/_health` answers that way for the whole timeout and then fails anyway, so it fails at
  once instead, with a different message.
- **Drift that outlasts 480 s.** That is no longer a pool recycling slowly; it is a pool
  not recycling, and the deploy says so rather than waiting forever.

`DEPLOY_DRIFT_TIMEOUT`, `DEPLOY_DRIFT_INTERVAL` and `DEPLOY_DRIFT_STREAK` override the
defaults (480 / 45 / 3). `test/Deploy/drift-wait-test.sh` drives the loop with scripted
responses and a stubbed clock, so the eight-minute timeout is exercised in microseconds.

**The release is exactly `git ls-files`.** The transfer list
comes from git, not the working directory, so local cruft cannot reach production,
and untracked files on the server inside `public/` are **deleted by a swap** —
search-engine verification files live in `shared/public/` (see The layout).

**Warming does not include `data/config`.** `bin/console` runs with
`config_cache_enabled = false`, so no console command can populate the merged-config
cache; the deploy's own probes and smoke run write it within ~30 s of the swap. An
empty `data/config` in a release means **nothing has executed that release yet** —
a diagnostic, not a fault.

**The APCu flush must stay an HTTP request.** An APCu segment belongs to the SAPI
that created it; a CLI `apcu_clear_cache()` flushes a segment nobody reads.
`cache:flush-persistent` makes the request; it takes `--url` when
`sion_model.canonical_base_url` is not the host you mean and reads the key from
`SCH_MAINTENANCE_KEY` — prefer that to `--key`, which lands in shell history.

## When a step stops answering

Every remote call is bounded; a slow step and a wedged one no longer look alike.

| defence | catches | budget |
|---|---|---|
| ssh keepalives (`ServerAliveInterval=15` × `CountMax=4`) | a dead network path the local end still believes in | ~60 s |
| `timeout` around every call | a healthy connection whose remote command is stuck | **180 s** default (`RSH_DEFAULT_TIMEOUT`) |
| heartbeat on stderr | nothing — it makes a slow step visible | first beat at 15 s, then every 15 s |

Per-call budgets (`RSH_TIMEOUT=<s> RSH_LABEL=… rsh …`): composer install 1800 s,
vendor copies 600 s, the two warming commands 300 s, `cache:flush-persistent` 120 s,
the symlink swap **30 s**, exit-trap cleanup 20 s. rsync uses `--timeout=120`, an
I/O-stall timeout, so a large transfer that is still moving is never killed.

`timeout` exit **124** is reported separately from a remote command's own failure:
a normal failure is the server telling you something, a 124 is it telling you
nothing, and only the second implicates the deploy rather than the release.

Rules held in place by `test/Deploy/rsh-behaviour-test.sh`: ssh is never
backgrounded (it could not prompt for the key passphrase and would stop receiving
Ctrl-C), and never `ssh -n` or `< /dev/null` — two call sites pipe into `rsh` (the
`.revision` write and the reset-helper distribution). Without `timeout`/`gtimeout`
(macOS: `brew install coreutils`) the preflight warns and continues unbounded.

## Why a symlink swap is not enough: OPcache

**Repointing the release symlink does not change what PHP executes.** The previous
release keeps serving and nothing in any response says so. OPcache files script
entries under the *resolved* path; `opcache.revalidate_path=1` does **not** fix it
(measured), and production has stayed stale for 12–20 minutes against a
`realpath_cache_ttl` of 120, so do not plan around it clearing. What *is* measured
is that **`opcache_reset()` clears a stale resolution** — the lever;
`test/Deploy/opcache-swap-test.sh` is the capsule repro (warm workers required).

**This host runs at least three PHP pools, each with its own OPcache segment.** A
reset request clears only the segment that served it, so a partial reset looks like
"some pages work and some don't". The capsule has one segment and cannot show this.

### The incident that bought all of this (2026-08-17)

`db8.1` dropped five columns as a `@phase: post` migration. The swap had not taken
effect — a pool still executing the previous release selected the dropped columns —
and the smoke failures triggered the automatic rollback **to the release that could
not run against the new schema**. ~40 minutes of empty HTTP 200s on every
navigation-building page (a fatal with `display_errors=Off` *is* a zero-byte 200),
449 fatals, no data lost. The signature is **a live process reporting an older
`.revision` than the live symlink**. Four protections came out of it: (1) the
per-segment opcode-cache reset after every swap, rollbacks included; (2) the
`/_health` revision poll, which stops the deploy before any post-deploy migration;
(3) `@destructive: yes`, which both rollback paths refuse to cross; (4)
`tools/smoke-prod.sh` names a zero-byte 200 as a fatal. `migrate.sh reseal` exists
so `@destructive` could be added to an already applied file.

### Step 12: the reset

The deploy writes a single-use, randomly-named PHP helper into **every** release
directory, waits up to **60 s** for Apache to see it (a fresh file is not instantly
visible here), then requests it until **every segment that has answered is provably
serving the new release** — born after the swap or restarted after it — and at least
as many distinct segments answered as the pre-swap census counted. The reset is
conditional on that same test, so healthy segments are not wiped under live traffic.
Capped at 80 polls. The helper is deleted afterwards, including on failure — a stray
one is a publicly reachable cache flush. It cannot be an application endpoint: a
pool serving the previous release resolves routes against that release's code.

The census is a **lower bound** (segments churn every 5–15 minutes); only the count
is used, never the ids. "N consecutive `/_health` probes agree" is not an exit
condition: probes need not land on the same pool, and it let two deploys through
with `manualRestarts: 0` on every segment.

The helper also prints each pool's **interned-strings usage** the instant before
resetting it — the only warm reading that ever exists, since the buffer is
append-only and every reset zeroes it (segments under five minutes old are
skipped). `./tools/opcache-sample.sh` answers the same question on demand.

### Step 13: the revision gate

`/_health` reports the release's `.revision` when given `DEPLOY_API_KEY`. Twelve
probes, because one samples one pool. Any disagreement aborts with no migration run.
With an empty `DEPLOY_API_KEY` the gate proceeds blind and says so — set the key. If
the reset misses and the gate refuses, re-running the deploy hits the same wall: run
`./tools/deploy.sh --reset-caches`, then re-run.

### Step 13b: `@destructive` waits longer

Pools the reset never reached have served old code for ~4 minutes *after* a 12/12
gate before recycling — harmless when both releases share a schema, the incident
again when a migration DROPs something. A pending `-- @destructive: yes` migration
therefore needs three consecutive rounds, 45 s apart, all 12/12. A disagreeing round
aborts having changed nothing; wait a few minutes and run
`bash tools/migrate.sh apply --phase=post`. Deliberately not applied to every
deploy: a tax people route around protects nothing.

## Database migrations

`tools/deploy.sh` applies them at both phases; `tools/migrate.sh` is the same runner
standalone. **Every `database/*.sql` is tracked in the `sch_migration` ledger**
(filename, sha256, phase, kind, when, by what, duration, rows per statement).

```bash
./tools/migrate.sh status                      # applied vs pending (= deploy.sh --migrations)
./tools/migrate.sh plan                        # the pending files, with headers
./tools/migrate.sh apply --phase=pre|post [--env=capsule] [--dry-run] [-y]
./tools/migrate.sh backfill --through=db7.7    # mark historical files applied, run nothing
./tools/migrate.sh reseal db8.1.sql            # re-record an applied file whose COMMENTS changed
```

**Rehearse against the capsule first** (`--env=capsule`) and read the counts: the
ledger proves a migration ran, not that it was correct.

### Headers a new migration must carry

```sql
-- @phase: post
-- @kind: dml
-- @tables: trans_phrases
-- @idempotent: yes          -- ddl only
-- @destructive: yes|no      -- when older code cannot run once this is applied
-- @verify: SELECT id FROM t WHERE <still wrong>
```

| header | required | meaning |
|---|---|---|
| `@phase` | yes | `pre` runs before the swap (old code serving), `post` after the gate. **No default** |
| `@kind` | yes | `dml` is wrapped in a transaction; `ddl` cannot be |
| `@tables` | yes | dumped to `data/deploy/backups/` before it runs; `none` is allowed and is a claim |
| `@idempotent` | `ddl` only | `yes` — your assertion that re-running is a no-op |
| `@destructive` | no | `yes` means older code cannot survive it: triggers step 13b and blocks rollback across it |
| `@verify` | no | must return **zero rows** afterwards — "select what is still wrong", never the end state |

`@phase` has no default because both orders are real and guessing wrong is silent:
`pre` when new code selects columns the old schema lacks; `post` for every phrase
retirement, because phrase discovery un-retires whatever the old code still looks up.

### Guarantees and refusals

- For `dml`, the ledger row commits **inside the same transaction** as the change:
  applied-but-unrecorded and recorded-but-unapplied are both impossible, and a
  statement failing part-way rolls the whole file back with no ledger row (every
  table is InnoDB). `mysql` aborts on the first error; `--force` is never used.
- `ddl` cannot have that — MariaDB commits implicitly — so it must declare
  `@idempotent: yes` (refused without it), and a `dml` file containing DDL is
  refused. A forcing function, not a proof: write the guards.
- `@tables` are dumped first, to `data/deploy/backups/` on **your** machine
  (gitignored — production data). Row counts are recorded per statement.
- `@verify` returning rows fails the migration, naming them; the change stays applied.
- No `@phase`, or `@destructive` other than `yes`/`no`: refused.
- An applied migration whose bytes changed aborts before anything runs. `reseal` is
  the only way past it, for comment-only edits, after proving via git history that
  no statement moved.

### Snapshot retention and credentials

**A production snapshot lives on the server**, in `shared/migration-snapshots/`, and the
migration does not run until it is there and intact. Anything else is
`data/deploy/backups/` in the deploying checkout, which is where capsule rehearsals go.

That split exists because of where a deploy can run from. `data/deploy/backups/` is right
for `make prod-deploy` — it lands in your tree and stays. On a GitHub runner it is
`/home/runner/work/...`, destroyed with the job: the dump would be taken, reported `ok`
with its size, and deleted minutes later, which reads in the log as more protection than
the deploy had. The snapshot is the only undo for a `@destructive` migration, and
`tools/deploy.sh` refuses a *code* rollback past one precisely because the database cannot
go back on its own.

`shared/migration-snapshots/` is a **sibling** of `shared/data/` and `shared/public/`, not
inside them, because everything under those two is symlinked into every release. The
docroot is `public/`, so nothing there was ever web-reachable — the point is that no
application code should be able to reach a database dump by walking `data/`.

**The dump runs on the server.** `mysqldump` executes there, writes straight into
`shared/migration-snapshots/`, and the bytes never cross the connection. `sch_visits`
alone is 8.7 million rows and 1.9 GB: dumped through the tunnel that is an hour and a half
with a deploy waiting on it, and a connection that drops at minute eighty starts again
from nothing.

That needs credentials on the server, and the ones in `.deploy.local` stay where they are.
A snapshot is a read, and the server already holds an account with `SELECT` on these
tables — the application's own, in `shared/config-autoload/local.php`, which it has had all
along. PHP reads that config and writes a mode-600 `--defaults-extra-file`; only the
database *name* is ever printed, and the file is removed on every exit path.

The server answers with one line, and which line decides what happens next:

| the server says | what it means | what the runner does |
|---|---|---|
| `OK <size> <sha256>` | the snapshot is in place | applies the migration |
| `PRECHECK …` | this server cannot dump for itself: no `mysqldump`, no `php`, no config, no grant, under 2 GB free | warns, dumps through the tunnel instead, applies the migration |
| `FAIL …` | it dumped, and what it produced cannot be trusted | aborts; nothing is applied |
| anything else | the connection died, or said something unclassifiable | aborts; nothing is applied |

The gap between the middle two rows is the point. A server that *cannot* take the snapshot
must never stop a release — the tunnel still works, it is only slow. A dump that ran and
came back short must always stop one, because a valid gzip of the first half of a table is
indistinguishable from a good backup until the day you need it. `mysqldump` writes its own
end marker; its absence is the only thing that tells the two apart, and it is checked.
`test/Deploy/server-snapshot-test.sh` runs the shipped program here — against stubs for
every branch, and against the capsule for the one thing stubs cannot prove, that a real
`mysqldump` accepts what PHP wrote.

Know what this does not protect against: the snapshot sits on the host it was taken
from. That is the right trade for the failure it exists for — a migration that did the
wrong thing — and no protection at all against losing that host. The server's own backups
are what cover the second case.

`DEPLOY_KEEP_BACKUP_RUNS=2` (per environment) and `DEPLOY_KEEP_BACKUP_DAYS=30` are
both floors: a snapshot is deleted only when it is *both* outside the last N runs
for its environment *and* older than the day limit, so capsule rehearsals cannot
push out the last production snapshot. A run is a group — both snapshots of one apply
share a timestamp and are pruned together or not at all, or a restore finds one table and
not the other. Age comes from the filename's run stamp rather than an mtime, which a copy
or a restore would have moved. Every deletion is named on screen. A single
`sch_changes` dump is ~74 MB. The policy is one function, `prunable()`, used by both the
local and the server path and pinned by `test/Deploy/snapshot-retention-test.sh`.

Full-DDL credentials live only in `.deploy.local` and are used through an **SSH
tunnel** to the server's own `127.0.0.1:3306` (`DEPLOY_DB_TUNNEL_PORT` locally; if
taken, the runner moves to the next free port and says so, and it asserts
`sch_changes` + `trans_phrases` exist before touching anything). Never written to
the server, never in `argv` — a mode-600 `--defaults-extra-file` carries them. The
snapshot is the one step that runs on the server, and it uses the server's own
application account rather than these; see above. The
account must be granted for `127.0.0.1`. The production ledger is complete: 75 files
backfilled through `db7.7`, everything after applied through the runner.

## The layout on the server

```
public_html/schoenstatt.link/
  public -> releases/<ts>-<sha>/public               the swap; the only symlink
  releases/<ts>-<sha>/                               five kept; vendor hardlinked across releases
  shared/data/{logs,exceptions,htaccess-backups,fonts,musicas,texts,scans,import}
  shared/public/{covers,associations,dh,BingSiteAuth.xml,google0e1110cae0fbf177.html}
  shared/config-autoload/{local.php,*.local.php}
  shared/deploy.lock/holder                          present only while a deploy is running
  shared/migration-snapshots/                        pre-migration table dumps; NOT under shared/data
```

`shared/deploy.lock` is a directory, taken with `mkdir` because that is atomic where
`[ -f ] || touch` is not, and released by the exit handler of the run that took it. It is
what stops a dispatched Actions run and a laptop `make prod-deploy` from building over each
other; GitHub's own `concurrency` cannot see the laptop. `--dry-run`, `--rollback` and
`--reset-caches` never take it — the first writes nothing, and the other two are what you
reach for when the lock's likely holder is the deploy you are trying to undo. If one is
left behind, its `holder` file says who and since when:

```bash
ssh -p 222 <user>@<host> 'rm -rf <app>/shared/deploy.lock'
```

`public/index.php` resolves `__DIR__/../vendor` through the symlink into its own
release, so one link swaps code, vendor, config and compiled catalogs together. A
request in flight keeps serving from the old directory, which still exists — which
is why releases are pruned by count, not immediately.

| shared | per-release |
|---|---|
| `data/logs`, `data/exceptions`, `data/htaccess-backups` | `vendor/` |
| `data/fonts`, `data/musicas`, `data/texts`, `data/scans`, `data/import` | `data/config` (the merged config cache) |
| `public/covers`, `public/associations`, `public/dh` — 1.2 GB of uploads | `data/cache/twig` |
| `config/autoload/local.php` and `*.local.php` | compiled `*.lang.php` catalogs |
| server-only docroot files | `public/sitemap*.xml` |

Rules, each of which fails silently if broken:

- **`data/config` and `data/cache` are per-release.** A new tree meeting an old
  merged-config cache is what produced a fatal burst on every deploy.
- **`data/publications` is tracked repo content, not shared state.** `ln -sfn` onto
  an existing directory creates the link *inside* it; `tools/deploy.sh` refuses a
  shared entry that collides with tracked content.
- **`*.local.php` does not match `local.php`**, and the file without a prefix is the
  one holding the database credentials. Both scripts use both patterns.
- **`data/import` is the one shared directory the application writes to** (library
  spreadsheet uploads); `tools/deploy.sh` does `mkdir -p shared/data/import` first,
  since an absent shared directory would land uploads inside the release and lose
  them at the next swap. Nothing prunes it (docs/BACKLOG.md).
- **Anything dropped into `shared/public/` is symlinked into every future release.**
  Losing `BingSiteAuth.xml` or the Google file de-verifies the site. After any
  manual server work, before the next swap:

  ```bash
  ./tools/deploy-bootstrap.sh --check     # report only; lists server-only docroot files NOT claimed
  ```

  Whatever that list holds is what the next swap deletes. Keep a file by adding it
  to `SHARED_PUBLIC` in `tools/deploy-bootstrap.sh`; inspect random-hex `.php`
  files first — that is also what a webshell looks like.

The shell account (`$DEPLOY_SSH_USER`, uid 1023) **is the web-server user**, so nothing
chmods anything. Apache follows the symlinked docroot and honours `.htaccess` at
the target. A release costs ~36 MB of code plus ~128 MB of vendor, mostly hardlinked.

Cutover leftovers still on the server *(verify with `ls -A`)*: the old flat tree as
`_flat_old/` and the old docroot as `public.pre-atomic`. Delete them by name, never
by wildcard — `shared/` sits beside them with 1.2 GB of uploads and every `*.local.php`.

## Server facts

- **Production runs PHP 8.5.9, CGI/FastCGI**, MariaDB 10.11, on Hetzner shared
  hosting (`$DEPLOY_SSH_HOST`). OPcache on (`validate_timestamps=1`,
  `revalidate_freq=2`, 128 MB, 10000 files), APCu 5.1.27 (`apc.shm_size=256M`,
  `apc.ttl=0`, `apc.entries_hint=4096`), `memory_limit=512M`,
  `max_execution_time=240`, `display_errors=Off`. ICU 72.1 (the capsule has 76.1).
- Port **222** is the shell account and the only identity a deploy uses. Port 22
  is a restricted SFTP jail (no exec) and nothing uses it.
- **php.ini changes are the one case that needs `pkill -u $DEPLOY_SSH_USER -f php`**
  (workers re-read ini on respawn; cost is a cold OPcache, no downtime). Deploys
  never need it while `validate_timestamps=1`; at 0, every deploy would.
- `.htaccess` cannot set PHP directives here (`php_value` is mod_php); a tracked
  `public/.user.ini` could set `PHP_INI_ALL` ones, but none exists or is needed.

### The per-account `php.ini`

```
/home/httpd/php85-ini/<account>/php.ini   # root-owned; every change is a konsoleH ticket
```

**One directory per PHP version** (`php53-ini` … `php85-ini`): flipping the version
in konsoleH silently reinstates that version's defaults and every tuned value is
gone — it has happened. After any version switch, diff the live values against this
table, from the web SAPI (`/en/sm/phpinfo` reloaded several times, or
`./tools/opcache-sample.sh`, which groups `/sm/cache-status` polls by segment) —
**never from CLI `php -i`**, which reads a different ini.

| setting | current | wanted | changeable | why |
|---|---|---|---|---|
| `opcache.interned_strings_buffer` | `32` | `32` | SYSTEM | landed 2026-08-21; at the old 8 MB it sat at 89–100% and stopped interning. Usage is append-only, so a post-deploy reading always looks healthy |
| `apc.ttl` | `0` | **non-zero** | SYSTEM | with 0 a failed allocation expunges the whole segment instead of evicting. Asked for with the `shm_size` raise; **did not land** |
| `apc.shm_size` | `256M` | keep | SYSTEM | raised from 32M; both historical oversized items now fit |
| `opcache.validate_timestamps` | `1` | keep | ALL | **load-bearing** for deploys, see above |
| `opcache.revalidate_freq` | `2` | keep | ALL | changed files picked up within seconds |
| `opcache.revalidate_path` | `0` | keep | ALL | measured not to affect the symlink-swap staleness; do not spend a ticket on it |
| `opcache.memory_consumption` | `128` | keep | SYSTEM | ~25% used, no OOM restarts |
| `opcache.max_accelerated_files` | `10000` | keep | SYSTEM | ~14% of slots used |
| `opcache.use_cwd` | `1` | keep | SYSTEM | same-named files in different releases must not collide |
| `realpath_cache_ttl` | `120` | keep | — | same as the capsule; cannot explain the 12–20 min staleness, not a lever |
| `memory_limit` | `512M` | keep | ALL | `public/index.php` also sets it, so a reverted ini does not show immediately |

`/en/sm/cache-status` (maintenance key) reports both caches live —
`internedPercentUsed`, `internedBufferConfiguredMb`, `memoryPercentUsed`,
`keysPercentUsed`, `startTimeUnix`, `validateTimestamps`, `revalidateFreq` — so a
change can be confirmed without SSH. A low `uptimeSeconds` means the cache was
reset, not that a process is new with a new ini.

### Deploying a PHP-version rung

**Flip the konsoleH PHP version first, then deploy — never the other way round.**
Composer writes `vendor/composer/platform_check.php` from `require.php` and PHP
evaluates it on every request, so a release whose lock requires the newer PHP
hard-fatals every page on the older server. The reverse (new PHP running the
previous release) is a combination the capsule verifies before the rung lands.
Then copy the tuned `php.ini` values across — the new version reads a different
file — and confirm from `/en/sm/phpinfo`. `config.platform.php` is **8.5.9** since
2026-09-11, the version production runs; it read 8.4.24 while locked packages
capped PHP there and none does any more. That is a statement about what composer
resolves against, and it did **not** change the gate: `platform_check.php` is
written from the packages' own requirements and still reads `>= 8.4.1`.

## The front controller

`public/index.php` runs `App\Kernel` (symfony/http-kernel) unconditionally. **There
is one front controller and no laminas one to fall back to** — nothing has
dispatched through laminas-mvc since 2026-09-08, the `SYMFONY_KERNEL` canary and
`LegacyBridge` are deleted, and `DEPLOY_CANARY_COOKIE` in `.deploy.local` is
ignored. Rolling back to laminas is not possible; `--rollback` reaches only earlier
Symfony-served releases. See [laminas-exit.md](laminas-exit.md).

```bash
curl https://schoenstatt.link/_health              # {"status":"ok","kernel":"symfony"} — the site booted
curl https://schoenstatt.link/api/v3/schema        # 200 JSON — the whole ServiceBridge path works
curl https://schoenstatt.link/api/v3/associations  # 401 JSON — the token gate is on
```

## Smoke checks

`bash tools/smoke-prod.sh` is the deploy's last step and runnable any time. It
covers the homepage, both 404 flavours, the 410s for retired `/api/v1|v2` URLs
(fetching the advertised `/api/v3/schema` successor, not pattern-matching the
header), the sitemap (Apache-served: `Accept-Ranges: bytes`, no `Set-Cookie`; this
host sends no `ETag`), every public route, the v3 API, response compression and the
static-asset `Cache-Control` policies from the tracked `public/.htaccess` (HTTP/2
only warns), and `SMOKE_PROD_COLD_SAMPLES` random *cold* sitemap pages — the
fatal-200 class, which fixed URLs cannot catch. A **zero-byte HTTP 200 is a fatal**,
reported once per URL. Non-zero exit on any failure. With `SMOKE_PROD_CACHE_KEY` set
it polls `/en/sm/cache-status` and **warns**, without failing, when APCu is ≥80%
full or has ever expunged, or OPcache has restarted — a real signal since the 256M
raise, not the known condition.

It also runs against the capsule in `tools/ci-local.sh`, the only thing besides a
deploy that ever executes it:

```bash
docker compose exec -T app php bin/console sitemap:build --force --url=http://localhost:8080
SMOKE_PROD_BASE_URL=http://localhost:8080 SMOKE_PROD_CACHE_KEY=local-dev-api-key bash tools/smoke-prod.sh
```

The sitemap rebuild comes first because the script filters the sitemap index by
base URL, deliberately (a foreign `<loc>` in production is a real fault).
**Still manual after a deploy:** a sign-in round trip with a real email.

## Reading production exceptions

```bash
bash tools/fetch-exceptions.sh              # mirror data/exceptions/ + summary table
less data/exceptions-prod/<fp>/first.txt    # the write-up; each carries the release's .revision
bash tools/clear-exceptions.sh --yes <fp>   # fixed it? clearing re-arms notification
```

The first occurrence of each fingerprint is emailed to `webmaster@schoenstatt.link`.
Failures **before the container exists** are recorded but cannot be emailed — check
the store and `shared/data/logs/bootstrap-fatal.log` when a deploy goes quiet.
Full reference: [exception-reporting.md](exception-reporting.md).

## Rollback

```bash
./tools/deploy.sh --rollback              # the previous release
./tools/deploy.sh --rollback --to <rel>   # a specific one
./tools/deploy.sh --releases              # what is on the server, and what is live
```

One symlink, one `rename(2)`, then the opcode-cache reset, the revision gate against
the target's own `.revision`, an APCu flush and a smoke run. It reinstates code,
`vendor/`, compiled catalogs and `.htaccess` together, because all four live inside
the release directory. A failed smoke run in a deploy rolls back on its own and
keeps the failed release for inspection.

What it does not do:

- **Undo a database migration.** A `pre` migration is compatible with the previous
  code, so rolling code back alone is safe. A `@destructive: yes` migration is the
  opposite: **both rollback paths refuse a target release that does not ship that
  file** — the automatic one declines to roll back at all and leaves the site on
  the release that matches the schema. `--allow-incompatible` overrides once you
  have decided the breakage is acceptable; the table snapshots are in
  `data/deploy/backups/`.
- **Survive the next deploy.** To undo a commit, revert it and deploy.
- **Reach a pruned release** (five kept; the live one and its predecessor never
  pruned) **or laminas** (see The front controller).

A clobbered `.htaccess` can be restored from `shared/data/htaccess-backups/`.

## Running the server-side steps by hand

If a step fails mid-run the release directory is still there and nothing is live
until the swap. On the port-222 account:

```bash
cd ~/public_html/schoenstatt.link/releases/<the-release>
php composer.phar install --no-dev --no-interaction --optimize-autoloader
php bin/console jtranslate:export-catalogs
php bin/console sitemap:build --force
cd ~/public_html/schoenstatt.link && ln -sfn releases/<the-release>/public public.swap && mv -Tf public.swap public
cd releases/<the-release> && php bin/console cache:flush-persistent
```

Then, locally, `./tools/deploy.sh --reset-caches` (the swap is invisible to OPcache
until you do) and `bash tools/smoke-prod.sh`. `cache:clear-config` is not a deploy
step (each release starts with an empty `data/config`); it is the fix after editing
config *on* the server.

## Scheduled jobs

Every cron entry must resolve *through* the `public/` symlink so it runs the live
release — `cd -P` into `public`, then `cd ..`; the application directory itself
holds no code.

```cron
*/15 * * * * cd -P ~/public_html/schoenstatt.link/public && cd .. && php bin/console sitemap:build >/dev/null
```

A run with nothing to do costs ~0.11 s; a rebuild ~1.2 s. konsoleH's scheduler
reads the same crontab, so `crontab -l` on the port-222 account is the full
inventory. Overdue-book notices are `php bin/console books:send-notices
--library=N [--dry-run]`, needing no API key; whether the monthly entry is enabled
is a server fact — check `crontab -l` *(verify)*. `15 11 1 * *` in German server
time lands at 05:15–07:15 in Santiago depending on month.

Creating API bot accounts and granting their roles is a manual step too — see
[api-v3.md](api-v3.md); a role inserted by raw SQL needs
`php bin/console cache:flush-persistent` before the users screen shows it.
