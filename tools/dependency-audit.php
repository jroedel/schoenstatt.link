<?php

/**
 * For every installed package: does this package's own code use its namespace, and does
 * its `composer.json` declare it?
 *
 * Written as `laminas-audit.php` after dropping `laminas-captcha` in step 1a silently
 * dropped `laminas-session` too — captcha was the only package requiring it, and we had
 * never declared it, though `App\Http\SessionListener` and `SionModel\Messaging\FlashMessages`
 * use it on every request. An undeclared dependency is invisible until something else stops
 * pulling it in, and then it leaves in a lock file nobody reads closely.
 *
 * Widened to every package on 2026-09-21, because the mandate is two things — remove every
 * `laminas/*` **and** minimise dependencies everywhere — and only the first half had a
 * standing check. Pass `--laminas` for the original scope.
 *
 * ## The three rows worth acting on
 *
 * - `USED / NOT DECLARED` — add it to composer.json. It works today only because something
 *   else happens to require it.
 * - `-    / declared`     — a line for a package this code does not name. Usually droppable;
 *   but read the note below first, because it may be a version pin.
 * - `?    / *`            — the package declares no PSR-0/PSR-4 prefix (a `files` or classmap
 *   package), so this tool cannot answer. Check it by hand.
 *
 * `-    / NOT DECLARED` is correct and expected: a transitive nothing of ours touches.
 *
 * **A declared-but-unused line can still be load-bearing.** `symfony/error-handler` has zero
 * uses here and is required by `symfony/http-kernel` anyway — but dropping the line let
 * composer resolve it from `^7.4` up to 8.1, because http-kernel accepts `^6.4|^7.0|^8.0`.
 * The line is a *pin*, not a use. Measured 2026-09-21; the line was put back.
 *
 * ## Scope
 *
 * One composer package at a time, which is what `--root` selects. The three submodules are
 * separate packages with their own `composer.json`, so a package can read as unused here and
 * still be load-bearing there — that is how removing `laminas-json` broke
 * `JTranslate\Service\CountriesFactory`. Audit all four:
 *
 *     docker compose exec -T app php tools/dependency-audit.php
 *     docker compose exec -T app php tools/dependency-audit.php --root=module/SionModel
 *
 * Comments are stripped before matching, so prose about a package is not a use of it.
 */

$options = [
    'laminasOnly' => false,
    'root'        => '.',
];
foreach (array_slice($argv, 1) as $argument) {
    if ('--laminas' === $argument) {
        $options['laminasOnly'] = true;
    } elseif (str_starts_with($argument, '--root=')) {
        $options['root'] = rtrim(substr($argument, 7), '/');
    } else {
        fwrite(STDERR, "usage: dependency-audit.php [--laminas] [--root=<path>]\n");
        exit(2);
    }
}

$root = $options['root'];

//The lock file is always the superproject's: it is the only one that says what is actually
//installed. A submodule's composer.json says what it *requires*, which is the other half.
$lock     = json_decode(file_get_contents('composer.lock'), true);
$manifest = json_decode(file_get_contents($root . '/composer.json'), true);
$declared = array_merge($manifest['require'] ?? [], $manifest['require-dev'] ?? []);

/** @var array<string, string> $installed */
$installed = [];
/** @var array<string, list<string>> $namespaces */
$namespaces = [];
foreach (array_merge($lock['packages'], $lock['packages-dev'] ?? []) as $package) {
    if ($options['laminasOnly'] && ! str_starts_with($package['name'], 'laminas/')) {
        continue;
    }
    $installed[$package['name']] = $package['version'];

    foreach (['psr-4', 'psr-0'] as $standard) {
        foreach ($package['autoload'][$standard] ?? [] as $prefix => $_) {
            $prefix = rtrim((string) $prefix, '\\');
            if ('' !== $prefix) {
                $namespaces[$package['name']][] = $prefix;
            }
        }
    }
}

/**
 * This package's own source, comments stripped.
 *
 * The superproject's roots exclude `module/{SionModel,JUser,JTranslate}`: those are separate
 * composer packages and are audited with `--root`.
 */
$roots = '.' === $root
    ? ['src', 'config', 'public', 'bin', 'tools', 'test', 'module/Application', 'module/Books', 'module/Schoenstatt']
    : [$root . '/src', $root . '/config', $root . '/test'];

$code = '';
foreach ($roots as $directory) {
    if (! is_dir($directory)) {
        continue;
    }
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($files as $file) {
        if (! $file->isFile() || ! in_array($file->getExtension(), ['php', ''], true)) {
            continue;
        }
        $source = file_get_contents($file->getPathname());
        if (! str_contains($source, '<?php')) {
            continue;
        }
        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $code .= $token[1];
            } else {
                $code .= $token;
            }
        }
    }
}

$rows = [];
foreach ($installed as $name => $version) {
    if (! isset($namespaces[$name])) {
        //A `files` or classmap package: there is no prefix to search for, so say so rather
        //than report a confident "unused".
        $used = '?';
    } else {
        $used = '-';
        foreach ($namespaces[$name] as $prefix) {
            //A prefix with no separator is a bare class name, not a namespace — Parsedown
            //ships one. Searching for `Parsedown\` would never match its only class.
            $needle = str_contains($prefix, '\\') ? $prefix . '\\' : $prefix;
            if (str_contains($code, $needle)) {
                $used = 'USED';
                break;
            }
        }
    }

    $rows[] = [$name, $version, $used, isset($declared[$name]) ? 'declared' : 'NOT DECLARED'];
}

//Actionable rows first: used-but-undeclared, then unknown, then declared-but-unused.
$rank = static fn(array $row): int => match (true) {
    'USED' === $row[2] && 'NOT DECLARED' === $row[3] => 0,
    '?' === $row[2]                                  => 1,
    '-' === $row[2] && 'declared' === $row[3]        => 2,
    'USED' === $row[2]                               => 3,
    default                                          => 4,
};
usort($rows, static fn(array $a, array $b): int => [$rank($a), $a[0]] <=> [$rank($b), $b[0]]);

printf("%-46s %-12s %-5s %s\n", 'PACKAGE', 'VERSION', 'USED', 'IN ' . $root . '/composer.json');
foreach ($rows as $row) {
    printf("%-46s %-12s %-5s %s\n", ...$row);
}
