# PHP 8.5, and the dependency ceiling behind it

What it takes to run this application on PHP 8.5, what currently stops the
*lockfile* from admitting it, and why that turns out to be the same obstacle as
the one blocking `symfony/framework-bundle`.

Measured 2026-08-05 against the installed tree and Packagist.

## Where things stand

- **The capsule runs PHP 8.5.9, and the whole suite is green on it**: 432 tests
  across unit (109), integration (222), fuzz (18) and smoke (83), plus PHPStan
  clean. `PHP_VERSION=8.5` in `.env` with `APCU_VERSION=5.1.24`; intl, pdo_mysql,
  zip, gd, apcu and OPcache all load. See the Dockerfile for the three
  base-image changes that had to be absorbed first.
- **No first-party code emits a deprecation on 8.5.** Three did and were fixed:
  `curl_close()` (no effect since 8.0) in the smoke helpers and
  `tools/form-regression.php`; `ReflectionMethod`/`ReflectionProperty::setAccessible()`
  (no effect since 8.1) in two integration tests; and
  `PDO::MYSQL_ATTR_INIT_COMMAND`, now written as
  `class_exists('Pdo\Mysql') ? \Pdo\Mysql::ATTR_INIT_COMMAND : \PDO::MYSQL_ATTR_INIT_COMMAND`
  because the replacement class does not exist before 8.4 and the capsule can
  still be switched down to 7.4. **The live `config/autoload/local.php` on each
  machine and on the server carries its own copy of that line** — it is
  gitignored, so only `local.php.dist` and `docker/local.docker.php` could be
  updated here.
- **Four deprecations remain and all are vendor**, in
  `laminas-cache 3.14`'s `AbstractAdapter`: `SplObjectStorage::attach()`,
  `contains()` and `detach()`, all deprecated in 8.5 in favour of the
  `offset*()` forms. They are warnings today and breakage on PHP 9. The fix is
  `laminas-cache 4`, which cannot be installed — see the gate below. This is the
  most concrete cost of staying on laminas-mvc that we have measured.
- **`require.php` is `~8.4.0 || ~8.5.0`** — the application's own code targets
  both, and the lockfile records the same. No package versions moved with it.
- **`config.platform.php` stays at 8.4.24, which is now *below* the runtime
  everywhere.** This is deliberate and is explained below. It is not an
  oversight, it is not a claim about any machine's PHP, and raising it breaks
  `composer install` outright.
- **Production runs PHP 8.5 as of 2026-08-11, and the capsule now matches it
  again.** For the week before that the capsule was deliberately ahead; the
  trade that bought — continuous 8.5 testing at the cost of not reproducing
  production exactly — has simply expired, in the good direction. Reproducing a
  live bug locally no longer needs `PHP_VERSION=8.4` in `.env` first; the
  earlier rungs stay switchable for bisecting and nothing else.
- **CI runs 8.5 on every job** (`.github/workflows/ci.yml`), for the reason the
  pin makes unavoidable: the resolver is looking at an older PHP than the one
  that will execute the code, so lint and tests on 8.5 are the only thing
  between an 8.5-only fatal and the live site.

## The ceiling

Thirteen locked packages exclude PHP 8.5 — twelve installed by `--no-dev`, the
thirteenth being `laminas/laminas-developer-tools`. (This line said *fourteen*
until 2026-08-11. Re-measured with
`Composer\Semver\Semver::satisfies('8.5.9', …)` over every locked `require.php`,
and re-measured the same way against the lock as it stood on 2026-08-05: both
give the identical thirteen names. Nothing moved in the tree; the original
number was simply one too many.) Most are incidental and would lift on a routine
bump. One does not:

    laminas/laminas-mvc  3.8.0  requires php ~8.1.0 || ~8.2.0 || ~8.3.0 || ~8.4.0

3.8.0 is the newest stable release (2024-11-18). Checking the unreleased branches
is what makes this conclusive rather than a matter of waiting:

| laminas-mvc | php | laminas-servicemanager |
|---|---|---|
| 3.8.0 (stable) | `~8.1.0 – ~8.4.0` | `^3.20.0` |
| 3.9.x-dev | `~8.1.0 – ~8.4.0` | `^3.20.0` |
| 4.0.x-dev | `~8.1.0 – ~8.3.0` | `^3.20.0` |

**No version of laminas-mvc — released or in development — admits PHP 8.5.** The
future major admits less than the current stable one. This is consistent with
laminas-mvc being in security-only maintenance until 2028-12-31, and it is the
premise the Symfony strangler was adopted on (see [strangler.md](strangler.md)).

## Why the platform pin stays at 8.4.24, below the runtime

