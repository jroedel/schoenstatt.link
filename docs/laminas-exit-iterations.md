# The three iterations that finish the laminas exit

[laminas-exit.md](laminas-exit.md) is the plan and the dependency picture; this file is the
order the remaining work can actually **ship** in. The numbering there — steps 0 to 8 — is
the order it was planned in, and what is left of it does not line up with what can be
released together, which is what this file supplies.

**3 `laminas/*` packages are installed**, down from 37. Every step but 8 is done.
Iteration C is what is left:

| | removes | leaves | content |
|---|---|---|---|
| **A** ✅ | form, inputfilter, filter, validator, hydrator, escaper, uri | 9 | the form model, and our own validator and filter classes |
| **B** ✅ | modulemanager, config, loader · session, eventmanager · servicemanager | 3 | the module system, the session and the container |
| **C** | db, stdlib, translator | 0 | the database layer |

**Iteration B shipped in four parts, not one (2026-09-21).** The grouping in this table
was wrong three times in the same direction. The module system turned out to be separable
from the container — `laminas-modulemanager`, `laminas-config` and `laminas-loader` left
with `brick/varexporter` and `webimpress/safe-writer` behind them — then the session turned
out to be separable too, taking `laminas-eventmanager` with it, because nothing else had
ever required one; and the container, measured, turned out to be five configuration keys
rather than the plugin-manager hierarchy the plan had budgeted for. Each part deployed on
its own.

Each iteration is **one superproject PR over three submodule PRs, and one deploy**. Nothing
new is added: both design decisions were taken 2026-09-11 in favour of our own code, so the
exit is 37 packages out and none in.

## Why this order

**Two of the three were forced by the dependency graph**, read from the `require` blocks in
`vendor/laminas/*/composer.json` rather than from anyone's memory of them:

- `stdlib` is required by servicemanager, config and db. It cannot leave until all three
  have, so it is last whatever else changes.
- `escaper` was required by laminas-form **and** laminas-uri; `validator` by laminas-form,
  laminas-inputfilter **and** laminas-uri. So laminas-uri had to go in the same iteration as
  the forms or those two would have stayed — which is why an 8-file package sat in the
  largest iteration, and why A removed seven packages at once.

**One is chosen: the container before the database.** The 58 factory classes are rewritten
either way; doing the container second means `SionTable`'s wiring changes once instead of
twice.

## The rule all three share: record the oracle first

**Each iteration deletes the tests that prove it.** `WholeFormEngineParityTest`,
`EngineMatchesAssembledFilterTest`, `ElementModelParityTest` and `InputFilterEngineParityTest`
all worked by running laminas' implementation beside ours and comparing; they stopped
existing the moment the package did. The same will be true of anything written for B and C.

So each iteration opens the same way: **record what laminas answers as a data file, then
replace it, then diff the recording.** Five recordings exist, and iteration A wrote or used
all of them:

| file | what it holds | floor |
|---|---|---|
| `test/Element/element-surface.php` | what all 441 elements answer to every question the application asks | — |
| `test/Form/form-markup.php` | 491 rendered surfaces in four states, every helper the Twig extension can call | — |
| `test/Form/engine-surface.php` | every form's verdict, values and messages under two datasets | — |
| `test/Rules/rule-surface.php` | every (rule, options) pair in the application against a fixed 90-value corpus | 20 filters, 40 validators |
| `test/Rules/uri-surface.php` | 40 URL shapes through `Http` and through `SionTable::filterUrl()` | 35 |

A recording outlives its subject; a parity test does not. Each has a `composer *-baseline`
script that rewrites it and an integration test that compares it, and the floors exist
because a recording that silently records nothing passes.

The second half of the rule: **a verification fed the same narrowed input as the thing it
tests will always agree with itself.** That is how 101 element-supplied validators went
unnoticed for months while a spec-versus-spec parity test reported nothing.

## A — the form stack (16 → 9) ✅

