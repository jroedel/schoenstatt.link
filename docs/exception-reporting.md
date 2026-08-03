# Exception reporting

When something fails in production, you get an email. Once.

That is the whole feature. The rest of this document is how it works, what it
deliberately does not do, and what to do when the email arrives.

## When an email arrives

The subject looks like this:

```
[schoenstatt.link] NEW ServiceNotCreatedException on books/library
```

`NEW` means this particular failure has never been seen before. The body is the
full write-up: the request, the redacted request state, every exception in the
chain, and every stack trace. Usually that is enough to act on without going
near the server.

If you need the surrounding history — is it still happening, is it the same
input every time — pull the store down:

```bash
bash tools/fetch-exceptions.sh
```

That prints a summary table and mirrors everything into `data/exceptions-prod/`.
Then read the write-ups for the fingerprint named in the email:

```bash
less data/exceptions-prod/<fp>/first.txt   # the first occurrence, in full
less data/exceptions-prod/<fp>/last.txt    # the most recent one
ls   data/exceptions-prod/<fp>/recent/     # the last three
```

When you have fixed it, clear the record:

```bash
bash tools/clear-exceptions.sh --yes <fp>
```

Clearing is what re-arms notification, so this doubles as *"I fixed this, tell
me if it comes back."* If it recurs, the next occurrence is treated as brand new
and you get another email.

## What counts as one failure

A "distinct failure" — a fingerprint — is eight hex characters derived from
three things:

1. the exception class chain,
2. the **matched route name**,
3. the enclosing function of the **root cause**.

Both of the non-obvious choices there are load-bearing, and changing either one
reintroduces a specific problem:

**The route name, never the request URI.** A URI-keyed fingerprint would mint a
fresh "first occurrence" — and therefore a fresh email — on every request to a
variable URL. Any exception reachable through a parameterised path would become
a mail flood, and an attacker could trigger one deliberately.

**The enclosing function, never a line number.** Line numbers shift whenever
anything above them is edited, so a line-keyed fingerprint would re-report bugs
nobody had touched. PHP records a called function together with its call site,
so frame 0 of the root cause's trace names the method the `throw` sits in, which
survives unrelated edits to the same file.

The root cause — the deepest `getPrevious()` — is used rather than the outermost
wrapper, because the outermost is usually just whatever the service container
rethrew it as.

## The store

One directory per fingerprint under `data/exceptions/`:

| path | what it holds |
| --- | --- |
| `<fp>/meta.json` | counters and notification bookkeeping |
| `<fp>/first.txt` | the first occurrence, in full — the original context |
| `<fp>/last.txt` | the most recent occurrence — is it still happening? |
| `<fp>/recent/1..3.txt` | the last three, newest first — same input every time? |
| `.emails/<YmdH>` | hourly send counter |
| `.emails/breaker` | epoch until which sending is suspended |
| `.overflow` | fingerprints dropped because the store was at capacity |

Deliberately **not** one file per occurrence: a failure inside a loop would write
thousands of them. The counter in `meta.json` carries the volume; first, last and
a short ring answer the questions a human actually asks.

`meta.json` looks like this:

```json
{
  "fingerprint": "ebcaf845",
  "class": "Laminas\\ServiceManager\\Exception\\ServiceNotCreatedException",
  "chain": ["Laminas\\...\\ServiceNotCreatedException", "InvalidArgumentException"],
  "message": "Service with name \"Books\\Form\\SearchForm\" could not be created...",
  "route": "books/library",
  "controller": "Books\\Controller\\LibrariesController",
  "action": "library",
  "origin": "Books\\Model\\LibraryTable::getIsbnsInLibrary",
  "thrown_at": "module/Books/src/Model/LibraryTable.php:377",
  "first_seen": "2026-08-03T17:09:15+00:00",
  "last_seen": "2026-08-03T17:41:02+00:00",
  "count": 27,
  "notified_at": "2026-08-03T17:09:16+00:00",
  "notified_counts": [1, 10],
  "revision": "fd1de0d",
  "php": "8.3.33"
}
```

`revision` is whatever phploy left in `.revision`, so you know which deployed
code produced the failure.

## When you get emailed, and when you don't

- **The first occurrence** of a fingerprint. Always (subject to the rules below).
- **Volume spikes**, when the count reaches 10, 100 or 1000. A rare annoyance
  turning into an outage is worth hearing about; the subject reads `SPIKE 100`.
- **Never again otherwise.** Occurrences 2 through 9 bump a counter silently.

Every threshold that has been crossed is recorded, not just the highest one.
Marking only the highest would leave a lower threshold permanently unmarked, and
it would then re-fire on every single subsequent occurrence — an endless mail
loop.

Three things suppress email entirely:

