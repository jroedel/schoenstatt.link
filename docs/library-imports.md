# Importing books from a spreadsheet

Cataloguing through the web form is one book at a time. Every library that has
ever put a real collection into this application catalogued in a spreadsheet
instead and imported it — Colegio Mayor's 12,394 books arrived in fourteen
imports between 2017 and 2019 — and until August 2026 that route was open to
exactly one of the six libraries. This document is what the feature is now, what
it does that is surprising, and what it still does not do.

## For a librarian

1. **Library → Imports → Blank template** (or **This library's books**, which is
   the same layout with the catalogue already in it). Both are `.xlsx`, and both
   carry an **Instructions** sheet beside the data.
2. Fill it in. One row per copy.
3. **Start new import**, name it, and upload the file. Nothing is read yet.
4. **Check the columns.** They are matched from the headings in your own file;
   change any the guess got wrong. What you save is what a re-run reads.
5. **Read the summary.** How many books would be created, changed and marked
   inactive, then the rows themselves — errors first, each with a reason.
6. **Run it.** Only this step writes anything.

### Three rules that decide what an import does

**The barcode identifies the copy.** A barcode already in the library updates
that book; a new one creates a book. It is the only thing matched on — not the
title, not the call number. `lib_books` has a unique index on
(`library_id`, `original_id`) and the import relies on it.

**An empty cell erases the stored value.** Present-and-empty means empty, not
"leave this alone". That is what makes the export-edit-reimport round trip able
to *clear* a field, and it is why the blank template ships fifteen columns rather
than the nineteen the import accepts: a template carrying **Admin notes** would
be a way to blank the admin notes of every book you touch. To leave a field
alone, delete its whole column.

**A complete import inactivates what the sheet does not list.** Every active book
with no row is marked inactive with the reason `Mass book import`. Rows that
errored are excluded from that sweep — a typo must not be able to retire a shelf.
This is the option worth being slow about: a complete import from a year-old
spreadsheet retires a year of acquisitions.

### The one behaviour that surprises everybody

**A linked literature record wins.** If a row carries a **Literature ID** naming
a record in the site-wide literature catalogue, that record supplies the title,
author, year, publisher, place of publication, pages, language and ISBN, and
whatever those columns say in the spreadsheet is ignored for that row.

This is not a bug and it is not new — it is what linking a copy to a work means —
but it has never been written down anywhere a librarian would see it. It shows up
as an export that does not round-trip: exporting Colegio Mayor and re-importing
it unchanged reports **326 changed books**, all of them among the 459 whose rows
name a record whose data differs. The review page now says so, and counts them.

Clear the Literature ID for a row if the spreadsheet should win.

## The columns

Headings are English and stable across locales, because a heading is a data key:
it decides what a re-import matches, and it has to mean the same thing to a file
saved in 2019 and read in 2026. The prose beside them — the Instructions sheet —
is translated.

Matching ignores capitals and accents, and a large set of aliases is recognised,
so **an existing spreadsheet does not need renaming**. Every heading Colegio
Mayor's files use (`Autor`, `Titulo`, `Lomo`, `Categoría`, `Categorías`, `ID`,
`Año`, `PubID`, `Biblioteca`, …) is one. `App\Books\Import\ImportColumns` is the
list, and it is the only list: the template writer, the matcher, the mapping
screen, the review table and the console command all read it.

Two traps live in that alias table and are deliberate:

- `Categoría` and `Categorías` differ by one letter and mean **different
  fields** — Category and Keywords. Normalisation folds accents and case but
  never touches the end of a word, precisely so those stay apart.
- `copyrightYear` is accepted as a *heading* and maps to the field
  `publishedYear`. They are not the same name and never were; see below.

Columns in the file that nothing recognises are **listed on screen**, not
silently dropped. Colegio Mayor's spreadsheets have five (`Orden`, `Imprimir`,
`Subtítulo`, `Info`, `Estado`), and nothing has ever said so.

## What the old import got wrong

All four were silent, and all four are fixed. They are recorded because each one
was invisible for years and the same shapes will recur.

