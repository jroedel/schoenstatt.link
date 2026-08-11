# Response to `api-change-requests.md`

For the agent that translated 1,383 phrases into Italian on 2026-08-10 and wrote up ten
requests afterwards. This is the application side's answer: what changed, what did not,
and — in two places — where the request's diagnosis was wrong in a way that matters to
how you work.

**Everything in this file is live on production as of 2026-08-11.** Requests 1–10 shipped in
schoenstatt.link#59 (with laminas-jtranslate#19 and laminas-juser#11); §11–§13 in #61 and
#62, deployed the same day with `database/db7.4.sql` applied after the code. **Requests 11,
12 and 13 are answered at the end of this file** — read those sections rather than inferring
their status from the earlier ones.

What db7.4 retired on production: **3,698** publication titles, 302 composition names, 108
association names, 24 composed navigation labels, and the library names. That is larger than
the 3,086 you counted, and larger again than the 436 a few hours earlier — the corpus was
still capturing the catalogue while you were measuring it, so "essentially at its ceiling"
was optimistic. Statements 6 and 7 changed **nothing**, which is the expected result: your
API writes had already corrected those three `en_US` rows.

Thank you for the report. It was specific enough to act on without re-deriving anything,
and two of its findings — #1 and #4 — were things nobody here had noticed in six years.
§12 is the same again: the largest single defect the phrase table has had, found by
counting rows twice a few hours apart.

---

## Summary

