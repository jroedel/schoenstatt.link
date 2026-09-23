schoenstatt.link
================

A database application for Schoenstatt-related topics — shrines, the movement,
literature and libraries, and music — served in five locales. PHP 8.5 against
MariaDB 10.11; a hand-wired Symfony HTTP kernel (`App\Kernel`, `src/`) serves
every page and the `/api/v3` API, with laminas-db underneath it as a library. Live at <https://schoenstatt.link>.

Getting started
---------------

The whole environment is a Docker "time capsule" that matches production:

```bash
./config.sh                               # seeds .dist configs, runs composer install
bash tools/get-phars.sh                   # pinned phpunit + phpstan
docker compose up -d
```

| what | where |
| --- | --- |
| The app | <http://localhost:8080> (redirects to `/en/`) |
| Outgoing mail (Mailpit) | <http://localhost:8025> |
| MariaDB | host port 33306 — `schoenstatt`/`schoenstatt`, database `ourlink_db1` |

The database is imported on first boot from a production export in
`database/dumps/` (gitignored — place one there). The PHP version is switchable
via `PHP_VERSION`/`APCU_VERSION` in `.env` and a rebuild; check what is live with
`docker compose exec -T app php -v`. Headless work goes through `bin/console`
(symfony/console): `docker compose exec -T app php bin/console list`.

Verifying a change
------------------

```bash
php composer.phar unit          # no HTTP, no app, no vendor/ — safe to run freely
php composer.phar integration   # ServiceManager + database, no running app
php composer.phar fuzz          # hostile input through every form; contract "no new gaps"
php composer.phar smoke         # HTTP characterization tests against the running capsule
php composer.phar test          # all of the above

php composer.phar stan          # PHPStan; contract "no new errors" (phpstan-baseline.neon)
php composer.phar cs-check      # phpcs, PSR-12 — judge only the paths you pass it
./tools/ci-local.sh             # everything CI runs, plus smoke, fuzz and smoke-prod
```

All of these shell into the capsule; the host PHP lacks the extensions PHPUnit and
phpcs need. Run the **smoke suite one process at a time** — never in parallel.
`./tools/ci-local.sh` is the stricter check: GitHub CI has no database and
executes under half of the assertions.

Authorization is diffed, not tested — a rule that quietly stops matching makes a
page work for *more* people and nothing fails:

```bash
docker compose exec -T app php tools/acl-table.php --format=json   # diff against docs/acl-baseline.json
```

Layout
------

| where | what |
| --- | --- |
| `src/` (`App\`) | the Symfony kernel, controllers, `/api/v3`, console commands; PHPStan level 8 |
| `templates/` | Twig templates; `layout.html.twig` is the shared chrome |
| `config/symfony/routes.php` | every route the site serves |
| `module/{Application,Books,Schoenstatt}` | laminas modules: config, models, forms |
| `module/{SionModel,JUser,JTranslate}` | shared libraries: the form stack, the db layer, users, translation |
| `config/autoload/` | `*.global.php` committed, `*.local.php` machine-specific (from `.dist` via `config.sh`) |
| `database/` | incremental migrations, applied through the `sch_migration` ledger |

Documentation
-------------

- **[docs/README.md](docs/README.md)** — index of every document under `docs/`.
- **[CLAUDE.md](CLAUDE.md)** — architecture, environment and working conventions.
- **[docs/DEPLOY.md](docs/DEPLOY.md)** — the atomic deploy (`tools/deploy.sh`), migrations, rollback.
- **[docs/BACKLOG.md](docs/BACKLOG.md)** — what is still to do, and why in that order.
