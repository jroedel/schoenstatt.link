<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use PHPUnit\Framework\TestCase;
use SchoenstattTest\Element\ElementSurface;

use function array_diff;
use function array_key_exists;
use function array_keys;
use function count;
use function implode;
use function array_slice;
use function sprintf;
use function array_values;
use function preg_replace;
use function strlen;
use function substr;
use function var_export;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Element/ElementSurface.php';

/**
 * Every element still answers what it answered when the baseline was taken.
 *
 * ## What this is for
 *
 * Step 6 replaces `Laminas\Form\Element\*` with our own classes. What makes that safe is
 * knowing exactly what the replacement has to answer, across all 441 elements, and this
 * is that list. Until step 6 lands it is also a plain regression test on the forms: an
 * option renamed, a `value_options` list that quietly emptied, a checkbox whose
 * unchecked value moved.
 *
 * ## Why not rendered HTML
 *
 * The renderer is not what step 6 changes — `BootstrapFormRenderer` keeps working the same
 * way, from the same twelve questions. What changes is what an element *answers*. An HTML
 * baseline over 136 database-populated selects would also be a megabyte of `<option>` tags
 * that churns whenever the capsule's export does, and it would put that data in the
 * repository.
 *
 * ## Reading a failure
 *
 * The message names the element and the field that moved. If the change is intended,
 * regenerate — deliberately, and read the diff:
 *
 *     docker compose exec -T -u www-data app php test/Element/regenerate-element-surface.php
 */
final class ElementSurfaceTest extends TestCase
{
    /**
     * A floor on the number of elements, so a discovery walk that broke reads as a failure
     * rather than as a baseline that suddenly agrees about nothing.
     */
    private const ELEMENT_FLOOR = 400;

    public function testEveryElementAnswersWhatTheBaselineRecords(): void
    {
        /** @var array<string, array<string, mixed>> $baseline */
        $baseline = require __DIR__ . '/../Element/element-surface.php';
        $actual   = ElementSurface::collect();

        self::assertGreaterThanOrEqual(
            self::ELEMENT_FLOOR,
            count($actual),
            'almost no element was found, so this test proves nothing'
        );

        $missing = array_diff(array_keys($baseline), array_keys($actual));
        $added   = array_diff(array_keys($actual), array_keys($baseline));

        self::assertSame(
            [],
            array_values($missing),
            "Elements the baseline records and this run did not find:\n  "
            . implode("\n  ", array_slice(array_values($missing), 0, 20))
        );
        self::assertSame(
            [],
            array_values($added),
            "Elements this run found that the baseline does not record. If they are meant to "
            . "exist, regenerate the baseline and read the diff:\n  "
            . implode("\n  ", array_slice(array_values($added), 0, 20))
        );

        $differences = [];
        foreach ($baseline as $path => $recorded) {
            foreach ($recorded as $field => $expected) {
                //array_key_exists, not `??`: a recorded null is a real answer, and `??`
                //reports every one of them as missing. The same slip made an earlier
                //measurement claim twenty fields had vanished from getData().
                $got = array_key_exists($field, $actual[$path] ?? [])
                    ? $actual[$path][$field]
                    : '<<absent>>';
                if ($got === $expected) {
                    continue;
                }
                $differences[] = sprintf(
                    '%s: %s was %s, is now %s',
                    $path,
                    $field,
                    self::brief($expected),
                    self::brief($got)
                );
            }
        }

        self::assertSame(
            [],
            $differences,
            "Elements answer differently from the recorded baseline:\n  "
            . implode("\n  ", array_slice($differences, 0, 30))
        );
    }

    private static function brief(mixed $value): string
    {
        $printed = var_export($value, true);
        $printed = (string) preg_replace('/\s+/', ' ', $printed);

        return strlen($printed) > 90 ? substr($printed, 0, 87) . '...' : $printed;
    }
}
