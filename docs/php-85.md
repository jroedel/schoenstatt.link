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
- **`config.platform.php` stays at production's version (8.4.24).** This is
  deliberate and is explained below. It is not an oversight, and raising it
  breaks `composer install` outright.
- **The capsule is therefore ahead of production**, which still serves 8.4.24.
  That is a deliberate trade: it is what tests 8.5 continuously, and the cost is
  that the capsule no longer reproduces production exactly. Switching back is
  `PHP_VERSION=8.4` in `.env` plus a rebuild, and it is the first thing to do
  before concluding anything from a local reproduction of a live bug.

## The ceiling

Fourteen installed packages cap PHP at `~8.4.0`. Most are incidental and would
lift on a routine bump. One does not:

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

## Why the platform pin stays at production's version

`config.platform` tells composer what to resolve *against*, independently of the
PHP actually running. Pinning it to 8.4.24 has two effects, and the second is the
one that matters:

1. Resolution treats `~8.4.0` constraints as satisfied, so `composer install`
   succeeds.
2. It succeeds **even when the PHP running it is 8.5**, because the pin overrides
   detection.

So the application can run on 8.5 with the pin at 8.4.24, and that is the only
combination that both installs and runs. Raising the pin to 8.5 would make
`composer install` fail against all fourteen packages, on every machine and on
the server, for no runtime benefit.

The honest reading: those `~8.4.0` caps are conservative upper bounds declaring
what each maintainer has *tested*, not statements of incompatibility. Running
8.5 against them is early adoption, and the thing that makes it defensible is
test coverage on 8.5 rather than the lockfile.

**When production moves to 8.5**, move the pin with it and expect
`composer install` to need `--ignore-platform-req=php+` (the `+` suffix ignores
only upper-bound violations) until laminas-mvc is gone. Prefer not to reach that
state: it disables a real safety check for every package at once.

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
that describes production rather than the runtime, and FrameworkBundle waits.
[strangler.md](strangler.md) is the mechanism; `config/symfony/routes.php` is the
progress bar.
