<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use Laminas\Db\Adapter\AdapterInterface;
use PHPUnit\Framework\TestCase;
use SchoenstattTest\Form\EngineSurface;
use SchoenstattTest\Fuzz\FormRepository;
use Throwable;

use function array_diff;
use function array_key_exists;
use function array_keys;
use function array_slice;
use function array_values;
use function count;
use function implode;
use function is_array;
use function preg_replace;
use function sprintf;
use function strlen;
use function substr;
use function var_export;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Form/EngineSurface.php';

/**
 * Every form's engine still answers what the baseline records.
 *
 * ## What this is for
 *
 * Steps 3 and 4 of iteration A replace the twenty laminas validator classes and the twelve
 * filter classes the specifications name. What they change is a verdict, a message and a
 * **stored value** — and the last of those is invisible to `test/Form/form-markup.php`,
 * because `value=""` is what both `''` and `null` render as. `getValues()` is the array
 * that reaches `SionTable::updateEntity()`, so this is the file that says a filter
 * replacement wrote the same thing to the database.
 *
 * It also outlives the three parity tests that measure the engine today, all of which
 * work by running laminas' assembled input filter beside it.
 *
 * ## Reading a failure
 *
 * The message names the form, the dataset and the path that moved. If the change is
 * intended, regenerate — deliberately, and read the diff:
 *
 *     docker compose exec -T -u www-data app php -d memory_limit=1G \
 *         test/Form/regenerate-engine-surface.php
 */
final class EngineSurfaceTest extends TestCase
{
    /** A floor on the forms measured, so a broken discovery reads as a failure. 43 today. */
    private const FORM_FLOOR = 40;

    /** How many differences a failure message lists before it stops. */
    private const REPORTED = 25;

    public function testEveryFormAnswersWhatTheBaselineRecords(): void
    {
        self::requireDatabase();

        /** @var array<string, array<string, array<string, mixed>>> $baseline */
        $baseline = require __DIR__ . '/../Form/engine-surface.php';
        $actual   = EngineSurface::collect();

        self::assertGreaterThanOrEqual(
            self::FORM_FLOOR,
            count($actual),
            'almost no form was measured, so this test proves nothing'
        );

        $missing = array_values(array_diff(array_keys($baseline), array_keys($actual)));
        $added   = array_values(array_diff(array_keys($actual), array_keys($baseline)));

        self::assertSame([], $missing, 'forms the baseline records and this run did not build: '
            . implode(', ', array_slice($missing, 0, self::REPORTED)));
        self::assertSame([], $added, 'forms this run built that the baseline does not record. If they '
            . 'are meant to exist, regenerate and read the diff: '
            . implode(', ', array_slice($added, 0, self::REPORTED)));

        $differences = [];
        foreach ($baseline as $class => $states) {
            self::compare($class, $states, $actual[$class] ?? [], $differences);
        }

        self::assertSame(
            [],
            $differences,
            "The engine answers differently from the recorded baseline:\n  "
            . implode("\n  ", array_slice($differences, 0, self::REPORTED))
        );
    }

    /**
     * Walk both trees together, reporting a leaf at a time.
     *
     * Leaf by leaf rather than an assertion per form: a form whose whole `values` array is
     * printed on a failure is 80 lines of context around one changed key, and the change
     * is the thing to read.
     *
     * @param array<string, mixed> $expected
     * @param array<string, mixed> $got
     * @param list<string>         $differences
     */
    private static function compare(string $path, array $expected, mixed $got, array &$differences): void
    {
        if (! is_array($got)) {
            $differences[] = sprintf('%s: was an array, is now %s', $path, self::brief($got));

            return;
        }

        foreach ($expected as $key => $value) {
            //array_key_exists, not `??`: a recorded null is a real answer — it is what
            //`ToNull` produces, which is most of what this file is for — and `??` would
            //report every one of them as absent.
            $found = array_key_exists($key, $got) ? $got[$key] : '<<absent>>';

            if (is_array($value)) {
                self::compare($path . '/' . $key, $value, $found, $differences);
                continue;
            }

            if ($found !== $value) {
                $differences[] = sprintf(
                    '%s/%s: was %s, is now %s',
                    $path,
                    $key,
                    self::brief($value),
                    self::brief($found)
                );
            }
        }

        foreach (array_keys($got) as $key) {
            if (! array_key_exists($key, $expected)) {
                $differences[] = sprintf('%s/%s: present now, absent from the baseline', $path, $key);
            }
        }
    }

    /** Skips rather than fails where there is no database — CI has none. */
    private static function requireDatabase(): void
    {
        try {
            /** @var AdapterInterface $adapter */
            $adapter = FormRepository::instance()->container()->get('Laminas\Db\Adapter\Adapter');
            $adapter->query('SELECT 1', []);
        } catch (Throwable $e) {
            self::markTestSkipped('no database: ' . $e->getMessage());
        }
    }

    private static function brief(mixed $value): string
    {
        $printed = (string) preg_replace('/\s+/', ' ', var_export($value, true));

        return strlen($printed) > 90 ? substr($printed, 0, 87) . '...' : $printed;
    }
}
