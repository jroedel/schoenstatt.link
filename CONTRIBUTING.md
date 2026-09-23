Contributing
============

Thank you for looking. This is a small volunteer project behind a live site, so
the conventions below are less about style than about not breaking something
quietly — most of what this application does wrong, it does wrong *silently*: a
page that works for more people than it should, a cache that stopped being read,
a validator that was never wired up. Nearly every rule here exists because one of
those happened.

If you are reporting a security problem, read [SECURITY.md](SECURITY.md) first and
do not open a public issue.

Setting up
----------

```bash
git clone https://github.com/jroedel/schoenstatt.link.git
cd schoenstatt.link
./config.sh            # .dist configs, composer install, pinned phars, database seed
docker compose up -d
```

That is the whole setup, and it needs nothing you do not already have in the
repository. `config.sh` seeds `database/dumps/` from `database/ci/base-schema.sql`
and `database/ci/ci-seed.sql` — the recorded schema and an invented corpus of
eight associations, three people, three libraries and six books. `docker compose`
mounts that directory as the database's init directory on first boot.

**The corpus is made up, and that is deliberate.** `ci-seed.sql` says so in its own
header: a redacted extract of production would carry the privacy question forever.
The practical consequences:

| suite | against the invented corpus |
| --- | --- |
| `unit` | passes — it touches no database at all |
| `integration` | passes; 16 classes skip, every one pinned in `test/known-ci-skips.txt` with its reason |
| `smoke`, `fuzz` | assume the real catalogue; expect failures |

The GitHub integration job builds its database from exactly this pair on every
push, so "it passes on the seed" is a property that is checked continuously rather
than a claim.

Everything runs *inside* the capsule — the host PHP lacks the extensions PHPUnit
and phpcs need. `php composer.phar <script>` wraps the `docker compose exec` for
you.

What to run
-----------

```bash
./tools/ci-local.sh          # the real gate: every CI job, plus smoke, fuzz and smoke-prod
./tools/ci-local.sh qa       # phpcs + phpstan + unit — the fast triad between edits
./tools/ci-local.sh --last   # print the last run's complete output; --last '<pattern>' filters
```

**Do not re-run a suite to see a different slice of its output.** Every run writes
its whole output to `.ci-local/last.log`, and a median run is around two minutes.

A stage that passed is skipped while the tree it passed against is unchanged, and
the summary says how many came from cache. `--no-cache` forces a full run.

`ci-local.sh` is stricter than GitHub CI. CI has run against a real MariaDB since
2026-09-23, but it still runs only unit and integration — smoke, fuzz and
`smoke-prod.sh` need a running application. In tests that is 3,768 of 4,487.

Run the **smoke suite one process at a time**, never in parallel. A run where every
response is a ~800-byte 200 is a known wedge, not a test failure.

`make dev-hooks` points `core.hooksPath` at `.githooks`, after which `pre-push`
runs `ci-local.sh qa`. It warns rather than blocks when the capsule is down.

The recordings are the part that surprises people
-------------------------------------------------

Several checks are *recordings* rather than assertions — a file holding what the
application answered when the recording was taken, compared byte for byte:

| file | what it records |
| --- | --- |
| `test/Form/form-markup.php` | rendered markup for every form |
| `test/Form/engine-surface.php` | the validation engine's verdict, values and messages |
| `test/Element/element-surface.php` | every element's answer to every question asked of it |
| `test/Rules/rule-surface.php` | every validation rule over a fixed corpus |
| `test/Rules/uri-surface.php` | URLs through `Http` and `SionTable::filterUrl()` |
| `test/Db/sql-surface.txt` | the SQL the server actually receives |
| `docs/acl-baseline.json` | every route's guard and every role's reach |
| `test/Fuzz/known-form-gaps.php` | validation gaps that are known and accepted |

Each has a `composer *-baseline` script that regenerates it. **A changed line is a
real answer moving, never something to regenerate away.** If your change moves one,
read every moved line and say in the pull request why it moved. Regenerating to make
a test pass is the one thing that turns all of this into nothing.

The same applies to `database/ci/base-schema.sql`: regenerate it with
`./tools/schema-dump.sh` in the same pull request as any migration that changes the
schema, or `ci-local.sh schema` will say so.

Conventions
-----------

- `php -l` every file you touch, then phpcs and PHPStan **on the paths you touched**.
- **PSR-12** is enforced on the paths listed in `tools/phpcs-clean-paths.txt`, which
  are clean today and must stay clean. `composer cs-check` runs at a wider scope whose
  exit status has carried no signal for years — pass it your own paths. Ask before
  running `cs-fix` broadly.
- **PHPStan** is committed at level 0 with every pre-existing error in
  `phpstan-baseline.neon`; the contract is "no new errors". `src/` and
  `config/symfony` hold themselves to **level 8**.
- **Change the code that owns the behaviour**, not a controller or template that
  duplicates it. If a policy lives in the ACL or a table, that is where it changes.
- **Before adding a dependency, ask whether twenty lines of our own code would do.**
  This project removed 37 `laminas/*` packages over thirteen days to get here
  ([docs/laminas-exit.md](docs/laminas-exit.md)); it is not looking to refill the
  space. Preferring to remove a dependency over rescuing one is a standing rule.
- **Migrations**: `database/*.sql` is a migration and nothing else — `tools/migrate.sh`
  globs that directory and a stray file there aborts a deploy. Each carries `@phase`,
  `@kind` and `@tables`, and an applied migration is never edited: the ledger records a
  sha256 per file.
- Comments here carry *why*, and often a measurement. Matching that is more useful
  than matching the formatting.

Git
---

- Branch, commit, push, open a pull request. **Never push to `master`**, never
  force-push, never merge your own.
- There are no submodules. The three shared libraries were absorbed on 2026-09-23,
  so one repository, one pull request, one dependency manifest.
- Say in the pull request body that `./tools/ci-local.sh` ran, and name anything that
  SKIPped and why. If something could not run, say that instead — a narrower check is
  never offered as equivalent to a broader one.

Where to look first
-------------------

- **[docs/agent-guide.md](docs/agent-guide.md)** maps a task to the code that owns it
  and the check that covers it. It is the fastest way in.
- **[CLAUDE.md](CLAUDE.md)** is the architecture and the working conventions in full.
- **[docs/README.md](docs/README.md)** indexes everything under `docs/`.
- **[docs/BACKLOG.md](docs/BACKLOG.md)** is what is still to do, and why in that order.

`tools/ctx` is worth knowing about before reaching for `rg`: `ctx def <Symbol>` prints
a declaration with exact bounds and `ctx outline <file>` orients a large file in one
screen, both from the AST rather than from brace matching.
