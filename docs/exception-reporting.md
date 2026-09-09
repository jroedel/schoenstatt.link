# Exception reporting

When something fails in production, you get an email. Once.

## How a failure reaches the inbox

`public/index.php` calls `SionModel\Error\FatalErrorHandler::registerEarly(dirname(__DIR__))`
before any configuration is loaded: a shutdown and uncaught-exception handler that can record
to `data/exceptions/` under default settings with no container at all. `App\Kernel::handle()`
then calls `FatalErrorHandler::upgrade()` with a **lazy** resolver that, only once something
has already failed, pulls `SionModel\Service\ErrorHandling` and
`SionModel\Error\RequestContext` out of the laminas `ServiceManager`
(`App\Laminas\ServiceBridge`) — the configured store, the request context and the email. If
the resolver itself throws, the handler takes the container-free path rather than losing the
record; the fallback of last resort is one line in `data/logs/bootstrap-fatal.log`.

Nothing listens on `kernel.exception`, by design: an uncaught throwable rethrows out of
`HttpKernel` to the handler, so there is exactly one error path. Two things therefore never
enter the store: a **404**, which is a rendered response from the catch-all
`App\Controller\NotFoundController`, and a **guard refusal**, which
`App\Http\AuthorizationListener` sets as a response before any controller runs.
`BjyAuthorize\Exception\UnAuthorizedException` is still thrown by Symfony-side code that
refuses inside a controller (`App\Books\LibraryPage`, `App\Sion\EntityCreate`,
`App\Http\MaintenanceKey`), which is why it stays on the ignore list below.

PHP fatals — memory exhaustion, a hit `max_execution_time`, a `TypeError` escaping every
catch — are recorded and emailed like anything else, attributed to where PHP died rather than
to the shutdown handler, which holds back 256 KB so it can still record after an
out-of-memory fatal. `SionModel\Error\ErrorListener` is laminas-host code for the other Sion
sites; no module `onBootstrap()` runs under `App\Kernel`, so it plays no part here.

## When an email arrives

```
[schoenstatt.link] NEW ServiceNotCreatedException on books/library
```

`NEW` means this fingerprint has never been seen. The body is the full write-up: request,
redacted request state, every exception in the chain, every stack trace. For history — is it
still happening, is it the same input every time — pull the store down:

```bash
bash tools/fetch-exceptions.sh                 # mirrors into data/exceptions-prod/, prints a summary
less data/exceptions-prod/<fp>/first.txt       # the first occurrence, in full
less data/exceptions-prod/<fp>/last.txt        # the most recent one
ls   data/exceptions-prod/<fp>/recent/         # the last three
bash tools/clear-exceptions.sh --yes <fp>      # fixed: clear the record, re-arm notification
```

Clearing re-arms notification — "I fixed this, tell me if it comes back" — so the next
occurrence is treated as brand new.

## What counts as one failure

