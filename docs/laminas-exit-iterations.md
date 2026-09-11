# The three iterations that finish the laminas exit

[laminas-exit.md](laminas-exit.md) is the plan and the dependency picture; this file is the
order the remaining work can actually **ship** in. The numbering there — steps 0 to 8 — is
the order it was planned in, and three of those steps are now part done, which makes it a
poor map of what is left.

**16 `laminas/*` packages are installed**, down from 37. Steps 0, 1a, 1b, 3, 4 and 6 are
done; 1c (only `uri` left), 2 (only `session` left) and 5 (only the form model left) are
part done. Everything remaining regroups into three iterations:

| | removes | leaves | content |
|---|---|---|---|
| **A** | form, inputfilter, filter, validator, hydrator, escaper, uri | 9 | the form model, and our own validator and filter classes |
| **B** | session, servicemanager, modulemanager, eventmanager, config, loader | 3 | the session and the container |
| **C** | db, stdlib, translator | 0 | the database layer |

Each is **one superproject PR over three submodule PRs, and one deploy**. Nothing new is
added: both remaining design decisions were taken 2026-09-11 in favour of our own code, so
the exit is 16 packages out and none in.

## Why this order

**Two of the three are forced by the dependency graph**, read from the `require` blocks in
`vendor/laminas/*/composer.json` rather than from anyone's memory of them:

- `stdlib` is required by servicemanager, config, filter, validator, db and hydrator. It
  cannot leave until all six have, so it is last whatever else changes.
- `escaper` is required by laminas-form **and** laminas-uri; `validator` by laminas-form,
  laminas-inputfilter **and** laminas-uri. So laminas-uri has to go in the same iteration
  as the forms or those two stay — which is why an 8-file package sits in the largest
  iteration.

**One is chosen: the container before the database.** The 58 factory classes are rewritten
either way; doing the container second means `SionTable`'s wiring changes once instead of
twice.

## The rule all three share: record the oracle first

**Each iteration deletes the tests that prove it.** `WholeFormEngineParityTest`,
`EngineMatchesAssembledFilterTest` and `ElementModelParityTest` all work by running
laminas' implementation beside ours and comparing; they stop existing the moment the
package does. The same is true of anything written for B and C.

So each iteration opens the same way: **record what laminas answers as a data file, then
replace it, then diff the recording.** `test/Element/element-surface.php` is the worked
example — it recorded what all 441 elements answer before the element swap, and the swap
then showed 432 changed lines, every one of them a class name, and nothing else. A parity
test that outlives its subject is worth more than one that dies with it, and the recording
is what makes that possible.

The second half of the rule: **a verification fed the same narrowed input as the thing it
tests will always agree with itself.** That is how 101 element-supplied validators went
unnoticed for months while a spec-versus-spec parity test reported nothing.

## A — the form stack (16 → 9)

**Removes:** laminas-form, laminas-inputfilter, laminas-filter, laminas-validator,
laminas-hydrator, laminas-escaper, laminas-uri.

Validation already runs on our engine and every element is already ours. What is left is
the form itself and the rules' implementations.

1. **The rendered-markup baseline, first, because none exists.** `test/Fuzz/FormRepository`
   builds every form in the application, and 42 classes own the 441 elements: the forms,
   two fieldset subclasses (`Books\Form\MassCheckoutFieldset`,
   `App\Books\Import\ImportMappingFieldset`) and the one `Collection`.
   `SionModel\Form\BootstrapFormRenderer` is 1,154 lines. The contract is rendered-HTML
   parity, so the baseline is the markup each form produces in each state the application
   renders it. `tools/port-baseline.php` is retired but holds the normalization rules such
   a capture needs.
2. **The form model**: `SionModel\Form\{Form, Fieldset, Collection}`, implementing what the
   **77 files** naming `Laminas\Form` actually use, and the renderer moved onto it.
