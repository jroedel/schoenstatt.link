# Keeping the shrine data current

The shrine pages are the most visited entity on this site and the data behind them
stopped moving in 2019. This document is the plan for fixing that, and the record of
what has been measured, decided and built.

Opened 2026-08-23. Nothing below is built yet.

## Why this project exists

`association` is the site's most-read entity — **3,878 page visits since 2026-05-01**,
ahead of `publication` (3,702) and every other kind. The shrine index and the individual
shrine pages fill a real gap: there is no other public, multilingual, structured
directory of Schoenstatt shrines.

The data behind them is a 2019 snapshot. Of the 250 shrines
(207 `sch-shrine` + 43 `sch-wayside-shrine`):

| `UpdatedOn` | shrines |
| --- | --- |
| 2017–2018 | 17 |
| **2019** | **216** |
| 2020–2022 | 16 |
| 2023 | 1 |
| since 2023-10-19 | **0** |

And the fields a pilgrim actually needs are the least populated ones:

| field | of 250 | |
| --- | --- | --- |
| geo point | 247 | |
| website (`Url1`) | 219 | |
| time zone | 216 | |
| foundation date | 213 | |
| postal address | 210 | |
| Google place id | 80 | |
| phone | 85 | |
| **Mass/adoration/confession times** (`EventsHuman`) | **74** | free text, untranslated |
| **opening hours** (`OpeningHoursHuman`) | **71** | free text, untranslated |
| **email** | **63** | |
| opening hours, structured (`OpeningHoursSpecification`) | 42 | renders nowhere |
| events, structured (`EventsJson`) | 4 | renders nowhere, unwritable |

No shrine's opening hours have been edited since **2022-04-15**.

So the constraint on this project is **verification throughput, not field capacity**.
The schema can already hold more than we know; what we lack is a way to find out what is
true, record who said so, and ask again later. Every decision below follows from that.

## What already exists — do not rebuild it

Three things that look like work for this project and are already done. Read this
section before writing any code.

- **The v3 API can already read and write associations.**
  `GET /api/v3/associations`, `GET`/`PATCH /api/v3/associations/{identifier}`, with
  `ETag`/`If-Match`, gated on `sch_api_bot`, validated by *the moderator form's own
  `InputFilter`* rather than a copy of its rules, and every changed field recorded in
  `sch_changes` under the bot's user id. See [api-v3.md](api-v3.md). There is also
  precedent for an agent working productively through it —
  [api-change-requests-response.md](api-change-requests-response.md) is the answer to ten
  requests filed by the agent that translated 1,383 phrases.
- **The validation rules are already in one place, off the MVC stack.**
  `App\Schoenstatt\Association\AssociationInputFilterSpec` holds them,
  `AssociationForm` delegates to it, and `AssociationValidator` gives the API the form's
  own filter. `test/Integration/AssociationValidationParityTest` fails if the web and API
  surfaces diverge. A new field is added *there*, once, and both surfaces get it.
- **The pattern for letting a named human edit one record without an account exists.**
  `Books\Model\BorrowerTokenTable` (table `lib_borrower_tokens`): a scoped, sha256-hashed,
  expiring, deliberately **not** single-use token that authorises exactly one person at one
  library, on a Symfony-served page so its authorization is ordinary code rather than an
  ACL role. Read [libraries.md](libraries.md) for why it is shaped that way. Shrine
  verification wants the same shape for the same reasons, including keeping any person id
  out of the URL.

## What is actually missing

Five gaps, in the order they block things.

1. **There is no way to record a confirmation.** A `PATCH` that sends a value already
   stored answers `200` with `"changed": []` and writes nothing — deliberately, so a
   polling agent does not fill `sch_changes` with noise. The consequence is that "an agent
   checked this shrine's website today and the hours still say 9–5" is unrecordable. For a
   project whose whole problem is staleness, that is the central gap.
2. **There is no provenance.** A scraped value and a value the shrine's own rector supplied
   are byte-identical in the database and overwrite each other silently. Nothing records
   where a value came from, so nothing can decide which of two claims wins.
3. **The structured fields are write-only.** `OpeningHoursSpecification` and `EventsJson`
   feed only the JSON-LD that `SchoenstattTable` emits for search engines;
   `templates/schoenstatt/association.html.twig:156-162` renders **only** the markdown free
   text. A contributor who fills in the JSON sees the page not change. That is why there are
   42 rows of one and 4 of the other.
