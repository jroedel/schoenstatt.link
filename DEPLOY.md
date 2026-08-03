# Deploying schoenstatt.link

Deployment is phploy over **SFTP as a restricted deploy account**
(port-22 jail, no exec — its port-222 shell was intentionally revoked
2026-08-03, so a phploy vulnerability could move files but never run
anything). Server-side commands still happen, but as explicit `ssh`
invocations from `pre-deploy[]`/`post-deploy[]` hooks running under
*your own* shell account (port 222). Everything is plain `post-deploy[]`
(never `post-deploy-remote[]`) so the exact execution order is under our
control — mixing the two makes the order unpredictable.

**Never run `phploy --submodules`/`-m`** — its directory purge recursively
deletes freshly-uploaded trees (it took out `module/JUser/src` on
2026-08-02; see BACKLOG.md "Deploy ops").

## The deploy

```bash
git checkout master && git pull origin master
git submodule update --init --recursive

php8.0 phploy.phar
```

The phploy run does, in order (`phploy.ini.dist` is the committed
template: `config.sh` copies it to the gitignored `phploy.ini`, where the
TODOs get real values):

1. `pre-deploy[]`: ssh — back up the server's `public/.htaccess` to
   `data/htaccess-backups/htaccess-<timestamp>`. The file is tracked and
   clobbered on every deploy (since 2026-08-03); the backup is the escape
   hatch, outside the docroot, ~2.6 KB per deploy.
2. Diff-uploads the superproject against the server's `.revision` (SFTP).
3. `purge[] = "data/config/"` — empties the merged-config/module-map cache
   (production runs with config caching on; this is why config changes
   take effect).
