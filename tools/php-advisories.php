<?php

/**
 * Which of PHP's own security advisories affect the PHP that production runs?
 *
 * Dependabot and `composer audit` cover the packages in composer.lock; neither covers the
 * interpreter, whose advisories are published on github.com/php/php-src and belong to no
 * package ecosystem. php2shell (CVE-2026-109618/-109619) reached production that way.
 *
 *   gh api --paginate 'repos/php/php-src/security-advisories?state=published&per_page=100' \
 *       --jq '.[]' > advisories.ndjson
 *   php tools/php-advisories.php advisories.ndjson [--since=2026-10-15] [--php=8.5.12] [--all]
 *
 * Input is one advisory per line, as `--jq '.[]'` writes them. The version defaults to
 * `config.platform.php` in composer.json, which states what production runs — so the pin
 * must move whenever production's PHP does, or this answers for a server that no longer
 * exists. `--since` keeps advisories published or updated on or after that date.
 *
 * Output is one JSON object per line for each advisory whose verdict is `affected` or
 * `unknown` (every verdict with `--all`). Exit 0 when none is printed, 1 when one is,
 * 2 on bad input.
 *
 * ## The verdict
 *
 * The advisories' version data is hand-written and inconsistent: the component is
 * `php-src`, `ext-soap`, `PHP-FPM` or nothing; a range is `< 8.5.12`, `>=8.2.0, <=8.2.2`,
 * `8.1.0 - 8.1.28` or a single version; `patched_versions` is one version or a list.
 * So, per advisory:
 *
 * 1. If any entry names a patched version on our branch (same major.minor), those entries
 *    alone decide: affected when we are below it. Another branch's open-ended range
 *    (`< 8.5.12` while we run 8.4.30) must not outvote our branch's own fix.
 * 2. Otherwise every entry's range is evaluated, and any match means affected — with no
 *    fix on our branch, which is the worst case and is stated as such.
 * 3. A range that does not parse, or an advisory with no entries, is `unknown`: a person
 *    reads it. A guess either way is worse than an issue someone closes.
 *
 * The component is reported, not filtered on: whether production loads `ext-soap` is a
 * fact of the server, read from /en/sm/phpinfo, not of this repository.
 */

declare(strict_types=1);

const VERSION = '\d+\.\d+\.\d+';

/**
 * @return list<array{string, string}>|null [operator, version] pairs that must all hold, or
 *                                      null when the range does not parse
 */
function parseRange(string $range): ?array
{
    $range = trim($range);
    if ($range === '') {
        return null;
    }
    $conditions = [];
    foreach (explode(',', $range) as $part) {
        $part = trim($part);
        if (preg_match('/^(' . VERSION . ')\s+-\s+(' . VERSION . ')$/', $part, $m)) {
            $conditions[] = ['>=', $m[1]];
            $conditions[] = ['<=', $m[2]];
        } elseif (preg_match('/^(<=|>=|<|>|=)?\s*(' . VERSION . ')$/', $part, $m)) {
            $conditions[] = [$m[1] === '' ? '=' : $m[1], $m[2]];
        } else {
            return null;
        }
    }

    return $conditions;
}

/**
 * Some ranges are a list of alternatives rather than one conjunction:
 * `7.3.27 - 7.3.33, 7.4.15 - 7.4.33`. A comma-separated list of hyphen spans is read
 * as "any of these".
 */
function rangeMatches(string $range, string $version): ?bool
{
    $parts = array_map('trim', explode(',', $range));
    $spans = array_filter($parts, static fn (string $p): bool => str_contains($p, ' - '));
    if (count($parts) > 1 && count($spans) === count($parts)) {
        foreach ($parts as $part) {
            if (rangeMatches($part, $version) === true) {
                return true;
            }
        }

        return false;
    }
    $conditions = parseRange($range);
    if ($conditions === null) {
        return null;
    }
    foreach ($conditions as [$operator, $bound]) {
        if (! version_compare($version, $bound, $operator === '=' ? '==' : $operator)) {
            return false;
        }
    }

    return true;
}

