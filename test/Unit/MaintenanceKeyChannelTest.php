<?php

namespace SchoenstattTest\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Structural test: every maintenance-key check goes through
 * SionModel\Controller\MaintenanceKeyTrait (laminas side) or
 * App\Http\MaintenanceKey (Symfony side), and nowhere else.
 *
 * This exists because a bespoke one hid in plain sight for years.
 * Books\Controller\LibrariesController::sendBookNoticesAction() read the key
 * with `$this->params()->fromQuery('key')` and compared it with `in_array()`,
 * so it:
 *
 *   - accepted the key ONLY in a query string, which is written verbatim to the
 *     web server's access log on every call and kept in the shell history of
 *     whatever invoked it. The one caller was a cron entry, so the key was
 *     logged monthly, in the clear, for a secret that never rotates;
 *   - compared with in_array() rather than hash_equals();
 *   - was invisible to the plan in docs/BACKLOG.md for dropping the `?key=`
 *     fallback, which names only the trait and App\Http\MaintenanceKey. Carrying
 *     that plan out would have left this endpoint leaking, with nobody looking.
 *
 * The endpoint sends real mail to real borrowers, and its route guard is
 * ['user', 'guest'] — the controller check is the only protection it has.
 *
 * Nothing about a second implementation looks wrong at the call site; it reads
 * like an ordinary authorization check. The only reliable defence is that no
 * second implementation is allowed to exist, which is what this asserts.
 *
 * Filesystem-only: no vendor autoload, no container, no database.
 * php composer.phar unit
 */
class MaintenanceKeyChannelTest extends TestCase
{
    /**
     * The two sanctioned implementations.
     *
     * Until 2026-08-17 these were *excluded* from the query-string sweep below,
     * because both accepted `?key=` as a documented fallback. Neither does now, so
     * the invariant tightened from "only these two may read the key from a query
     * string" to "nothing may" — including them. The list survives only to guard
     * against a rename (see the second test).
     */
    private const SANCTIONED = [
        'module/SionModel/src/Controller/MaintenanceKeyTrait.php',
        'src/Http/MaintenanceKey.php',
    ];

    /** @return list<string> */
    private function phpFilesUnder(string ...$roots): array
    {
        $found = [];
        foreach ($roots as $root) {
            $dir = dirname(__DIR__, 2) . '/' . $root;
            if (! is_dir($dir)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $file) {
                if ($file->isFile() && 'php' === $file->getExtension()) {
                    $found[] = $file->getPathname();
                }
            }
        }
        sort($found);

        return $found;
    }

    private function relative(string $absolute): string
    {
        return ltrim(str_replace(dirname(__DIR__, 2), '', $absolute), '/');
    }

    /**
     * The file's PHP with every comment removed.
     *
     * Both checks below have to reason about what the code *does*, and the two
     * gates carry long docblocks explaining precisely why a query string is
     * unsafe. Matching raw source would fail on the explanation and pass on a
     * commented-out reintroduction — exactly backwards.
     */
    private function codeOnly(string $source): string
    {
        $code = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if (T_COMMENT === $token[0] || T_DOC_COMMENT === $token[0]) {
                    continue;
                }
                $code .= $token[1];
                continue;
            }
            $code .= $token;
        }

        return $code;
    }

    public function testNothingReadsTheMaintenanceKeyFromTheQueryString(): void
    {
        $offenders = [];
        foreach ($this->phpFilesUnder('module', 'src') as $file) {
            // The laminas form, `$this->params()->fromQuery('key')`; the Symfony
            // one, `$request->query->get('key')`; and `query->all()['key']`, which
            // is how the Symfony gate itself used to read it — InputBag::get()
            // throws on `?key[]=`, so all() was the deliberate spelling and a
            // sweep that missed it would miss the most likely reintroduction.
            $code = $this->codeOnly(file_get_contents($file));
            if (
                preg_match('/fromQuery\(\s*[\'"]key[\'"]/', $code)
                || preg_match('/query->get\(\s*[\'"]key[\'"]/', $code)
                || preg_match('/query->all\(\)\s*\[\s*[\'"]key[\'"]\s*\]/', $code)
            ) {
                $offenders[] = $this->relative($file);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "These read the maintenance key from the query string:\n  "
            . implode("\n  ", $offenders)
            . "\n\nA query string is written verbatim to the web server's access log and kept in\n"
            . "the shell history of whatever invoked it, so for a secret that never rotates it\n"
            . "is a leak by default. The X-Api-Key header is the only channel as of 2026-08-17.\n"
            . 'Use MaintenanceKeyTrait::assertApiKeyIn() or App\Http\MaintenanceKey.'
        );
    }

    public function testNeitherGateStillAcceptsAQueryString(): void
    {
        // The sweep above would also catch this, but only for the exact spellings
        // it knows. This asserts the positive property directly: whatever the two
        // gates do to find the key, the request's query bag is not part of it.
        foreach (self::SANCTIONED as $relative) {
            $code = $this->codeOnly(file_get_contents(dirname(__DIR__, 2) . '/' . $relative));

            $this->assertStringNotContainsString(
                'query',
                $code,
                "$relative still reaches for the query string in code. The header is the only\n"
                . "channel; accepting a secret in a URL cannot be made safe by merely preferring\n"
                . 'the header, because the access-log entry is written either way.'
            );
        }
    }

    public function testTheSanctionedImplementationsStillExist(): void
    {
        // Guards the test itself: if a sanctioned file is renamed, the exclusion
        // list above silently stops excluding anything and this test would go on
        // passing while checking a set that no longer contains the real gate.
        foreach (self::SANCTIONED as $relative) {
            $this->assertFileExists(
                dirname(__DIR__, 2) . '/' . $relative,
                "$relative is in the sanctioned list but does not exist; update self::SANCTIONED."
            );
        }
    }

    public function testTheSharedGateComparesInConstantTimeAndRefusesNonStrings(): void
    {
        $trait = file_get_contents(
            dirname(__DIR__, 2) . '/module/SionModel/src/Controller/MaintenanceKeyTrait.php'
        );

        $this->assertStringContainsString(
            'hash_equals(',
            $trait,
            'The shared gate must compare keys with hash_equals(), not in_array()/===.'
        );
        $this->assertStringContainsString(
            'X-Api-Key',
            $trait,
            'The shared gate must accept the key in an X-Api-Key header.'
        );
        $this->assertStringContainsString(
            'is_string(',
            $trait,
            'The shared gate must refuse a non-string key; ?key[]= must never reach hash_equals().'
        );
    }
}
