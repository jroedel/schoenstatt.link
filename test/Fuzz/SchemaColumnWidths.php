<?php

declare(strict_types=1);

namespace SchoenstattTest\Fuzz;

/**
 * The database's own opinion about how long a value may be, replayed from
 * `database/*.sql`.
 *
 * Why parse the migration scripts instead of asking the running database?
 *
 * The capsule's database is built from a 2021 production dump plus these
 * scripts, and the dump is gitignored — so information_schema is available on a
 * developer's machine and absent on a bare CI runner. The scripts are committed,
 * which makes them the only source of truth that travels with the repository.
 * They are also the source the *next* schema change will be written into, so a
 * bound derived from them stays honest after a migration lands but before anyone
 * re-imports.
 *
 * The parse is a replay, not a snapshot: the files are applied in version order
 * (db0.0 → db6.4) and each `CREATE TABLE` / `ADD` / `MODIFY` / `CHANGE` /
 * `DROP COLUMN` mutates an in-memory table map, so a column widened in db5.6
 * reports its widened width. Ordering is by the numeric version in the filename,
 * not by strcmp — `db10.0` must sort after `db9.0`, and `db0.1` after `db0.0`.
 *
 * Only the width matters here, and only for the types where "too long" is a
 * MariaDB error rather than a judgement call. Production runs
 * STRICT_TRANS_TABLES, so an over-length varchar is SQLSTATE 22001 — a 500, not
 * a silent truncation. Types with no character bound (INT, DATE, BIT, DECIMAL)
 * deliberately return null: this class reports "I cannot derive a bound" rather
 * than guessing one, because a guessed bound in a regression net is worse than
 * no bound at all.
 *
 * Deliberately hand-rolled rather than reaching for a SQL parser dependency: the
 * grammar this needs is four statement forms, and the harness must keep working
 * while the vendor tree is mid-migration.
 */
final class SchemaColumnWidths
{
    /**
     * Character capacity of the BLOB/TEXT family. MariaDB measures these in
     * *bytes*, not characters, so for multi-byte data the real character
     * capacity is lower — that makes these bounds generous, which is the safe
     * direction for a test that fails when a form is looser than the column.
     */
    private const TEXT_TYPE_WIDTHS = [
        'tinytext'   => 255,
        'text'       => 65535,
        'mediumtext' => 16777215,
        'longtext'   => 4294967295,
        'tinyblob'   => 255,
        'blob'       => 65535,
        'mediumblob' => 16777215,
        'longblob'   => 4294967295,
    ];

    /** Types whose declared `(n)` really is a character/byte capacity. */
    private const SIZED_STRING_TYPES = ['varchar', 'char', 'varbinary', 'binary'];

    /** @var array<string, array<string, int|null>>|null table => column => width */
    private static ?array $tables = null;

    /**
     * @return array<string, array<string, int|null>> table (lowercased) =>
     *         column (as written) => max characters, or null when the type
     *         carries no character bound
     */
    public static function tables(): array
    {
        return self::$tables ??= self::replay();
    }

    /**
     * The width of one column, or null when the table/column is unknown or the
     * type carries no character bound. Column lookup is case-insensitive
     * because the codebase spells the same column both ways
     * (`update_columns` uses the schema's casing, but not reliably).
     */
    public static function widthOf(string $table, string $column): ?int
    {
        $columns = self::tables()[strtolower($table)] ?? null;
        if (null === $columns) {
            return null;
        }

        foreach ($columns as $name => $width) {
            if (strcasecmp($name, $column) === 0) {
                return $width;
            }
        }

        return null;
    }

    /** True when the table was found at all — distinguishes "no bound" from "no table". */
    public static function knowsTable(string $table): bool
    {
        return isset(self::tables()[strtolower($table)]);
    }