A fingerprint is eight hex characters derived from the exception class chain, the **matched
route name**, and the enclosing function of the **root cause** (the deepest `getPrevious()`,
not the container's rethrow). Both choices are load-bearing: a URI-keyed fingerprint would
mint a fresh "first occurrence" — and an email — per request to a variable URL, a flood an
attacker could trigger deliberately; a line-keyed one would re-report bugs nobody touched
whenever anything above them was edited (frame 0 names the method the `throw` sits in).

## The store

One directory per fingerprint under `data/exceptions/` (`store_path`, relative to the app
root):

| path | holds |
| --- | --- |
| `<fp>/meta.json` | counters and notification bookkeeping |
| `<fp>/first.txt` | the first occurrence, in full |
| `<fp>/last.txt` | the most recent occurrence |
| `<fp>/recent/1..3.txt` | the last three, newest first |
| `.emails/<YmdH>` | hourly send counter |
| `.emails/breaker` | epoch until which sending is suspended |
| `.overflow` | fingerprints dropped because the store was at capacity |

Deliberately **not** one file per occurrence — a failure inside a loop would write thousands.
`meta.json` carries `class`, `chain`, `message`, `route`, `controller`, `action`, `origin`,
`thrown_at`, `first_seen`, `last_seen`, `count`, `notified_at`, `notified_counts`,
`revision` (the release's `.revision`, written by `tools/deploy.sh`) and `php`.

## When you are emailed, and when not

- **The first occurrence** of a fingerprint.
- **Spikes**, when the count reaches 10, 100 or 1000 (`spike_counts`); subject `SPIKE 100`.
  Every crossed threshold is recorded, not just the highest — otherwise a lower one would
  re-fire on every subsequent occurrence forever.
- **Never otherwise.** Occurrences 2–9 bump a counter silently.

Three things suppress mail but never recording: **ignored classes**
(`UnAuthorizedException` — ordinary traffic, ~17 distinct fingerprints per smoke run), the
**hourly ceiling** (`max_emails_per_hour`, 20), and the **circuit breaker** (`breaker_seconds`,
900, after the transport fails). Everything still lands in `data/logs/exceptions_*.log` in
the same format as always.

## Configuration

Defaults in `module/SionModel/config/module.config.php` under
`sion_model.exception_notifications`, each key documented there. This app overrides only
`to` and `subject_prefix`, in `config/autoload/sionmodel.global.php`.

| key | default | what it does |
| --- | --- | --- |
| `enabled` | `true` | master switch for notification |
| `store_path` | `data/exceptions` | relative to the app root |
| `to` | `[]` (here: `webmaster@schoenstatt.link`) | empty means record without mailing |
| `from` | `null` | falls back to the SMTP account |
| `subject_prefix` | `null` (here: `[schoenstatt.link]`) | falls back to the request host |
| `ignore_classes` | `['BjyAuthorize\Exception\UnAuthorizedException']` | exact names, or a prefix ending `*` |
| `spike_counts` | `[10, 100, 1000]` | counts that re-notify |
| `max_fingerprints` | `500` | ceiling, so a storm cannot fill the disk |
| `max_emails_per_hour` | `20` | ceiling, so a storm cannot flood the inbox |
| `max_write_up_bytes` | `262144` | truncation per write-up |
| `ring_size` | `3` | recent write-ups kept besides first and last |
| `breaker_seconds` | `900` | pause after a transport failure |
| `capture.ip` | `truncate` | `truncate` \| `full` \| `none` |
| `capture.identity` | `id` | `id` \| `none` |
| `capture.params` | `keys` | `keys` \| `full` \| `none` |

The recipient is a role address so reporting survives a handover; make sure someone reads it.
The notifier sends through the same top-level `smtp_options` block as everything else, which
is why the capsule's Mailpit (http://localhost:8025) receives it.

## Capture and privacy

The store is rsynced off the server and its contents are emailed, so "more debugging detail"
and "personal data spill" are one decision. Defaults are the least data that still reproduces
a failure: client address truncated (IPv4 `/24`, IPv6 `/48`); user id only, never the
address; parameter names kept and values redacted to a length marker
(`password = <redacted>:7`, nested parameters flattened to dotted paths); absolute server
paths stripped from messages and traces. `capture.params = full` exists; it will put
credentials in your inbox.

## The scripts

Both use **port 222** (port 22 is a restricted SFTP jail with no exec — see
[DEPLOY.md](DEPLOY.md)); default remote `ourlink@dedi2934.your-server.de`.

**`tools/fetch-exceptions.sh [remote] [ssh_port] [--summary-only]`** mirrors the server's
store into `data/exceptions-prod/` and prints a summary sorted newest-first. `rsync`
**without** `--delete`, deliberately: clearing the server must never wipe the local archive.
`--summary-only` works offline. A missing `data/exceptions/` on the server exits 0 — that is
the healthy case.

**`tools/clear-exceptions.sh [--yes] [--no-archive] [--older-than DAYS] [fp...]`** deletes
fingerprints from the **server**. No fingerprints named means all of them; `--older-than`
filters by `last_seen` and composes with a list. Without `--yes` it prints what it would
remove and exits non-zero. It archives via `fetch-exceptions.sh` first unless `--no-archive`,
aborting if the archive fails; every fingerprint is validated against `^[0-9a-f]{8}$` before
reaching a remote command; and it lists the server over SSH rather than trusting the mirror,
which never has entries removed.

## Troubleshooting

- **No email, but the failure is in the store.** Check `notified_counts` in `meta.json`.
  `[]` with an ignored class is by design; `[1]` means the email went out and 2–9 are silent.
- **`NOTIFIED` shows `FAILED`.** The transport refused; `notify_error` says why. Not retried
  (below).
- **`Unable to resolve service …` for a registered service.** Stale merged-config cache:
  `rm -f data/config/module-*-cache.*.php`.
- **Nothing recorded at all.** `data/exceptions/` must be writable by the web user; check
  `data/logs/bootstrap-fatal.log` for a failure too early to record properly.
- **A `.overflow` file appeared.** The 500-fingerprint ceiling was reached; clear the store.

Locally: `docker compose up -d`, hit a route that throws, `ls data/exceptions/`, check Mailpit.
`test/Unit` (`php composer.phar unit`) pins fingerprinting, spike bookkeeping and redaction.

## Limitations

- **Mail lost to an SMTP outage is not retried.** A fingerprint is marked notified *before*
  delivery, so a dead mail host costs one request the 30-second socket timeout
  (`SionModel\Service\MailTransportFactory`) rather than every one; `FAILED` and
  `notify_error` are how you find out.
- **Failures before the container exists cannot be emailed** — the recipients live in the
  configuration that failed to build; see `data/logs/bootstrap-fatal.log`.
- **The fingerprint ceiling can overshoot** by the number of concurrent workers (unlocked on
  purpose); **ignored classes consume store slots**, bounded by the set of guarded routes;
  **the first occurrence pays SMTP latency inside the failing request.**

## Where the code lives

`module/SionModel/src/Error/` (plus `SionModel\Service\ErrorHandling`), shared with the other
Sion sites. App-side: `public/index.php`, the `upgrade()` call in `src/Kernel.php`, the
recipient in `config/autoload/sionmodel.global.php`, the two scripts.

| class | responsibility |
| --- | --- |
| `Fingerprinter` | reduces a `Throwable` to a stable key |
| `Redactor` | the privacy rules |
| `RequestContext` | collects request state, already redacted |
| `ExceptionRecord` | one occurrence as plain data; renders the write-up |
| `ExceptionStore` | the on-disk store; never throws |
| `NotificationGate` | decides whether an occurrence deserves an email |
| `ExceptionNotifier` | builds and sends it |
| `FatalErrorHandler` | the shutdown and uncaught-exception handler |
| `Service\ErrorHandling` | orchestrates log → record → notify |
| `ErrorListener` | laminas `dispatch.error` / `render.error` listener (other hosts only) |

`Fingerprinter`, `Redactor`, `ExceptionRecord`, `RecordOutcome`, `ExceptionStore` and
`NotificationGate` are dependency-free on purpose: the unit suite requires them directly.
