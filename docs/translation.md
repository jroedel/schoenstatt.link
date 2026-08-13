# Translation

How the site gets translated, what JTranslate is doing underneath, and what to
check before you write a Twig template that shows text to a human.

**Supersedes [translation-migration.md](translation-migration.md)**, which is now
history: it argued about JTranslate's *future scope* — what 3.0 removes, whether
`symfony/translation` takes the rest — at a moment when the mechanism itself was
changing weekly. The mechanism has settled. This file describes it as it is.

The library's own [README](../module/JTranslate/README.md) and
[UPGRADE.md](../module/JTranslate/UPGRADE.md) remain the reference for JTranslate
as a *library*: its configuration keys, its commands, its 3.0 removal list. This
file is about the application — how these five locales actually reach a page, and
which of the ways to break that have already been found the hard way.

Related: [strangler.md](strangler.md) for the two front controllers,
[view-scripts.md](view-scripts.md) for `.phtml` → Twig mechanics,
[caching.md](caching.md) for the APCu layer, [api-v3.md](api-v3.md) for the phrase
API that automated translators use.

## What JTranslate is for

Five locales, and **the source text is the key**. There is no `home.title` style
message id anywhere: the English sentence in the template *is* the lookup key, and
a translation is a row keyed by its hash.

That choice is what the library exists to support, because it makes two things
possible that a message-id scheme does not:

- **Discovery.** Nobody maintains a list of translatable strings. A string becomes
  translatable by being rendered once, missing, and getting written down. Strings
  that never appear in source at all — a navigation label assembled from config, a
  validator message template — are found the same way as any other.
- **Translation without a developer.** A translator signs in, sees the phrases with
  no translation in their language, and fills them in. No file, no commit, no
  deploy.

What it is *not*: it is not a message catalogue format, not an ICU implementation,
not a pluralization engine. Dates, numbers and currency go through `intl` (the
`short_date`, `long_date`, `day_format` Twig functions); pluralization is not
solved here and no phrase should rely on it.