    /**
     * True when the column was seen in the replay.
     *
     * `widthOf()` returns null for two very different situations and the caller
     * must not confuse them: the column exists and its type simply has no
     * character bound (INT, DATE, BIT — the check does not apply), or the column
     * was never seen at all (the check *should* apply and cannot, which has to be
     * reported as a skip). Several tables — `lib_libraries`, `lib_checkouts`,
     * `sch_dictionary_dictionary` — plus a scattering of columns on
     * `lib_books` have no `CREATE TABLE` anywhere in `database/*.sql`: they exist
     * only inside the production dump, so 45 real varchar columns are invisible
     * from here.
     */
    public static function knowsColumn(string $table, string $column): bool
    {
        foreach (self::tables()[strtolower($table)] ?? [] as $name => $width) {
            if (strcasecmp($name, $column) === 0) {
                unset($width);
                return true;
            }
        }

        return false;
    }

    /** @return array<string, array<string, int|null>> */
    private static function replay(): array
    {
        $tables = [];

        foreach (self::migrationFiles() as $file) {
            $sql = file_get_contents($file);
            if (false === $sql) {
                continue;
            }

            foreach (self::statements($sql) as $statement) {
                self::apply($statement, $tables);
            }
        }

        ksort($tables);

        return $tables;
    }

