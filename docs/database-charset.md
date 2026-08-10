# Database engine, charset and collation

The standard for every table this application uses:

```
ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci
```

Applied by [`database/db7.0.sql`](../database/db7.0.sql) (29 tables) and
[`database/db7.1.sql`](../database/db7.1.sql) (`sch_visits`, split out because it
is larger than everything else combined). New tables should be created this way;
SionModel's `dist/*.sql` templates and JTranslate's migration DDL already are.

## What it replaced

Four collations coexisted, none of them chosen — they accreted:

| collation | tables | origin |
| --- | --- | --- |
| `utf8mb3_general_ci` | most | the original schema |
| `utf8mb3_unicode_ci` | `user`, `user_api_token` | ZfcUser/JUser lineage |
| `latin1_swedish_ci` | `comments`, `events`, `files`, `predicates`, `relationships` | MySQL's own default, never overridden |
| `utf8mb4_unicode_ci` | `jtranslate_migration` | created recently by JTranslate's runner |

Eleven tables were also still **MyISAM**, which has no crash recovery and no
transactions. Three of those were live application tables — `lib_books`,
`sch_roles`, `sch_assignments`.

Mixed collations are not merely untidy. A cross-table string comparison between
two of them raises *Illegal mix of collations* at runtime, in whichever query
reaches it first.

## Why `utf8mb4_unicode_520_ci`

It is the newest Unicode Collation Algorithm available on **both** engines this
application could run on. The two collations people reach for by default are each
unavailable here:

- MySQL 8's default `utf8mb4_0900_ai_ci` (UCA 9.0.0) does not exist on MariaDB.
- MariaDB's `utf8mb4_uca1400_*` family (UCA 14.0.0) arrived in MariaDB 11.
  Production runs **10.11**, which has *zero* of them — verified against the
  server, not assumed.
- `utf8mb4_unicode_520_ci` (UCA 5.2.0) exists on both.

`utf8mb4_general_ci` was rejected outright: it is not a Unicode collation at all
but a legacy MySQL 4.x sort table. Measured on this server:

| comparison | `general_ci` | `unicode_ci` | `unicode_520_ci` |
| --- | --- | --- | --- |
| `straße` = `strasse` | ✗ | ✓ | ✓ |
| `Æon` = `Aeon` | ✗ | ✗ | ✓ |
| `Œuvre` = `Oeuvre` | ✗ | ✓ | ✓ |
| `ﬁnal` = `final` | ✗ | ✓ | ✓ |
| `Ⅻ` = `XII` | ✗ | ✓ | ✓ |

For a catalogue holding German, Spanish and Portuguese titles, `straße` failing to
match `strasse` is a live search bug. `utf8mb4_unicode_ci` (UCA 4.0.0, 2003) gets
four of the five right but predates the Æ/AE expansion.

Contrary to a reasonable first guess, moving off `general_ci` did **not** change
sort order on real data: `general_ci` does fold Latin-1 accents, so `Öfele` already
sorted after `Odero`. The change is in equality, not ordering.

Cost on existing data: none. Measured before applying — 0 unique-key collisions on
`lib_books`, and identical `DISTINCT` counts under old and new collations for
`lib_books.author` (12,406), `.category` (864), `.public_tags` (738) and
`sch_roles.RoleTitle` (39), across 8,431 rows containing non-ASCII authors.

### The one caveat to carry forward

Every pre-`uca1400` collation is **PAD SPACE** — trailing spaces are ignored in
comparison. Both modern families (`0900_*`, `uca1400_*`) are **NO PAD**. That
semantic changes once more whenever this database moves to MariaDB 11 or MySQL 8.
It is a property of the era, not of this choice, and it would apply to any `ci`
collation selectable on 10.11.

## The connection charset is half the job

The application connects through `PDO::MYSQL_ATTR_INIT_COMMAND`, and in MariaDB
**`utf8` means `utf8mb3`**. A connection opened with `SET NAMES UTF8` is a 3-byte
connection: the server silently converts anything outside the BMP down to `?` on
the way out. Demonstrated on this server — two *different* emoji sent over such a
connection came back identical and compared equal.

So the init command must read:

```php
'driver_options' => [
    (class_exists('Pdo\Mysql') ? \Pdo\Mysql::ATTR_INIT_COMMAND : \PDO::MYSQL_ATTR_INIT_COMMAND)
        => 'SET NAMES utf8mb4;',
],
```

`config/autoload/local.php` is **gitignored**, so every machine and the server
carries its own copy of that line. `config/autoload/local.php.dist` and
`docker/local.docker.php` are updated in the repository; **production's copy is a
manual server-side edit** and deploying does not perform it.

Either order is safe while no 4-byte data exists yet. Tables first is recommended.

## Scope: what is *not* converted, and why

A table is in scope if the repository mentions it in `module/`, `src/`, `config/`,
`templates/`, `tools/`, `bin/`, `test/` or `public/`. Historical migrations under
`database/` and prose in `docs/` deliberately do not count — old migration files
name nearly every table this project has ever had.

Nineteen tables are therefore **server maintenance**, not application migrations:

- The nine Bible tables — `bib_books`, `bib_book_abbreviations`, `bib_dh_page`,
  `bib_greek_root_words`, `bib_verses`, and the four `bib_*_temp` tables that exist
  only in production. The Bible module was removed 2026-08-05. `bib_verses` is the
  largest MyISAM table in production at 236,422 rows / 45.8 MiB.