- **Ignored classes.** `BjyAuthorize\Exception\UnAuthorizedException` ships on
  this list. `dispatch.error` is not a bug channel: an unauthenticated visitor
  touching a guarded route raises that exception through the very same event.
  One run of this repo's smoke suite produces **17** distinct such fingerprints,
  so mailing them would bury every real failure on day one. They are still
  logged and still recorded — just never mailed.
- **The hourly ceiling** (20 by default). A blast shield, not a normal condition.
- **The circuit breaker**, for 15 minutes after the mail transport fails.

Nothing about this suppresses *recording*. Everything that reaches
`dispatch.error` or `render.error` still lands in `data/logs/exceptions_*.log`
exactly as it did before this feature existed, in the same format, so the logs
going back to 2020 stay greppable with the same eyes.

## PHP fatals

Memory exhaustion, a hit `max_execution_time`, a `TypeError` escaping every
catch block — none of these reach the MVC error events. Historically the visitor
got a truncated HTTP 200 with a blank body and the developer got nothing at all:
no log line, no error page, no record. This site has been bitten by that before.

`public/index.php` installs a shutdown handler before any configuration is
loaded, and `SionModel\Module::onBootstrap()` upgrades it to the fully
configured pipeline once the container exists. Fatals are recorded and emailed
like anything else, attributed to the file and line where PHP actually died —
not to the shutdown handler, which would otherwise collapse every fatal on a
route into a single fingerprint.

The handler holds back 256 KB of memory and releases it at shutdown, so it can
still build a record after an out-of-memory fatal.

## Configuration

Defaults live in `module/SionModel/config/module.config.php` under
`sion_model.exception_notifications`, and each key is documented there. This app
overrides only the recipient, in `config/autoload/sionmodel.global.php`.

| key | default | what it does |
| --- | --- | --- |
| `enabled` | `true` | master switch for notification |
| `store_path` | `data/exceptions` | relative to the app root |
| `to` | `[]` (this app: `webmaster@schoenstatt.link`) | empty means record without mailing |
| `from` | `null` | falls back to the SMTP account |
| `subject_prefix` | `null` | falls back to the request host in brackets |
| `ignore_classes` | `UnAuthorizedException` | exact names, or a namespace prefix ending `*` |
| `spike_counts` | `[10, 100, 1000]` | counts that re-notify |
| `max_fingerprints` | `500` | ceiling, so a storm cannot fill the disk |
| `max_emails_per_hour` | `20` | ceiling, so a storm cannot flood the inbox |
| `max_write_up_bytes` | `262144` | truncation ceiling per write-up |
| `ring_size` | `3` | recent write-ups kept besides first and last |
| `breaker_seconds` | `900` | how long to stop trying after a transport failure |
| `capture.ip` | `truncate` | `truncate` \| `full` \| `none` |
| `capture.identity` | `id` | `id` \| `none` |
| `capture.params` | `keys` | `keys` \| `full` \| `none` |

The recipient is a role address rather than an individual so reporting survives
a handover. Make sure it lands in an inbox somebody reads.

## What gets captured, and what does not

The store is rsynced off the production server and its contents are emailed, so
"more debugging detail" and "personal data spill" are the same decision here.
The defaults are the least data that still lets you reproduce a failure:

- **Client address truncated** — IPv4 to `/24`, IPv6 to `/48`. Enough to
  distinguish "one visitor keeps hitting this" from "everyone hits this",
  without storing a personal identifier.
- **User id only**, never the email address, and resolved at failure time rather
  than at construction.
- **Parameter names kept, values redacted** to a marker carrying only the
  length: `password = <redacted>:7`. Enough to tell an empty field from a filled
  one, or a 4-character search term from a 400-character paste. The login form
  posts a password, and an email is not a safe place for it.

Nested parameters are flattened to dotted paths so a write-up stays a readable
flat list. Absolute server paths are stripped from messages and traces, both to
shorten them and to avoid disclosing the hosting layout by email.

`capture.params = full` exists but think before using it — it will put
credentials in your inbox.

## The scripts

Both talk to the server over **port 222**. Port 22 is a restricted SFTP jail
with no exec; see [DEPLOY.md](DEPLOY.md) for the two-identity arrangement.

### `tools/fetch-exceptions.sh [remote] [ssh_port]`

Mirrors the server's store into `data/exceptions-prod/` and prints a summary
table sorted newest-first. `rsync` **without** `--delete`, deliberately: clearing
the server must never wipe your local archive, so the mirror accumulates a
durable history of what production has seen.

`--summary-only` skips the transfer and just summarises the existing mirror,
which works offline.

A missing `data/exceptions/` on the server is reported and exits 0 — that is the
healthy case, not an error.

### `tools/clear-exceptions.sh [--yes] [--no-archive] [--older-than DAYS] [fp...]`

Deletes fingerprints from the **server**. With no fingerprints named it targets
all of them; `--older-than` filters by `last_seen` and composes with an explicit
list.

