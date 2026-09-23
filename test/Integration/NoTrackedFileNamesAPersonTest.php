<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use PHPUnit\Framework\TestCase;
use SchoenstattTest\Fuzz\FormRepository;
use Throwable;

use function array_slice;
use function count;
use function dirname;
use function exec;
use function escapeshellarg;
use function file_get_contents;
use function getenv;
use function implode;
use function is_readable;
use function is_string;
use function mb_strlen;
use function sprintf;
use function str_contains;
use function trim;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Fuzz/FormRepository.php';

/**
 * No file this repository tracks contains the name of a person in the database.
 *
 * ## Why a test rather than a one-off sweep
 *
 * The repository is going public (#259), and a name that reaches a public git history
 * cannot be taken back out of it — `git filter-repo` rewrites every commit after it and
 * every clone anyone already has keeps the old one. A person who asks to be forgotten can
 * be erased from the database and from the live site; they cannot be erased from a
 * published history. So the check that matters is the one that runs *before* the commit.
 *
 * Nothing here was malicious or even careless. Both form recordings sample the first three
 * options of every list and the last, which is a good idea for 130 of the 136
 * database-filled selects and was four real names for the six filled from the persons
 * table. The fifth was an illustrative URL in a Twig comment, written while measuring a
 * genuine rendering bug on a real page. That is the shape of this problem: it arrives as a
 * side effect of doing something else, which is why it needs a check rather than a rule.
 *
 * ## What counts as a name
 *
 * `SchoenstattTable::getPersonValueOptions(true)` — the one answer every person select in
 * the application is filled from, inactive persons included. A label from that list
 * appearing anywhere in a tracked file is the failure. Not a pattern, not a heuristic: a
 * `Last, First` regex would flag `A. Deichertsche Verlagsbuchhandlung, Leipzig` and miss a
 * person recorded with one name.
 *
 * Labels shorter than {@see MIN_LENGTH} are skipped. A person recorded as `Li` or `Ana`
 * is a substring of ordinary prose and of a hundred identifiers, and a test that fails on
 * the word "analysis" is a test that gets deleted.
 *
 * ## What a failure means
 *
 * Not "regenerate the baseline". The recorders redact a person label where they sample one
 * (`ElementSurface::redactPersons()`, `FormMarkup::redactPersons()`), so a recording that
 * fails here means a label reached the file by a path neither of them covers. Find that
 * path. Anywhere else — a comment, a fixture, a document — remove the name and use an
 * invented one, the way `database/ci/ci-seed.sql` does.
 */
final class NoTrackedFileNamesAPersonTest extends TestCase
{
    /**
     * Below this many characters a name is a substring of ordinary text rather than a
     * disclosure. Measured against the capsule's corpus: 325 labels, none under this
     * length is distinctive enough to search for.
     */
    private const MIN_LENGTH = 7;

    public function testNoTrackedFileContainsAPersonName(): void
    {
        if ('seed' === getenv('SCHOENSTATT_TEST_CORPUS')) {
            self::markTestSkipped('the seed corpus invents its persons, so it can prove nothing about the real ones');
        }

        if (! is_readable(dirname(__DIR__, 2) . '/config/autoload/local.php')) {
            self::markTestSkipped('no config/autoload/local.php, so there is no db configuration');
        }

        try {
            $labels = FormRepository::personLabels();
        } catch (Throwable $e) {
            self::markTestSkipped('no reachable database: ' . $e->getMessage());
        }

        $names = [];
        foreach ($labels as $label => $_) {
            if (mb_strlen((string) $label) >= self::MIN_LENGTH) {
                $names[] = (string) $label;
            }
        }

        self::assertNotSame([], $names, 'no person labels were loaded, so this test proves nothing');

        $found = [];
        foreach ($this->trackedFiles() as $file) {
            $contents = @file_get_contents($file);
            if (! is_string($contents) || '' === $contents) {
                continue;
            }

            foreach ($names as $name) {
                if (str_contains($contents, $name)) {
                    $found[] = sprintf('%s names %s', $file, $name);
                }
            }
        }

        self::assertSame(
            [],
            array_slice($found, 0, 20),
            sprintf(
                "%d tracked file(s) contain the name of a person in the database:\n  %s\n\n"
                . "A public history cannot be un-published. Remove the name — an invented one "
                . "reads just as well, as database/ci/ci-seed.sql argues.",
                count($found),
                implode("\n  ", array_slice($found, 0, 20))
            )
        );
    }

    /**
     * What `git ls-files` says, which is also exactly what a release is (docs/DEPLOY.md)
     * and what a public repository would publish.
     *
     * @return list<string>
     */
    private function trackedFiles(): array
    {
        $root = dirname(__DIR__, 2);

        //GIT_CONFIG_* rather than a repository setting, because the capsule runs its PHP
        //as root over a tree owned by the host user and git refuses that with "detected
        //dubious ownership" — on stderr, with exit 0 and no output. Without this the test
        //skips in the one environment that can run it, which is worse than not having it.
        $output = [];
        $status = 0;
        exec(
            sprintf(
                'cd %s && GIT_CONFIG_COUNT=1 GIT_CONFIG_KEY_0=safe.directory '
                . 'GIT_CONFIG_VALUE_0=%s git ls-files 2>/dev/null',
                escapeshellarg($root),
                escapeshellarg($root)
            ),
            $output,
            $status
        );

        if (0 !== $status || [] === $output) {
            self::markTestSkipped('git ls-files answered nothing; this is not a working tree');
        }

        $files = [];
        foreach ($output as $line) {
            $path = trim($line);
            if ('' !== $path) {
                $files[] = $root . '/' . $path;
            }
        }

        return $files;
    }
}