    /**
     * `database/*.sql` ordered by the version in the filename. A file whose name
     * does not parse is skipped rather than misordered — silently applying a
     * migration out of order would produce wrong widths, which is exactly the
     * failure mode this class must not have.
     *
     * @return list<string>
     */
    private static function migrationFiles(): array
    {
        $files = glob(dirname(__DIR__, 2) . '/database/db*.sql') ?: [];

        $keyed = [];
        foreach ($files as $file) {
            if (preg_match('/db(\d+)\.(\d+)\.sql$/', basename($file), $m)) {
                $keyed[] = [(int) $m[1], (int) $m[2], $file];
            }
        }

        usort($keyed, static fn(array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        return array_column($keyed, 2);
    }

    /**
     * Split a script into statements safely.
     *
     * This has to be a single-pass lexer rather than a stack of regexes, and the
     * reason is worth recording because the naive version silently produced
     * *wrong widths* rather than an error.
     *
     * db0.1.sql is a 1 MB dump whose INSERT rows contain semicolons, apostrophes
     * and `--` inside string literals, so splitting the raw text on `;` corrupts
     * everything after the first row of prose. Blanking string literals first and
     * stripping comments second fixes that but breaks db5.9.sql, whose line 2 is
     *
     *     UPDATE `sch_publications` SET ... ; # the rest we're already 1
     *
     * — the apostrophe in "we're" sits inside a `#` comment, opens a string
     * literal that never closes, and swallows the two `ALTER TABLE ... CHANGE`
     * statements below it. The observable symptom was
     * `sch_publications.InLanguage` reported as VARCHAR(3) when it is VARCHAR(30):
     * a bound three times *tighter* than reality, which would have made the
     * harness demand impossible validation. Stripping comments first instead just
     * moves the bug to `--` inside a literal.
     *
     * So quotes, backtick identifiers and both comment syntaxes are recognised in
     * one pass, each in the others' context, and only then is `;` a separator.
     * String contents are dropped (they never carry schema information); backtick
     * identifiers are kept.
     *
     * @return list<string>
     */
    private static function statements(string $sql): array
    {
        $out       = [];
        $buffer    = '';
        $length    = strlen($sql);
        $flush     = static function () use (&$buffer, &$out): void {
            $statement = trim($buffer);
            if ('' !== $statement) {
                $out[] = $statement;
            }
            $buffer = '';
        };

        for ($i = 0; $i < $length; $i++) {
            $c    = $sql[$i];
            $next = ($i + 1) < $length ? $sql[$i + 1] : '';

            // Comments: `--` (SQL requires whitespace after it), `#`, `/* */`.
            $lineComment = '#' === $c
                || ('-' === $c && '-' === $next
                    && ($i + 2 >= $length || 1 === preg_match('/\s/', $sql[$i + 2])));

            if ($lineComment) {
                $end     = strpos($sql, "\n", $i);
                $i       = false === $end ? $length : $end;
                $buffer .= "\n";
                continue;
            }
            if ('/' === $c && '*' === $next) {
                $end     = strpos($sql, '*/', $i + 2);
                $i       = false === $end ? $length : $end + 1;
                $buffer .= ' ';
                continue;
            }

            // Quoted string literal: keep the delimiters, drop the contents.
            if ("'" === $c || '"' === $c) {
                $buffer .= $c . $c;
                for ($i++; $i < $length; $i++) {
                    if ('\\' === $sql[$i]) {
                        $i++;
                        continue;
                    }
                    if ($sql[$i] === $c) {
                        // A doubled delimiter escapes itself.
                        if (($i + 1) < $length && $sql[$i + 1] === $c) {
                            $i++;
                            continue;
                        }
                        break;
                    }
                }
                continue;
            }

            // Backtick identifier: kept verbatim, and may legally contain `;`.
            if ('`' === $c) {
                $end     = strpos($sql, '`', $i + 1);
                $end     = false === $end ? $length - 1 : $end;
                $buffer .= substr($sql, $i, $end - $i + 1);
                $i       = $end;
                continue;
            }

            if (';' === $c) {
                $flush();
                continue;
            }

            $buffer .= $c;
        }

        $flush();

        return $out;
    }

    /** @param array<string, array<string, int|null>> $tables */
    private static function apply(string $statement, array &$tables): void
    {
        $normalized = preg_replace('/\s+/', ' ', $statement) ?? $statement;

        $createPattern = '/^CREATE TABLE (?:IF NOT EXISTS )?(' . self::TABLE_REF . ')\s*\((.*)\)[^)]*$/is';

        if (preg_match($createPattern, $normalized, $m)) {
            $table = self::tableName($m[1]);
            // A CREATE that the schema already has (IF NOT EXISTS, or a
            // re-CREATE after a DROP) replaces what was there.
            $tables[$table] = [];
            foreach (self::splitTopLevel($m[2]) as $clause) {
                self::applyColumnClause($clause, $tables[$table]);
            }
            return;
        }

        if (preg_match('/^DROP TABLE (?:IF EXISTS )?(' . self::TABLE_REF . ')/is', $normalized, $m)) {
            unset($tables[self::tableName($m[1])]);
            return;
        }

        if (preg_match('/^RENAME TABLE (' . self::TABLE_REF . ') TO (' . self::TABLE_REF . ')/is', $normalized, $m)) {
            $from = self::tableName($m[1]);
            $to   = self::tableName($m[2]);
            if (isset($tables[$from])) {
                $tables[$to] = $tables[$from];
                unset($tables[$from]);
            }
            return;
        }

        if (! preg_match('/^ALTER TABLE (' . self::TABLE_REF . ') (.*)$/is', $normalized, $m)) {
            return;
        }

        $table = self::tableName($m[1]);
        $tables[$table] ??= [];
        foreach (self::splitTopLevel($m[2]) as $clause) {
            self::applyAlterClause($clause, $tables[$table]);
        }
    }

    /** `tbl`, tbl, `db`.`tbl` or db.tbl */
    private const TABLE_REF = '(?:`[^`]+`|\w+)(?:\s*\.\s*(?:`[^`]+`|\w+))?';

    private static function tableName(string $ref): string
    {
        $parts = preg_split('/\s*\.\s*/', $ref) ?: [$ref];

        return strtolower(trim((string) end($parts), '` '));
    }

    /** @param array<string, int|null> $columns */
    private static function applyAlterClause(string $clause, array &$columns): void
    {
        $clause = trim($clause);

        if (preg_match('/^DROP\s+(?:COLUMN\s+)?`?(\w+)`?/i', $clause, $m)) {
            // DROP KEY/INDEX/PRIMARY also matches the shape; only unset when the
            // name is actually a column we know.
            if (! preg_match('/^DROP\s+(KEY|INDEX|PRIMARY|FOREIGN|CONSTRAINT|CHECK)\b/i', $clause)) {
                unset($columns[$m[1]]);
            }
            return;
        }

        // CHANGE [COLUMN] `old` `new` <type>
        if (preg_match('/^CHANGE\s+(?:COLUMN\s+)?`?(\w+)`?\s+`?(\w+)`?\s+(.*)$/is', $clause, $m)) {
            unset($columns[$m[1]]);
            $columns[$m[2]] = self::widthOfType($m[3]);
            return;
        }

        // ADD/MODIFY [COLUMN] `name` <type>
        if (preg_match('/^(?:ADD|MODIFY)\s+(?:COLUMN\s+)?(.*)$/is', $clause, $m)) {
            self::applyColumnClause($m[1], $columns);
        }
    }

    /**
     * One `name type ...` column definition. Constraint and index clauses share
     * the shape and must not be mistaken for columns.
     *
     * @param array<string, int|null> $columns
     */
    private static function applyColumnClause(string $clause, array &$columns): void
    {
        $clause = trim($clause);

        if (preg_match('/^(PRIMARY|UNIQUE|KEY|INDEX|FULLTEXT|SPATIAL|CONSTRAINT|FOREIGN|CHECK|PERIOD)\b/i', $clause)) {
            return;
        }

        if (! preg_match('/^`?(\w+)`?\s+(.+)$/is', $clause, $m)) {
            return;
        }

        $columns[$m[1]] = self::widthOfType($m[2]);
    }

    /**
     * Character capacity declared by a type, or null when the type has none.
     *
     * ENUM reports the length of its longest member: over-length is impossible
     * there, but a bound is still the honest answer and it keeps the caller from
     * having to special-case the type.
     */
    private static function widthOfType(string $type): ?int
    {
        $type = ltrim($type);

        if (preg_match('/^(' . implode('|', self::SIZED_STRING_TYPES) . ')\s*\(\s*(\d+)/i', $type, $m)) {
            return (int) $m[2];
        }

        if (preg_match('/^(\w+)/', $type, $m)) {
            $bare = strtolower($m[1]);

            if (isset(self::TEXT_TYPE_WIDTHS[$bare])) {
                return self::TEXT_TYPE_WIDTHS[$bare];
            }

            // An unsized VARCHAR/CHAR is not legal MariaDB, but CHAR alone means
            // CHAR(1); treat any unsized member of the family as unknown rather
            // than inventing a number.
            if (in_array($bare, self::SIZED_STRING_TYPES, true)) {
                return null;
            }

            if ('enum' === $bare && preg_match('/^enum\s*\((.*)$/is', $type, $e)) {
                $longest = 0;
                foreach (self::splitTopLevel(self::stripTrailingParen($e[1])) as $member) {
                    $longest = max($longest, strlen(trim(trim($member), "'\" ")));
                }
                return $longest > 0 ? $longest : null;
            }
        }

        return null;
    }

    private static function stripTrailingParen(string $s): string
    {
        $depth = 0;
        $out   = '';
        $len   = strlen($s);

        for ($i = 0; $i < $len; $i++) {
            $c = $s[$i];
            if ('(' === $c) {
                $depth++;
            } elseif (')' === $c) {
                if (0 === $depth) {
                    break;
                }
                $depth--;
            }
            $out .= $c;
        }

        return $out;
    }

    /**
     * Split on commas that are not inside parentheses — needed because
     * `enum('a','b')` and `decimal(10,2)` both contain commas that do not
     * separate clauses.
     *
     * @return list<string>
     */
    private static function splitTopLevel(string $body): array
    {
        $parts = [];
        $depth = 0;
        $buf   = '';
        $len   = strlen($body);

        for ($i = 0; $i < $len; $i++) {
            $c = $body[$i];

            if ('(' === $c) {
                $depth++;
            } elseif (')' === $c) {
                $depth--;
            } elseif (',' === $c && 0 === $depth) {
                $parts[] = $buf;
                $buf     = '';
                continue;
            }

            $buf .= $c;
        }

        if ('' !== trim($buf)) {
            $parts[] = $buf;
        }

        return $parts;
    }
}
