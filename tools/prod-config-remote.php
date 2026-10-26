<?php

/**
 * The server half of `make prod-config`, `prod-config-diff` and `prod-config-import`.
 *
 * tools/prod-config.sh sends this program in the ssh command line, base64, and
 * everything it works on over stdin — so a value never appears in an argument, in
 * the environment, or in a file anywhere but the one it is installed into:
 *
 *     ssh host "php -r 'eval(\"?>\".base64_decode(\"…\"));'" < payload
 *
 * The payload is NUL-separated: mode, the target path relative to the working
 * directory, ALLOW_DB_CHANGE (0|1), the revision the template came from, the
 * template's source, then name/value pairs from secrets.env.
 *
 *   diff     render the candidate and compare it with the target by key path:
 *            same, differs, only on the server, only new. Never a value; the keys
 *            under an `api_keys` list are secrets themselves and appear as a short
 *            hash.
 *   install  the same comparison, unprinted, then write the candidate beside the target at
 *            mode 0600, require it back and compare, keep the target as
 *            local.php.prev and rename the candidate over it. Refuses a change of
 *            db.hostname or db.database unless ALLOW_DB_CHANGE is 1: a moved
 *            database is a migration, not a credential change.
 *   import   the inverse, for filling secrets.env from what the server holds: the
 *            template is evaluated with every env() call answering a marker, and
 *            the server's value at each marker's path becomes a NAME='value' line on
 *            stdout. Anything the template cannot account for is named on stderr.
 *
 * Plain PHP with no autoloader: it runs in the server's CLI with nothing of the
 * application loaded, and in test/Deploy/prod-config-test.sh with a stand-in home.
 */

declare(strict_types=1);

final class ProdConfig
{
    /** @var array<string, string> */
    public static array $values = [];

    /** @var array<string, true> */
    public static array $used = [];

    public static bool $markers = false;

    public static function fail(string $message): never
    {
        fwrite(STDERR, "prod-config: $message\n");
        exit(1);
    }

    public static function checked(string $name, string $value): string
    {
        // The same test tools/deploy.sh applies to its own placeholders.
        if (str_contains($value, 'TODO')) {
            throw new RuntimeException("$name in secrets.env is still a placeholder");
        }

        return $value;
    }
}

/** The value of NAME from secrets.env, refused when empty or a placeholder. */
function env(string $name): string
{
    ProdConfig::$used[$name] = true;
    if (ProdConfig::$markers) {
        return "\0env:$name\0";
    }
    $value = ProdConfig::$values[$name] ?? '';
    if ($value === '') {
        throw new RuntimeException("secrets.env does not set $name");
    }

    return ProdConfig::checked($name, $value);
}

/**
 * A comma-separated NAME as a list. Optional means an empty NAME is an empty list.
 *
 * @return list<string>
 */
function env_list(string $name, bool $optional = false): array
{
    ProdConfig::$used[$name] = true;
    if (ProdConfig::$markers) {
        return ["\0list:$name\0"];
    }
    $items = array_values(array_filter(
        array_map('trim', explode(',', ProdConfig::$values[$name] ?? '')),
        static fn (string $item): bool => $item !== ''
    ));
    if ($items === [] && ! $optional) {
        throw new RuntimeException("secrets.env does not set $name");
    }

    return array_map(static fn (string $item): string => ProdConfig::checked($name, $item), $items);
}

/** @return array<mixed> */
function render_template(string $source): array
{
    try {
        $config = eval('?>' . $source);
    } catch (Throwable $e) {
        ProdConfig::fail($e->getMessage());
    }
    if (! is_array($config)) {
        ProdConfig::fail('the template does not return an array');
    }

    return $config;
}

/** @return array<mixed>|null null when the file is absent or does not load */
function load_config(string $file): ?array
{
    if (! is_file($file)) {
        return null;
    }
    try {
        $config = (static fn (string $f): mixed => require $f)($file);
    } catch (Throwable) {
        return null;
    }

    return is_array($config) ? $config : null;
}

/**
 * Key paths to leaves. A list is a leaf — api_keys is compared as a whole.
 *
 * @param array<mixed> $config
 * @return array<string, mixed>
 */