4. `post-deploy[]` hooks:
   1. `bash tools/deploy-submodules.sh` — rsync `--delete` of the three
      submodule trees over the shell account (clean-tree guard built in;
      no-ops fast when the pointers didn't move).
   2. ssh — `rm -f data/config/module-*-cache.*.php && php composer.phar
      install --no-dev --no-interaction --optimize-autoloader`. The rm
      re-clears the config cache: a visitor may have re-cached the merged
      config from the half-deployed tree since step 3.
   3. wget `/sm/clear-persistent-cache?key=…` — SionModel's endpoint
      flushes the APCu storage adapter, i.e. `apcu_clear_cache()`: the
      whole web APCu segment. No process kills needed.
   4. wget `/en/associations/do-work?key=…` — post-deploy data
      maintenance. Needs the fully-deployed site.
   5. `git tag -f deploy/$(date +%Y%m%d-%H%M)` — local tag recording
      exactly what went live (`git tag -l 'deploy/*'` answers "what's
      deployed?"). Never pushed.
   6. `SMOKE_PROD_CACHE_KEY=<api key> bash tools/smoke-prod.sh` — the
      scripted smoke checks (next section). A failure ends the deploy
      loudly with a non-zero exit. It runs after the server-side steps,
      so a passing run means the *fully* deployed site is healthy — no
      expected-failure window.

## Running the server-side steps by hand

If a hook fails mid-run (or you deploy from a machine without the shell
key), finish in an interactive SSH session (port 222) in this order:
sync submodules (`tools/deploy-submodules.sh <user@host> <port>`, or
tar-over-SFTP + extract), then in the app dir
`rm -f data/config/module-*-cache.*.php && php composer.phar install
--no-dev --no-interaction --optimize-autoloader`, then re-run the two
wget endpoints and `bash tools/smoke-prod.sh` locally.

## Server facts worth remembering

- SSH port **22 is a restricted SFTP jail** (no exec — rsync/scp fail with
  "exec request failed on channel 0"). The deploy account's full shell on
  port 222 was **revoked 2026-08-03**; phploy connects over 22, SFTP only.
  Server commands run as `ssh` hooks (and `deploy-submodules.sh`) under
  your own shell account on port 222 — two different identities by design.
- phploy needs `php8.0` locally (newer CLIs lack mbstring).
- `phploy.ini` + `.phploy` hold credentials — never committed. `config.sh`
  seeds them from the committed templates (`phploy.ini.dist`,
  `.phploy.dist`); the hooks above live in `phploy.ini` under
  `[production]`.
- Web PHP is FastCGI with a per-account php.ini at
  `/home/httpd/php83-ini/ourlink/php.ini` (per PHP version: the 7.4-era
  file was under `php74-ini/`). php.ini changes are the ONE case that
  needs `pkill -u ourlink -f php` (workers re-read ini on respawn);
  deploys never do.

## Smoke checks after deploying

- `bash tools/smoke-prod.sh` (the last post-deploy hook, also runnable any
  time) covers everything scriptable: homepage, both 404 flavors, the
  sitemap, and a random sample of *cold* sitemap pages (the fatal-200
  regression class — fixed URLs can't catch it). It also guards the
  transport layer: response compression (br/gzip) and the static-asset
  Cache-Control policies from the now-tracked `public/.htaccess` fail the
  deploy on regression; HTTP/2 only WARNs (hoster-provided, not ours to
  fix). Non-zero exit on any failure.
- With `SMOKE_PROD_CACHE_KEY` set (any `sion_model.api_keys` value — the
  hook reuses the clear-persistent-cache key), it also polls
  `/en/sm/cache-status` and WARNs — without failing — when the APCu
  segment is ≥80% full or has ever expunged. Until the production
  `apc.shm_size` raise lands, expect this to be the early-warning signal
  that the 32M default is saturating (first live reading 2026-08-03:
  2 expunges within hours of the PHP 8.3 flip).
- Still manual: a sign-in round trip with a real email.

## Reading production exceptions

Every exception that reaches `dispatch.error` or `render.error` is logged to
`data/logs/exceptions_<YYYY-MM>.log` as it always was, and additionally
**recorded** under `data/exceptions/<fingerprint>/` — one directory per
distinct failure, not per occurrence:

| file | what it is |
| --- | --- |
| `meta.json` | counters and notification bookkeeping |
| `first.txt` | the first occurrence, in full — the original context |
| `last.txt` | the most recent occurrence — is it still happening? |
| `recent/1..3.txt` | the last three, so you can see whether it is always the same input |

A "distinct failure" is keyed on the exception class chain, the matched
**route name**, and the enclosing function of the root cause. Deliberately
not the request URI: URI-keyed fingerprints would make every request to a
variable URL look like a brand new bug. Not a line number either, so
editing a file does not re-report bugs you never touched.

**The first occurrence of each distinct failure is emailed** to
`webmaster@schoenstatt.link`, once. After that it stays quiet until the
count crosses 10, 100 or 1000 — a rare annoyance turning into an outage is
worth hearing about. `BjyAuthorize\Exception\UnAuthorizedException` is on
the never-mail list: an unauthenticated visitor touching a guarded route
raises it through the very same event, and mailing that would bury every
real failure. It is still recorded.

```bash
bash tools/fetch-exceptions.sh              # mirror + summary table
less data/exceptions-prod/<fp>/first.txt    # the write-up

bash tools/clear-exceptions.sh --yes <fp>   # fixed it? clear it
bash tools/clear-exceptions.sh --yes        # clear everything
bash tools/clear-exceptions.sh --yes --older-than 30
```

Both scripts use the shell account on port 222 (port 22 is the SFTP jail,
no exec). The fetch is `rsync` **without** `--delete`, so the local mirror
accumulates history even after the server side is cleared. Clearing a
fingerprint re-arms its notification, so it doubles as "I fixed this, tell
me if it comes back."

Two columns in the summary table are worth knowing. `NOTIFIED` shows
`FAILED` when the mail transport refused the send: the recorder marks a
fingerprint notified *before* it attempts delivery, because laminas-mail
hard-codes a 30 second connection timeout and a dead mail host would
otherwise cost every subsequent visitor 30 seconds on an already-failed
request. The consequence is that a send lost to an SMTP outage is not
retried — that column is how you find out. A `.overflow` file appears if
the 500-fingerprint ceiling ever forced the recorder to drop something.

What is captured is deliberately conservative, because this data gets
rsynced off the server and mailed: client IP truncated to /24, user **id**
only (never the address), and request parameter *names* with every value
replaced by a redaction marker carrying only its length. Enough to tell an
empty field from a filled one without putting a password in an email. The
`capture` block in `module/SionModel/config/module.config.php` documents
the alternatives.

Two caveats:

- **A deploy must clear `data/config/`** for newly registered services to
  be seen. The existing post-deploy hooks already do this twice; it is
  called out because the symptom is confusing — `Module.php` picks up
  changes immediately (it is a class file) while the merged service map
  stays stale, so you get `Unable to resolve service ...` for a service
  that is plainly registered in the config you are looking at.
- **Failures before the container exists** — a broken merged config, a
  module that will not load — are recorded but *cannot* be emailed: the
  recipients live in the very configuration that failed to build. They land
  in the store and in `data/logs/bootstrap-fatal.log`.

PHP fatals are covered too. Memory exhaustion, a hit `max_execution_time`
or a `TypeError` escaping every catch block never reach the MVC error
events at all — the visitor gets a blank HTTP 200 and historically nothing
was logged anywhere. `public/index.php` installs a shutdown handler that
records and mails those like any other failure, attributed to the file and
line where PHP actually died.

## Rollback

`php8.0 phploy.phar --rollback` reverts the superproject files (SFTP, so
this still works unchanged) — but the hooks don't re-run, so follow the
by-hand list above: check out the matching older superproject commit
locally so the submodule pointers roll back too, re-sync the submodule
trees, and run `php composer.phar install --no-dev` in your SSH session
to reinstate the older lock. A clobbered `.htaccess` can be restored from
`data/htaccess-backups/`. The DB migrations so far are
backward-compatible.
