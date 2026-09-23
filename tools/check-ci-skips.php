<?php

declare(strict_types=1);

/**
 * Which test classes do not actually run on a bare CI runner — as a checked contract.
 *
 * ## Why this exists
 *
 * On 2026-09-08 the integration job reported `Tests: 1334, Assertions: 3085, Skipped: 195`
 * and a green tick. The same suite in the docker capsule reports `Tests: 1364,
 * Assertions: 7214, Skipped: 14`. So CI runs **43% of the assertions** the capsule does,
 * and the green tick says nothing about the other 57%.
 *
 * That gap is legitimate and cannot be closed: `database/` holds ninety incremental
 * migrations and no base schema, the capsule's data comes from a production export that is
 * gitignored, and a good number of these tests assert against real rows (527 events, the
 * per-library ACL rules, the sort-text coverage). A CI database would not make them pass;
 * it would make them fail.
 *
 * What *can* be closed is the silence. The number 195 tells a reader nothing, and a test
 * that starts skipping for a **new** reason — an `is_readable()` guard added to a class
 * that used to run, an extension that stops being installed, a container that stops
 * building — lands inside that number and is indistinguishable from the rest of it.
 *
 * So this script pins the *set of classes*, not the count:
 *
 * - a class with a skipped test that is **not** in the baseline fails the build, and its
 *   name is printed, because that is a class which used to run here and no longer does;
 * - a baselined class with **no** skipped test prints as stale, and does not fail, exactly
 *   as `test/Fuzz/known-form-gaps.php` treats a gap that has been closed.
 *
 * Per class rather than per test on purpose: a data provider's cases come and go with the
 * data, so a per-test list would flap without telling anyone anything. A class either runs
 * here or it does not.
 *
 * ## Usage
 *
 *     php tools/check-ci-skips.php --bare <junit.xml> [baseline]
 *
 * The XML comes from `phpunit --log-junit`. `<skipped/>` carries no message there — the
 * reason lives in the baseline file, written by hand, which is the point: a human says why
 * a class cannot run on a bare runner, and the machine checks that the list is still true.
 *
 * ## Why `--bare` is mandatory rather than detected
 *
 * The baseline describes a runner with **no database**. Point this at a log from the
 * docker capsule, which has one, and 39 of the 42 entries are legitimately stale while
 * `TwigCacheDegradationTest` — which skips there because `docker compose exec` runs as
 * root and root is never denied a write — is legitimately unlisted. Every single verdict
 * inverts.
 *
 * An earlier draft guessed the environment from the ratio of stale entries. That is the
 * wrong shape for a check whose entire job is to notice when something stops running: the
 * guess would have suppressed a genuine finding on any run where several classes were
 * fixed at once, and it would have done so silently. So the caller states which
 * environment produced the log, and a caller that does not state it gets no verdict.
 */

const DEFAULT_BASELINE = __DIR__ . '/../test/known-ci-skips.txt';

$args = array_slice($argv, 1);
$bare = in_array('--bare', $args, true);
$args = array_values(array_filter($args, static fn (string $a): bool => '--bare' !== $a));

$xmlPath      = $args[0] ?? '';
$baselinePath = $args[1] ?? DEFAULT_BASELINE;

if ('' === $xmlPath) {
    fwrite(STDERR, "usage: php tools/check-ci-skips.php --bare <junit.xml> [baseline]\n");
    exit(2);
}

if (! $bare) {
    echo "check-ci-skips: --bare not given, so nothing was checked.\n"
        . "  This baseline is about what a runner on the CI SEED cannot run. Against a\n"
        . "  capsule log every verdict in it inverts — the five corpus-bound classes skip\n"
        . "  there only because SCHOENSTATT_TEST_CORPUS is set, and do not skip here — so\n"
        . "  the check refuses to guess which kind of log it was handed. CI passes --bare;\n"
        . "  nothing else should.\n";
    exit(0);
}

if (! is_readable($xmlPath)) {
    fwrite(STDERR, "check-ci-skips: cannot read '$xmlPath'.\n");
    exit(2);
}

if (! is_readable($baselinePath)) {
    fwrite(STDERR, "check-ci-skips: cannot read baseline '$baselinePath'.\n");
    exit(2);
}