function flatten(array $config, string $prefix = ''): array
{
    $out = [];
    foreach ($config as $key => $value) {
        $path = $prefix === '' ? (string) $key : "$prefix.$key";
        if (is_array($value) && $value !== [] && ! array_is_list($value)) {
            $out += flatten($value, $path);
        } else {
            $out[$path] = $value;
        }
    }

    return $out;
}

/** Enough to tell two keys apart in a transcript, and not enough to be one. */
function key_hashes(mixed $list): string
{
    if (! is_array($list)) {
        return get_debug_type($list);
    }

    return '[' . implode(', ', array_map(
        static fn (mixed $k): string => substr(hash('sha256', (string) $k), 0, 6),
        $list
    )) . ']';
}

/**
 * The comparison, printed when asked. Returns how many paths differ.
 *
 * @param array<mixed>|null $current
 * @param array<mixed>      $candidate
 */
function report(?array $current, array $candidate, bool $print): int
{
    $old = flatten($current ?? []);
    $new = flatten($candidate);
    $changes = 0;
    foreach (array_unique([...array_keys($new), ...array_keys($old)]) as $path) {
        $path = (string) $path;
        $detail = '';
        if (! array_key_exists($path, $old)) {
            $status = 'only new';
        } elseif (! array_key_exists($path, $new)) {
            $status = 'only on the server';
        } elseif ($old[$path] === $new[$path]) {
            $status = 'same';
        } else {
            $status = 'differs';
            if (get_debug_type($old[$path]) !== get_debug_type($new[$path])) {
                $detail = get_debug_type($old[$path]) . ' → ' . get_debug_type($new[$path]);
            }
        }
        if ($status !== 'same' && str_ends_with($path, 'api_keys')) {
            $detail = key_hashes($old[$path] ?? null) . ' → ' . key_hashes($new[$path] ?? null);
        }
        if ($status !== 'same') {
            $changes++;
        }
        if ($print) {
            printf("  %-19s %s%s\n", $status, $path, $detail === '' ? '' : "   $detail");
        }
    }

    return $changes;
}

/**
 * @param array<mixed>|null $current
 * @param array<mixed>      $candidate
 */
function database_moved(?array $current, array $candidate): ?string
{
    foreach (['hostname', 'database'] as $field) {
        $was = $current['db'][$field] ?? null;
        if ($was !== null && $was !== ($candidate['db'][$field] ?? null)) {
            return "db.$field";
        }
    }

    return null;
}

/** @param array<mixed> $config */
function rendered_file(array $config, string $revision): string
{
    return "<?php\n\n"
        . "// Rendered by `make prod-config` from config/autoload/local.production.php.dist\n"
        . "// at $revision, " . gmdate('Y-m-d H:i') . " UTC. Do not edit it here: change secrets.env\n"
        . "// and run `make prod-config`. The file it replaced is local.php.prev.\n\n"
        . 'return ' . var_export($config, true) . ";\n";
}

/**
 * A NAME='value' line the parser in tools/secrets-env.sh reads back exactly.
 */
function env_line(string $name, string $value): string
{
    if (str_contains($value, "\n") || (str_contains($value, "'") && str_contains($value, '"'))) {
        fwrite(STDERR, "  set by hand  $name (its value cannot be written on one quoted line)\n");

        return "# $name=";
    }

    return str_contains($value, "'") ? "$name=\"$value\"" : "$name='$value'";
}

/**
 * Walks the template (with markers) beside the server's file.
 *
 * @param array<string, string> $out
 */
function inverse(mixed $template, mixed $server, bool $present, string $path, array &$out): void
{
    if (is_string($template) && preg_match('/^\0env:([A-Z][A-Z0-9_]*)\0$/', $template, $m)) {
        if (! $present) {
            fwrite(STDERR, "  not on the server  $path ({$m[1]})\n");
        } elseif (! is_scalar($server)) {
            fwrite(STDERR, "  not a scalar       $path ({$m[1]})\n");
        } else {
            $out[$m[1]] = (string) $server;
        }

        return;
    }
    $isListMarker = is_array($template) && array_is_list($template) && $template !== []
        && is_string($template[0]) && str_starts_with($template[0], "\0list:");
    if ($isListMarker) {
        $names = array_map(static fn (string $t): string => substr($t, 6, -1), $template);
        $items = $present && is_array($server) ? array_values($server) : [];
        if (! $present) {
            fwrite(STDERR, "  not on the server  $path\n");
        }
        // Positional, and the last name takes whatever is left: one key per name for
        // the maintenance key and its predecessor, the whole list for a list.
        foreach ($names as $i => $name) {
            $take = $i === count($names) - 1 ? array_slice($items, $i) : array_slice($items, $i, 1);
            $out[$name] = implode(',', array_map('strval', $take));
        }

        return;
    }
    if (is_array($template)) {
        $server = $present && is_array($server) ? $server : [];
        foreach ($template as $key => $value) {
            $child = $path === '' ? (string) $key : "$path.$key";
            inverse($value, $server[$key] ?? null, array_key_exists($key, $server), $child, $out);
        }
        foreach (array_diff_key($server, $template) as $key => $_) {
            fwrite(STDERR, '  only on the server  ' . ($path === '' ? $key : "$path.$key") . "\n");
        }

        return;
    }
    if (! $present || $server !== $template) {
        fwrite(STDERR, "  template differs   $path (written in the template, not secrets.env)\n");
    }
}

