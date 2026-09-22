# Agent guide: who owns what, and the narrowest check that proves it

This is a navigation document. It says which code owns a behaviour, what to read beside
it, and the smallest verification that actually exercises a change of that kind. It does
not repeat the rules — those are in [`../CLAUDE.md`](../CLAUDE.md) — and it is not a
substitute for reading the owner.

**Focused verification** means the narrowest command that exercises the change, not the
largest one available. A check close to the owner is more diagnostic than a full suite,
and a full suite that passes proves nothing specific. When a change crosses rows, verify
each contract it crosses rather than running one broad command over all of them.

Treat the names here as wayfinding. Before relying on an exact flag, key or signature,
read its owner: `tools/ctx def <Symbol>` prints the declaration with exact bounds.

## Task → owner → verification

| Task | Primary owner | Read adjacent | Focused verification |
| --- | --- | --- | --- |
| A page, or its URL | `config/symfony/routes.php` and the controller it names | the route's `RouteAccess`, `templates/`, laminas-exit.md §7 | `ci-local.sh smoke`; `ReservedVerbsTest`; the page in the capsule |
| A JUser or JTranslate page | `module/{JUser,JTranslate}/config/symfony-routes.php` | the host adapters in `src/{JUser,JTranslate}/Host`, `HostMessages`, `HostUrls` | `ci-local.sh smoke integration` |
| Roles, guards, per-row access | `src/Acl`, `src/Authorization` | the `bjyauthorize` config keys the rules are still declared in | `tools/acl-table.php --format=json` diffed against `docs/acl-baseline.json` — **every** authorization change |
| A form, or a field on one | the form class, and its `getInputFilterSpecification()` — which is the whole of what the engine reads | `SionModel\Form\BootstrapFormRenderer`; `SionModel\Form\Validation\InputFilter` | `ci-local.sh fuzz`, contract "no new gaps"; read every line `fuzz-baseline` adds, and every changed line in the five recordings |
| A validator or a filter | `SionModel\{Validator,Filter}\*`, and the `Registry` that names it | every specification that names it — they are addressed by class, so `rg` the class name | `test/Rules/rule-surface.php` via `RuleSurfaceTest`; a changed line is a stored value, a verdict or a message |
| A translated string | JTranslate; `App\Laminas\TranslatorConfigurator` | the route's `_text_domain`; the Twig checklist in [translation.md](translation.md) | render the page in a non-English locale; `jtranslate:export-catalogs` as `-u www-data` |
| Anything a `SionTable` caches | `SionModel\Db\Model\SionCacheTrait`, `SionModel\Cache\CacheFlushQueue` | [caching.md](caching.md); the dependency map and its invariants | `/en/sm/cache-status` over HTTP — APCu is per-SAPI, so a CLI reading measures a different segment |
| An API resource or field | `src/Api`, `src/Controller/Api` | [api-v3.md](api-v3.md); `requiredRole()` | `ci-local.sh integration`; and a migration plus a `juser.api_token_roles` row, or no token can ever carry the role |
| Circulation, borrowers, notices | `App\Books\LibraryPage`, `App\Books\LibraryDelete` | [libraries.md](libraries.md), [library-imports.md](library-imports.md) | `ci-local.sh smoke`; a per-library ACL check for a real row |
| A canonical URL or an hreflang | `App\View\PreferredUrls` | `App\View\SiteChrome::canonicalLinks()`, `templates/layout.html.twig` and `App\Sitemap\SitemapWriter` — all four must agree; [sitemap.md](sitemap.md) | `bin/console sitemap:build --force --url=…`; `CanonicalLinkSmokeTest`; `ci-local.sh smoke-prod` |
| A schema change | `database/db<N>.sql` | the `@phase`, `@kind`, `@tables` and `@verify` headers; [DEPLOY.md](DEPLOY.md) | rehearse through `tools/migrate.sh`, never by piping into `mysql`. `@verify` must select what is still **wrong** and return zero rows on success |
| A database query | `SionModel\Db\Model\SionTable`, the module `*Table` classes | the SQL actually issued — a builder change is invisible in the source of its callers | `./tools/sql-surface.sh --check` against `test/Db/sql-surface.txt`; then `ci-local.sh integration smoke` |
| A service factory | `App\Kernel::container()`, `App\Laminas\ContainerFactory`, `App\Services\Container` | `App\Laminas\ServiceBridge` — nothing may build it on `/_health` | `bin/console cache:clear-config`, then `ci-local.sh integration smoke`. Integration passes against a stale merged config while smoke fails. A **new name** in the container moves `test/Container/container-surface.php`: `composer container-surface-baseline`, and the added lines are the review |
| A console command | the command class; the `console.commands` config key | `bin/console` is the headless seam | run the command; `ci-local.sh integration` |
| The deploy, or its machinery | `tools/deploy.sh`, `tools/prod-deploy.sh` | `test/Deploy/*-test.sh`; [DEPLOY.md](DEPLOY.md) | `ci-local.sh deploy`; `DRY_RUN=1`. Never run a real deploy — give the user the command |
| A dependency | `composer.json` | `tools/dependency-audit.php` (`--laminas` for that scope, `--root=module/SionModel` per submodule); [laminas-exit.md](laminas-exit.md) §8 | `ci-local.sh composer`, and read the `composer.lock` diff package by package. Removing a package silently drops the undeclared ones it pulled in, and a declared-but-unused line may be a version pin |
| Anything in a submodule | the submodule repo | CLAUDE.md's submodule process — one PR per repo, same branch name | the same focused check as the equivalent superproject row, plus `ci-local.sh composer` for the autoload sanity |
| Performance | `tools/perf/`, `tools/port-baseline.php` | [caching.md](caching.md) | time it over HTTP. OPcache revalidates every 2s, so a rapid A/B measures OPcache; and the CLI has its own APCu segment |
| A production failure | `tools/fetch-exceptions.sh` | [exception-reporting.md](exception-reporting.md) — the store is under `shared/` | the fingerprint list; production logs are local time and DB stamps are UTC |

## Choosing an effective check

- **Pure logic**: the focused unit test, plus `ci-local.sh qa`.
- **Anything a visitor sees**: `smoke`. It is the only suite that exercises a real request
  through a real kernel against a real database.
- **Anything a form accepts**: `fuzz`, plus the recording that covers the layer you changed
  — markup, engine, element or rule. They are recordings, not assertions somebody wrote, so
  a changed line is an answer that moved; regenerating one to make a test pass turns it into
  a record of whatever the code now happens to do.
- **Anything authorization touches**: the ACL diff, always, even when nothing looks like a
  rule change. A dynamic resource that has no entry 403s everyone.
- **Anything cached**: measure over HTTP, warm and cold. Identical query counts in both
  captures mean nothing caches it.
- **Anything the deploy runs**: `ci-local.sh deploy` and `smoke-prod`. Until 2026-08-19 the
  only thing that ever executed `smoke-prod.sh` was a production deploy.

Report what actually ran, including what was skipped and why. `ci-local.sh` executes
roughly twice the assertions CI does, and CI cannot get a database; neither run is a
superset of the other, so "everything passed" and "everything that could run passed" are
different claims and only one of them is usually true.