[$skipped, $present] = classes($xmlPath);
$known               = baseline($baselinePath);

$unlisted = array_values(array_diff($skipped, $known));
//Three verdicts, not two. A baselined name that is absent from the log entirely is a
//DIFFERENT finding from one that ran, and conflating them cost real time on the first
//run of this check: `SchoenstattTest\Integration\PublicationLinkingTest` was reported
//as "baselined, but ran here" when no such class exists — the file's namespace is
//`BooksTest\Integration`, so the name had simply never matched anything.
$stale   = array_values(array_intersect(array_diff($known, $skipped), $present));
$unknown = array_values(array_diff($known, $present));

sort($unlisted);
sort($stale);
sort($unknown);

printf(
    "check-ci-skips: %d classes skipped tests, %d of them baselined.\n",
    count($skipped),
    count($skipped) - count($unlisted)
);

foreach ($stale as $class) {
    //Not a failure. A class that stopped skipping is the direction everyone wants, and
    //its line should be deleted from the baseline — but a build should not go red for it.
    printf("  stale   %s — baselined, and ran here. Delete its baseline line.\n", $class);
}

//This one IS a failure, because a baseline entry naming nothing protects nothing. A
//typo'd namespace looks identical to a correct entry and silently exempts the class it
//was meant to cover.
foreach ($unknown as $class) {
    fwrite(
        STDERR,
        "  unknown $class — baselined, but no test of that class ran or skipped.\n"
        . "          Either the name is wrong (check the file's `namespace`; not every test\n"
        . "          in this suite is SchoenstattTest\\Integration) or the class is gone.\n"
    );
}

if ([] === $unlisted && [] === $unknown) {
    echo "check-ci-skips: ok, no class skipped tests without saying why.\n";
    exit(0);
}

if ([] === $unlisted) {
    fwrite(STDERR, "\ncheck-ci-skips: FAIL — the baseline names classes that do not exist (above).\n");
    exit(1);
}

fwrite(STDERR, "\ncheck-ci-skips: FAIL — these classes skipped tests and are not in the baseline:\n\n");
foreach ($unlisted as $class) {
    fwrite(STDERR, "  $class\n");
}
fwrite(
    STDERR,
    "\nEach one used to run here. Either it lost something it needs — a php extension, a\n"
    . "readable config, a service — or a skip guard was added to it. Fix that, or add the\n"
    . "class to " . realpath($baselinePath) . " with the reason it cannot run\n"
    . "on a runner that has no database.\n"
);

exit(1);

/**
 * Two sets from one PHPUnit JUnit log: classes with a skipped test, and every class the
 * log mentions at all.
 *
 * The second is what makes a typo'd baseline entry detectable — see the call site.
 *
 * @return array{0: list<string>, 1: list<string>}
 */
function classes(string $path): array
{
    $previous = libxml_use_internal_errors(true);
    $xml      = simplexml_load_file($path);
    libxml_use_internal_errors($previous);

    if (false === $xml) {
        fwrite(STDERR, "check-ci-skips: '$path' is not parseable XML.\n");
        exit(2);
    }

    //`//testcase` rather than a walk of the suite tree: PHPUnit nests testsuite elements
    //by directory, file and data provider, and the depth is not stable across versions.
    //The attribute is, and it has been since the format was introduced.
    $skipped = [];
    $present = [];
    foreach ($xml->xpath('//testcase') ?? [] as $case) {
        $class = (string) ($case['class'] ?? '');
        if ('' === $class) {
            continue;
        }
        $present[$class] = true;
        if (isset($case->skipped)) {
            $skipped[$class] = true;
        }
    }

    $skippedNames = array_keys($skipped);
    $presentNames = array_keys($present);
    sort($skippedNames);
    sort($presentNames);

    return [$skippedNames, $presentNames];
}

/**
 * The baselined class names. `#` starts a comment, in column one or after a name.
 *
 * @return list<string>
 */
function baseline(string $path): array
{
    $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];

    $classes = [];
    foreach ($lines as $line) {
        $name = trim(explode('#', $line, 2)[0]);
        if ('' !== $name) {
            $classes[] = $name;
        }
    }

    sort($classes);

    return array_values(array_unique($classes));
}
