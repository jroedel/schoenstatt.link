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
 * One composer package, because there is one: `module/{SionModel,JUser,JTranslate}` are
 * directories of this repository, autoloaded through its own PSR-4, so their code is ours
 * and a package only they name reads as plainly USED. When they were audited separately a
 * package could read as unused here and be load-bearing there, which is how removing
 * `laminas-json` broke `JTranslate\Service\CountriesFactory` without this tool saying a word.
 *
 *     docker compose exec -T app php tools/dependency-audit.php
 *
 * Comments are stripped before matching, so prose about a package is not a use of it.
 */

$options = ['laminasOnly' => false];
foreach (array_slice($argv, 1) as $argument) {
    if ('--laminas' === $argument) {
        $options['laminasOnly'] = true;
    } else {
        fwrite(STDERR, "usage: dependency-audit.php [--laminas]\n");
        exit(2);
    }
}

$lock     = json_decode(file_get_contents('composer.lock'), true);
$manifest = json_decode(file_get_contents('composer.json'), true);
$declared = array_merge($manifest['require'] ?? [], $manifest['require-dev'] ?? []);

/** @var array<string, string> $installed */
$installed = [];
/** @var array<string, list<string>> $namespaces */
$namespaces = [];
/** @var array<string, array<string, string>> $packageRequires */
$packageRequires = [];
foreach (array_merge($lock['packages'], $lock['packages-dev'] ?? []) as $package) {
    if ($options['laminasOnly'] && ! str_starts_with($package['name'], 'laminas/')) {
        continue;
    }
    $installed[$package['name']]       = $package['version'];
    $packageRequires[$package['name']] = $package['require'] ?? [];

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
 * This package's own source, comments stripped. Every module is ours, the three shared
 * libraries included — so a package only SionModel names now reads as plainly USED.
 */
$roots = [
    'src', 'config', 'public', 'bin', 'tools', 'test',
    'module/Application', 'module/Books', 'module/Schoenstatt',
    'module/SionModel', 'module/JUser', 'module/JTranslate',
];

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

/**
 * Who else names a package our own code does not?
 *
 * WHY: `-` against a declared line reads as "drop it", and in this repository that reading
 * has been wrong on **every** line it has been tested against — six for six on 2026-09-21.
 * Two mechanisms, and neither is visible in the table above:
 *
 * 1. An installed package names it without requiring it. The case that proved this was
 *    `laminas-modulemanager`, which imported `Laminas\Loader\ModuleAutoloader` in
 *    `ModuleLoaderListener` without requiring `laminas/laminas-loader` — so our own
 *    direct line was the only thing installing a package our code never names, and
 *    dropping it fatalled at boot. Both packages left on 2026-09-21 with
 *    `App\Modules\ModuleConfig`; the mechanism did not.
 *
 * So the two verdicts differ on one question: would the package still arrive without our
 * line? A consumer that also **requires** it answers yes — composer installs it either way
 * and the line is at most a version pin (`PIN?`). A consumer that names it and does not
 * require it answers no, and the line is load-bearing (`USED*`).
 *
 * `PIN?` is not "droppable" either — dropping `symfony/error-handler`'s line let composer
 * resolve it from ^7.4 up to 8.1, because http-kernel accepts `^6.4|^7.0|^8.0`. It means
 * only that the question is about a version, not about existence, and a human answers it.
 *
 * @param array<string, list<string>> $candidates package name => its namespace prefixes
 * @param array<string, array<string, string>> $requires package name => its own require map
 * @return array<string, array<string, bool>> package name => [who => does it require it]
 */
$findConsumers = static function (array $candidates, array $requires): array {
    if ([] === $candidates) {
        return [];
    }

    $sources = [];
    foreach (glob('vendor/*/*', GLOB_ONLYDIR) ?: [] as $directory) {
        $sources[substr($directory, 7)] = [$directory];
    }

    $found = [];
    foreach ($sources as $who => $directories) {
        foreach ($directories as $directory) {
            if (! is_dir($directory)) {
                continue;
            }
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($files as $file) {
                if (! $file->isFile() || 'php' !== $file->getExtension()) {
                    continue;
                }
                $source = file_get_contents($file->getPathname());
                foreach ($candidates as $name => $prefixes) {
                    if ($name === $who || isset($found[$name][$who])) {
                        continue;
                    }
                    foreach ($prefixes as $prefix) {
                        $needle = str_contains($prefix, '\\') ? $prefix . '\\' : $prefix;
                        if (str_contains($source, $needle)) {
                            $found[$name][$who] = isset($requires[$who][$name]);
                            break;
                        }
                    }
                }
            }
        }
    }

    return $found;
};

$candidates = [];
foreach ($rows as $row) {
    if ('-' === $row[2] && 'declared' === $row[3] && isset($namespaces[$row[0]])) {
        $candidates[$row[0]] = $namespaces[$row[0]];
    }
}
$consumers = $findConsumers($candidates, $packageRequires);

foreach ($rows as $index => $row) {
    if (! isset($consumers[$row[0]])) {
        continue;
    }
    //Not "unused": unused by OUR code. Whether the line may go turns on ONE question —
    //would the package still arrive without it? A single consumer that requires it is
    //enough for yes, however many others merely name it. Getting this backwards reads
    //`symfony/error-handler` as load-bearing when it is the measured pin.
    $rows[$index][2] = in_array(true, $consumers[$row[0]], true) ? 'PIN?' : 'USED*';
}

//Actionable rows first: used-but-undeclared, then unknown, then declared-but-unused.
$rank = static fn(array $row): int => match (true) {
    'USED' === $row[2] && 'NOT DECLARED' === $row[3] => 0,
    '?' === $row[2]                                  => 1,
    '-' === $row[2] && 'declared' === $row[3]        => 2,
    'PIN?' === $row[2]                               => 3,
    'USED*' === $row[2]                              => 4,
    'USED' === $row[2]                               => 5,
    default                                          => 6,
};
usort($rows, static fn(array $a, array $b): int => [$rank($a), $a[0]] <=> [$rank($b), $b[0]]);

printf("%-46s %-12s %-5s %s\n", 'PACKAGE', 'VERSION', 'USED', 'IN composer.json');
foreach ($rows as $row) {
    printf("%-46s %-12s %-5s %s\n", ...$row);
}

if ([] !== $consumers) {
    printf(
        "\nUSED* — our own code does not name it, but something the installed set "
        . "serves names it\n        and does NOT require it. The line is what installs the "
        . "package; dropping it breaks them.\nPIN?  — every consumer requires it too, so "
        . "composer installs it either way. The line only bounds\n        the version — "
        . "which can still matter: dropping symfony/error-handler's let it go 7.4 -> 8.1.\n\n"
    );
    foreach ($consumers as $name => $who) {
        ksort($who);
        $parts = [];
        foreach ($who as $consumer => $alsoRequires) {
            $parts[] = $consumer . ($alsoRequires ? ' (requires it)' : ' (does NOT require it)');
        }
        printf("  %s\n      %s\n", $name, implode("\n      ", $parts));
    }
}