**The year was thrown away.** The column map named `copyrightYear`; the book
entity calls that field `publishedYear` — commit `4933155` renamed it on
2018-11-02 — and `SionTable::createHelper()` and `updateHelper()` both skip a key
they have no column for. From that day every import read the year, cast it to an
int, and discarded it. Three imports ran afterwards.

**Language, keywords and admin tags were rewritten on every row.** Those three
come back from the database as arrays (`filterDbArray`) and were handed to
`updateEntity()` as strings. `updateHelper()` compares with `==`, and an array
never equals a string, so every matched book had its language rewritten and a
change row logged. December 2017 holds **44,095** `inLanguage` rows in
`sch_changes` against 734 for the next-busiest field that month. The importer now
splits those three fields into arrays before handing them over.

**A non-numeric barcode became zero.** `(int) $cell` ran *before* the
`is_numeric()` check meant to catch it, so the check was asked about an int and
always said yes. A blank barcode became barcode 0 and was planned as a new book;
two of them collided and blocked the whole file with a message about duplicates.
Import 1 still contains one such row, which is the single difference between the
old engine's plan and the new one's on that file.

**Errors had no reason.** Import 3's preview showed eight yellow rows and said
nothing about any of them. Six were rows carrying a Literature ID for a record
that does not exist — the case the page's own instructions describe as supported.
It is supported only when the record exists, which nothing said.

## The review page, and why running one is a POST

The old preview was **11,381 rows in a 5.27 MB HTML document**, of which 9,749
were books the spreadsheet changed nothing about, and under it a button marked
*Import data* that did the work on a plain submit. Nothing named the scale first.
Two imports last configured in August 2017 were still live in August 2026: import
3's plan retires **1,281** of Colegio Mayor's 10,874 active books, import 1's
retires **3,492**. `database/db8.3.sql` marks both `abandoned`, along with import
14, whose file path is `C:\Users\Ramon Vergara\Desktop\intento.xlsx`.

So the page now shows counts first, lists only what is not "no change", and puts
errors at the top with a sentence each. Running it is a POST carrying a CSRF
token **and a digest of the counts that were shown**. The plan is recomputed on
the POST and compared; if the catalogue or the file moved in between, the preview
is re-shown rather than applied. That costs a second plan — about nine seconds on
Colegio Mayor — and it is the price of the confirmation meaning something.

## When the browser is the wrong place

`bin/console books:import <id>` prints the same plan; `--apply` runs it, asking
first unless `--force` is passed. `--list` shows the imports and their ids.

It exists because a request cannot always finish. Applying a complete import of
PUC is 16,383 rows of writes, each of which `SionTable::updateEntity()` brackets
with two `getObject()` calls, against a production `max_execution_time` of 240
seconds. It is also how an import is run *for* somebody: the page needs
`administrate` on the library, a console on the server needs no account.

## Where the files live

Uploads go to `data/import/<libraryId>/`, named `<date>-<8 hex>-<your filename>`.
On the server that directory is `shared/data/import/`, symlinked into every
release — `tools/deploy-bootstrap.sh` already lists `import` in `SHARED_DATA`,
which matters more than it sounds: a release swap deletes anything inside the
release tree, so an upload directory that was not shared would lose every file at
the next deploy, and the loss would surface weeks later as imports whose file
"went missing".

Files are kept after an import runs, so a librarian can see what was actually
loaded. **Nothing prunes them.** Only paths under `data/import/` are ever opened:
an import row can name any path a 2017 text box accepted, and two of them do.

## What is still not built

- **No pruning of uploaded files**, and no size budget on the directory.
- **No transaction.** An import applies row by row, as it always has. A failure
  halfway through leaves the first half applied. Wrapping 16,000 writes in one
  transaction would hold locks on the whole library for the duration, which is
  a real trade rather than an oversight — but it has not been decided.
- **No undo.** `sch_changes` records every field change with its old value, so
  the information to reverse an import exists; nothing reads it that way.
- **Subtitle is not importable**, though four of Colegio Mayor's spreadsheets
  carry a `Subtítulo` column. `lib_books` has no column for it.
- **The mapping screen cannot split or combine columns.** One spreadsheet column
  feeds one field.