3. **The validator classes.** About twenty are used: `Regex` 19 references, `StringLength`
   14, `Csrf` 10, `NotEmpty` 8, `InArray` 8, `GpsPoint` 7, `Db\NoRecordExists` 6, `Date` 6,
   `Digits` 5, then `Identical`, `Explode`, `EmailAddress`, `Db\RecordExists` at 4 each,
   `Uri` 3, and singles. The seam is already in place and is the easy part: a specification
   names its validators **as strings**, and `SionModel\Form\Validation\InputFilter` takes a
   name → object callable, so one map replaces `ValidatorPluginManager`.
4. **The filter classes.** About twelve: `ToNull` is 96 of the references, `StringTrim` 15,
   `StripTags` 10, `StripNewlines` 9, `ToInt` 5, `PregReplace` 5, `DateSelect` 4, then
   singles.
5. **`Laminas\Uri\Http` in 8 files**, 17 of the 18 references as an option to a URI
   validator — it falls out with our own `Uri`. `escaper` is named only in doc comments and
   leaves for free; `hydrator` only laminas-form ever wanted.

**Risk** sits in rendering, not in validation: the engine has been live since the cutover
and the fuzz harness measures it on every run. The smoke suite covers create and edit on
every entity, and `test/Fuzz/known-form-gaps.php` must stay at zero in both categories.

## B — the session and the container (9 → 3)

**Removes:** laminas-session, laminas-servicemanager, laminas-modulemanager,
laminas-eventmanager, laminas-config, laminas-loader.

1. **The session moves first**, and it is small but unforgiving: 14 files.
   `App\Http\SessionListener` starts a manager per request, `SionModel\Messaging\FlashMessages`
   stores flashes in a container, and JUser keeps the identity there.
   **The storage key must stay `Laminas_Auth`** or the deploy signs every visitor out.
   symfony/http-foundation is already a dependency and already has a session.
2. **The container.** 91 files name `Laminas\ServiceManager`, and 58 classes implement one
   of its factory contracts — 56 `FactoryInterface` and 2 `DelegatorFactoryInterface`.
   Ours is a PSR-11 implementation reading factories, aliases and invokables from the
   merged config; the factories become `__invoke($container)`; the
   `data/config/` merge cache is unchanged. Module and config loading —
   `config/modules.config.php` and six module configs — moves with it, and
   `laminas-config` and `laminas-loader` fall out (the latter is a direct line of ours only
   because laminas-modulemanager uses `Laminas\Loader\*` without requiring it).

**This is the highest-risk deploy of the three.** A container fault is site-wide, and its
shape is a fatal under HTTP 200 rather than an error — a few hundred bytes of 200 on every
page. It ships the way step 0's batch 2 did: **our services shadow laminas' under the same
ids and deploy with the package still installed**, so the swap is observable before
anything is removed, and a second commit removes it.

Also required here: `bin/console` builds its container the same way and must keep doing so
with the config caches **off**, and no `data/config/*` file may appear owned by the deploy
user after a console run.

## C — the database layer (3 → 0)

**Removes:** laminas-db, laminas-stdlib, laminas-translator.

1. **`App\Db\Connection`, a thin PDO wrapper of ours** — not Doctrine DBAL; see
   laminas-exit.md §6. 94 files name `Laminas\Db` and `SionModel\Db\Model\SionTable` is
   2,412 lines over `TableGateway`/`Sql`, but the query logic is already in `SionTable`;
   what laminas-db supplies beneath it is largely a parameter binder and a result iterator.
   Open with a census of which `TableGateway`/`Sql` features are actually reached, by call
   site.
2. **`laminas-translator`** is a zero-dependency interface package, named in 30 files:
   declare the interface ourselves and change the imports.
3. **`laminas-stdlib`** is six references across six files — `ArraySerializableInterface`
   twice, then `StringUtils`, `PriorityList`, `InitializableInterface` and `ArrayUtils`
   once each — and falls out once its parents have gone.

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
| A | the rendered-markup baseline diff; `known-form-gaps.php` still 0/0; smoke create+edit on every entity |
| B | sign in on the old release, deploy, still signed in; a flash written before the deploy still renders; `bin/console` writes no `data/config/*` |
| C | MariaDB general-log statement diff across a full smoke run, before and after |

And after each: `composer show --locked | grep laminas` — the count is the progress metric —
plus the `--no-dev` rehearsal and `composer audit --locked`.