// ----------------------------------------------------------------------- main ---

$parts = explode("\0", (string) stream_get_contents(STDIN));
if (end($parts) === '') {
    array_pop($parts);
}
if (count($parts) < 5 || (count($parts) - 5) % 2 !== 0) {
    ProdConfig::fail('malformed payload on stdin');
}
[$mode, $target, $allowDbChange, $revision, $template] = array_splice($parts, 0, 5);
for ($i = 0; $i < count($parts); $i += 2) {
    ProdConfig::$values[$parts[$i]] = $parts[$i + 1];
}
umask(077);

if (is_link($target)) {
    ProdConfig::fail("$target is a symlink; the shared copy is the real file. Check DEPLOY_APP_PATH.");
}
if (! is_file($target)) {
    ProdConfig::fail("there is no $target. Check DEPLOY_APP_PATH, and that the atomic layout exists.");
}
$current = load_config($target);

if ($mode === 'import') {
    if ($current === null) {
        ProdConfig::fail("$target does not load as a configuration array");
    }
    ProdConfig::$markers = true;
    $out = [];
    inverse(render_template($template), $current, true, '', $out);
    foreach ($out as $name => $value) {
        echo env_line($name, $value), "\n";
    }
    exit(0);
}

if ($mode !== 'diff' && $mode !== 'install') {
    ProdConfig::fail("unknown mode $mode");
}

$candidate = render_template($template);
foreach (array_keys(ProdConfig::$values) as $name) {
    if (! isset(ProdConfig::$used[$name])) {
        fwrite(STDERR, "  warn  $name is set in secrets.env and no template uses it\n");
    }
}
if ($current === null) {
    fwrite(STDERR, "  warn  $target does not load; every key reads as new\n");
}

// install has been shown the diff already, by the run of diff it asked about.
$changes = report($current, $candidate, $mode === 'diff');
$moved = database_moved($current, $candidate);
if ($moved !== null) {
    printf("\n  %s would point the application at another database.\n", $moved);
}
printf("\n  %d key path(s) would change.\n", $changes);

if ($mode === 'diff') {
    exit(0);
}

if ($moved !== null && $allowDbChange !== '1') {
    ProdConfig::fail("refused: $moved changes. Moving the database is a migration; if it is meant, ALLOW_DB_CHANGE=1.");
}
if ($changes === 0) {
    echo "  nothing to install\n";
    exit(0);
}

$dir = dirname($target);
$tmp = tempnam($dir, '.local.php.');
if ($tmp === false) {
    ProdConfig::fail("could not create a file in $dir");
}
chmod($tmp, 0600);
if (file_put_contents($tmp, rendered_file($candidate, $revision)) === false) {
    unlink($tmp);
    ProdConfig::fail("could not write $tmp");
}
// What PHP will actually read, not what this process meant to write.
if (load_config($tmp) !== $candidate) {
    unlink($tmp);
    ProdConfig::fail('the rendered file does not read back as the candidate; nothing was installed');
}
if (! copy($target, "$target.prev") || ! chmod("$target.prev", 0600)) {
    unlink($tmp);
    ProdConfig::fail("could not keep $target.prev; nothing was installed");
}
if (! rename($tmp, $target)) {
    unlink($tmp);
    ProdConfig::fail("could not move the candidate over $target; nothing was installed");
}
echo "  installed $target (mode 0600); the previous file is $target.prev\n";
