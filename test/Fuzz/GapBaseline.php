<?php

declare(strict_types=1);

namespace SchoenstattTest\Fuzz;

use RuntimeException;

/**
 * The committed record of validation gaps this codebase is currently known to
 * have, and the comparison rule that turns it into a regression net.
 *
 * ## Why a baseline at all
 *
 * The gaps this harness finds number in the hundreds, and they are being fixed by
 * someone else, right now, one field at a time. A suite that failed on all of them
 * would be red on the day it was written, would stay red for weeks, and would
 * therefore be ignored — or, worse, would be the thing blocking the very work it
 * exists to protect. `phpstan-baseline.neon` already established the pattern in
 * this repository and the contract that goes with it: **no new errors**. This is
 * the same idea for form validation.
 *
 * ## Subset semantics, and why this differs from AclGuardRouteDriftTest
 *
 * `AclGuardRouteDriftTest` compares its known-issue list with `assertSame()`, so
 * *fixing* something turns the suite red until the list is edited. That is a
 * reasonable trade when the list has eight entries and nobody is working on it.
 * Here it would be actively harmful: every fix landing on the hardening branch
 * would break the build for whoever pulled next.
 *
 * So the rule is one-directional:
 *
 *  - a gap in the actual result that is **not** in the baseline fails the test —
 *    that is a regression, and it is the whole point;
 *  - a gap in the baseline that is **no longer** in the actual result does not fail
 *    anything. It is printed as a stale entry with the regeneration command, so the
 *    baseline can be tightened at leisure without ever gating a fix.
 *
 * The cost of that choice is that a stale baseline slowly stops protecting the
 * lines it lists, which is why the staleness report is loud rather than silent.
 *
 * ## Regenerating
 *
 *     docker compose exec -T app php test/Fuzz/regenerate-baseline.php
 *
 * Review the diff before committing it. An entry appearing in that diff is a new
 * gap being *accepted*, and accepting one should be a decision, not a side effect
 * of running a script.
 */
final class GapBaseline
{
    public const FILE = __DIR__ . '/known-form-gaps.php';

    /** @var array<string, list<string>> */
    private array $entries;

    /** @param array<string, list<string>> $entries */
    private function __construct(array $entries)
    {
        $this->entries = $entries;
    }

    public static function load(): self
    {
        if (! is_file(self::FILE)) {
            throw new RuntimeException(sprintf(
                'No baseline at %s. Generate one with: '
                . 'docker compose exec -T app php test/Fuzz/regenerate-baseline.php',
                self::FILE
            ));
        }

        /** @var array<string, list<string>> $entries */
        $entries = require self::FILE;

        return new self($entries);
    }

    /**
     * Entries present in $actual but absent from the baseline: the regressions.
     *
     * @param list<string> $actual
     * @return list<string>
     */
    public function newIn(string $category, array $actual): array
    {
        $known = $this->entries[$category] ?? [];
        $new   = array_values(array_diff($actual, $known));
        sort($new);

        return $new;
    }

    /**
     * Entries the baseline still lists that no longer occur: fixed, and safe to
     * delete. Never a failure — see the class docblock.
     *
     * @param list<string> $actual
     * @return list<string>
     */
    public function staleIn(string $category, array $actual): array
    {
        $known = $this->entries[$category] ?? [];
        $stale = array_values(array_diff($known, $actual));
        sort($stale);

        return $stale;
    }

    public function countIn(string $category): int
    {
        return count($this->entries[$category] ?? []);
    }

    /** @return list<string> */
    public function categories(): array
    {
        $categories = array_keys($this->entries);
        sort($categories);

        return $categories;
    }

    /**
     * Write a fresh baseline file.
     *
     * The output is `var_export`-flavoured by hand rather than by `var_export()`
     * itself, so the file stays readable and produces a small, reviewable diff when
     * one entry changes.
     *
     * @param array<string, list<string>> $gaps
     * @param array<string, string>       $descriptions category => one-line explanation
     */
    public static function write(array $gaps, array $descriptions): int
    {
        ksort($gaps);

        $lines = [
            '<?php',
            '',
            '/**',
            ' * Validation gaps this codebase is known to have, as of the last regeneration.',
            ' *',
            ' * GENERATED FILE — do not hand-edit except to delete an entry that has been fixed.',
            ' * Regenerate with:',
            ' *',
            ' *     docker compose exec -T app php test/Fuzz/regenerate-baseline.php',
            ' *',
            ' * The contract is phpstan-baseline.neon\'s: no *new* gaps. An entry here is a gap',
            ' * that is accepted for now, not one that is acceptable. Adding to this file should',
            ' * always be a deliberate decision — see SchoenstattTest\\Fuzz\\GapBaseline.',
            ' *',
            ' * @return array<string, list<string>>',
            ' */',
            '',
            'declare(strict_types=1);',
            '',
            'return [',
        ];

        foreach ($gaps as $category => $entries) {
            sort($entries);
            $lines[] = '';
            if (isset($descriptions[$category])) {
                foreach (self::wrapComment($descriptions[$category], 4) as $comment) {
                    $lines[] = $comment;
                }
            }
            $lines[] = sprintf('    %s => [', self::quote((string) $category));
            foreach ($entries as $entry) {
                $lines[] = sprintf('        %s,', self::quote($entry));
            }
            $lines[] = '    ],';
        }

        $lines[] = '];';
        $lines[] = '';

        $written = file_put_contents(self::FILE, implode("\n", $lines));

        if (false === $written) {
            throw new RuntimeException('could not write ' . self::FILE);
        }

        self::matchOwnershipOfContainingDirectory();

        return array_sum(array_map('count', $gaps));
    }

    /**
     * Hand the generated file back to whoever owns the working tree.
     *
     * The regenerator runs as root inside the capsule against a bind-mounted repo,
     * so a plain `file_put_contents()` leaves a root-owned file on the host that the
     * developer can neither edit nor `git checkout` — the same class of problem
     * `HOST_UID` exists to solve for `data/`. The owner of `test/Fuzz/` is the right
     * answer and needs no configuration. Failures are ignored on purpose: outside a
     * container this is both unnecessary and not permitted.
     */
    private static function matchOwnershipOfContainingDirectory(): void
    {
        $directory = dirname(self::FILE);
        $stat       = @stat($directory);

        if (false === $stat) {
            return;
        }

        @chown(self::FILE, $stat['uid']);
        @chgrp(self::FILE, $stat['gid']);
        @chmod(self::FILE, 0664);
    }

    /** @return list<string> */
    private static function wrapComment(string $text, int $indent): array
    {
        $prefix = str_repeat(' ', $indent) . '// ';
        $out    = [];
        foreach (explode("\n", wordwrap($text, 100 - $indent, "\n", false)) as $line) {
            $out[] = rtrim($prefix . $line);
        }

        return $out;
    }

    /**
     * Single-quoted PHP string. Entries can contain quotes (field names are quoted
     * in the messages) and, in principle, backslashes from class names.
     */
    private static function quote(string $value): string
    {
        return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
    }
}
