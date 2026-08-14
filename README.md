schoenstatt.link
================

A database application for Schoenstatt-related topics — shrines, the movement,
literature and libraries, and music — served in four locales. Built on
the Laminas MVC layer (formerly Zend Framework 3) with a Symfony kernel in front
of it, running PHP 8.5 in production and in development, against MariaDB
10.11.

Live at <https://schoenstatt.link>.

Getting started
---------------

The whole environment is a Docker "time capsule" that matches production. From a
fresh clone:

```bash
git submodule update --init --recursive   # SionModel, JUser and JTranslate
./config.sh                               # seeds .dist configs, runs composer install
bash tools/get-phars.sh                   # pinned phpunit + phpstan
docker compose up -d
```

Then:

| what | where |
| --- | --- |
| The app | <http://localhost:8080> (redirects to `/en/`) |
| Outgoing mail (Mailpit) | <http://localhost:8025> |
| MariaDB | host port 33306 — `schoenstatt`/`schoenstatt`, database `ourlink_db1` |

The database is imported on first boot from a production dump in
`database/dumps/` (gitignored — you need to place one there). To reimport from
scratch: `docker compose down -v && docker compose up -d`.

**The PHP version is switchable.** Set `PHP_VERSION` and `APCU_VERSION` in
`.env` and rebuild (`docker compose build && docker compose up -d`). Check which
one is live with `docker compose exec -T app php -v` before drawing conclusions
from a test run.

Verifying a change
------------------

```bash
php composer.phar unit          # no HTTP, no app, no vendor/ — safe to run freely
php composer.phar integration   # exercises real vendor/ libraries
php composer.phar fuzz          # form input validation: hostile input through every form
php composer.phar smoke         # HTTP characterization tests against the running capsule
php composer.phar test          # all of the above

php composer.phar stan          # PHPStan, contract is "no new errors"
php composer.phar cs-check      # phpcs, PSR-12 based
php -l path/to/File.php         # syntax check anything you touched
```

All of these shell into the capsule: the host PHP typically lacks the extensions
PHPUnit and phpcs need. Run the **smoke suite one process at a time** — never
fan it out in parallel.

Two checks are baseline-driven, and in both cases the baseline is the point:
`fuzz` accepts the validation gaps listed in `test/Fuzz/known-form-gaps.php` and
fails on any new one (`php composer.phar fuzz-baseline` regenerates it — every
line the diff *adds* is a gap being accepted), and `stan` accepts the errors in
`phpstan-baseline.neon`.

Authorization is not covered by any of them, because a permission that quietly
stops matching makes a page work for *more* people and nothing fails. Diff it
instead:

```bash
docker compose exec -T app php tools/acl-table.php                  # reviewable
docker compose exec -T app php tools/acl-table.php --format=json    # diffable
```

`docs/acl-rules.md` and `docs/acl-baseline.json` are the committed snapshots;
regenerate and diff them after touching a route, a guard entry or a role.

Layout
------

Application modules live in `module/` and are PSR-4 autoloaded, with class
namespace and file path matching exactly:

| module | what it is |
| --- | --- |
| `Application` | site chrome, routing, home page, GDPR strategy |
| `Schoenstatt` | shrines, the movement, associations, persons |
| `Books` | literature, libraries, publications, music, dictionary |
| `RestApi` | the JSON refusal for unmatched `/api/` paths (410 for the retired v1/v2, 404 otherwise), and nothing else since v1/v2 were retired — the API itself is `/api/v3`, in `src/` |
| `SionModel`, `JUser`, `JTranslate` | shared libraries, **git submodules** — changes here affect other sites, so commit in the submodule first, then move the pointer |

Each follows the Laminas convention: `config/module.config.php`, `src/`, and
`view/` for `.phtml` templates. Enabled modules are listed in
`config/modules.config.php`; environment-specific configuration lives in
`config/autoload/`, where `*.global.php` is committed and `*.local.php` is
machine-specific and seeded from the `.dist` files by `config.sh`.

Documentation
-------------

- **[docs/DEPLOY.md](docs/DEPLOY.md)** — how a deploy runs, server facts, rollback.
- **[docs/exception-reporting.md](docs/exception-reporting.md)** — how production
  failures reach your inbox, and the tooling for reading and clearing them.
- **[docs/BACKLOG.md](docs/BACKLOG.md)** — the modernization backlog and the
  reasoning behind its sequencing.
- **[CLAUDE.md](CLAUDE.md)** — architecture notes, environment details and
  working conventions, kept deliberately current.

Deployment is phploy over SFTP straight to production; read
[docs/DEPLOY.md](docs/DEPLOY.md) before running it.