4. **`eventsJson` is currently unwritable by anything.** Its form element is commented out
   (`AssociationForm:245`), and its key was deliberately removed from
   `AssociationInputFilterSpec` because a specification key naming no element still becomes
   an input — so `getData()` emitted `eventsJson => null` on every save and erased the four
   stored values. Since the API's writable field set is *derived* from that specification,
   the API cannot write it either. Structured event data has no door at all right now.
5. **74% of shrines are unreachable.** Only **64 of 250** have any email address:
   63 association addresses plus exactly one reachable through a role assignment
   (`sch_roles` → `sch_assignments` → `sch_persons`). Every shrine has roles defined, but
   only 265 assignments exist site-wide and almost none carry an address. Step 4 of this
   project — asking the people on the ground to verify — is blocked for three shrines in
   four until that changes.

## Decisions taken 2026-08-23

Four, with the reasoning, because each closes off alternatives that will otherwise be
re-proposed.

### 1. Provenance and confirmation, with writes staying direct

Not a moderation queue. A queue is the safer-sounding option and it is the wrong one here:
the measurement above is that this database has received roughly one moderator's attention
since 2019, so a queue would become the place updates go to wait. Writes stay direct and
auditable — `sch_changes` already makes every agent edit attributable and revertible beside
the human ones, which is the safety model the API was built on.

**The granularity is the field group, not the field** (settled 2026-08-23). A group is
what the page already displays and what a person confirming a shrine actually confirms —
"the contact details are right", not eleven separate assertions about `phone2Label`. It also
matches the shape `sch_associations` already declares. The cost is that an agent reporting
"the website is current but the phone number is stale" has to be refused that precision;
that is accepted, because the alternative is a provenance row per field per shrine and a
form nobody will fill in.

What gets added is the two facts a direct write currently loses:

- **Where the value came from** — a source class (the shrine itself, a diocesan or national
  office, the shrine's own website, a third-party directory, an agent's inference) and,
  where there is one, a URL. A human-sourced value outranks a scraped one, and that ranking
  is enforced in code rather than left to whoever writes last.
- **When it was last confirmed**, as distinct from when it last changed. These are different
  facts and only one of them is currently recorded. "Verified correct on 2026-08-23" is the
  thing a pilgrim needs and the thing that decides who we email next.

**The existing `*UpdatedOn` column groups cannot serve as the confirmation store.** They
are close enough to look usable and they are not: the stamp moves on saves that changed
nothing, so 3 shrines carry an `OpeningHoursHumanUpdatedOn` with no hours value at all, 1
carries an `EventsHumanUpdatedOn` with no events value, and 2 carry a
`ContactInfoUpdatedOn` with no email, no URL and no address. A "last verified" reading off
those columns would report confidence in fields that are empty.

**And two of the ten declared groups have never been written at all.** `EmailsUpdatedOn`,
`EmailsUpdatedBy`, `PhonesUpdatedOn` and `PhonesUpdatedBy` are 0 of 498 on
`sch_associations` and 0 of 325 on `sch_persons`, because
`module/Schoenstatt/config/module.config.php` maps the same seven field names **twice inside
one array literal** — `'email' => 'emails'` at line 1505 and `'email' => 'contactInfo'` at
line 1515, and the same for `phone1` through `phone3Label`. PHP keeps the last value for a
duplicate key silently, so every email and phone field lands in the `contactInfo` group and
the `emails` and `phones` groups are unreachable. The person entity has the identical
duplication at lines 1298–1335.

The visible consequence: **the "✓ Up-to-date" tooltip has never rendered, on any page, on
either front controller.** It is guarded by `{% if entity.phonesUpdatedOn is not null %}`
(`templates/schoenstatt/association.html.twig:77`, and the same on `person.html.twig:104`
and both `.phtml` originals) on a column nothing writes. The only verification indicator the
UI has ever had is dead — which also makes the `BACKLOG.md` item about it saying `jeff`
moot, since the block it is in is unreachable.

So: provenance gets its own store. Reusing a group scheme whose declared groups can be
silently unreachable, and whose stamps move on saves that changed nothing, would build the
staleness problem into the fix for it.

### 2. Structured times primary, free text as the fallback