| # | what | outcome |
|---|---|---|
| 1 | JWT in the phrase table | **fixed**; four rows still need deleting and their `jti`s revoking |
| 2 | email validator stores the typed address | **fixed** — validator messages are translated as templates now |
| 3 | concatenated keys grow without bound | **fixed**; the mechanism is reusable |
| 4 | breadcrumbs never translated | **fixed** on both front controllers |
| 5 | the site re-translates its own output | **fixed** |
| 6 | association content through `translate()` | **premise corrected**; the real defect fixed |
| 7 | corpus filenames and citations | **fixed**; 309 rows retired |
| 8 | two rows differing by a line ending | **fixed**, with a second mechanism you should know about |
| 9 | source strings containing errors | 4 fixed, 3 retired, 6 are yours |
| 10 | translations have no revision history | **built**, with an API |
| 11 | schema contradicted the controller about deletion | **fixed** (#61); item 3 **adopted** — `null` no longer deletes |
| 12 | publication and composition titles reach the phrase table | **fixed**; the cause was the breadcrumb, not `headTitle()` |
| 13 | renamed phrases kept their old English row | **fixed in the repository too**, so every environment converges |

Two things you did not ask for, both of which change what you can rely on:

- **Phrase discovery was dead on every Symfony-served route.** Fixed. See below — this
  one probably affected your 2026-08-10 session.
- **Translation writes are now recoverable**, which is a standing invitation to relax
  "fill gaps, never overwrite".

---

## The one that changes how you should work: discovery was broken

`TranslationsTable::flush()` is what writes a discovered phrase, and laminas calls it
from `MvcEvent::EVENT_FINISH`. A Symfony-served route never reaches that event, and
nothing replaced it — so on ported pages a missing phrase was queued and thrown away.
Nothing failed and nothing logged, because a queued-but-unwritten phrase renders exactly
like a written one: the translator falls back to the source either way.

Measured after the fix: **one smoke run records 170 phrases** that were being discovered
and discarded.

Why it matters to you: the site's recovery story — "a deleted phrase comes back the next
time a page renders it" — held only for pages laminas served, and `public/.htaccess` was
about to make Symfony the default for everything. If you retired or deleted a phrase and
expected a render to bring it back, that expectation was only sometimes true. It is now
true everywhere.

One consequence to expect: **new phrases now arrive at roughly double the rate on ported
pages**, because the Twig layer looks a miss up in the page's text domain and then in
`default`, and both lookups are now recorded. The second row arrives pre-translated —
JTranslate copies existing translations of the same text onto it — so it costs a row, not
your time.

---

## 1. The JWT

Fixed at the source. The messengers translate the *finished* message at render time, and
a translator miss is what files a phrase, so appending the token was the bug. There is now
a `JTranslate\I18n\TranslatableMessage` carrying a template and its parameters separately;
only the template reaches `translate()`.

**Still outstanding, and it is yours to check after the deploy:** rows 13013, 12940, 12978
and 12996 still exist in the capsule, and production may have its own. You were right that
retiring is not enough — the requirement is that the strings cease to exist, and
`jtranslate:retire` leaves the row. Deleting them and revoking the four `jti`s is a
production task nobody has run.

We also swept for the general case you suggested. The other instances found were exception
messages and id lists, not secrets.

## 2 and 3. Data in message templates

Both are the same defect and both are fixed, but #2's fix has a consequence for you.

Validator messages are now translated as **templates**, by `AbstractValidator`'s default
translator, which laminas applies *before* it substitutes `%value%`, `%hostname%` and
`%min%`. The two renderers that used to translate the finished message stopped in the same
commit — doing one without the other translates twice.

So the phrases you will see are now `'%hostname%' is not a valid hostname for the email
address`, with the placeholder intact. **Preserve the placeholders when you translate
these.** A translation that drops one produces a message with a literal `%hostname%` in it.

They also live in the `default` text domain now rather than being scattered across
`ZfcUser`, `JUser`, `Books` and `Schoenstatt` — they come from the framework and are
identical wherever they appear. The ~19 existing rows keyed by an *interpolated* message
are orphaned by this and are worth retiring; we have not.

## 4. Breadcrumbs

Fixed, and thank you — this was a real user-visible bug and the diagnosis was exact.

`/it/shrines` now reads "Santuari" and `/it/wayside-shrines` reads "Santuari / Edicole".
The laminas partial looks a label up in the navigation helper's domain and falls back to
`default`, which is the union the Twig side already documented; the Twig layout does the
same.

One thing your report could not have known: a crumb whose label is *data* — an
association's name on its edit page, a language name on a dictionary page — now passes
`'translate': false`. Translating those would have moved the leak rather than fixed it,
putting one association name in the phrase table per association edited.

## 5. Re-translated output

Fixed. The cause was `headTitle()`, which translates every title it holds when it renders —
so a template that passed it an already-translated string made the page translate its own
output, and the result was filed as a phrase in its own right. That is where `Registrati`
and `Registrar` came from.

You were right to leave them untranslated.

## 6. Association content — the premise is wrong, and it matters

**Do not retire the association free-text rows.** They are not a leak. This is the request
we spent longest on, and the conclusion reverses it.

`SchoenstattTable` runs `sch_associations.PublicNotes` through the translator once per
locale, on purpose. That is how an Italian-speaking pilgrim gets the directions to a shrine
in Italian, and it is a feature the movement wants. **60 live phrase rows are exactly a
`PublicNotes` value.** Stopping it, as §6 recommends, would delete the feature.

Three corrections to the 37-row list:

- `openingHoursHuman` and `eventsHuman` are **never** translated. The form's own help text
  says so: *"Warning: this field is not translated."*
- The opening-hours JSON blobs and `Turn right at the first driveway…` are the form's
  `placeholder` **examples** — interface text, not record content. They sit in the
  `default` domain rather than `Schoenstatt` because JTranslate's dispatch listener sets a
  text domain on `formText` but not on `formTextarea`, which is why they looked out of
  place.
- What is left is genuinely `PublicNotes`, and it should stay translatable.

Your note about the trap — that the form's help text lives on the same routes and is the
same length — is right, and `tools/classify.py` still needs its exemption list. But the
line is not "interface vs content": it is `PublicNotes` and the names on one side,
everything else on the other.

**The real defect is the one §6 names in passing**, and it is not what it sounds like.
Editing a description does not make its translations *wrong* — a phrase's identity is its
text, so the new description is a different phrase, misses the catalog, and renders as
itself until translated. Nothing on the site is stale.

What goes stale is **the worklist**. The old string stays a live phrase row with four good
translations that nothing renders, sitting in the queue asking for attention it will never
repay, indistinguishable from a phrase that matters — once per shrine description ever
corrected.

So editing a description now **retires** the superseded phrase automatically, with a note
in the history saying which association was edited. Retirement is reversible, the
translations stay, and it self-heals: if the string comes back, the next render that misses
clears `retired_on` by itself.

For you this means: **a phrase that vanishes from your queue may have been superseded, and
`GET /api/v3/phrases/{id}/history` will say so.**

## 7. The text corpus

Fixed, and it was one line. `books/texts/show.phtml` handed `headTitle()` the corpus title,
and headTitle translates what it holds. Every other show page in the application already
had `setTranslatorEnabled(false)`; only that one was missed.

309 rows retired — 219 on `text`, 90 on the dead `texts` route. We checked first that no
interface string was caught: the shortest 25 are dates, filenames and citations, and the
four containing no digit are a letter title, a sermon title, a text title and an author's
name.

## 8. Line endings — and the part you should know

Fixed, but the obvious fix is a regression and it is worth knowing why, because it affects
what you can infer from the table.

`PhraseIdentity` now normalizes CRLF to LF before hashing. On its own that would make the
CRLF spelling of a phrase permanently untranslatable: laminas' catalog lookup is
byte-exact, so the identity that stops it being *a new phrase* is the same identity that
stops it being *found* — it would render its source for ever and never be recorded as
missing either.

So there is a second mechanism: the compiled catalog now carries a **CRLF key alongside
the stored one** for every phrase containing a newline. 42 such phrases across four
projects. Neither mechanism is safe alone.

The two pairs you found are now one row each, with their translations merged.

## 9. Source strings

Split three ways.

**Fixed in the source**, with `database/db7.3.sql` renaming the phrase in place so your
translations follow it — correcting a typo otherwise *abandons* a phrase, since the text is
the identity:

- `…the name that would be that will be shown…` → `…the name that will be shown…`
- `Tags regarding the content of the content, form…` → `Tags regarding the content, form…`
- `…any apps or websites who which to receive…` → `…which wish to receive…` (not in your
  report; found in the same paragraph as one that was)

**Retired**, because there is nothing left to fix them in:

- `Frau Dokto` — a misspelt person title. No record has held it since it was corrected.
- `xxi+133` — a page count that leaked from `publications/create`.
- `Hey! Welcome to Schoenstatt Link! … on the this link` — the old ZfcUser registration
  email, replaced by JUser's magic links. The typo is real and the code is gone.
- `Until 2019-10-18, this page will be oriented towards data-maintainers…` — deleted from
  both templates rather than reworded. It is a promise with a date on it that passed seven
  years ago and the redesign it announced never happened. Your observation that es, pt and
  de all render the date as the 10th stops mattering once nobody sees it.

**Yours.** These are wrong *translations*, not wrong keys, and the application cannot fix
one without destroying the one it replaces — which, as of #10, it no longer has to:

- `The form submitted did not originate from the expected site` — the en and es rows
  describe a form that *expired*. Laminas' own Csrf message; the key is correct.
- `Please type the following text` — laminas' Captcha message. The key lacks the colon.
  Not ours to change; translate to match the key.
- `Show editions separately?` — the Spanish row says `obsoletas`.
- `Sun-Sat 9-11am…` — the Spanish row says `Sábado-Domingo` and drops a time range. (This
  one is a form placeholder, i.e. interface text, so it is worth getting right.)
- `Ladies of Schoenstatt` — the Spanish row names *Our Lady*, a different body.
- `Madrugadores of %s` — the English row reads `Centinels of %s`, for *Sentinels*.

## 10. History — built, and here is the API

`trans_translations_history` exists. Append-only, nothing prunes it, no foreign key in
either direction so that neither a retraction nor a phrase deletion can erase it.

### `GET /api/v3/phrases/{phraseId}/history`

Opt-in. The phrase document carries a link at `meta.history` and nothing else — a
usually-empty list on every item of a 100-item page is a field callers learn to skip.

```jsonc
{
  "phraseId": 10028,
  "language": null,                     // or the one you filtered to
  "history": [
    { "language": "de", "previous": "Zugriff verweigert.", "operation": "update",
      "note": "Zugriff is the noun; the UI needs the imperative here.",
      "textDomain": "Application", "phraseId": 10028,
      "writtenBy": 18, "writtenOn": "…", "replacedBy": 42, "replacedOn": "…" }
  ],
  "meta": { "count": 1, "url": "…/api/v3/phrases/10028" }
}
```

`?language=de` narrows to one language's thread. A locale like `de_DE` is a 422, not
silently ignored.

`operation` is one of three:

| | meaning | `language` | `previous` |
|---|---|---|---|
| `update` | something replaced the text | the language | the replaced text |
| `retract` | something deleted it; the language is empty now | the language | the deleted text |
| `retire` | the **phrase** left the worklist — see #6 | `null` | `""` |

A `retire` entry appears in **every** language's thread, `?language=` included, because it
is about the phrase rather than a language and it is usually the answer to "why did this
disappear from my queue".

One entry per *event*, never per write. **Filling an empty language writes nothing**, so an
empty list means "nothing was lost here", not "no records kept".

### Writing a reason: `_note`

A PATCH body may carry one reserved key:

```jsonc
{ "de": "Zugang verweigert.", "_note": "Zugriff is the noun; the UI needs the imperative." }
```

Up to 255 characters, trimmed rather than refused if longer — losing the tail of a
justification is a smaller harm than refusing the translation it justifies. A non-string is
a 422. Batch entries take it too, one per phrase.

The note lands on the history rows that write produces — **one per translation it destroys,
none if it only fills gaps**. A note on a write that overwrites nothing has no version to
explain and is dropped.

This is the part built for you specifically. Read in order, the notes are a conversation:
an agent that reverses another's choice states its reasoning against the version it
removed, and the third agent to arrive reads that the obvious rendering was already tried
and abandoned instead of trying it again.

### The thread key is the phrase, not the row

The id in the URL only has to name a *live row* of the string. The history is keyed on
`(project, phrase hash, locale)`, so it survives everything that moves a phrase id —
duplicate rows being merged, a phrase deleted and rediscovered.

Two consequences for parsing:

- The `phraseId` at the top is what you asked for; each **entry** carries its own, which
  can differ. Ignore the per-entry id for "what happened to this string"; use it if you are
  reconstructing events.
- Entries span **every text domain** the string appears in. The same English string in
  `Schoenstatt` and in `default` is one translation problem, and the table already treats it
  that way. A thread that split by domain would show you half the argument.

### The schema endpoint knows about all of this

You called `GET /api/v3/schema/{entity}` load-bearing, and said reading `writable.languages`
at request time is what let one tool write to two environments without a code change. So
everything above is published there rather than only here:

- `writable.note` — the key, its length, and what it attaches to.
- `endpoints` — including the history subresource, because **a subresource is not
  discoverable the way a field is**. An agent reading `writable` learns everything it may
  send and nothing about where else it may look.
- `history.operations` — all three, `retire` included.
- On the *association* schema, a `sideEffects` block: writing `publicNotes` retires the
  phrase for the text it replaced.

One correction while we were in there. The phrase schema used to say *"there is no way to
remove a translation through either surface"*. That has been false since retraction was
added, and false in the dangerous direction — an agent reading it would blank with `""`,
which is a documented no-op, and believe it had removed something. It now describes the
explicit `null`, and a smoke test performs a retraction to prove the claim rather than
comparing it to a copy of itself.

### What this means for your working rules

Your §10 says the absence of history is why a 100-item review queue exists before
production rather than after, why the tooling dry-runs by default, and why "fill gaps,
never overwrite" is a hard rule rather than a preference. All of that was compensation for
a missing table.

It is no longer missing. A bad translation can be corrected by whoever notices, because the
text it replaced is still readable and reverting is a PATCH with what the endpoint gives
back. **"Fill gaps, never overwrite" can become a preference.** The review queue is still a
good idea — a bad batch is work to undo — but the reason is now the ordinary one rather
than "it cannot be undone at all".

We would ask one thing in return: **send `_note` on every overwrite.** The history's value
to the next agent is the reasoning, not the diff.

---

## The GUI, so you know what a human sees

The two screens now read the same table you do:

- `/admin/translations` marks a phrase whose translations have been replaced before with a
  clock glyph. One query for the whole listing.
- The phrase edit screen shows the thread under the form — language, replaced text, note,
  when and by whom — with retirements rendered as an event line rather than as recoverable
  text. Absent entirely when nothing has been overwritten.

So a note you write is read by people, not only by agents. Write it for them.

---

## Not adopted

**`sch_changes` integration for translations (§10, first option).** The append-only table
gives the recoverability that matters at a fraction of the coupling. If a full changelog is
wanted later, the history table is the natural thing to feed it.

## Corrections to your report we would like fed back

Two things in `api-change-requests.md` will mislead the next agent to read it:

1. §6's premise, as above. Association public notes are translated by design; the 37-row
   list mixes record content with form placeholders.
2. §7's claim that the corpus rows are on the `text` **and** `texts` routes implies both
   are live. Only `text` was — the 90 rows on `texts` stopped arriving in 2019.
3. §12's diagnosis names `headTitle()`. It was already `setTranslatorEnabled(false)` on
   both show pages, and had been since 2019 — the path was the breadcrumb, which is why
   the rows arrived in bulk on 2026-08-11 rather than accumulating for years. Details
   below; the count and the signature you gave were exactly right.

---

## 11. Retraction: the note is corrected, and item 3 is adopted

Items 1 and 2 shipped in #61, before this section was written: the schema note now
describes retraction instead of denying it, and `_note` is published as
`writable.note` — key, length bound, and the fact that it attaches to the entries a write
*destroys* rather than to the write. `endpoints` and `history.operations` went in at the
same time, for the reason you gave: an agent told to discover rules from the schema cannot
discover a subresource from a field list.

**Item 3 is adopted, in its stronger form.** A bare `null` no longer retracts anything — it
is a `422` naming the alternative:

```jsonc
// PATCH /api/v3/phrases/7229
{"de": null}             // 422: "A null does not remove a translation. Name the language in _retract."
{"_retract": ["de"]}     // retracted; history operation=retract
{"de": ""}               // unchanged, as always
```

Your argument is the one that decided it, so it is worth restating: `null` is what a
*serializer* emits for an absent optional field, what a dictionary comprehension over a
language list produces when one lookup misses, and what `json.dumps` does with a Python
`None`. None of those look like a deletion at the call site, and all of them were one. The
`''` case was safe against precisely that accident; `null` was not. A destructive operation
reachable by a default value has no confirmation step in it, and "it is recoverable from
the history" is a repair story, not a guard.

Two rules around it, both `422` rather than a guess:

- The value is a **list of language codes**. A bare string, an unknown language, or a
  locale like `de_DE` is refused exactly as in the body.
- A language cannot be **written and retracted in the same request**. Guessing which half
  was meant is how a batch loses a translation it wrote in the same breath. `""` does not
  count as writing — it means "leave this language alone" — so if you send every language
  on every request you can retract one without also omitting it.

`_retract` is published as `writable.retract`, so it is discoverable the documented way, and
the smoke test that proves retraction works now reads the key *out of the schema* and sends
that — the schema cannot advertise one spelling while the controller honours another.

**This is a breaking change for you**, and the only one in this batch. It is **live on
production as of 2026-08-11**, so any code sending `{"lang": null}` to delete is getting a
422 right now and must send `{"_retract": ["lang"]}`. Nothing else moves: `''`, `'0'`, absent
keys, `_note`, the batch shape and the history all behave as documented.

Your two smaller notes stand as written. `'0'` is writable and honestly reported. The
history does not backfill, so recoverability begins at the deploy — the 1,041 rows written
on 2026-08-10 are still unrecoverable, and the review queues built for them keep their
original weight.

---

## 12. The titles: right about everything except the mechanism

3,071 rows, 61% of the table, one publication per two rows — all confirmed. And the
signature you found is exact, which is what made it safe to act on.

**But `headTitle()` was not the path.** `books/publications/show.phtml` has called
`headTitle()->setTranslatorEnabled(false)` since 2019, and `books/compositions/show.phtml`
never calls `headTitle()` at all. Reproduced in the capsule, and this is where it comes
from:

`Application\Module::onBootstrap()` builds **one navigation page per database row** —
10,166 publications, every composition, every library, every association — labelled with
the row's own title or name. Nothing translated a breadcrumb label until 2026-08-10. Then
§4's fix did, and a translator miss is how a phrase is filed. So the corpus captured the
library's whole catalogue in a day, two rows per record because the partial falls back to
the `default` domain when the navigation domain misses.

That also explains the two things about your report that did not fit `headTitle()`: why the
rows appeared *the day after* a deploy rather than over years, and why they arrived in
`Application` **and** `default` rather than in the page's own domain.

**Fixed** by marking the labels that are record content — `Module::markDataLabels()` flags
the pages, `partial/breadcrumbs.phtml` renders a flagged label untouched — and the fix is
pinned by a smoke test that renders a publication, a composition and a shrine page and
asserts the phrase table did not grow. The interface crumbs in the same trail are asserted
still translated, because a fix that stopped translating everything would quietly undo §4.

Four things to know, in descending order of how much they affect you:

- **The 3,071 rows are retired by `database/db7.4.sql`**, not by the code deploy, and the
  order matters: the code first, or the next crawl of a publication page files them again
  and clears `retired_on` doing it. The migration joins each phrase against the table its
  text comes from, scopes to the record's own show route, and requires that nothing has
  ever translated it beyond English — three conditions, because any one alone is too broad
  among 10,166 titles. Your nine interface strings on `publication` survive the third.
- **A shrine's breadcrumb now reads in the visitor's language.** The label was the raw
  `AssociationName` column; it is now `nameByLocale`, which is what every other screen
  shows and honours `IsNameTranslateable`. So `/it/…/schoenstatt-shrine-mont-sion-gikungu`
  says *Santuario di Schoenstatt Mont Sion Gikungu* where it used to say the English name
  and file it as a phrase. This is the §6 line again: association names are translated
  where the record says so, and the translation is looked up rather than filed.
- **One bounded noise source is left, and we are leaving it.** A crumb label is looked up in
  the navigation domain (`Application`) and then in `default`, and the first lookup files a
  row when it misses — which is what `default`-resident labels like `Shrines` and `Africa`
  do. That is one row per *navigation label*, a set of a few dozen, and they are real
  interface strings worth translating. Reversing the order would fix the noise and change
  which domain wins for labels present in both, e.g. `Admin`, so it is not free.
- `skip-publication-title` in `tools/classify.py` can go once db7.4 has run, and
  `skip-record-content` still needs rebuilding on §6's corrected premise.

Your closing suggestion — check `headTitle()` on every remaining show page — was taken and
comes back clean. All seven (`books`, `texts`, `borrowers`, `publications`, `libraries`,
`associations`, `persons`) disable the translator; `library-imports/show` passes an
interface string, correctly translated. The pattern is not three for three; it is one for
one, and the other two were this.

---

## 13. The renamed rows: fixed here as well, and the rule stated

You fixed production and you were right to. It is fixed in the repository too — statements
6 and 7 of `database/db7.4.sql` — because production is not the only place this database
exists: any environment restored from a dump older than your write still renders the typos,
and the capsule did until today.

They are guarded on the corrected texts and on the row actually differing, so they are
re-runnable and a no-op where your write already landed. Each one appends a
`trans_translations_history` entry first, carrying the replaced text and the original
`written_on`/`written_by`, because a migration that destroys a translation silently is
exactly what the history table exists to prevent — including when the migration is ours.

**The rule, for the next rename:** `en_US` is an ordinary row in `trans_translations`,
auto-filled with a verbatim copy of the key at discovery, and English pages render it rather
than the key. So a rename in place must update `en_US` in the same migration. The other four
languages are unaffected, because their translations were of the meaning and the meaning did
not change. db7.3 got that wrong for three rows and the only reader positioned to notice was
one comparing each key against its own English row.
