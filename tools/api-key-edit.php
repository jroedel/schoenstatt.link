<?php

/**
 * Rewrite `sion_model.api_keys` inside a laminas-style `local.php`, in place, by
 * replacing exact key literals — never by locating the array.
 *
 * ## Why not just edit the array
 *
 * `config/autoload/local.php` holds **two** `api_keys` lists: `sion_model.api_keys`,
 * which guards the maintenance endpoints, and `schoenstatt.api_keys`, which
 * `App\Controller\LibraryNoticesController` reads. They are different secrets. Anything
 * that finds "the api_keys array" by pattern is one blank line away from rotating the
 * wrong one, and the failure would be silent in both directions: the maintenance key
 * unchanged, and the notices key changed with nothing told about it.
 *
 * So this never looks for the array. It asks PHP what `sion_model.api_keys` currently
 * holds, then replaces those exact string literals in the file text. Formatting,
 * comments and the rest of the file survive untouched, which matters because that file
 * is hand-maintained on a server and is not in git.
 *
 * ## What it refuses
 *
 * Refusing is most of the value here. It gives up rather than guess when:
 *
 *  - `sion_model.api_keys` is missing, empty, or not a list of strings;
 *  - a key also appears in `schoenstatt.api_keys` — the two secrets are then
 *    indistinguishable by value and no textual edit can tell them apart;
 *  - a key literal does not appear in the file exactly once, in exactly one quote
 *    style. Twice means an edit would touch something it was not asked to;
 *  - the result does not parse, or no longer returns an array. Checked by requiring a
 *    temporary copy *before* anything is written back, and again semantically after.
 *
 * It is normally piped to the server rather than copied there —
 * `ssh host "php -- read <file>" < tools/api-key-edit.php` — so that a script which
 * rewrites the site's configuration never exists as a file on it.
 *
 * ## Usage
 *
 *     php tools/api-key-edit.php read     <file>
 *     php tools/api-key-edit.php add      <file> <new-key>
 *     php tools/api-key-edit.php keep-only <file> <key-to-keep>
 *
 * `add` widens the accepted set; `keep-only` narrows it. A rotation is add, verify,
 * keep-only — so that at no point is there a moment when neither key works.
 *
 * `read` prints the current keys one per line and writes nothing.
 */

declare(strict_types=1);

// ---------------------------------------------------------------- helpers ---

function fail(string $message): never
{
    fwrite(STDERR, "api-key-edit: $message\n");
    exit(1);
}

/**
 * The two key lists, as the file actually evaluates them.
 *
 * Included in a function scope so the file's own `return [...]` comes back as a value
 * and none of its top-level statements can reach this script's variables.
 *
 * @return array{sion: list<string>, schoenstatt: list<string>}
 */
function readKeys(string $file): array
{
    $config = (static fn(): mixed => require $file)();

    if (! is_array($config)) {
        fail("$file did not return an array.");
    }

    $pull = static function (mixed $block) use ($file): array {
        if (! is_array($block) || ! isset($block['api_keys']) || ! is_array($block['api_keys'])) {
            return [];
        }
        foreach ($block['api_keys'] as $k) {
            if (! is_string($k)) {
                fail("$file has a non-string entry in an api_keys list.");
            }
        }
        return array_values($block['api_keys']);
    };

    return [
        'sion'        => $pull($config['sion_model'] ?? null),
        'schoenstatt' => $pull($config['schoenstatt'] ?? null),
    ];
}

/**
 * The one literal in `$src` that spells `$key`, with its quote character.
 *
 * Both quote styles are searched and exactly one occurrence across both must exist. A
 * key that appears twice is not necessarily a bug in the file — it could legitimately
 * be in both lists — but it is a key this script must not touch, and the caller has
 * already been told why.
 */
function soleLiteral(string $src, string $key, string $file): string
{
    $candidates = ["'" . $key . "'", '"' . $key . '"'];
    $found      = null;
    $total      = 0;

    foreach ($candidates as $literal) {
        $n = substr_count($src, $literal);
        $total += $n;
        if ($n > 0) {
            $found = $literal;
        }
    }

    if (1 !== $total) {
        fail(sprintf(
            "the key ending %s appears %d time(s) as a quoted literal in %s; expected exactly 1.",
            '…' . substr($key, -4),
            $total,
            $file
        ));
    }

    return (string) $found;
}

/**
 * Write `$contents` to `$file` only if it parses, keeping a timestamped backup.
 *
 * The check is a `require` of a temporary copy inside a try/catch rather than a shell
 * out to `php -l`. A ParseError in an included file has been catchable since PHP 7, and
 * `exec()` is exactly the kind of function a shared host disables — a syntax check that
 * silently cannot run is worse than none, because it reports success.
 *
 * Executing the candidate is safe for the same reason reading it is: this file is a
 * configuration literal, and it has already been included once to read the keys.
 */
