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
- **Production runs PHP 8.5.9 as of 2026-08-11** — the same patch the capsule
  serves, verified from a live `phpinfo()`, so the two match exactly again. For
  the week before that the capsule was deliberately ahead; the trade that bought
  — continuous 8.5 testing at the cost of not reproducing production exactly —
  has simply expired, in the good direction. Reproducing a live bug locally no
  longer needs `PHP_VERSION=8.4` in `.env` first; the earlier rungs stay
  switchable for bisecting and nothing else. Two differences survive the match
  and are worth knowing before trusting a local repro: ICU is **76.1 in the
  capsule against 72.1 in production** (the base image's Debian is newer than
  the hoster's build), and APCu is **5.1.24 against 5.1.27**.
- **CI runs 8.5 on every job** (`.github/workflows/ci.yml`), for the reason the
  pin makes unavoidable: the resolver is looking at an older PHP than the one
  that will execute the code, so lint and tests on 8.5 are the only thing
  between an 8.5-only fatal and the live site.

## The per-account `php.ini`, and the values we want in it

**A konsoleH PHP version switch does not carry these forward.** The file is

```
/home/httpd/php85-ini/ourlink/php.ini
```

— one directory *per PHP version* (`php53-ini` … `php85-ini` all exist on the
server), so flipping the version in konsoleH silently reinstates that version's
defaults and every tuned value is gone. This has already happened once. Nothing
else in the repository records what those values are supposed to be, which is what
this table is for: after a version flip, diff reality against it.

It is `----r----- root users` — **not writable by the `ourlink` account**. Changing
anything here is a konsoleH console change or a support ticket, not something a
deploy can do.

### Current vs wanted

Measured on production 2026-08-17, straight from the live ini and
`ini_get_all(null, true)`. "Changeable" is the PHP ini access level, which decides
whether we could set it ourselves instead of asking.

| setting | current | wanted | changeable | why |
| --- | --- | --- | --- | --- |
| `opcache.interned_strings_buffer` | `8` | **`32`** | SYSTEM | sits at **89–99% of 8 MiB** (77,504 strings) on an ordinary day. Once full, OPcache stops interning and stores duplicate strings per script. Nothing to do with whether the app fits — that is `memory_consumption`, at 24.6%. **Raise requested via konsoleH 2026-08-18; still reporting `8` on that date** (checked from `/en/sm/phpinfo`, and see § Confirming the raise below) |
| `opcache.revalidate_path` | `0` | **`1`** | ALL | with 0, OPcache never re-resolves a symlinked path, so a release swap keeps executing the previous release. Root cause of [the 2026-08-17 outage](incident-2026-08-17-stale-opcache.md) |
| `apc.ttl` | `0` | **non-zero** | SYSTEM | with 0 a failed allocation expunges the entire segment instead of evicting. Asked for in the same ticket as the `shm_size` raise and **did not land**; the raise did |
| `opcache.memory_consumption` | `128` | keep | SYSTEM | 24.6% used, 0 wasted, no OOM restarts. No pressure |
| `opcache.max_accelerated_files` | `10000` | keep | SYSTEM | prime-adjusted to 16,229 slots, 13.6% used |
| `opcache.validate_timestamps` | `1` | keep | ALL | **load-bearing**: at 0 every deploy needs `pkill -u ourlink -f php`. See [DEPLOY.md](DEPLOY.md) |
| `opcache.revalidate_freq` | `2` | keep | ALL | deploys pick up changed files within seconds |
| `opcache.use_cwd` | `1` | keep | SYSTEM | keeps same-named files in different releases from colliding in the cache |
| `apc.shm_size` | `256M` | keep | SYSTEM | raised from 32M on 2026-08-11; both historical oversized-item offenders now fit |
| `memory_limit` | `512M` | keep | ALL | `public/index.php` also sets it, so a reverted ini does not immediately show |

Two of these are `PHP_INI_ALL`, which means a `.user.ini` in the docroot could set
them without a ticket — `.htaccess` cannot, because `php_value` is a mod_php
directive and this host runs CGI/FastCGI. Prefer the ini anyway for
`revalidate_path`: an ini value applies before `public/index.php` is compiled, and
that file is exactly the one that goes stale.

### Verifying

`/en/sm/cache-status` (maintenance key) reports `internedPercentUsed`,
`internedBufferBytes`, `internedBufferConfiguredMb`, `memoryPercentUsed`,
`keysPercentUsed`, `startTimeUnix`, `uptimeSeconds`, `validateTimestamps` and
`revalidateFreq` live, so a change can be confirmed without SSH.
`tools/smoke-prod.sh` warns on APCu saturation and on any OPcache restart. For the
access level of a setting rather than its value, `ini_get_all(null, true)` over SSH
— note `ini_get_all('Zend OPcache')` returns nothing, and the `$details` argument
must be `true` or there is no `access` field to read.

**Do not read a `SYSTEM` directive from the CLI.** `php -i` over SSH answers for
the CLI SAPI, reading a different ini than the web pools; the per-account file is
`/home/httpd/php85-ini/ourlink/php.ini` and is *version-scoped*, which is the same
trap as expecting a CLI `apcu_clear_cache()` to flush the web server's segment. The
authoritative read is a web request: `/en/sm/phpinfo` (`sch_administrator` only)
prints the Zend OPcache directive table as the pool that served it booted with.

### Confirming the raise

`opcache.interned_strings_buffer` is `PHP_INI_SYSTEM` — read once, when a process
starts. Writing the ini file changes nothing for FastCGI workers already running,
and this host runs at least three pools that recycle independently. So confirming
a raise is a sampling problem, not a lookup:

1. **`/en/sm/phpinfo`**, reloaded several times. The directive table shows what the
   answering pool booted with. One reload showing `8` proves nothing about the
   other two.
2. **`./tools/opcache-sample.sh`** for the same question answered systematically:
   it polls `/sm/cache-status`, groups by `startTimeUnix`, and prints each
   segment's configured buffer beside its age and saturation. It reports the number
   of segments as a **lower bound**, with the odds it missed one.
3. **If it has not landed after a day**, `pkill -u ourlink -f php` over SSH forces
   new processes to read the new ini. Cost is a cold OPcache — seconds of
   recompilation, no downtime. This is the same command [DEPLOY.md](DEPLOY.md)
   names for the `validate_timestamps=0` case.

Do not read `uptimeSeconds` as proof a pool restarted: a deploy's `opcache_reset()`
resets `start_time` too, so a low uptime means the cache was cleared, not that a
process is new with a new ini. And do not measure saturation just after a deploy —
see [caching.md](caching.md) on why the buffer being append-only makes every
post-deploy reading look healthy. `tools/deploy.sh` prints each pool's warm reading
at the moment it resets it, which is the one time something reaches every pool.

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