`config.platform` tells composer what to resolve *against*, independently of the
PHP actually running. Pinning it to 8.4.24 has two effects, and the second is the
one that matters:

1. Resolution treats `~8.4.0` constraints as satisfied, so `composer install`
   succeeds.
2. It succeeds **even when the PHP running it is 8.5**, because the pin overrides
   detection.

So the application can run on 8.5 with the pin at 8.4.24, and that is the only
combination that both installs and runs. Raising the pin to 8.5 would make
`composer install` fail against every capped package, on every machine and on
the server, for no runtime benefit.

Worth knowing before testing this yourself, because it produces a convincing
false negative: `composer install` verifies the lock against
`platform-overrides` **recorded in composer.lock**, not against the current
`composer.json`. Editing the pin and running `install --dry-run` therefore
succeeds while proving nothing — composer used the old 8.4.24 from the lock and
never looked at the edit. The claim has to be checked against the constraints
themselves; `Composer\Semver\Semver::satisfies('8.5.9', $constraint)` over
`composer.lock` is the one-liner that does it.

**Production moved to 8.5 on 2026-08-11, and the pin did not move with it.** An
earlier draft of this section said it should; that advice is withdrawn, and the
reasoning is worth keeping because it is the whole value of the pin. Moving it
would force `--ignore-platform-req=php+` on every install, on every machine and
on the server, until laminas-mvc is gone — and that flag is not selective. It
would suppress the upper-bound check for *every* package at once, including the
next dependency that caps PHP for a reason that is not conservatism. What the
pin costs today is honesty in one config value; what raising it costs is the
check itself. Keep the pin, and let CI-on-8.5 be what says the runtime is fine.

Which leaves one thing genuinely unpinned, and it should be stated plainly: a
`~8.4.0` cap is a declaration of what a maintainer has *tested*, not a statement
of incompatibility — but twelve production packages are now running on a PHP
none of their authors claims to support. That is early adoption, and the only
thing that makes it defensible is test coverage on 8.5 rather than the lockfile:
green suites locally, and since 2026-08-11 in CI too.

## The same obstacle blocks FrameworkBundle

This is a correction to what [BACKLOG.md](BACKLOG.md) recorded, and it changes
what the authorization work is worth.

The chain as previously understood put `kokspflanze/bjy-authorize` at the root:
its `composer.json` caps `laminas-cache` at `^2.13.2 || ^3.1.0`, and
`laminas-cache 3.14` pins `psr/cache ^1`, while `framework-bundle → symfony/cache`
needs `psr/cache ^2|^3`. All of that is true. It is also not the binding
constraint:

    symfony/framework-bundle  → symfony/cache → psr/cache ^2|^3
    laminas/laminas-cache 3.14                → psr/cache ^1        ← the pin
    laminas/laminas-cache 4.3                 → psr/cache ^2|^3     ← lifts it
    laminas/laminas-cache 4.3                 → laminas-servicemanager ^4.5
    laminas/laminas-mvc 3.8 (and 3.9, and 4.0) → laminas-servicemanager ^3.20.0

Retiring bjy-authorize removes *one* of two caps on `laminas-cache`. laminas-mvc
still forbids servicemanager 4, so `laminas-cache 4` remains uninstallable,
`psr/cache` stays at 1, and FrameworkBundle stays out. **The gate is laminas-mvc,
not bjy-authorize.**

Two consequences worth being explicit about:

- Retiring bjy-authorize is still worth doing — it removes an abandoned
  dependency that the whole site's authorization runs through, and it is the
  prerequisite for moving to Symfony's Security component. It just does not open
  the bundle gate, so it should not be sequenced as though it does.
- The parked servicemanager-4 migration ("SM4 prep", ~102 factories) is not
  merely deferred: it is *unreachable* while laminas-mvc is installed, because
  laminas-mvc is what pins servicemanager to 3.x.

## What actually lifts all of it

Removing `laminas/laminas-mvc` from the dependency tree — i.e. finishing the
strangler, so every route is served by `App\Kernel` and `LegacyBridge` is
deleted. That single removal simultaneously:

- lifts the PHP ceiling to whatever Symfony supports (8.5 today),
- lifts `laminas-servicemanager` to 4, and with it `laminas-cache` to 4 and
  `psr/cache` to 2/3, making FrameworkBundle installable,
- retires `container-interop/container-interop`, which only laminas-mvc still
  requires.

Until then the two front controllers coexist, PHP 8.5 runs behind a platform pin
that describes neither machine — it describes the ceiling — and FrameworkBundle
waits.
[strangler.md](strangler.md) is the mechanism; `config/symfony/routes.php` is the
progress bar.
