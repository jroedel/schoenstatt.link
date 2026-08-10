# Translation migration

What JTranslate is for now that its scope is being cut, and what Symfony takes over.

**Revised 2026-08-09.** The first version of this document argued for retiring
JTranslate outright. That was written without knowing that a second production
application — **patres** (schoenstatt-fathers.link) — depends on it. It does, so
the module has a future and the question is not whether to keep it but what to
keep in it. The retirement argument is preserved below only where it still holds:
as an argument about *scope*, not about existence.

Two facts found after that draft changed the plan as much as patres did. First,
JTranslate's `1.0.x` line — the one patres runs — already contained fixes
`modernization` lacked, including the guard that accounts for half of a laminas
page's time; the merge base between the two is 2020-05-10, so this repo was the
branch that had regressed. Second, the phrase index that looked like a cache
problem was a 9.9 MiB array that had never been cached at all, because it stored
6,860 phrases whose average length is 1,494 characters.

[JTranslate's own README](../module/JTranslate/README.md) and
[UPGRADE.md](../module/JTranslate/UPGRADE.md) are now the authoritative
description of the library, including the list of what 3.0 removes. This file is
about the application's side of the migration.

Mechanism context: [strangler.md](strangler.md) for the two front controllers,
[view-scripts.md](view-scripts.md) for what a `.phtml` → Twig port costs,
[caching.md](caching.md) for the APCu layer.

## Verdict

**Keep JTranslate, cut its scope hard.** The library owns something Symfony does
not provide and cannot: a phrase database, runtime discovery of phrases that
never appear in source, and an editing workflow for translators. What it should
stop owning is everything else. Of the code that existed when this was written,
roughly a third is either reimplemented better by PHP's own `intl` extension, or
is not about translation at all — `NowMessenger`, the countries and flag helpers,
a 440-line hardcoded CLDR locale table. The 2.0 release breaks the couplings that
made the library unusable on its own; 3.0 removes the rest. The README's
"What is leaving in 3.0" table is the running list.

The "shared library across projects" premise deserves one caution rather than
dismissal: it is implemented by having several applications write to one
`trans_phrases` table discriminated by a `project_name` string, which couples
those applications through a database rather than through code. That is worth
revisiting on its own merits, separately from anything here.

**Keep the format.** The precompiled-PHP-array decision was right and should
survive the migration untouched. It is not merely fast, it is unbeatable, and
`symfony/translation` independently arrived at the same answer: its own compiled
catalogue cache is a `return [...]` PHP file. Migrating *toward* Symfony is
therefore not a departure from the original design — it is that design with the
framework's implementation, a real cache directory, and a console command.

**The UI is the last thing to move, not the thing to keep.** The original
proposal was to keep JTranslate's UI and replace everything underneath it. The
opposite is closer to right. The admin UI is 458 lines and
three `.phtml` templates — the smallest, least valuable, most framework-entangled
piece in the module. It is built on `AbstractActionController`, laminas-form,
`FlashMessenger`, a BjyAuthorize route guard and a reflection-based abstract
factory: five things on the retirement list. Keeping it means keeping laminas-mvc
alive for three admin screens. What is genuinely worth keeping is the **schema
and the workflow** — runtime discovery of missing phrases, per-project scoping,
cross-domain translation reuse, key-locale auto-insert, and compilation to fast
arrays. That is the accumulated behaviour; the UI is a thin CRUD form over it and
this repo already has the parts to rebuild it (`src/Form/BootstrapFormRenderer`,
Twig, and `association-edit` as a ported-form precedent).

So the UI is not the thing to *keep* — it is the thing to move **last**. Leave it
running on laminas as one of the final laminas-only routes while everything
beneath it migrates, then rewrite it in Twig. That ordering is also the safest:
the UI is admin-only and low-traffic, so it is the one part where being last
costs nothing.

## The evidence

Measured in the capsule, PHP 8.5.9, OPcache on. All figures reproducible.

**This section describes JTranslate as it was before 2.0**, and is kept in the
present tense as it was written, because it is the record of how the problems
were found and what they cost. Everything in "The format is not where the time
goes" and "The permission problem is a symptom" is fixed as of
laminas-jtranslate #6, #7 and #9. What has *not* changed is the first
subsection — the benchmark is the reason the format survives — and the last one,
the artifacts-in-the-source-tree problem, which is Stage 2 and still open.

### The precompiled-array hypothesis is confirmed, and by a wide margin

`module/Schoenstatt/language/en_US.lang.php` — 698 messages, 64 KB, 300 iterations:

| loader | OPcache on | OPcache off |
| --- | --- | --- |
| `include` php array | **0.1 µs** | 352 µs |
| laminas `PhpArray` loader (adds `TextDomain`) | 4.3 µs | 340 µs |
| `json_decode` | 360 µs | 337 µs |
| `unserialize` | 76 µs | 83 µs |
| `apcu_fetch` | 63 µs | 71 µs |
| gettext `.mo` via laminas loader | 3,297 µs | 3,540 µs |

OPcache interns a literal array as an immutable shared-memory array, so
`return [...]` hands back a pointer: no parse, no copy, no allocation. 550× faster
than APCu, 3,000× faster than JSON, 29,000× faster than gettext. Nothing can beat
it, because every alternative must materialize the array in request memory. All 25
catalogs together cost 2.9 ms to compile cold and ~2.5 µs warm.

The caveat matters for the historical record: **the entire advantage is contingent
on OPcache**, which production only got on 2026-08-04. Before that this design was
the slowest file-based option and 5× worse than APCu. The design was right; the
environment was wrong for five years.

### The format is not where the time goes

| what | measured |
| --- | --- |
| `include` all 25 catalogs | 2.9 ms |
| `TranslationsTable` constructor's `getPhraseKeysFromDb()` (6,860 rows) | 97 ms |
| `getTranslations(true)` — 7,766 phrases + full user table, run at **every** `EVENT_FINISH` | 221 ms |

TTFB on `/en/books`, which falls through to laminas, five requests each:

| configuration | TTFB |
| --- | --- |
| as shipped | **440 ms** |
| `finishUp` listener disabled | 210 ms |
| also with the eager constructor query stubbed | **162 ms** |

**About 63% of every laminas-served page is JTranslate**, and none of it is the
part the design optimizes. Three compounding causes:

1. `writeMissingPhrasesToDb()` calls `getTranslations(true)` unconditionally
   *before* checking whether anything is missing, so nearly every page pays 221 ms
   for nothing.
2. It then calls `removeDependentCacheItems('phrase')` unconditionally.
   `onFinishWriteCache` is attached at priority 100 and writes `phrase-keys` to
   APCu; `finishUp` is attached at priority −1 and deletes it. **The persistent
   cache is written and destroyed within every single request** and can never
   produce a hit on a laminas-served request.
3. The constructor queries the database before the router has matched, so every
   request pays it regardless of route.

This also explains the asymmetry already visible in production-shaped traffic:
Symfony-served routes never fire `EVENT_FINISH`, so nothing evicts the cache
there. `/en/dictionary` and `/en/music` return in **37–52 ms** against 440 ms for
anything reaching laminas.

### The permission problem is a symptom, not the disease

Four distinct defects, one of them live silent data loss:

1. **Write failures are silent.** `writePhpTranslationArrays()` discards the
   return value of `mkdir()`, `file_put_contents()` and `chmod()`. Right now, for
   `www-data` in this capsule, `module/Schoenstatt/language/en_US.lang.php` is
   `is_writable() === false`, and so are `language/` and `language/default`. A
   translator saves a phrase, the DB `UPDATE` succeeds, the controller flashes
   *"Translations successfully updated."*, the `.lang.php` is never rewritten, and
   nothing is logged. The success message is a lie.
2. **Writes are not atomic.** `file_put_contents()` overwrites in place a file
   another process may be mid-`include`. A torn read is a parse error inside a
   page render — intermittent blank pages that look exactly like a permission
   problem and are not.
3. **Mode 0775 on data files.** Generated catalogs are chmod'd executable, and
   `@chmod()` silently fails when the process does not own the file. The `@chmod`
   after `mkdir(0775)` exists only because umask defeats the mode; a setgid parent
   would have been the fix.
4. **OPcache is never invalidated after a write.** `validate_timestamps=On` /
   `revalidate_freq=2` covers it today. The moment anyone sets
   `validate_timestamps=0` — a normal production tuning step — newly written
   translations become permanently invisible.

The root cause is structural: **these are generated build artifacts stored inside
the deployed source tree.** Two of the three symptoms that used to follow from it
have since been dealt with; the third has not, and it is the one this section is
really about.

**Fixed — the inconsistent tracking.** `module/Application/language/*` and
`module/Books/*` used to be tracked in git while `module/Schoenstatt/language/`
was ignored, so the site's largest catalog — 65 KB en_US, 52 KB es_ES — was
untracked, never deployed by phploy, and existed only as a file the web server
wrote to itself. Since 2026-08-09 nothing is tracked: `/module/*/language/` joins
`/language/` in `.gitignore`, and the JTranslate, JUser and SionModel submodules
carry the same rule in their own repositories. Untracking was gated on proving the
files were regenerable — two `pt_BR` translations turned out to exist *only* in a
committed catalog and were moved into the database by `database/db6.9.sql` first.

**Fixed — nothing regenerated them.** `bin/console jtranslate:export-catalogs`
rebuilds every catalog from the phrase table, so a fresh checkout, a `git clean`
or a lost server directory is now one command rather than an undocumented
recovery. Before it existed the only trigger was a human clicking Save in the
admin GUI, and the site would sit silently English-only until someone did.

**Not fixed — the write target is still inside deployed source.** The web server
still has to write into `module/*/language/` and `language/`, which is where the
permission failures below come from and where a deploy running as a different user
collides with it.

No amount of `chmod` fixes this. Anything the web server must write has to live
outside the source tree, and it has to be rebuildable from the database by a
command. That single change dissolves the entire permission category.

### PHP 9 blockers, both on the every-request path

- `TranslationsTable.php:568` — `new \DateTime(null, …)`. Deprecated since 8.1,
  **TypeError in PHP 9**. Reached from `finishUp()`, so PHP 9 takes down every
  page, not one admin screen.
- `TranslationsTable.php:183` — `$row['locale']` is NULL for phrases with no
  translation rows (it is a `LEFT JOIN`), giving `$return[$id][null]`. Deprecated
  in 8.5, an `Error` in 9. Also every request. There is a `@todo` on line 165
  about exactly this.

## What JTranslate actually is

2,835 lines across 21 `src/` files, plus 157 lines of config and three `.phtml`
templates. Categorised by what each part is worth:

| what | lines | share | verdict |
| --- | --- | --- | --- |
| `TranslationsTable::getLocaleNames()` — a hardcoded CLDR locale-name map | 439 | 15% | **delete.** `\Locale::getDisplayName()` does this natively, correctly, and localized into the viewer's language. The table is circa-2012 CLDR. |
| Countries / flags / language names — `CountriesInfo`, `data/countries.json`, three view helpers, three factories | 419 | 15% | **delete or relocate.** Not translation. `symfony/intl` or plain `\Locale` covers the names; flags are CSS or emoji. This got parked here. |
| `NowMessenger` — controller plugin + view helper, a FlashMessenger clone that renders in the same request | 454 | 16% | **delete.** Not translation either. Under Twig this is a template variable. |
| Framework glue — `Module.php`, `LazyControllerFactory`, `TranslatorEventListener`, four service factories | 340 | 12% | **delete.** Symfony DI autowiring and one translator decorator replace all of it. `LazyControllerFactory` predates `ReflectionBasedAbstractFactory`. |
| Admin UI — controller, `EditPhraseForm`, `DeletePhraseForm`, three `.phtml` | 458 | 16% | **rewrite in Twig, last.** Behaviour worth keeping; code worth discarding. |
| Translation domain — `TranslationsTable` minus the locale table | 725 | 26% | **port, shrunk hard.** Of this, ~90 lines (`writePhpTranslationArrays` + `exportArray` + `exportValue`) are a hand-rolled `PhpFileDumper` that Symfony already ships. |

Roughly **58% is deletion**, and the part with durable value is the ~300 lines of
repository-plus-recorder that survives inside that last row.

Two facts about the data shape that constrain the design:

- **Text domains** (`trans_phrases`, project `Schoenstatt`): Books 5,922 ·
  Schoenstatt 700 · `default` 78 · SionModel 45 · Application 34 · ZfcUser 29 ·
  JUser 23 · Bible 19 · JTranslate 10. Total 6,860. `Bible` was removed from the
  application on 2026-08-05 and its 19 phrases are still there.
- **Locales** (`trans_translations`): en_US 7,762 · es_ES 2,072 · de_DE 1,532 ·
  pt_BR 1,529 · it_IT 17. Total 12,912 rows.

> **Both counts above are obsolete, and the conclusion drawn from them was wrong.**
> Measured 2026-08-10: 5,088 of the Books rows were exactly 2,000 characters long
> and held **two** distinct strings. `trans_phrases.phrase` was `varchar(2000)`, so
> any longer phrase was silently truncated on insert and could never be matched
> again — every render of those two blog posts inserted another row. JTranslate's
> migrations 003 and 004 widen the column, add
> `UNIQUE (project, text_domain, phrase_hash)` and merge the duplicates. **Applied to
> production 2026-08-10**, where they took project `Schoenstatt` from 6,895 phrases to
> **1,787** (the capsule lands on 1,744 after `db7.2.sql` also removes the blog's 39),
> averaging 43 characters — 75 KB of text in total. So "Books alone is 86% of the
> phrases, and they are data, not source literals" was an artifact of the defect, not a
> property of the corpus. The content-versus-labels tension below is real but far
> smaller than it looked, which materially shrinks this whole migration.

Books alone is 86% of the phrases, and they are **data, not source literals** —
book titles and dictionary entries passed through `translate()`. This is the single
most important thing to understand before touching the design, and it is what the
next section turns on.

## What Symfony gives you, and what it does not

Gives you, already written and maintained:

- `Loader\PhpFileLoader` and `Dumper\PhpFileDumper` — the precompiled-array format,
  as a supported component feature.
- `MessageCatalogue` / `TranslatorBag` — domains, per-locale fallback chains,
  metadata. Correct fallback semantics instead of hand-rolled ones.
- `Translator` with a **compiled catalogue cache**: one
  `catalogue.<locale>.<hash>.php` per locale covering *all* domains, written
  through `ConfigCache`. Fewer includes than today's 25 files, and invalidation is
  the framework's job.
- ICU `MessageFormat`, plural rules, `TranslatableMessage`, and the Twig `trans`
  filter. JTranslate has **no plural support at all** — none of the 25 catalogs
  carries a `plural_forms` rule — so this is capability gained, not preserved. (No
  regression risk either: `translatePlural()` has **zero** call sites.)
- `Provider\ProviderInterface` — `write(TranslatorBagInterface)`,
  `read(array $domains, array $locales): TranslatorBag`, `delete(...)`. This is
  exactly the shape of `trans_phrases` / `trans_translations` access, and it is
  the extension point a database provider should implement.

Does **not** give you, and these must be kept and owned:

- **The phrase database.** There is no DB-backed translation source in the
  component. The providers that exist are for remote SaaS (Loco, Crowdin, …). A
  `DatabaseProvider` is ours to write.
- **Runtime discovery of missing phrases.** `translation:extract` scans *source
  code* for `trans()` calls. It cannot find the 5,922 Books phrases, because they
  are not in the source — they arrive as data at runtime. This is the strongest
  argument in defence of the original design and the reason the miss-recorder is
  genuinely necessary rather than a workaround. Symfony's translator fires no
  missing-translation event, so this becomes a decorator on `TranslatorInterface`.
- **The editing workflow.** The GUI, the per-project scoping, the key-locale
  auto-insert, the cross-domain translation reuse.
- **Atomic writes.** `Dumper\FileDumper` uses plain `file_put_contents`, so it has
  defect #2 above. Symfony's *cache* layer writes atomically via
  `Filesystem::dumpFile()`, which is one more reason to route compilation through
  the catalogue cache rather than the dumper.

## Target architecture

```
DB: trans_phrases + trans_translations   (schema unchanged)
  │
  ├─ App\Translation\DatabaseProvider          implements Symfony ProviderInterface
  │    read(domains, locales)  -> TranslatorBag        (the only DB read path)
  │    write(TranslatorBag)    -> UPDATE/INSERT        (the GUI's write path)
  │
  ▼
Symfony\Component\Translation\Translator
  cacheDir: data/cache/translations/
  └─ catalogue.es_ES.<hash>.php   one immutable PHP array per locale, all domains
       ▲                          written atomically, read at ~0.1 µs
       │
       └── rebuilt by: bin/console translations:warm   (deploy + post-edit)
           purged by:  bin/console translations:purge  (or on GUI save)
  │
  ├─────────────────────────────► Twig  {{ 'phrase'|trans }}   (Symfony routes)
  │
  └─ App\Translation\LaminasTranslatorAdapter
       implements Laminas\I18n\Translator\TranslatorInterface
       └─ injected into Laminas\Mvc\I18n\Translator
            └────────────────────► 428 translate() calls in 116 .phtml files,
                                   laminas-form, laminas-validator — untouched

App\Translation\MissingPhraseRecorder      decorates TranslatorInterface
  └─ records misses to an append-only file; drained by
     bin/console translations:collect (cron), never in the request path
```

### The seam that makes this safe

```php
// vendor/laminas/laminas-mvc-i18n/src/Translator.php
public function __construct(protected I18nTranslatorInterface $translator)
```

`Laminas\Mvc\I18n\Translator` accepts **any** implementation of
`Laminas\I18n\Translator\TranslatorInterface`. That interface is two methods,
`translate()` and `translatePlural()`. So a ~60-line adapter over Symfony's
translator can be injected into `MvcTranslator`, and every laminas consumer — 428
`translate()` calls across 116 `.phtml` files, plus laminas-form and
laminas-validator — keeps working with no edit. Both front controllers then read
one catalogue, from one source of truth, in one format.

This is what makes the migration incremental and reversible instead of a
flag-day. It is also why the UI can be left for last: it goes on translating
through the adapter like every other laminas page.

`src/Laminas/TranslatorConfigurator` is what gets replaced. It reaches the
*concrete* `Laminas\I18n\Translator\Translator` to call `enableEventManager()`,
`setFallbackLocale()` and `addTranslationFilePattern()` — all of which disappear
with the file patterns. Its docblock should be read before deleting it: it records
a production bug from 2026-08-08 where every translated string on ported pages
silently reverted to English in all four non-English languages. That is the exact
failure mode this migration risks repeating, so read on.

## Stages

Each stage is independently reviewable, independently deployable, and leaves the
app bootable.

**Stage 1 — stop the bleeding. DONE**, laminas-jtranslate #6, merged 2026-08-09.
The early return in `writeMissingPhrasesToDb()`, `DateTime('now')`, the LEFT-JOIN
null-locale restructure, atomic catalog writes that raise instead of failing
silently, `opcache_invalidate()`, 0664 instead of 0775. Measured on `/en/books`:
**440 ms → 225 ms**.

**Stage 1b — 2.0 foundations. DONE**, #7 and #9, merged 2026-08-09. Not in the
original plan; it became worth doing on its own once patres turned out to depend
on the library. `PhraseCache` over PSR-16 replaces `SionCacheTrait`, the phrase
index became a hash set (9.9 MiB → ~270 KiB, and cached for the first time),
`finishUp(MvcEvent)` became `flush(?string)`, the model's SionModel and JUser
imports became this library's own interfaces, and the coding standard went from
1,116 errors to zero. **440 → 225 → ~175 ms.** The remaining scope cuts are
tracked in the library's own README rather than here.

**Stage 2 — move the artifacts out of the source tree.** Still JTranslate, and
still the real permission fix.
- Compile to `data/cache/translations/` (writable by design; `data/cache/twig`
  is the existing precedent, including its unwritable-fallback behaviour).
- ~~Add `translations:export` … so the catalogs become rebuildable from the DB by
  anything — deploy hook, cron, a developer.~~ **Done**: `bin/console
  jtranslate:export-catalogs`. A `translations:purge` counterpart is still open.
- Delete `module/*/language/` and `/language/` as write targets; register the new
  path with the translator.
- Proves itself with: a full rebuild from an empty cache directory, plus a golden
  diff of rendered pages before and after.

**Stage 3 — move into this repo, on Symfony.**
- `App\Translation\DatabaseProvider`, `App\Translation\LaminasTranslatorAdapter`,
  `App\Translation\MissingPhraseRecorder`, under `src/`, at PHPStan level 8, in
  phpcs scope, with unit and integration tests.
- Add `symfony/translation` as a root require and align it to ^7.4; add
  `symfony/config` + `symfony/filesystem` for the catalogue cache.
- Swap `MvcTranslator` to the adapter; delete `TranslatorConfigurator`.
- `\Locale::getDisplayName()` replaces the 440-line locale table. (`LanguageName`
  already made this switch in 2.0; `TranslationsTable::getLocaleNames()` has not.)
- Retire `NowMessenger`, `CountriesInfo`, the flag/country/language helpers, and
  `LazyControllerFactory` — or relocate the country data if something still needs
  it (check `Flag` usages first).
- Proves itself with: the ACL diff (`tools/acl-table.php`), the full test suite,
  and a rendered-page golden diff across all five locales on both front
  controllers.

**Stage 4 — the UI, last.** Port the three admin screens to Twig with
`BootstrapFormRenderer`, following `association-edit`. Then the JTranslate
submodule is empty and the repo reference can be removed.

## Traps

- **Text-domain semantics differ, and getting it wrong is silent.** Laminas
  defaults to the domain `default` and falls back by *locale* only; JTranslate's
  dispatch listener sets the domain to the controller's root namespace. Symfony
  defaults to `messages` and has no per-controller magic. The domain *names* in
  the database (`Books`, `Schoenstatt`, `default`, `ZfcUser`, …) must map through
  unchanged, and `src/Twig/LaminasExtension::translate()` already encodes a
  two-step domain fallback — page domain, then `default` — that has to be
  preserved deliberately. This is precisely how the 2026-08-08 production bug
  happened: nothing throws, every string just quietly becomes its English source.
  The mitigation is a rendered-page golden diff across all five locales, not a
  unit test.
- **The miss-recorder must consult the catalogue, not compare strings.** Every
  `en_US` row stores the phrase as its own translation, so "result equals input"
  is true for thousands of legitimate hits. Use `MessageCatalogue::has()`.
  Laminas's `EVENT_MISSING_TRANSLATION` is exact today for the same reason.
- **The catalogue cache will not self-invalidate on a DB write.** `ConfigCache`
  checks *file* resource freshness, and a DB edit touches no file. Purge
  explicitly on save and re-warm in the same request, so the translator keeps the
  instant feedback the original design was built for. Do not bump `cacheVary`
  instead: it is hashed into the filename, so old files accumulate.
- **`symfony/translation` is currently only a transitive dependency** of
  `nesbot/carbon`, pinned at v6.4.42 while the rest of the Symfony stack is
  7.3/7.4. "Already installed" is true but incidental — it must become an explicit
  root require at ^7.4.
- **`data/cache/translations` must degrade, not fatal, when unwritable.** Copy
  `TwigFactory`'s fallback behaviour. A read-only cache directory should cost
  speed, not availability.
- **Books is 86% of the corpus and it is data.** Any design that assumes phrases
  come from source code — including `translation:extract` — is wrong for this
  application.

## Dependency ledger

Added: `symfony/translation` ^7.4 (root require; php >=8.1,
deprecation-contracts, polyfill-mbstring, translation-contracts — very light),
`symfony/config`, `symfony/filesystem`.

Removed with the module: `laminas/laminas-code` (no longer referenced after the
`exportArray()` rewrite, and never actually installed — so
`writePhpTranslationArrays()` used to fatal with class-not-found for anyone who
reached it), `cakephp/utility` (zero references), `mledoze/countries` (data
vendored into `data/countries.json`), and JTranslate's `php: >=5.3.3` constraint.
Eight factories currently type-hint `Interop\Container\ContainerInterface`, which
resolves only through laminas-servicemanager's `class_alias` shim; they go too.

`laminas/laminas-i18n` stays for now — the adapter implements its interface, and
`\IntlDateFormatter` call sites are unaffected. It can go when the last `.phtml`
does.

## What this does not change

The database schema. The precompiled-array format. The workflow: a page renders,
an unknown phrase is recorded, a translator edits it in a web GUI, the catalog
recompiles, the next page render reads it at 0.1 µs. Every one of those was a good
decision. What changes is that the artifacts move out of the source tree, the
compilation becomes a command instead of a side effect, the per-request database
tax goes away, and the framework maintaining the machinery becomes one that is
still alive.
