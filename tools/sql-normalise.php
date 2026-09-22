<?php

/**
 * Turns a raw dump of MariaDB's general log into the recording `test/Db/sql-surface.txt`.
 *
 * Reads tab-separated `command_type<TAB>argument` lines on stdin and writes the distinct
 * statement *shapes*, sorted, one per line. Three normalisations make the result stable
 * across runs, and each one removes something that is not part of the contract:
 *
 * - **literals become `?`**, so a recording taken against one row of the production export
 *   is the same as one taken against another. laminas-db parameterises almost everything
 *   already, so this mostly affects the few statements built with values inline;
 * - **whitespace collapses**, because a builder is free to lay a statement out differently;
 * - **duplicates and order are dropped**. A page that runs the same query twice, or a
 *   caching change that makes it run once, is a different question from whether the SQL
 *   itself moved — and answering both at once is what makes a raw log useless as a contract.
 *
 * What survives is exactly what a query builder is responsible for: which tables and
 * columns are named, how they are quoted, the shape of the joins and predicates, and the
 * order of the clauses.
 */

declare(strict_types=1);

$statements = [];

while (false !== ($line = fgets(STDIN))) {
    $line = rtrim($line, "\r\n");
    if ('' === $line) {
        continue;
    }

    [$type, $sql] = array_pad(explode("\t", $line, 2), 2, '');
    if (! in_array($type, ['Query', 'Prepare'], true) || '' === $sql) {
        continue;
    }

    //The capture's own bookkeeping, and the connection handshake every client performs.
    if (preg_match('/^(SET |SHOW |TRUNCATE TABLE mysql\.|SELECT .* FROM mysql\.general_log)/i', $sql)) {
        continue;
    }

    $statements[normalise($sql)] = true;
}

$shapes = array_keys($statements);
sort($shapes, SORT_STRING);

foreach ($shapes as $shape) {
    echo $shape, "\n";
}

/** Reduce a statement to its shape: no literals, no incidental whitespace. */
function normalise(string $sql): string
{
    $sql = blankLiterals($sql);
    //Bare numbers, but never one that is part of an identifier (`utf8mb4`, `sha256`).
    $sql = preg_replace('/(?<![A-Za-z0-9_`])\d+(?:\.\d+)?(?![A-Za-z0-9_`])/', '?', $sql) ?? $sql;
    $sql = preg_replace('/\s+/', ' ', $sql) ?? $sql;

    return trim($sql);
}

/**
 * Replace every quoted literal with `'?'`, scanning rather than matching.
 *
 * A regex cannot do this here. Some values reach the log as **raw binary** — `phrase_hash`
 * is a 16-byte digest written inline rather than bound — and a digest containing the byte
 * `0x27` closes the literal early, so the tail of the hash survives into the output and the
 * recording differs from run to run depending on which phrases were discovered. That was
 * measured: two consecutive captures disagreed on nine lines, every one a `trans_phrases`
 * or `trans_translations_history` statement carrying a hash.
 *
 * So a literal ends at the first `'` that is followed by something structural — a comma, a
 * closing parenthesis, whitespace or the end of the statement — which is true of SQL and
 * not of a digest's interior. A doubled `''` is passed over for the same reason.
 */
function blankLiterals(string $sql): string
{
    $out    = '';
    $length = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        if ("'" !== $sql[$i]) {
            $out .= $sql[$i];
            continue;
        }

        for ($j = $i + 1; $j < $length; $j++) {
            if ("'" !== $sql[$j]) {
                continue;
            }
            $next = $sql[$j + 1] ?? '';
            if ('' === $next || ',' === $next || ')' === $next || ' ' === $next || "\t" === $next) {
                break;
            }
        }

        $out .= "'?'";
        $i    = $j;
    }

    return $out;
}
