# `.lang.php` export fixtures

Three translation catalogs, **byte for byte as `Laminas\Code\Generator\ValueGenerator`
emitted them**, kept so that `TranslationArrayExportTest` can keep proving that
`TranslationsTable::exportArray()` reproduces that format exactly.

## Why they are here rather than in `module/*/language/`

They used to *be* the live catalogs, and the test simply globbed those. That worked
only by accident: the same files were doing two unrelated jobs — build output the
site renders from, and the frozen evidence of what the old generator produced.

When the catalogs stopped being tracked (`.gitignore`, 2026-08-09) both jobs went
away at once. A fresh checkout had no catalogs, the data provider returned an empty
set, and PHPUnit failed the test as an error — correctly, because its premise was
gone. Splitting the two jobs is the fix: the site's catalogs are generated and
ignored, and the fidelity evidence is a fixture that is never regenerated.

## Do not edit or regenerate these

Their whole value is that nothing in this repository produced them. Re-exporting
them through the current exporter would make the test compare the exporter against
itself, which proves nothing. That includes not adding a header comment to them —
a byte is a byte.

If a catalog format change is ever deliberate, these files are what has to be
argued with, and the argument belongs in the commit message.

## Provenance

Taken from the last commit that tracked them, `cc7f68f`:

| fixture | was |
| --- | --- |
| `Application.de_DE.lang.php` | `module/Application/language/de_DE.lang.php` |
| `Application.es_ES.lang.php` | `module/Application/language/es_ES.lang.php` |
| `Books.es_ES.lang.php` | `module/Books/language/es_ES.lang.php` |

Chosen for coverage rather than size: plain ASCII; an escaped single quote with
accented characters; and a 274-key file carrying an embedded newline, 141 non-ASCII
values and five escaped quotes. All three are `Schoenstatt`-project phrases from
this application's own modules — deliberately none from `SionModel`, whose committed
catalogs turned out to hold another project's rows.
