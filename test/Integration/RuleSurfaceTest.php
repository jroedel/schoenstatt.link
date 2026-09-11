<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use Laminas\Db\Adapter\AdapterInterface;
use PHPUnit\Framework\TestCase;
use SchoenstattTest\Fuzz\FormRepository;
use SchoenstattTest\Rules\RuleSurface;
use SchoenstattTest\Rules\UriSurface;
use Throwable;

use function array_diff;
use function array_keys;
use function array_slice;
use function array_values;
use function count;
use function implode;
use function is_array;
use function sprintf;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Rules/RuleSurface.php';
require_once __DIR__ . '/../Rules/UriSurface.php';

/**
 * Every rule still answers what the baseline records, and every URL still stores the same.
 *
 * ## What these two files are for
 *
 * Iteration A replaces the twenty laminas validator classes, the twelve filter classes and
 * `Laminas\Uri\Http` with our own. `test/Rules/rule-surface.php` is what says a
 * replacement decides the same way about the same value, and `test/Rules/uri-surface.php`
 * is what says a URL column receives the same bytes.
 *
 * They exist because the recordings that came before them cannot answer either question.
 * `test/Form/engine-surface.php` reaches each rule with the one or two values its form's
 * dataset happens to carry — never `'0'` for `ToNull`, never an unclosed tag for
 * `StripTags` — and `SionTable::filterUrl()` is not on a form path at all.
 *
 * ## Reading a failure
 *
 * The message names the rule, the input and the two answers. If the change is intended,
 * regenerate — deliberately, and read the diff:
 *
 *     docker compose exec -T -u www-data app php -d memory_limit=1G \
 *         test/Rules/regenerate-rule-surface.php
 *     docker compose exec -T -u www-data app php test/Rules/regenerate-uri-surface.php
 */
final class RuleSurfaceTest extends TestCase
{
    /** Floors on what is measured, so a broken case list reads as a failure. */
    private const FILTER_FLOOR    = 20;
    private const VALIDATOR_FLOOR = 40;
    private const URL_FLOOR       = 35;

    /** How many differences a failure message lists before it stops. */
    private const REPORTED = 25;

    public function testEveryRuleAnswersWhatTheBaselineRecords(): void
    {
        self::requireDatabase();

        /** @var array<string, array<string, mixed>> $baseline */
        $baseline = require __DIR__ . '/../Rules/rule-surface.php';
        $actual   = RuleSurface::collect();

        self::assertGreaterThanOrEqual(
            self::FILTER_FLOOR,
            count($actual['filter'] ?? []),
            'almost no filter was measured, so this test proves nothing'
        );
        self::assertGreaterThanOrEqual(
            self::VALIDATOR_FLOOR,
            count($actual['validator'] ?? []),
            'almost no validator was measured, so this test proves nothing'
        );

        self::assertSurfaceUnchanged($baseline, $actual, 'The rules answer differently from the recording');
    }

    public function testEveryUrlStoresWhatTheBaselineRecords(): void
    {
        /** @var array<string, array<string, string>> $baseline */
        $baseline = require __DIR__ . '/../Rules/uri-surface.php';
        $actual   = UriSurface::collect();

        self::assertGreaterThanOrEqual(
            self::URL_FLOOR,
            count($actual['uri'] ?? []),
            'almost no URL was measured, so this test proves nothing'
        );

        self::assertSurfaceUnchanged($baseline, $actual, 'A URL is parsed or stored differently');
    }

    /**
     * @param array<string, mixed> $baseline
     * @param array<string, mixed> $actual
     */
    private static function assertSurfaceUnchanged(array $baseline, array $actual, string $headline): void
    {
        $missing = array_values(array_diff(array_keys($baseline), array_keys($actual)));
        $added   = array_values(array_diff(array_keys($actual), array_keys($baseline)));

        self::assertSame([], $missing, 'sections the recording holds and this run did not produce: '
            . implode(', ', $missing));
        self::assertSame([], $added, 'sections this run produced that the recording does not hold: '
            . implode(', ', $added));

        $differences = [];
        foreach ($baseline as $section => $cases) {
            self::compare((string) $section, $cases, $actual[$section] ?? [], $differences);
        }

        self::assertSame(
            [],
            $differences,
            $headline . ":\n  " . implode("\n  ", array_slice($differences, 0, self::REPORTED))
            . (count($differences) > self::REPORTED
                ? sprintf("\n  … and %d more", count($differences) - self::REPORTED)
                : '')
        );
    }

    /**
     * Walk both recordings together, reporting one answer at a time.
     *
     * @param mixed                $expected
     * @param mixed                $got
     * @param list<string>         $differences
     */
    private static function compare(string $path, mixed $expected, mixed $got, array &$differences): void
    {
        if (is_array($expected)) {
            if (! is_array($got)) {
                $differences[] = sprintf('%s: was a set of answers, is now %s', $path, self::brief($got));

                return;
            }

            foreach ($expected as $key => $value) {
                self::compare(
                    $path . '/' . $key,
                    $value,
                    array_key_exists($key, $got) ? $got[$key] : '<<absent>>',
                    $differences
                );
            }

            foreach (array_keys($got) as $key) {
                if (! array_key_exists($key, $expected)) {
                    $differences[] = sprintf('%s/%s: answered now, absent from the recording', $path, $key);
                }
            }

            return;
        }

        if ($expected !== $got) {
            $differences[] = sprintf('%s:\n      was %s\n      now %s', $path, self::brief($expected), self::brief($got));
        }
    }

    /**
     * Skips rather than fails where there is no database.
     *
     * Only the rule surface needs one, and only for two of its answers — the SQL the
     * `Db\*` validators build is quoted through the live connection. The URL recording
     * needs nothing and runs on a bare CI runner.
     */
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
        if (! is_string($value)) {
            return get_debug_type($value);
        }

        return strlen($value) > 160 ? substr($value, 0, 157) . '...' : $value;
    }
}