/**
 * @param array<string, mixed> $advisory
 * @return array{string, ?string} the verdict, and the fixed version on our branch if any
 */
function verdict(array $advisory, string $version): array
{
    $entries = is_array($advisory['vulnerabilities'] ?? null) ? $advisory['vulnerabilities'] : [];
    if ($entries === []) {
        return ['unknown', null];
    }
    $branch = implode('.', array_slice(explode('.', $version), 0, 2)) . '.';

    $fixes = [];
    foreach ($entries as $entry) {
        preg_match_all('/' . VERSION . '/', (string) ($entry['patched_versions'] ?? ''), $m);
        foreach ($m[0] as $patched) {
            if (str_starts_with($patched, $branch)) {
                $fixes[] = $patched;
            }
        }
    }
    if ($fixes !== []) {
        usort($fixes, static fn (string $a, string $b): int => version_compare($a, $b));
        $fix = $fixes[0];

        return [version_compare($version, $fix, '<') ? 'affected' : 'not-affected', $fix];
    }

    $unknown = false;
    foreach ($entries as $entry) {
        $match = rangeMatches((string) ($entry['vulnerable_version_range'] ?? ''), $version);
        if ($match === true) {
            return ['affected', null];
        }
        $unknown = $unknown || $match === null;
    }

    return [$unknown ? 'unknown' : 'not-affected', null];
}

$file  = null;
$since = null;
$php   = null;
$all   = false;
foreach (array_slice($_SERVER['argv'] ?? [], 1) as $arg) {
    if (str_starts_with($arg, '--since=')) {
        $since = substr($arg, 8);
    } elseif (str_starts_with($arg, '--php=')) {
        $php = substr($arg, 6);
    } elseif ($arg === '--all') {
        $all = true;
    } elseif ($file === null && ! str_starts_with($arg, '--')) {
        $file = $arg;
    } else {
        fwrite(STDERR, "unknown argument: $arg\n");
        exit(2);
    }
}
if ($file === null || ! is_readable($file)) {
    fwrite(STDERR, "usage: php tools/php-advisories.php <advisories.ndjson>"
        . " [--since=YYYY-MM-DD] [--php=X.Y.Z] [--all]\n");
    exit(2);
}
if ($php === null) {
    $composer = json_decode((string) file_get_contents(dirname(__DIR__) . '/composer.json'), true);
    $php      = is_array($composer) ? ($composer['config']['platform']['php'] ?? null) : null;
}
if (! is_string($php) || ! preg_match('/^' . VERSION . '$/', $php)) {
    fwrite(STDERR, "no usable PHP version (config.platform.php, or --php=X.Y.Z)\n");
    exit(2);
}

$printed = 0;
foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $n => $line) {
    $advisory = json_decode($line, true);
    if (! is_array($advisory) || ! isset($advisory['ghsa_id'])) {
        fwrite(STDERR, 'line ' . ($n + 1) . " is not an advisory\n");
        exit(2);
    }
    $touched = max((string) ($advisory['published_at'] ?? ''), (string) ($advisory['updated_at'] ?? ''));
    if ($since !== null && $touched < $since) {
        continue;
    }
    [$verdict, $fix] = verdict($advisory, $php);
    if (! $all && $verdict === 'not-affected') {
        continue;
    }
    $components = [];
    foreach ($advisory['vulnerabilities'] ?? [] as $entry) {
        $components[] = (string) ($entry['package']['name'] ?? '');
    }
    echo json_encode([
        'ghsa'      => $advisory['ghsa_id'],
        'cve'       => $advisory['cve_id'] ?? null,
        'severity'  => $advisory['severity'] ?? null,
        'summary'   => $advisory['summary'] ?? '',
        'url'       => $advisory['html_url'] ?? null,
        'published' => $advisory['published_at'] ?? null,
        'component' => implode(', ', array_values(array_unique(array_filter($components)))),
        'php'       => $php,
        'verdict'   => $verdict,
        'fixed_in'  => $fix,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    if ($verdict !== 'not-affected') {
        $printed++;
    }
}

exit($printed > 0 ? 1 : 0);