It refuses to delete anything without `--yes`, printing what it *would* remove
and exiting non-zero. It archives via `fetch-exceptions.sh` first unless told
`--no-archive`, and aborts if that archive fails. Every fingerprint, whether
typed by you or derived from `--older-than`, is validated against
`^[0-9a-f]{8}$` before it is ever interpolated into a remote command.

It lists the server directly over SSH rather than trusting the local mirror,
since the mirror never has entries removed from it and would drift.

## Troubleshooting

**No email, but the failure is in the store.** Check `notified_counts` in
`meta.json`. `[]` with a class on the ignore list is working as designed. `[1]`
means the email was already sent for that fingerprint — occurrences 2–9 are
silent by design.

**`NOTIFIED` shows `FAILED` in the summary table.** The transport refused the
send and `notify_error` in `meta.json` says why. This is not retried — see the
limitation below.

**`Unable to resolve service ...` for a service that is plainly registered.**
Stale merged-config cache. `rm -f data/config/module-*-cache.*.php`. The deploy
hooks already do this twice; the symptom is confusing because `Module.php` picks
up changes immediately, being a class file, while the merged service map does
not.

**Nothing recorded at all.** The store directory must be writable by the web
user. Check `data/exceptions/` ownership, and `data/logs/bootstrap-fatal.log`
for a failure too early to record properly.

**A `.overflow` file appeared.** The 500-fingerprint ceiling was reached and new
fingerprints are being dropped. Clear the store.

### Trying it locally

The capsule's Mailpit catches everything, so the whole path is verifiable
locally — that is why the notifier reads the same top-level `smtp_options` block
that `docker/local.docker.php` points at Mailpit.

```bash
docker compose up -d
curl -s -o /dev/null http://localhost:8080/en/users   # a guarded route
ls data/exceptions/                                    # recorded
open http://localhost:8025                             # and NOT mailed — deny-listed
```

Unit coverage for the pieces worth pinning lives in `test/Unit`
(`php composer.phar unit`): that the fingerprint follows the route and not the
URI, that a crossed spike threshold never re-fires, that `first.txt` survives
while `last.txt` is replaced, that notification bookkeeping is not reset by a new
occurrence, and that no submitted value survives redaction.

## Limitations worth knowing

**Mail lost to an SMTP outage is not retried.** The notifier marks a fingerprint
notified *before* it attempts delivery. That ordering is deliberate:
laminas-mail hard-codes a 30 second connection timeout, so a mail host that has
stopped answering would otherwise cost every subsequent visitor 30 seconds on a
request that has already failed. Marking first means at most one request pays
that price. The cost is that a genuinely failed send is gone — the `FAILED`
column and `notify_error` are how you find out.

**Failures before the container exists cannot be emailed.** A broken merged
config or a module that will not load is recorded in the store and appended to
`data/logs/bootstrap-fatal.log`, but there is no way to mail it: the recipients
live in the very configuration that failed to build.

**The fingerprint ceiling can overshoot.** The capacity check is not locked, so
concurrent workers each recording a different brand-new fingerprint at the
boundary can both pass it. Overshoot is bounded by the number of workers — a
handful of directories — against a ceiling whose job is stopping unbounded
growth rather than being exact. Locking it would serialise every first-ever
occurrence behind a global lock on the request path.

**Authorization noise consumes store slots.** Ignored classes are recorded even
though they are never mailed, so guarded routes do occupy fingerprints. This is
bounded rather than unbounded: fingerprints are route-keyed and the app has a
finite set of guarded routes (~17 appear in a smoke run), so they cannot crowd
out the remaining slots for real bugs.

**The first occurrence pays SMTP latency inside the failing request.** Sending
happens inline. Subsequent occurrences only bump a counter, so this is rare, but
it is real.

## Where the code lives

All of it is in the `SionModel` submodule, under `module/SionModel/src/Error/`,
so the other Sion sites get the same tooling. The app-side pieces are the
handler registration in `public/index.php`, the recipient in
`config/autoload/sionmodel.global.php`, and the two scripts in `tools/`.

| class | responsibility |
| --- | --- |
| `Fingerprinter` | reduces a `Throwable` to a stable key |
| `Redactor` | the privacy rules |
| `RequestContext` | collects request state, already redacted |
| `ExceptionRecord` | one occurrence as plain data; renders the write-up |
| `ExceptionStore` | the on-disk store; never throws |
| `NotificationGate` | decides whether an occurrence deserves an email |
| `ExceptionNotifier` | builds and sends it |
| `ErrorListener` | the `dispatch.error` / `render.error` listener |
| `FatalErrorHandler` | the shutdown and uncaught-exception handler |
| `ErrorHandling` | orchestrates log → record → notify |

`Fingerprinter`, `Redactor`, `ExceptionRecord`, `RecordOutcome`,
`ExceptionStore` and `NotificationGate` are dependency-free on purpose: the unit
suite loads no autoloader and requires each class directly, so those tests stay
valid even while `vendor/` is mid-migration.