`trans_phrases` is **shared across four applications**, discriminated by a
`project` column (`Schoenstatt` here, set by `jtranslate.project_name`). Every
query in every migration in `database/` is scoped to it. See
[the note on that table being private per project](#the-table-is-shared-but-not-public)
below — it is a real constraint, not bookkeeping.

## The loop

A phrase makes one circuit:

```
  template renders                   translator fills it in            deploy
  translate('Wayside shrines')       (GUI, or POST /api/v3/…)          rebuilds
          │                                    │                       catalogs
          ▼                                    ▼                          │
  Translator::translate()  ──miss──▶  trans_phrases row  ──▶  trans_translations  ──┐
          │                           (added_on, origin_route)                      │
          │                                                                         │
          └──── hit ◀── module/<Domain>/language/<locale>.lang.php ◀─────────────────┘
                        (or language/<Domain>/<locale>.lang.php)
```

Four things in that diagram are worth stating outright, because each has been
misunderstood in a way that cost a day:

**1. `TranslatorEventListener` is the only door into the table.** A miss fires
`Translator::EVENT_MISSING_TRANSLATION`; the listener catches it and calls
`TranslationsTable::reportMissingTranslation()`. Nothing else ever inserts a
phrase. So a lookup that does not happen is a phrase nobody can ever translate,
and a lookup that happens *by accident* is a junk row — which is the entire theme
of the failures listed at the bottom of this file.

**2. The listener filters by locale, and the key locale is in the list.** It is
built from `TranslationsTable::getLocales(true)` — `es_ES, de_DE, pt_BR, it_IT`
plus the key locale `en_US`. The `true` is load-bearing: without it an English
page view discovers nothing, so a string that appears only on English pages stays
unknown forever, and "a deleted phrase comes back the next time a page renders it"
holds only for visitors browsing in one of the other four languages. Both wiring
sites must pass the same list — `JTranslate\Module::onBootstrap()` for laminas and
`App\Laminas\TranslatorConfigurator` for Symfony — or discovery works under one
front controller and not the other.

**3. `locales_to_translate` in `config/autoload/jtranslate.global.php` *adds* to
the module default, it does not replace it.** The app file names only `it_IT`; the
module names `es_ES, de_DE, pt_BR`. laminas merges numeric-keyed arrays by
appending, so the effective list is all four. Reading either file alone gives the
wrong answer; ask the merged config.

**4. The catalogs are build output.** `.lang.php` files are gitignored in all four
repos, written by `TranslationsTable::writePhpTranslationArrays()`. A fresh
checkout renders English until `php bin/console jtranslate:export-catalogs` runs,
and a deploy rebuilds them through a phploy hook. They are plain
`<?php return [...]` arrays — the format is fast, staying, and is what
`symfony/translation` independently landed on for its own compiled cache.

## What makes two phrases the same phrase

`(project, text_domain, phrase_hash)`. The hash is `binary(32)` over the phrase
normalized to LF line endings, so a template saved with CRLF does not fork a
second row — but the compiled catalog gets **both** spellings as keys, because
laminas' catalog lookup is byte-exact and the CRLF render has to find something.

Two consequences worth holding on to:

- The same English string legitimately exists many times over, once per text
  domain, and those rows may carry *different* translations. `Association` is
  `Verband` in one domain and `Gremium / Institution` in another, and both are
  somebody's deliberate choice. This is why a "deduplicate the phrase table"
  instinct is wrong, and why cross-domain merges need a human.
- `TranslationsTable::writeMissingPhrasesToDb()` **copies translations onto a
  newly discovered phrase** from any row of the same project holding the same text
  in another domain — author, `modified_by` and all. So a brand new row can appear
  fully translated in four languages seconds after it was filed, and
  "somebody translated it, therefore it matters" is not a valid inference. That
  mistake is what `database/db7.6.sql` exists to correct.

## Text domains

This is the part that has broken most often, so it gets the most space.

A text domain is a namespace for phrases, named after the module whose strings
they are: `Schoenstatt`, `Books`, `JUser`, `SionModel`, `Application`,
`JTranslate`, plus `default`. There is **no cross-domain fallback in laminas** —
`Translator::translate()` falls back by *locale* only. What laminas does instead
is assign a domain per rendering context, and the phrases really are scattered
that way. Measured in `es_ES`:

| phrase | lives in | renders as |
| --- | --- | --- |
| `Shrines` | `default` only | Santuarios |
| `Wayside shrines` | `Schoenstatt` only | Hermitas |
| `Fr.` | the module domains only | P. |

### Under laminas: a dispatch listener

`JTranslate\Module::onBootstrap()` attaches to
`AbstractActionController::dispatch` and sets the text domain on **twelve** view
helpers from the dispatched controller's module namespace — `translate`,
`headTitle`, `flashMessenger`, seven form helpers, `formElementErrors`, and
`navigation`. `navigation` is the exception that proves the rule: it gets
`jtranslate.navigation_text_domain` (`Application`), because the menu is one tree
rendered on every page and its strings belong to whoever owns the menu, not to
whichever controller happened to answer.

The same listener also calls `formElementErrors()->setTranslateMessages(false)`,
and that is a data-integrity guard rather than a display choice — see
[user input](#3-never-let-user-input-reach-translate) below.

### Under Symfony: a route attribute, and only two bridged helpers

**A Symfony-served request never dispatches a laminas controller, so that listener
never runs.** Nothing sets any of the twelve domains. The Symfony side reproduces
what it needs, explicitly:

- Each route declares its domain in `config/symfony/routes.php` via
  `$textDomain('Books')`, which becomes the `_text_domain` request attribute
  (`LaminasExtension::TEXT_DOMAIN_ATTRIBUTE`).
- `App\Twig\LaminasExtension::translate()` reads it and passes it per call.
- `App\Laminas\ViewHelpers::useTextDomain()` points the shared `translate` **view
  helper** at it, for the helpers that reach translation through
  `$this->view->translate(...)` and pass no domain of their own —
  `Books\View\Helper\FormatField` is the one that forced it.
- `App\Laminas\ViewHelpers::useFlashMessengerTextDomain()` does the same for the
  flash messenger, called from `LaminasExtension::flashMessages()`.

The other ten are unreachable from Twig today — the layout writes its own
`<title>`, passes the navigation domain explicitly, and `BootstrapFormRenderer`
routes every form string through `LaminasExtension::translate()`. **That is a fact
about today's templates, not a guarantee.** If you bridge a helper that translates
anything, set its domain in the same commit; the flash messenger was missed for
three days and the symptom was not "untranslated", it was a growing phrase table.

`test/Integration/PortedRouteTranslationTest` asserts all of this: that every
ported HTML route declares a domain, that the domain is one the application
actually uses, that discovery happens in the page's domain and nowhere else, and
that `flashMessages()` sets the flash messenger's.

### What `translate()` actually does

```php
LaminasExtension::translate(string $message, ?string $domain = null): string
```

- An explicit `$domain` wins, and is what the layout passes for strings whose
  domain it knows (`translate('Sign in', 'Application')`).
- Otherwise the page's own domain is asked with `Translator::translate()` — **this
  call is a write**, in the sense that a miss files the phrase where it belongs.
- If that misses, `default` is consulted by *reading its compiled catalog*
  (`catalogValue()`), which fires no event and therefore files nothing. The
  rendering is the union of both domains, matching laminas' output; the discovery
  is single-domain, matching laminas' behaviour.

The distinction between those last two is the whole of `database/db7.8.sql`. When
the `default` consultation was a second `translate()` call, every string the page's
domain could not translate was *also* filed in `default`, where nobody had asked
for it — 232 duplicate rows in the capsule before it was caught.

## Writing a Twig template

A checklist, in the order it bites.

### 1. Give the route a text domain

In `config/symfony/routes.php`, `$textDomain('Books')` on the `$ported()` call, or
the `$domain` argument to `$content()`. Without one, `translate()` sees only
`default`; the page renders in English in all four non-English locales, and the
failure is invisible in English because a missing translation *is* the source
string. This shipped to production once and went unnoticed for three days.

A route that genuinely has no module — the maintenance and JSON endpoints — is
exempt, and `PortedRouteTranslationTest::rendersHtml()` is where the exemption is
recorded.

### 2. Never translate record data

A publication's title, a shrine's name, a library's name, a person's name. These
are not language in any language: translating one cannot succeed, and the miss
files a row. One row **per record**, and twice over when two domains are
consulted. Within a day of breadcrumb labels starting to be translated, publication
titles were 61% of the entire phrase table.

- In the navigation tree, mark the branch in
  `Application\Navigation\PageBuilder::markDataLabels()`.
- On a breadcrumb the controller passes itself, use `'translate' => false` on the
  crumb (precedent: `database/db7.5.sql` and the `150-preguntas` page).
- In a template, just don't: `{{ publication.title }}`, never
  `{{ translate(publication.title) }}`.

`format_entity` and friends already handle this; the risk is in hand-written
markup.

### 3. Never let user input reach `translate()`

Same failure, worse: it files a stranger's typo as a phrase. Six mistyped email
hostnames reached the table this way, through `formElementErrors` translating a
validation message **after** laminas had interpolated `%hostname%` into it. The
guard is to translate the *template* and interpolate afterwards, which is what
`setTranslateMessages(false)` plus `AbstractValidator`'s own translator does — and
what `App\Form\BootstrapFormRenderer` reproduces on the Symfony side (it does not
translate validation messages at all; `App\Laminas\TranslatorConfigurator` handles
the templates).

### 4. Never build a message by concatenation

```php
$this->flashMessenger()->addMessage(ucwords($entity) . ' not found.');   // no
```

That files one phrase per entity type, none of which a translator can render into
a language whose word order differs. Use `JTranslate\I18n\TranslatableMessage`,
which carries the template and its parameters separately so `translate()` receives
`'File not imported due to duplicate withinLibraryIds: %s.'` and the substitution
happens after. `database/db7.7.sql` cleaned up the residue of the last round of
this; `SionController` and `SendToNewUrlController` still hold a bounded version
of it (one row per entity name) and it is on the list.

### 5. Prefix every `{% set %}` in the layout with `chrome_`

Not a translation rule, but it lands here because it was found by a translation
bug. A `set` at the top level of `templates/layout.html.twig` is **not** scoped to
the layout: `{% block content %}` is called from that point with the context as it
stands, so any name set above it shadows the identically-named variable the
controller passed — silently, in every page that extends the file. The literature
catalogue rendered "Catálogo de livros em fr" in production because the layout set
`languages` for the locale chooser and `/literature` passes `languages` as the
ISO-639 map.

### 6. Expect form placeholders and public notes in the table

Two things that look like leaks and are not:

- **Form placeholder attributes are translated** by laminas' form helpers, so
  `ex. +49 151 55555555` and a JSON opening-hours example are legitimate phrase
  rows. Ugly, deliberate, load-bearing for translators.
- **An association's public notes are translated by design** — that free text *is*
  the per-locale description feature, not a data leak. See
  [the note in api-change-requests-response.md](api-change-requests-response.md).

## Retirement is not retraction

Two different verbs, and confusing them destroys work:

- **Retire** (`trans_phrases.retired_on`) takes a phrase off the translator's
  worklist and keeps every translation. Rendering is untouched:
  `getTranslatedText()`, the query the catalogs are compiled from, filters on
  `project` and nothing else, so a retired phrase still compiles into
  `.lang.php`. It is reversible by clearing the column, and **discovery clears it
  automatically** — a page that still looks the phrase up un-retires it on the next
  miss.
- **Retract** destroys a translation and keeps the phrase.

That auto-un-retirement is why every cleanup migration since `db7.5` carries the
same ordering note: **the code fix has to be live before the migration runs**, or
the next render undoes it. It is also a free alarm — if a retired row comes back,
the fix regressed.

`php bin/console jtranslate:retire` does both directions from the CLI; the v3 API
exposes `POST …/retire` with a mandatory `_note`.

One CLI trap: `TranslationsTable::flush()` needs a session and fatals in any
console process, *partially* — leaving a phrase row no render will ever complete.
Call `setActingUserId(null)` first.

## The table is shared, but not public

Four applications write to `trans_phrases`, and phrases can contain private data:
an association's internal notes, a moderator's admin annotations. **Never copy
phrases between projects**, and never seed a shared library's catalog from the
database — only from its own source literals.

Reads are the other half of that rule. `TableGateway::select()` takes a
*predicate*, not a `Laminas\Db\Sql\Select`; handing it the latter silently returns
the whole table. That is how JTranslate's phrase index read every project's rows
for years without a single error.

## Caching

Two layers, both APCu, both explained in [caching.md](caching.md):

- the compiled catalogs are read from disk and cached by the translator;
- JTranslate's own phrase index and `KEY_TRANSLATED_TEXT` are cached under the
  `jtranslate` namespace, TTL one hour.

The one thing to internalise: **an APCu segment belongs to its SAPI**. A CLI
process can never flush the web server's cache — proven, 21 entries survived a CLI
`apcu_clear_cache()`. Cache-clearing after a phrase edit has to be an HTTP request
(`/en/sm/clear-persistent-cache`).

## Checking your work

Three things, cheapest first.

**Does anything leak?** Run this before and after your change. It is the query that
found the last two:

```sql
SELECT p.text_domain, p.origin_route, COUNT(*) AS rows, MAX(p.added_on) AS newest
FROM trans_phrases p
WHERE p.project = 'Schoenstatt' AND p.retired_on IS NULL
  AND p.added_on >= CURDATE() - INTERVAL 1 DAY
GROUP BY p.text_domain, p.origin_route
ORDER BY rows DESC;
```

Healthy output is a handful of rows in module domains. A double-digit count in
`default`, or a route appearing twice under two domains, is the shape of every
failure below.

**Does a ported page still agree with the original?**
`tools/port-baseline.php capture` / `compare`, which renders every path in five
locales through both front controllers and diffs. English proves almost nothing —
in English a missing translation *is* the source string — which is exactly why the
tool exists.

**Do the invariants still hold?** `php composer.phar integration` runs
`PortedRouteTranslationTest`, `PhraseIdentityConstraintTest` and
`TranslationWriteSemanticsTest`. They need no running app.

## What has gone wrong, and what fixed it

Kept because each of these looked like something else at the time.

| when | symptom | actual cause |
| --- | --- | --- |
| 2026-08-08 | every string on every ported page rendered English in four locales | two causes: the Symfony-side translator had no sources registered (`JTranslate\Module::onBootstrap()` never runs), and no route declared a text domain |
| 2026-08-09 | 7,801 phrases, 74% of them junk; site slow | `phrase` was a truncating `varchar(2000)`, so long strings forked new rows forever. Not the assumed cache race. 7,801 → 2,693 in 1.2s |
| 2026-08-10 | publication titles became 61% of the table in one day | breadcrumb labels started being translated, and a navigation label is one row *per database record* (`db7.4`, `db7.5`, `db7.6`) |
| 2026-08-10 | untranslatable rows: field lists, import batches, strangers' email hostnames | messages built by concatenation, and validator messages translated after interpolation (`db7.7`) |
| 2026-08-12 | 232 duplicate rows in `default` | `LaminasExtension::translate()`'s fallback to `default` was a second `translate()` call, and a miss *writes*. Now reads the compiled catalog instead (`c5ef8f3`) |
| 2026-08-13 | duplicates in `default` kept arriving after that fix | `flashMessenger` was the one of JTranslate's twelve dispatch-set helpers that the Symfony bridge had not reproduced (`db7.8`, `ViewHelpers::useFlashMessengerTextDomain()`) |

The common shape: **a lookup nobody intended, in a domain nobody asked about.**
When the phrase table grows unexpectedly, the question is never "what is inserting
rows" — it is always the listener — but "which render is asking for a string it
should not be asking for, or asking for it twice."

## Where the code is

| what | where |
| --- | --- |
| discovery listener | `module/JTranslate/src/I18n/Translator/TranslatorEventListener.php` |
| everything about the tables | `module/JTranslate/src/Model/TranslationsTable.php` |
| phrase identity / hashing | `module/JTranslate/src/Model/PhraseIdentity.php` |
| data-carrying messages | `module/JTranslate/src/I18n/TranslatableMessage.php` |
| laminas per-request domains | `module/JTranslate/src/Module.php` |
| Symfony translator wiring | `src/Laminas/TranslatorConfigurator.php` |
| Twig `translate()` and friends | `src/Twig/LaminasExtension.php` |
| bridged view helpers | `src/Laminas/ViewHelpers.php` |
| route text domains | `config/symfony/routes.php` |
| navigation data labels | `module/Application/src/Navigation/PageBuilder.php` |
| commands | `jtranslate:export-catalogs`, `jtranslate:migrate`, `jtranslate:retire` |
| cleanup migrations | `database/db7.2.sql` … `database/db7.8.sql` (7.0 and 7.1 are charset work, not phrases) |