The structured model becomes the thing the page renders and the free text stays as an
authoritative fallback and as the place for exceptions prose.

The deciding argument is **translation**, not machine-readability. `EventsHuman` and
`OpeningHoursHuman` are untranslated — the form says so in its own help text — so today a
Brazilian shrine's Mass schedule is readable in Portuguese and nothing else, on a site whose
whole architecture is a five-language contract (see [sitemap.md](sitemap.md)). A structured
schedule renders in all five languages from one entry. Free text never can, at any budget.

The representation is **schema.org [`Schedule`](https://schema.org/Schedule)** —
`repeatFrequency`, `byDay`, `byMonth`, `byMonthDay`, `startTime`, `scheduleTimezone`,
`exceptDate` — which is RFC 5545 `RRULE`-shaped and therefore also exports to iCalendar and
to Google's event handling. This is not a new idea here: the four existing `EventsJson` rows
already use exactly that vocabulary, so whoever wrote them in 2019 was ahead of the
documentation. Access hours stay on `spatie/opening-hours`, which is already a dependency
and already has a validator (`Schoenstatt\Validator\OpeningHoursSpecificationJson`).

The free text is **not** migrated away. The 74 stored `EventsHuman` values carry exceptions
and caveats — summer schedules, months without Mass, "check the Facebook page", a bare link
to a parish timetable — that no schedule model expresses, and discarding them to reach a
clean end state would lose real information.

### 3. A shrine-focused contributor form, separate from the administrator form

The 46-field `AssociationForm` serves all 23 association kinds and is rendered as a flat
list (`templates/schoenstatt/_association-fields.html.twig`). A person editing a shrine
currently faces `parentId`, `kind`, `isAuthor`, `isLifeCommunity`, `internalName`, two
"is translateable" checkboxes and `adminNotes` before reaching anything a pilgrim would
recognise. That is the friction.

The answer is a shrine profile form showing only the pilgrim-relevant fields, grouped, with
a purpose-built hours-and-times widget in place of the raw-JSON textarea that currently
carries a link to a GitHub README as its help text. It shares the same `InputFilter`, so
there is no second rule set to keep in step. The existing form stays for administrators.

**No shrine-specific table.** One table with a `kind` discriminator is the right model and
is the one already in use; the friction is presentational. Two schema notes that follow
from having looked:

- **Twenty-one columns are 100% `NULL` across all 498 associations.** Sixteen of them are
  droppable outright and are listed under "The dead columns" below; four more are dead
  because of a defect rather than by intent, and one — `SuppressionDate` — is not dead at
  all. The distinction matters, so it has its own section.
- `Email2` is declared `int(200)` on a table whose every other address column is
  `varchar`. It holds one row. `Post2*` holds nine, `ContactNotes` one.

### 4. Bootstrap 5 first — the UI half of this project is gated

The front-end stack is **Bootstrap 3.0.3** (released 2013, long EOL), jQuery, selectize,
bootstrap-markdown, and 60 hand-concatenated `gen-*.js` bundles with no build tool. The
decision is to migrate off it *before* building shrine UI, rather than adding new widgets to
a dead framework.

This has a real sequencing cost and it should be stated plainly rather than discovered:
**the new contributor form and the verification micro-form cannot ship until that migration
does.** What is *not* gated, and should proceed in parallel, is everything server-side —
the provenance store, the times model, the API additions, the JSON-LD and rendering work,
and all of the contact-address acquisition. Those are the phases below numbered 1–3.

The Bootstrap 3 → 5 migration is **not currently tracked anywhere** — it is not in
[BACKLOG.md](BACKLOG.md) and it is not one of the "long-term answers on file" recorded
there for the Symfony rungs. It needs its own entry and probably its own document; it is a
site-wide CSS and JS decision, not a shrine one. Note one adjacency in its favour:
[BACKLOG.md](BACKLOG.md) already records TwbBundle → Symfony's built-in Bootstrap form
themes as the eventual form-rendering answer, and `SionModel\Form\BootstrapFormRenderer`
currently reproduces TwbBundle's Bootstrap 3 markup byte-for-byte. Those two facts meet in
this migration.

When it lands, the guidance for new shrine widgets is: **no SPA framework.** This is a
server-rendered Twig application with no Node build chain, and adding one buys little and
costs maintenance permanently. Dependency-free custom elements plus HTMX is the option that
still works in ten years, and a structured Mass-times editor is precisely the widget worth
writing that way.

## On coordinating with masstimes.org and the Mass Times app

Investigated 2026-08-23. **There is no public API and no documented data programme.**
masstimes.org is run by the Mass Times Trust and powered by FAITH Catholic; the database
covers roughly 117,000 churches in 201 countries and territories and is maintained by
dioceses, parishes and volunteers. Its `/about` page describes no feed, no licensing, no
partnership contact. USCCB surfaces its data, so bilateral arrangements evidently exist —
they are just not self-service.

Two consequences for this project:

- **This is a human conversation, not an integration**, and it should not be a dependency
  of any phase here. It is also worth noticing that the ask may run the other way: many
  Schoenstatt shrines are not parishes, so for those records *we* are plausibly the better
  source and the offer is ours to make. That is a stronger opening than asking for a feed.
- **What we can do unilaterally is be a good citizen of the format.** Emit clean, complete
  schema.org — `CatholicChurch` with `openingHoursSpecification` and `Event`/`eventSchedule`
  — so that crawlers and volunteers can find and reuse it, and store any third-party record
  URL (masstimes.org, a diocesan directory) as an external identifier alongside
  `GooglePlaceId`. That also gives the provenance model of decision 1 somewhere to point
  when a value came from a directory rather than from the shrine.

## The dead columns

Decided 2026-08-23: drop them. Measured across all 498 associations, not just the 250
shrines. **Twenty-one columns hold zero non-`NULL` values**, and they are not all the same
kind of dead.

**The migration is `database/db8.9.sql`** — 20 columns off `sch_associations` (15 prose +
`SlugFr` + the 4 stamps) and 4 off `sch_persons`. Written and rehearsed against the capsule
2026-08-23: applied in 101 ms, `@verify` returns no rows, and re-running the two `ALTER`
statements by hand exits 0, which is the `@idempotent: yes` claim actually tested rather than
asserted. `sch_associations` goes from 98 columns to 78.

**It is `@phase: post` and that is mandatory, not a preference.**
`SchoenstattTable::getSelectPrototype()` builds the association query from
`array_values($entitySpec->updateColumns)` — it enumerates its columns rather than issuing
`SELECT *` — so dropping before the code ships means the still-running old release asks for
columns that no longer exist: errno 1054 on every association page. The same trap sits in
`getUnlinkedPersons()`, which names all four person stamp columns in a **raw SQL string** and
has 14 callers including the library pages, the publication authors and the borrower lists.
`@destructive: yes` is the mirror image: once the columns are gone, any release predating the
config change cannot serve those pages, so both rollback paths must refuse it.

### Drop outright — 16 columns, no live code reference

| columns | |
| --- | --- |
| `IdealEn`, `IdealEs`, `IdealDe`, `IdealPt`, `IdealFr` | 5 |
| `VisitorsInformationEn`, `VisitorsInformationEs`, `VisitorsInformationDe`, `VisitorsInformationPt`, `VisitorsInformationFr` | 5 |
| `HistoryEn`, `HistoryEs`, `HistoryDe`, `HistoryPt`, `HistoryFr` | 5 |
| `SlugFr` | 1 |

The fifteen prose columns appear in the codebase **only** in commented-out
`update_columns` lines in `module/Schoenstatt/config/module.config.php`, each already
marked `@deprecated`. They were a previous generation's attempt at exactly the
shrine-specific prose this project is about, superseded by `PublicNotes` plus the phrase
table.

There is a second, decisive reason they can never be revived: **their locale set is wrong.**
They cover `en/es/de/pt/`**`fr`**, while this site's five configured locales are
`en_US/es_ES/de_DE/pt_BR/`**`it_IT`**. French was planned and Italian arrived instead, so
these columns could not serve the current site even if somebody wanted them to. `SlugFr` is
dead for the same reason — `SchoenstattTable::LOCALES_TO_SLUG_COLUMN_NAME` names five slug
columns and `SlugFr` is not one of them.

One care point when writing the migration: **a live `SlugFr` column exists on a different
table.** `module/Books/config/module.config.php:1397` maps `slugFr => SlugFr` and
`Books\Model\EventTextTable:134` reads it. Only `sch_associations.SlugFr` is being dropped.

### Dead by defect — 4 columns, dropped for a different reason

`EmailsUpdatedOn`, `EmailsUpdatedBy`, `PhonesUpdatedOn`, `PhonesUpdatedBy` are empty because
of the duplicate-key bug described under decision 1, not because the concept was abandoned.
The same four are empty on `sch_persons`.

Decided 2026-08-23: **drop all four, on both tables.** The reasoning is not "they are empty"
— it is that **this whole scheme is legacy either way.** Decision 1 gives provenance its own
store precisely because these columns cannot be trusted: the stamps move on saves that
changed nothing, and two of the ten declared groups turned out to be silently unreachable.
Fixing the mapping instead would start collecting data in a scheme we intend to supersede,
and would make the "✓ Up-to-date" tooltip render for the first time — surfacing the
hardcoded `jeff` that `BACKLOG.md` records, which would then have to be fixed in the same
change. Dropping loses nothing, because there is nothing in them.

The duplicate `'emails'` and `'phones'` lines come out of
`module/Schoenstatt/config/module.config.php` in the same change, for both the association
and person entities, since they would otherwise name columns that no longer exist.

**The granularity question itself is real and moves to phase 1.** "Should emails and phones
be their own provenance groups, or is one contact-details group enough?" is a good question
about the *new* store. It is not a reason to repair the old one.

### Not dead — leave `SuppressionDate` alone

Zero rows, and its form element is commented out (`AssociationForm:327`,
`fields-partial.phtml:48`) — but it is **read and rendered on both front controllers**
(`templates/schoenstatt/association.html.twig:140`, `associations/show.phtml:162`), mapped
in `update_columns`, and returned by `SchoenstattTable`. So it is not a dead column; it is a
live display field with no write path, the same category as `eventsJson` in gap 4.

For this project it is arguably wanted rather than unwanted: a suppressed or closed shrine
is exactly the kind of fact the verification loop will turn up, and `IsActive` alone cannot
say *when*. Giving it a write path belongs in the shrine form of decision 3.

### Other sparse columns — not dropping, recorded so nobody re-measures

Low counts that are genuinely used: `ContactNotes` (1), `Email2` (1), `Post2Country` (2),
`Phone3`/`Phone3Label` (3), `EventsJson` (4), `TwitterUser` (5), `Post2Street2` (7),
`Post2CityState`/`Post2Street1`/`Post2Zip` (9), `Post1Country` (12), `Url3Label` (12).
`Email2` is worth one note independent of this project: it is declared `int(200)` on a table
whose every other address column is `varchar`.

## Phases

Dependencies are the point of this ordering. Phases 1–3 are server-side and can run in
parallel with the Bootstrap migration; phase 4 cannot start until it lands.

**Phase 1 — provenance and confirmation. Built 2026-08-23**, except for the shrine-page
display. `sch_provenance` (`database/db9.0.sql`) plus `App\Provenance\*`:

| piece | what it is |
| --- | --- |
| `SourceClass` | the five sources and their ranks — the policy, deliberately not in the schema |
| `Outcome` | `confirmed` / `corrected` / `competing`; the third is why the table is append-only |
| `FieldGroups` | **derived** from the entity config, never hand-listed — see below |
| `WriteGate` | the ranking rule and the 180-day window |
| `Assertion`, `ProvenanceStore` | the row, and append-only reads (`latestFor`, `currentFor`, `historyFor`) |
| `Recorder`, `Assessment` | plan a write, then record what it did — two calls on purpose |
| `ApiEnvelope` | the four reserved keys a caller declares provenance with |

Wired into `PATCH /api/v3/associations/{id}`: a patch that changes nothing now records a
**confirmation** instead of silently doing nothing, and a group a fresher better-sourced claim
protects is **withheld** rather than overwritten, with the finding kept. Documented in
[api-v3.md](api-v3.md) § Provenance, because it changes what an existing caller sees.

**The groups are derived, and that is the load-bearing decision.** A field's group is its
`many_to_one_update_columns` entry, else the field itself when a `<field>UpdatedOn` column
exists. Against the real config that yields seven groups — `contactInfo` (17 fields) and one
each for `openingHoursHuman`, `openingHoursSpecificationJson`, `eventsHuman`, `eventsJson`,
`publicNotes`, `adminNotes` — and leaves `name`, `kind`, `country`, `parentId` and `geoPoint`
ungrouped, which is correct: they are identity, nobody verifies them on a schedule, and they
are never withheld. A hand-maintained copy of that mapping is the exact mistake db8.9 cleaned
up, so there is one place it can be wrong.

*Still to do in this phase:* surface it on the shrine page ("contact details verified in
March"), and record provenance on the **moderator form** save — until that lands, human edits
through the web form file no assertion, so the ranking has little high-ranked material to
work with. That is the next piece, not a nice-to-have.
*Blocks everything else that matters.*

**Phase 2 — the times and hours model.** A `Schedule`-shaped store for Mass, adoration and
confession; render it in all five languages; render the structured opening hours that 42
shrines already have and nobody can see; give `eventsJson` a door again. Ends with the
JSON-LD emitting both, and an iCalendar export if it is cheap once the model exists.
*Depends on nothing; independent of phase 1.*

**Phase 3 — API additions for agents.** Confirmation-without-change, provenance on write,
the structured-times fields, and a decision on `POST` (creation is currently refused because
kind, parent and roles are human decisions — that reasoning still holds, and a wayside
shrine found online is the case that will test it). Also worth exposing: a "stalest records"
query, so an agent can be pointed at the work rather than crawling the collection.
*Depends on phases 1 and 2 for the vocabulary it exposes.*

**Phase 4 — the human loop.** Contact-address acquisition first, because 74% unreachable is
the binding constraint. Then the emailed verification request carrying the current data, and
a token-scoped correction page on the `lib_borrower_tokens` pattern. The "authoritative
tip-off from a higher office" case — a national or diocesan office correcting one of its
shrines — is a variant of the same mechanism with a wider scope, and the provenance source
ranking from phase 1 is what makes their word outrank a scrape.
*Gated on the Bootstrap 5 migration for its UI. The address acquisition is not.*

Work happening in a separate directory on finding shrine information online feeds phases 3
and 4 and is not tracked here. The most valuable thing it can produce is **email addresses**,
not hours.

## Deliberately not being built

- **A moderation queue.** See decision 1. Revisit only if the volume of agent writes turns
  out to need it, and revisit with the `sch_changes` evidence in hand.
- **Association creation by agents.** Unchanged from [api-v3.md](api-v3.md): `POST` stays
  unimplemented until phase 3 decides otherwise.
- **Migrating the free-text hours and events away.** Decision 2.
- **A JavaScript framework.** Decision 4.
- **Anything depending on a masstimes.org integration.** No API exists.

## Open questions

- **Which field groups does the new provenance store recognise?** Inherited from
  `sch_associations` it would be contact details, opening hours, events, public notes. The
  open part is whether emails and phones are their own groups or one contact-details group is
  enough — the question the legacy scheme was reaching for when it broke.
- ~~**Does a confirmation belong in `sch_changes`?**~~ **Settled 2026-08-23: no.** The
  deciding argument was not tidiness. SionModel already has a confirm-without-changing
  primitive — `updateEntity()`'s `$fieldsToTouch`, which nothing in this codebase passes — and
  it would bump the entity's own `UpdatedOn`. That column is the only thing that can still say
  "216 of 250 shrines were last edited in 2019", the measurement this project rests on, so
  recording confirmations through it would erase the staleness signal within a year.
- **What is the unit of a verification email?** One shrine to one recipient is the obvious
  answer, but a national office holding twenty shrines is the case that gets twenty
  corrections from one message.
- **Does `SuppressionDate` get its write path in the shrine form, or does the concept need
  more thought?** It renders today and can never be set.

## Findings log

Corrections and measurements, newest first. Recorded here so they are not rediscovered.

- **2026-08-23 — two declared field groups have never been written, from a duplicate key in
  a PHP array literal.** `module/Schoenstatt/config/module.config.php` maps `email`, `phone1`,
  `phone1Label`, `phone2`, `phone2Label`, `phone3` and `phone3Label` to `'emails'`/`'phones'`
  and then, **twenty lines later in the same literal**, to `'contactInfo'`. PHP keeps the last
  value silently. Result: `EmailsUpdatedOn/By` and `PhonesUpdatedOn/By` are 0 of 498 on
  `sch_associations` and 0 of 325 on `sch_persons`, and the `contactInfo` group absorbs
  everything (247 of 498). The person entity has the same duplication independently. The
  general lesson is that a duplicate key in a config array is invisible to every tool this
  repo runs — no lint, no PHPStan level, no test — and the only symptom is a column that
  stays empty.
- **2026-08-23 — the "✓ Up-to-date" tooltip has never rendered anywhere.** It is guarded on
  `entity.phonesUpdatedOn` (`association.html.twig:77`, `person.html.twig:104`, and both
  `.phtml` originals) and that column is 0 of 498 per the finding above. So the site's only
  verification indicator has always been invisible, on both front controllers, and the
  `BACKLOG.md` item about it displaying a hardcoded `jeff` describes unreachable code. Fixing
  the mapping without fixing the name would make the literal visible for the first time.
- **2026-08-23 — the dead prose columns use the wrong locale set.** `Ideal*`,
  `VisitorsInformation*` and `History*` cover `en/es/de/pt/fr`; the site's configured locales
  are `en_US/es_ES/de_DE/pt_BR/it_IT`. French was planned and Italian arrived instead, so
  these 15 columns could never have served the current site. `SlugFr` is dead for the same
  reason. This is what settled "drop, don't revive" rather than the row count alone.
- **2026-08-23 — the capsule cannot be rebuilt from `database/dumps/` alone, and finding that
  out cost a working capsule.** Re-importing (`docker compose down -v`) produced a database
  that failed 113 integration tests, because the initdb `zz-*.sql` set carried only 5 of the
  27 migrations that postdate the dump. Three things make this worse than "add the missing
  files", and all three were learned the hard way:
  1. **Some `database/*.sql` migrations depend on a submodule's console migrations.** Several
     `db7.x` files select `trans_phrases.phrase_hash`, which is added by
     `bin/console jtranslate:migrate` — not by any SQL file. initdb runs SQL only, so the
     correct order *interleaves* SQL and console steps and the `zz-` mechanism cannot express
     it. The working order is: `db6.6`–`db6.9`, then `jtranslate:migrate`, then `db7.0`–`db7.8`,
     then `db8.0`–`db8.6`, then `db8.9`–`db9.0`, then `jtranslate:export-catalogs`.
  2. **Replaying out of order breaks migrations that reference dropped columns.** `db7.0` (the
     charset conversion) names `VisitorsInformationEn`, which `db8.9` drops — so `db8.9` must
     come after it, and applying the newest migration first makes the older one unrunnable.
  3. **`mariadb` accepts connections while initdb is still importing.** A readiness check on
     one table passing does not mean the dump has finished; migrations applied in that window
     hit "table doesn't exist" for tables not yet loaded, and land on rows the import then
     replaces. Wait for a *stable* table count, not for one table.
  What cannot be recovered by any replay is **data created after the dump**. The capsule's
  translations now predate the 2026-08-10 Italian campaign, so
  `test/Smoke/BreadcrumbDataLabelsSmokeTest` fails on one shrine name — verified to fail
  identically on `master`. Only a fresh production export fixes that.
- **2026-08-23 — the field-group `*UpdatedOn` columns are stamped on saves that changed
  nothing.** 3 shrines carry an `OpeningHoursHumanUpdatedOn` with a `NULL` hours value, 1 an
  `EventsHumanUpdatedOn` with no events, 2 a `ContactInfoUpdatedOn` with no email, URL or
  address. This is why decision 1 gives provenance its own store instead of reusing them.
- **2026-08-23 — a capsule reading of "hours edited yesterday" was test noise.** Association
  319 (Original Schoenstatt Shrine, used by the smoke suites) showed
  `OpeningHoursHumanUpdatedOn = 2026-08-22` with `OpeningHoursHuman IS NULL`, written by a
  test account, while its own `UpdatedOn` still read 2019. Excluding it, the newest real
  opening-hours edit across all 250 shrines is **2022-04-15**. The general lesson is the one
  CLAUDE.md already records about `user` and `trans_phrases`: **the capsule's row counts are
  representative of production, but tables the test suites write to are not.**
  `sch_associations` is now one of those tables.
- **2026-08-23 — `EventsJson` has no write path at all**, in either the form or the API, and
  the reason is a correct fix to a worse bug (the form was erasing all four rows on save).
  Gap 4 above.
- **2026-08-23 — masstimes.org publishes no API and no data programme.** Gap closed as an
  option; see the section above.
