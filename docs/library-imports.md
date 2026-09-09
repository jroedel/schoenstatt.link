# Importing books from a spreadsheet

Cataloguing through the web form is one book at a time; every real collection in
this application arrived by spreadsheet. The engine is
`App\Books\Import\LibraryImporter` — `plan()` computes what an import would do,
`apply()` does it — and both the web pages and the console command run it. The
route tree is `/library-imports`, Symfony-only, gated on `administrate` for the
library.

## For a librarian

1. **Library → Imports → Blank template** (or **This library's books**, the same
   layout with the catalogue already in it). Both are `.xlsx` with an
   **Instructions** sheet beside the data.
2. Fill it in. One row per copy.
3. **Start new import**, name it, upload the file. Nothing is read yet.
4. **Check the columns.** They are matched from the headings in your own file;
   change any the guess got wrong. What you save is what a re-run reads.
5. **Read the summary**: how many books would be created, changed and marked
   inactive, then the rows themselves — errors first, each with a reason.
6. **Run it.** Only this step writes anything.

## The four rules that decide what an import does

**The barcode identifies the copy.** A barcode already in the library updates that
book; a new one creates a book. Nothing else is matched on — not title, not call
number. `lib_books` has a unique index on (`library_id`, `original_id`) and the
import relies on it. A non-numeric or blank barcode is an error row, not barcode 0.

**An empty cell erases the stored value.** Present-and-empty means empty, not
"leave alone". That is what lets an export-edit-reimport round trip *clear* a
field, and it is why the blank template ships only the core columns
(`ImportColumn::$core`) rather than the nineteen the import accepts: a template
carrying **Admin notes** would blank the admin notes of every book touched. To
leave a field alone, delete its whole column.

**A complete import inactivates what the sheet does not list.** Every active book
with no row is marked inactive with reason `Mass book import`. Rows that errored
are excluded from the sweep — a typo must not retire a shelf. Be slow about this
option: a complete import from a year-old spreadsheet retires a year of
acquisitions.

**A linked literature record wins.** If a row's **Literature ID** names a record in
the site-wide literature catalogue, that record supplies **title, author, year,
publisher, place of publication, pages, language and ISBN**, and the spreadsheet's
values for those eight columns are ignored for that row. That is what linking a
copy to a work means; it shows up as an export that does not round-trip (Colegio
Mayor re-imported unchanged reports ~326 changed books), and the review page says
so and counts them. A Literature ID naming a record that does not exist is an
error row. Clear the Literature ID if the spreadsheet should win.

## The columns

`App\Books\Import\ImportColumns` is the column vocabulary, and **the only one**:
the template writer, the matcher, the mapping screen, the review table and the
console command all read it.

Headings are English and stable across locales, because a heading is a data key —
a file saved in 2019 must mean the same thing in 2026. The Instructions sheet is
translated. Matching ignores case and accents and recognises a large alias set,
so an existing spreadsheet needs no renaming (`Autor`, `Titulo`, `Lomo`,
`Categoría`, `Categorías`, `ID`, `Año`, `PubID`, `Biblioteca`, … all match).

Two deliberate traps in the alias table:

- `Categoría` and `Categorías` mean **different fields** — Category and Keywords.
  Normalisation folds accents and case but never touches the end of a word,
  precisely so those stay apart.
- `copyrightYear` is accepted as a *heading* and maps to the field
  `publishedYear`; they were never the same name, and a map keyed on the former
  writes nothing (`SionTable::createHelper()`/`updateHelper()` skip unknown keys).

Columns nothing recognises are **listed on screen**, not silently dropped.

Two engine rules that are not visible but explain the code: the multi-valued
fields (language, keywords, admin tags) come back from the database as arrays and
must be handed to `updateEntity()` as arrays, or `updateHelper()`'s `==` compare
rewrites every matched book and logs a change row per row; and every error row
carries a reason.

## Running one is a POST carrying a digest

The review page shows counts first, lists only what is not "no change", and puts
errors at the top. Running it posts a CSRF token **and `ImportPlan::digest()` of
the counts that were shown** (`RunImportForm`). The plan is recomputed on the POST
and compared; if the catalogue or the file moved in between, the preview is
re-shown rather than applied. That costs a second plan (~9 s on Colegio Mayor) and
is the price of the confirmation meaning something.

## From the console

```bash
bin/console books:import --list          # imports and their ids
bin/console books:import <id>            # print the plan
bin/console books:import <id> --apply    # run it, asking first
bin/console books:import <id> --apply --force
```

It exists because a request cannot always finish: applying a complete import of
PUC is 16,383 rows of writes, each bracketed by two `getObject()` calls in
`SionTable::updateEntity()`, against a production `max_execution_time` of 240 s.
It is also how an import is run *for* somebody — the page needs `administrate` on
the library; a console on the server needs no account.

## Where the files live

Uploads go to `data/import/<libraryId>/`, named `<date>-<8 hex>-<original name>`
(`App\Books\Import\ImportStorage`). On the server that is `shared/data/import/`,
symlinked into every release — `import` is in `tools/deploy-bootstrap.sh`'s
`SHARED_DATA`, and must stay there: a release swap deletes anything inside the
release tree, so an unshared upload directory loses every file at the next deploy.

Files are kept after an import runs so a librarian can see what was loaded.
**Nothing prunes them.** Only paths under `data/import/` are ever opened; an import
row can name any path a 2017 text box accepted, and two legacy rows do.

## Not built

- **No pruning** of uploaded files, and no size budget on the directory.
- **No transaction.** An import applies row by row; a failure halfway leaves the
  first half applied. One transaction over 16,000 writes would hold locks on the
  whole library for the duration — a real trade, not yet decided.
- **No undo.** `sch_changes` holds every old value, so the information exists;
  nothing reads it that way.
- **Subtitle is not importable**: `lib_books` has no column for it, though several
  Colegio Mayor spreadsheets carry `Subtítulo`.
- **The mapping screen cannot split or combine columns.** One column, one field.
