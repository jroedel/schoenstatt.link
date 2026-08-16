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
    /** The two sanctioned implementations, which naturally mention the query form. */
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

    public function testNoControllerReadsTheKeyFromTheQueryStringItself(): void
    {
        $offenders = [];
        foreach ($this->phpFilesUnder('module', 'src') as $file) {
            $relative = $this->relative($file);
            if (in_array($relative, self::SANCTIONED, true)) {
                continue;
            }
            $source = file_get_contents($file);
            // The laminas form, `$this->params()->fromQuery('key')`, and the
            // Symfony one, `$request->query->get('key')`. Both spellings, because
            // the codebase has two front controllers and the hazard is identical.
            if (
                preg_match('/fromQuery\(\s*[\'"]key[\'"]/', $source)
                || preg_match('/query->get\(\s*[\'"]key[\'"]/', $source)
            ) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "These read the maintenance key from the query string directly instead of using\n"
            . "MaintenanceKeyTrait::assertApiKeyIn() / App\\Http\\MaintenanceKey:\n  "
            . implode("\n  ", $offenders)
            . "\n\nA query string is recorded in the access log, so it cannot be the only channel.\n"
            . 'Use the shared gate, which accepts an X-Api-Key header and compares with hash_equals().'
        );
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
