# Database engine, charset and collation

The standard for every table this application uses:

```
ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci
```

Applied by [`database/db7.0.sql`](../database/db7.0.sql) (29 tables) and
[`database/db7.1.sql`](../database/db7.1.sql) (`sch_visits`, split out because it
is larger than everything else combined). Create new tables this way; SionModel's
`dist/*.sql` templates and JTranslate's migration DDL already do. Mixed collations
are not merely untidy: a cross-table string comparison between two of them raises
*Illegal mix of collations* at runtime, in whichever query reaches it first, and
MyISAM has no crash recovery and no transactions.

## Why `utf8mb4_unicode_520_ci`

It is the newest Unicode Collation Algorithm available on **both** engines this
application could run on:

- MySQL 8's default `utf8mb4_0900_ai_ci` (UCA 9.0.0) does not exist on MariaDB.
- MariaDB's `utf8mb4_uca1400_*` family (UCA 14.0.0) arrived in MariaDB 11.
  Production runs **10.11**, which has none of them — verified on the server.
- `utf8mb4_unicode_520_ci` (UCA 5.2.0) exists on both.

`utf8mb4_general_ci` is not a Unicode collation at all but a legacy MySQL 4.x
sort table, and `utf8mb4_unicode_ci` (UCA 4.0.0, 2003) predates the Æ/AE
expansion. Measured on this server:

| comparison | `general_ci` | `unicode_ci` | `unicode_520_ci` |
| --- | --- | --- | --- |
| `straße` = `strasse` | ✗ | ✓ | ✓ |
| `Æon` = `Aeon` | ✗ | ✗ | ✓ |
| `Œuvre` = `Oeuvre` | ✗ | ✓ | ✓ |
| `ﬁnal` = `final` | ✗ | ✓ | ✓ |
| `Ⅻ` = `XII` | ✗ | ✓ | ✓ |

For a catalogue of German, Spanish and Portuguese titles, `straße` failing to match
`strasse` is a live search bug. The change is in **equality, not ordering**:
`general_ci` already folded Latin-1 accents, so sort order on real data did not
move, and no unique-key collisions or `DISTINCT` count changes occurred.

**Carry forward:** every pre-`uca1400` collation is **PAD SPACE** (trailing spaces
ignored in comparison); the `0900_*` and `uca1400_*` families are **NO PAD**. That
semantic changes whenever this database moves to MariaDB 11 or MySQL 8 — a
property of the era, not of this choice.

## The connection charset is half the job

In MariaDB **`utf8` means `utf8mb3`**. A connection opened with `SET NAMES UTF8`
is a 3-byte connection: the server silently converts anything outside the BMP to
`?` on the way out (two different emoji come back identical and compare equal). The
init command must read:

```php
'driver_options' => [
    (class_exists('Pdo\Mysql') ? \Pdo\Mysql::ATTR_INIT_COMMAND : \PDO::MYSQL_ATTR_INIT_COMMAND)
        => 'SET NAMES utf8mb4;',
],
```

`config/autoload/local.php` is **gitignored**, so every machine carries its own
copy of that line. `config/autoload/local.php.dist` and `docker/local.docker.php`
hold it in the repository; **production's copy is a manual server-side edit** and
a deploy does not perform it.

## Scope: what is server maintenance, not a migration

A table is in scope if the repository mentions it in `module/`, `src/`, `config/`,
`templates/`, `tools/`, `bin/`, `test/` or `public/`. Historical migrations under
`database/` and prose in `docs/` do **not** count — old migration files name nearly
every table this project has ever had. Nineteen tables are therefore server
maintenance, still on their old engine and collation:

- the nine Bible tables — `bib_books`, `bib_book_abbreviations`, `bib_dh_page`,
  `bib_greek_root_words`, `bib_verses` (the largest MyISAM table in production,
  ~236k rows / 46 MiB) and the four `bib_*_temp` tables that exist only in
  production; the Bible module is gone;
- the five `b_bib*` tables — source data for a one-time import from the old "sion"
  bibliography; nothing reads them;
- `sch_dictionary_dictionary` and `sch_dictionary_users` (the latter with a
  `password` column holding legacy hashes nothing reads);
- `user_remember_me` — its foreign key into `user` is on `user_id INT`, so
  converting `user` does not touch it;
- `sch_visits_rollover_2023-11-02` and `sch_visits_rollover_2025-07-17`.

The **database default charset is still `latin1_swedish_ci`**, so a table created
without an explicit charset inherits latin1. Changing it is `ALTER DATABASE`, a
server-level property outside these migrations, and belongs with the same
maintenance pass.

## Traps

**`SET FOREIGN_KEY_CHECKS = 0` does not permit converting a referenced column.**
MariaDB raises error 1833 regardless — that flag governs row validation, not
column-type compatibility. `relationships.PredicateKind` → `predicates.PredicateKind`
is the only string foreign key in the database: drop the constraint, convert both
tables, recreate it.

**`CONVERT TO CHARACTER SET` silently promotes column types.** A utf8mb3 `TEXT`
holds 21,845 characters and a utf8mb4 `TEXT` only 16,383, so the server promotes
`TEXT`→`MEDIUMTEXT` and `MEDIUMTEXT`→`LONGTEXT` to preserve capacity (45 columns
across the in-scope tables). `db7.0.sql` writes all 45 out explicitly so the result
is what the migration says, not what the server decided. The one place a
promotion *is* the intent: JTranslate's `trans_phrases.phrase` and
`trans_translations.translation` went `VARCHAR(2000)` → `TEXT` deliberately,
because the varchar was truncating phrases.

**Test latin1 data for double-encoding with a byte-exact round trip, not a NULL
check.** `CONVERT(BINARY(col) USING utf8mb4)` does not return NULL on invalid input,
it substitutes — as a validity test it reports every column "already UTF-8". The
correct test:

```sql
HEX(CONVERT(CONVERT(BINARY(`col`) USING utf8mb4) USING binary)) = HEX(`col`)
```

On a genuine latin1 column it returns 0 rows *before* conversion and every row
after (the bytes are valid UTF-8 now — that inversion is expected, not a
regression). Genuine latin1 (`podrían`, `años`, `México`) takes a plain
`CONVERT TO`; the BLOB round-trip usually recommended for double-encoded columns
would itself have been the corruption. Correct two-byte encodings look like
`0xC3AD` for `í`; double encoding shows `0xC383 0xC2AD`.

**AUTO_INCREMENT survives MyISAM → InnoDB and a server restart.** MariaDB 10.11
persists the counter (MDEV-6076) rather than recomputing `MAX(id)+1`. This matters
because `lib_checkouts.BookId` points at `lib_books.book_id` with no foreign key,
so id reuse would silently re-point checkout history.

**A fresh capsule is not necessarily at production's migration level.**
`user_api_token` exists only after `db6.6`–`db6.9`, and `jtranslate_migration` only
after JTranslate's runner has run; `db7.0.sql` guards the latter and documents the
former. Check the ledger before assuming a table is present.