function writeChecked(string $file, string $contents): string
{
    $probe = tempnam(sys_get_temp_dir(), 'apikey');
    if (false === $probe) {
        fail('could not create a temporary file for the syntax check.');
    }
    file_put_contents($probe, $contents);

    try {
        $parsed = (static fn(): mixed => require $probe)();
    } catch (ParseError $e) {
        unlink($probe);
        fail('the edit would not parse, so nothing was written: ' . $e->getMessage());
    } catch (Throwable $e) {
        unlink($probe);
        fail('the edit could not be evaluated, so nothing was written: ' . $e->getMessage());
    }
    unlink($probe);

    if (! is_array($parsed)) {
        fail('the edit produced a file that does not return an array; nothing was written.');
    }

    // Seconds are not unique enough: a rotation runs `add` and `keep-only` within the
    // same second, and without this the second backup overwrote the first — leaving the
    // post-add state under the name of the original, which is the one worth having.
    $stamp  = date('Ymd-His');
    $backup = $file . '.bak-' . $stamp;
    for ($n = 2; file_exists($backup); $n++) {
        $backup = $file . '.bak-' . $stamp . '-' . $n;
    }
    if (! copy($file, $backup)) {
        fail("could not write a backup at $backup; nothing was changed.");
    }
    if (false === file_put_contents($file, $contents)) {
        fail("could not write $file. The backup at $backup is the previous contents.");
    }

    return $backup;
}

// ------------------------------------------------------------------- main ---

$action = $argv[1] ?? '';
$file   = $argv[2] ?? '';

if ('' === $action || '' === $file) {
    fail('usage: api-key-edit.php read|add|keep-only <file> [key]');
}
if (! is_file($file) || ! is_readable($file)) {
    fail("$file is missing or unreadable.");
}

$keys = readKeys($file);

if ('read' === $action) {
    echo implode("\n", $keys['sion']), "\n";
    exit(0);
}

if ([] === $keys['sion']) {
    fail("sion_model.api_keys is missing or empty in $file, so there is nothing to rotate. "
        . 'Set one by hand first — this script edits, it does not create.');
}

$argument = $argv[3] ?? '';
if ('' === $argument) {
    fail("$action needs a key argument.");
}

// The ambiguity that matters. Checked before any edit and for every key, not just the
// one being touched, because `keep-only` removes all the others.
$shared = array_intersect($keys['sion'], $keys['schoenstatt']);
if ([] !== $shared) {
    fail('a key is in both sion_model.api_keys and schoenstatt.api_keys. They are different '
        . 'secrets and no textual edit can tell them apart. Give them distinct values by hand first.');
}

$src = file_get_contents($file);
if (false === $src) {
    fail("could not read $file.");
}

switch ($action) {
    case 'add':
        if (in_array($argument, $keys['sion'], true)) {
            fail('that key is already in sion_model.api_keys.');
        }
        // Widen beside the FIRST existing key rather than at the end of the array: the
        // end of a PHP array literal is a `]` this script would have to find, which is
        // the positional search the whole design avoids.
        $anchor = soleLiteral($src, $keys['sion'][0], $file);
        $quote  = $anchor[0];
        $src    = str_replace($anchor, $anchor . ', ' . $quote . $argument . $quote, $src);
        break;

    case 'keep-only':
        if (! in_array($argument, $keys['sion'], true)) {
            fail('the key to keep is not in sion_model.api_keys. Run `add` first, and verify it works.');
        }
        $doomed = array_values(array_diff($keys['sion'], [$argument]));
        if ([] === $doomed) {
            echo "already the only key; nothing to do\n";
            exit(0);
        }
        foreach ($doomed as $old) {
            $literal = soleLiteral($src, $old, $file);
            // Take the separator with it, so the array does not end up with `'a', , 'b'`.
            // Three shapes, tried longest first: the key followed by a comma and a space,
            // by a comma, or alone.
            $replaced = false;
            foreach ([$literal . ', ', $literal . ',', $literal] as $shape) {
                if (str_contains($src, $shape)) {
                    $src      = str_replace($shape, '', $src);
                    $replaced = true;
                    break;
                }
            }
            if (! $replaced) {
                fail('could not remove a key cleanly; nothing was written.');
            }
        }
        break;

    default:
        fail("unknown action '$action'.");
}

$backup = writeChecked($file, $src);

// Read the file back and confirm it says what was intended. The edit is textual and the
// check is semantic, which is the point: a replacement that produced parseable nonsense
// would pass `php -l` and fail here.
$after = readKeys($file);
$want  = 'add' === $action ? array_merge($keys['sion'], [$argument]) : [$argument];

if (array_values($after['sion']) !== array_values($want)) {
    fail("the file parsed but sion_model.api_keys is not what was intended. "
        . "Restore it from $backup.");
}
if (array_values($after['schoenstatt']) !== array_values($keys['schoenstatt'])) {
    fail("the edit changed schoenstatt.api_keys, which it must never do. Restore from $backup.");
}

echo $backup, "\n";
