<?php

/**
 * Prints the SQL written as string literals in `test/` and `tools/`, for
 * tools/sql-surface.sh to subtract from the recording.
 *
 * The integration suite is one of the two drivers behind `test/Db/sql-surface.txt`, and it
 * issues SQL of its own — `SELECT COUNT(*) FROM lib_books` to check a fixture, and the
 * like. Those statements are not the application's, so a recording that holds them moves
 * when a test is edited, and 30 of the first 192 shapes were exactly that.
 *
 * Output is `Query<TAB><sql>` so it can be piped straight through tools/sql-normalise.php
 * and compared with the capture on equal terms; normalising in two places is how the two
 * would drift.
 *
 * Deliberately cheap and deliberately incomplete: it finds single- and double-quoted
 * literals that begin with a statement keyword, and a statement assembled by concatenation
 * yields only its first fragment. That is why sql-surface.sh matches these as substrings
 * and never drops a shape the HTTP pass also produced — under-filtering leaves a test's
 * statement in the recording, which is untidy, while over-filtering would delete an
 * application statement from the contract, which is a hole in it.
 */

declare(strict_types=1);

$roots = ['test', 'tools'];

/**
 * A recording is not harness SQL.
 *
 * `test/Db/sql-builder-surface.php` holds the statements the builder writes, as string
 * literals — which is exactly the shape this scanner looks for, and every one of them is a
 * statement the application really issues. Left in, the two recordings cancel: a shape lands
 * in the builder's recording and is subtracted out of the SQL surface's, so `--check` reports
 * it as having disappeared from the application.
 */
$excluded = ['test/Db/sql-builder-surface.php'];

$seen = [];

foreach ($roots as $root) {
    if (! is_dir($root)) {
        continue;
    }

    /** @var SplFileInfo $file */
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
        if (! $file->isFile() || 'php' !== $file->getExtension()) {
            continue;
        }

        if (in_array(str_replace('\\', '/', $file->getPathname()), $excluded, true)) {
            continue;
        }

        $source = file_get_contents($file->getPathname());
        if (false === $source) {
            continue;
        }

        if (! preg_match_all(
            '/([\'"])((?:SELECT|INSERT|UPDATE|DELETE|REPLACE)\b(?:(?!\1).)*)\1/is',
            $source,
            $matches
        )) {
            continue;
        }

        foreach ($matches[2] as $sql) {
            $sql = trim(preg_replace('/\s+/', ' ', $sql) ?? $sql);
            //Short fragments match far too much: 'SELECT COUNT(*)' would subtract every
            //counting statement the application makes.
            if (strlen($sql) < 30) {
                continue;
            }
            $seen[$sql] = true;
        }
    }
}

foreach (array_keys($seen) as $sql) {
    echo "Query\t", $sql, "\n";
}
