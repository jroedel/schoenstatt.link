schoenstatt.link
================

A database of the Schoenstatt Movement: its shrines and wayside shrines, the
associations and institutes that make it up, the people who hold roles in them, a
catalogue of the literature, the lending libraries that circulate it, and the
music. Served in five languages — English, Spanish, German, Portuguese and French
— at <https://schoenstatt.link>.

It is a live production site holding real data about real people, maintained by a
small number of volunteers. This repository is public because how that data is
handled should be something anyone can read rather than something they have to
take on trust.

- **What it does with personal data** — <https://schoenstatt.link/en/privacy>, and
  the [Personal data](#personal-data) section below.
- **Found a security problem?** [SECURITY.md](SECURITY.md). Please do not test
  against the live site.
- **Want to change something?** [CONTRIBUTING.md](CONTRIBUTING.md).

Technically: PHP 8.5 against MariaDB 10.11, on a hand-wired Symfony HTTP kernel
(`App\Kernel`, `src/`) that serves every page and the `/api/v3` API. There is no
framework bundle and, since 2026-09-22, no `laminas/*` package of any kind — the
form stack, the database layer, the container and the ACL are all in this
repository. [docs/laminas-exit.md](docs/laminas-exit.md) is the account of that.

Getting started
---------------

The whole environment is a Docker "time capsule" that matches production:

```bash
./config.sh                               # .dist configs, composer install, database seed
docker compose up -d
```

| what | where |
| --- | --- |
| The app | <http://localhost:8080> (redirects to `/en/`) |
| Outgoing mail (Mailpit) | <http://localhost:8025> |
| MariaDB | host port 33306 — `schoenstatt`/`schoenstatt`, database `ourlink_db1` |

**The database this brings up is invented, not real.** `config.sh` seeds
`database/dumps/` — which `docker compose` mounts as the database's init
directory — from `database/ci/base-schema.sql` (the application's DDL, no rows in
it) and `database/ci/ci-seed.sql` (a small set of made-up associations, people,
libraries and books). That is the same pair the GitHub integration job builds its
database from on every push. If you already have a production export in
`database/dumps/`, `config.sh` leaves it alone and says so.

The unit and integration suites pass against the invented corpus. The smoke and
fuzz suites assume the real catalogue and will not.
[CONTRIBUTING.md](CONTRIBUTING.md) says which check to run when.

The PHP version is switchable via `PHP_VERSION`/`APCU_VERSION` in `.env` and a
rebuild; check what is live with `docker compose exec -T app php -v`. Headless
work goes through `bin/console` (symfony/console):
`docker compose exec -T app php bin/console list`.

Verifying a change
------------------

```bash
php composer.phar unit          # no HTTP, no app, no vendor/ — safe to run freely
php composer.phar integration   # container + database, no running app
php composer.phar fuzz          # hostile input through every form; contract "no new gaps"
php composer.phar smoke         # HTTP characterization tests against the running capsule
php composer.phar test          # all of the above

php composer.phar stan          # PHPStan; contract "no new errors" (phpstan-baseline.neon)
./tools/ci-local.sh             # everything CI runs, plus smoke, fuzz and smoke-prod
```

All of these shell into the capsule; the host PHP lacks the extensions PHPUnit and
phpcs need. Run the **smoke suite one process at a time** — never in parallel.

`./tools/ci-local.sh` is the stricter check. GitHub CI has run against a real
MariaDB since 2026-09-23, so the old "CI has no database" caveat is retired — but
it still runs only the unit and integration suites, because smoke, fuzz and
`smoke-prod.sh` need a *running application*. That is 3,768 tests of 4,487; in
assertions it is 8,410 of roughly 89,000, because the smoke suite asserts heavily.

Authorization is diffed, not tested — a rule that quietly stops matching makes a
page work for *more* people and nothing fails:

```bash
docker compose exec -T app php tools/acl-table.php --format=json   # diff against docs/acl-baseline.json
```

Personal data
-------------

The database holds names, e-mail addresses, telephone numbers, countries,
languages and birthdays (never birth years) for people who hold roles in the
Movement, plus borrower records for the lending libraries. Who may see what is
decided per row rather than per page, by the ACL in `src/Acl` and
`src/Authorization`; [docs/acl-baseline.json](docs/acl-baseline.json) is the full
recorded answer, diffed on every authorization change.

The published policy is <https://schoenstatt.link/en/privacy>. It dates from
2016-03-15 and predates the GDPR, and in several places it describes something the
code does not do. Rather than have the documentation pretend otherwise, the
differences are filed as open issues — #252 through #258. Publishing the gap is
part of the point of publishing the repository.

Nothing in this repository contains anyone's personal data, and a test keeps it
that way: `test/Integration/NoTrackedFileNamesAPersonTest.php` reads every person
in the database and asserts that no tracked file names one.
`database/ci/ci-seed.sql` is invented throughout, deliberately.

Layout
------

| where | what |
| --- | --- |
| `src/` (`App\`) | the Symfony kernel, controllers, `/api/v3`, console commands; PHPStan level 8 |
| `templates/` | Twig templates; `layout.html.twig` is the shared chrome |
| `config/symfony/routes.php` | every route the site serves |
| `module/{Application,Books,Schoenstatt}` | the application's models, forms and module config |
| `module/{SionModel,JUser,JTranslate}` | shared libraries: the form stack, the db layer, users, translation |
| `config/autoload/` | `*.global.php` committed, `*.local.php` machine-specific (from `.dist` via `config.sh`) |
| `database/` | incremental migrations, applied through the `sch_migration` ledger |
| `database/ci/` | the recorded schema and the invented seed — what CI and a fresh capsule build from |

Documentation
-------------

- **[CONTRIBUTING.md](CONTRIBUTING.md)** — setting up, what to run, and the conventions.
- **[SECURITY.md](SECURITY.md)** — reporting a vulnerability.
- **[docs/README.md](docs/README.md)** — index of every document under `docs/`.
- **[CLAUDE.md](CLAUDE.md)** — architecture, environment and working conventions.
- **[docs/DEPLOY.md](docs/DEPLOY.md)** — the atomic deploy (`tools/deploy.sh`), migrations, rollback.
- **[docs/BACKLOG.md](docs/BACKLOG.md)** — what is still to do, and why in that order.

Licence
-------

BSD-3-Clause — see **[LICENSE.txt](LICENSE.txt)**. Two modules under `module/`
carry their own MIT licence and the front-end assets under `public/` carry
theirs; **[THIRD-PARTY.md](THIRD-PARTY.md)** records which, and where.