**Removed 2026-09-11:** laminas-form, laminas-inputfilter, laminas-filter, laminas-validator,
laminas-hydrator, laminas-escaper, laminas-uri. Also gone with them: `psr/http-message` and
`webmozart/assert`, which nothing here named.

What the application runs on instead:

1. **The engine** is `SionModel\Form\Validation\InputFilter` over the specification
   `SionModel\Form\Validation\FormSpecification` assembles. `InputFilter::withRules()` is
   the one seam that says which rule set is meant.
2. **The form model** is `SionModel\Form\{Form, Fieldset, Collection, Factory}` over four
   interfaces of ours, and **every element** is `SionModel\Form\Element\*`, swapped in by
   `Element\Registry`.
3. **The rules** are `SionModel\Validator\*` (23 classes) and `SionModel\Filter\*`
   (21 classes), each named by a specification and built by a flat `Registry` — no plugin
   manager, no `ServiceManager`, no module loading, which is what lets the associations API
   validate a request with none of those present. `config/modules.config.php` lost its last
   laminas entries with them.
4. **`SionModel\Uri\Http`** replaces `Laminas\Uri\Http` in `SionTable::filterUrl()`, which
   is not validating a URL there but **rewriting the value that goes into the column**. Host
   validation is shape-based (IPv4, bracketed IPv6, reg-name) rather than laminas' 1,500-entry
   TLD table; `SionModel\Validator\EmailAddress` makes the same trade. Both are strictly more
   permissive, which is the whole of the behaviour change: measured against all 749 URLs
   stored in production, 749 identical and 0 different.
5. **Escaping** is `SionModel\View\Escape`, which predates this iteration; `laminas-escaper`
   and `laminas-hydrator` were held only by laminas-form and laminas-uri and fell out.

### What the recordings said

`rule-surface.php` moved **2 answers out of ~3,800**, both the EmailAddress trade:
`user@example.invalidtld` is now valid, and `user@localhost` reports one message where
laminas reported three. `uri-surface.php` moved 6 lines, all an exception class name.
`element-surface.php` lost 14 lines — the dead `uriHandler` option. `form-markup.php` and
`engine-surface.php` did not move at all.

### Three findings, and only one of them was fixed here

- **The engine never passed a validation context**, so all five `DateNotBefore` rules were
  inert: a person could be recorded as dying before their birth. Fixed — `isValid()` now
  hands `array_merge($this->getRawValues(), $data)` to each validator, as
  `Laminas\InputFilter\Input` did — and pinned by
  `test/Integration/CrossFieldValidationTest`, which discovers the pairs from the live
  specifications rather than listing them.
- **`SionModel\Filter\ToBit`'s `null_defaults_to` option has never done anything**: the
  constructor takes no argument, and 32 specifications pass it. Recorded as-is rather than
  fixed, because making it work would change stored rows.
- **A `messageTemplates` option replaces the template set rather than merging into it**, so
  two `Regex` specifications delete `regexInvalid` and arrays and booleans pass those fields
  silently. Reproduced exactly and left visible.

## B — the module system, the session and the container (9 → 3), done 2026-09-21

**Removed:** laminas-modulemanager, laminas-config, laminas-loader, laminas-session,
laminas-eventmanager, laminas-servicemanager — all on 2026-09-21, in four deploys.

0. **The module system, done.** `App\Modules\ModuleConfig` merges the module configs and
   caches them; `App\Laminas\ContainerFactory` applies the merged `service_manager` key
   itself, which is the only thing laminas' `ServiceListener` did here. It shipped before
   the rest because it touches neither the session nor the ServiceManager — and because
   three module configs held **closures**, which is what had made the config cache need
   `brick/varexporter`. Those are named factory classes now, and
   `SchoenstattTest\Integration\MergedConfigIsPlainDataTest` keeps them that way: our own
   cache writer is `var_export`, which can write back an array of scalars and nothing else.