- The five `b_bib*` tables — source data for a one-time import from the old "sion"
  bibliography system. The only code that read them was
  `PublicationsTable::importPublications()`, deleted alongside `db7.0` because it
  called `issett()` and fatalled on every invocation.
- `sch_dictionary_dictionary` (3,084 rows) and `sch_dictionary_users` (10 rows,
  with a `password` column holding legacy credential hashes nothing reads).
- `user_remember_me`. It holds a foreign key into `user`, but on `user_id INT`, so
  converting `user` does not touch it.
- `sch_visits_rollover_2023-11-02` and `sch_visits_rollover_2025-07-17`.

The **database default charset** is also still `latin1_swedish_ci`, so a table
created without an explicit charset inherits latin1. That is a server-level
property (`ALTER DATABASE`), outside these migrations, and belongs with the same
maintenance pass.

## Traps found while doing this

Recorded because each cost real time and each will recur.

**`SET FOREIGN_KEY_CHECKS = 0` does not permit converting a referenced column.**
MariaDB raises error 1833 regardless — that flag governs row validation, not
column-type compatibility. `relationships.PredicateKind` → `predicates.PredicateKind`
is the only string foreign key in the database; it has to be dropped, both tables
converted, and the constraint recreated.

**`CONVERT TO CHARACTER SET` silently promotes column types.** A utf8mb3 `TEXT`
holds 21,845 characters and a utf8mb4 `TEXT` only 16,383, so the server promotes it
to `MEDIUMTEXT` to preserve capacity. Across the in-scope tables that is 45 columns
(31 `TEXT`→`MEDIUMTEXT`, 14 `MEDIUMTEXT`→`LONGTEXT`). `db7.0.sql` writes all 45 out
explicitly; the result was diffed against the server's implicit behaviour and is
byte-identical.

**Test latin1 data for double-encoding with a byte-exact round trip, not a NULL
check.** `CONVERT(BINARY(col) USING utf8mb4)` does not return NULL on invalid input,
it substitutes — so using it as a validity test reports every column as "already
UTF-8" and is worthless. The correct test is:

```sql
HEX(CONVERT(CONVERT(BINARY(`col`) USING utf8mb4) USING binary)) = HEX(`col`)
```

Here it proved the latin1 data is *genuine* latin1 (`podrían`, `años`, `México`),
so a plain `CONVERT TO` is right and the BLOB round-trip usually recommended for
double-encoded columns would itself have been the corruption.

**AUTO_INCREMENT survives MyISAM → InnoDB and a server restart.** MariaDB 10.11
persists the counter (MDEV-6076) rather than recomputing `MAX(id)+1`. Verified
directly. This mattered because `lib_checkouts.BookId` points at `lib_books.book_id`
with no foreign key, so id reuse would have silently re-pointed checkout history.

**A fresh capsule is not at production's migration level.** The local dump predates
`db6.6`–`db6.9`, so `user_api_token` does not exist until those are applied, and
`jtranslate_migration` never exists until JTranslate's runner has run. Both are
in-scope tables. `db7.0.sql` guards the latter and documents the former.

## Applied to production 2026-08-10

Both migrations ran and the site was deployed. Confirmed against the live database:

- All **29** tables the application uses are InnoDB + `utf8mb4_unicode_520_ci`,
  including the three former MyISAM ones and `sch_visits` at 1.3 GiB.
- The database got **smaller** — 5.1 GiB to 5.0 GiB. The rebuilds reclaimed more
  fragmentation than utf8mb4's widening cost: `sch_changes` 329→292 MiB,
  `sch_associations` 1.5 MiB→432 KiB. `lib_books` grew 7.7→9.5 MiB, which is the
  MyISAM→InnoDB clustered index rather than the charset.
- The latin1 conversion is verified correct on live data. Post-migration the
  round-trip check returns 2 rather than 0, which is the *expected inversion*: the
  column is utf8mb4 now, so its bytes are valid UTF-8 and the round trip succeeds.
  phpMyAdmin's warnings name the bytes — `0xC3BA` (`ú`) and `0xC3AD` (`í`), correct
  two-byte encodings. Double-encoding would have shown `0xC383 0xC2AD` for `í`.
- **`jtranslate_migration` does not exist in production.** JTranslate's migration
  runner has never been invoked there — `trans_phrases` and `trans_translations`
  long predate it. The guard in `db7.0.sql` handled this silently, which is what it
  was for. Whenever the runner is first run against production it will create the
  tracking table at 520 directly, so nothing needs revisiting.

  That first run happened on **2026-08-10**: `jtranslate_migration` now exists in
  production and records 001, 003 and 004 (002 followed after the deploy). See
  [DEPLOY.md](DEPLOY.md) for the sequence and its two non-obvious constraints. They also convert
  `trans_phrases.phrase` and `trans_translations.translation` from `VARCHAR(2000)` to
  `TEXT`, which is a widening in the same sense as the charset work here and carries
  no reinterpretation risk. Note the interaction with the `TEXT` promotion trap
  described above: these two columns are being promoted *deliberately*, because the
  varchar limit was silently truncating phrases, so they are the one case where a
  `TEXT` column is the intended outcome rather than a side effect to be caught.

Still outstanding, unchanged: the nineteen out-of-scope tables above, and the
**database default collation, which is still `latin1_swedish_ci`** — visible in
phpMyAdmin's summary row. Any table created without an explicit charset still
inherits latin1.