1. **The session, done.** Five production files, not the fifteen that *name* the namespace.
   `SionModel\Session\*` reads and writes the namespaces and `App\Session\HttpSession`
   starts the session and owns the cookie; JUser was already behind its own
   `Host\SessionInterface`. The storage key stayed `Laminas_Auth`, and so did the whole
   **stored format**, which was the real hazard: laminas wrote a serialised
   `Laminas\Stdlib\ArrayObject` per namespace, so the session had to leave **before**
   `laminas-stdlib` while an old session still deserialises into a real object. Recorded in
   `test/Session/session-surface.php` and proved in both directions, including a page served
   to a hand-written old-format session that came back signed in.
   symfony/http-foundation was not used: it would have been a second session implementation
   beside PHP's own, and the stored format is PHP's.
2. **The container, done.** `App\Services\Container` — PSR-11, about 200 lines. The
   measurement is why it is that short: across the merged configuration of all six modules
   plus what `ContainerFactory` adds, the container is **five keys** — `services`,
   `factories`, `invokables`, `aliases`, `delegators`. No abstract factory, no initializer
   and no per-name `shared` flag survived; the last abstract factory went with
   laminas-session and the last initializer with laminas-eventmanager. Unsupported keys are
   rejected rather than ignored. The 58 factory classes kept their `__invoke()` and lost an
   `implements` clause — nothing ever checked `instanceof FactoryInterface`.

   **The trap was not the container.** `laminas/laminas-servicemanager/src/autoload.php`
   `class_alias`es `Interop\Container\ContainerInterface` onto the PSR-11 interface, and
   38 files in this tree type-hinted the Interop name. Removing the package removes the
   alias, so every one of them had to be rewritten in the same commit; JUser had already
   hit this and its README records it.

   Proved as a recording, like the session before it: `test/Container/container-surface.php`
   holds all 103 names with their types, their **sharing groups** (every name yielding the
   same instance collapses to one label, so an alias that stopped resolving or a service
   built twice shows up) and a probe of the state each delegator sets. Generated through
   laminas, then through ours, and the two files were byte-identical.

## C — the database layer (3 → 0)

**Removes:** laminas-db, laminas-stdlib, laminas-translator.

1. **`App\Db\Connection`, a thin PDO wrapper of ours** — not Doctrine DBAL; see
   laminas-exit.md §6. 99 files name `Laminas\Db` and `SionModel\Db\Model\SionTable` is
   2,412 lines over `TableGateway`/`Sql`, but the query logic is already in `SionTable`;
   what laminas-db supplies beneath it is largely a parameter binder and a result iterator.
   Open with a census of which `TableGateway`/`Sql` features are actually reached, by call
   site.
2. **`laminas-translator`** is a zero-dependency interface package, named in 32 files:
   declare the interface ourselves and change the imports. It is also the interface
   `SionModel\Validator\AbstractValidator::setDefaultTranslator()` type-hints, so the
   validator messages move with it.
3. **`laminas-stdlib`** is nine references across nine files — `ArraySerializableInterface`,
   `StringUtils`, `PriorityList`, `InitializableInterface` and `ArrayUtils` — and falls out
   once its parents have gone.

**This iteration has the best verification of the three, because the SQL is observable.**
Capture MariaDB's general log across a full smoke run before and after and diff the
statements: a change in what the application asks the database is the entire failure mode,
and it is visible in a way a form's markup or a container's wiring is not. Two traps that
are already recorded and apply directly here: a `Laminas\Db` `Where` is flat, so an OR group
plus an AND filter renders `A OR B OR C AND D`; and an `ORDER BY` with no tiebreaker makes
cached row order flap.

## Verifying each iteration

Beyond `./tools/ci-local.sh`, which every PR runs:

| iteration | the check that would catch the real failure |
|---|---|
| A ✅ | the five recordings above, each against its integration test; `known-form-gaps.php` no worse; smoke create+edit on every entity |
| B | sign in on the old release, deploy, still signed in; a flash written before the deploy still renders; `bin/console` writes no `data/config/*` |
| C | MariaDB general-log statement diff across a full smoke run, before and after |

And after each: `composer show --locked | grep laminas` — the count is the progress metric —
plus the `--no-dev` rehearsal and `composer audit --locked`.
