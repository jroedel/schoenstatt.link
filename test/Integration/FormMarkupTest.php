<?php

declare(strict_types=1);

namespace SchoenstattTest\Integration;

use SionModel\Db\Connection;
use PHPUnit\Framework\TestCase;
use SchoenstattTest\Form\FormMarkup;
use SchoenstattTest\Fuzz\FormRepository;
use Throwable;

use function array_diff;
use function array_key_exists;
use function array_keys;
use function array_slice;
use function array_values;
use function count;
use function implode;
use function preg_replace;
use function sprintf;
use function strlen;
use function substr;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../Form/FormMarkup.php';

/**
 * Every form still renders the markup the baseline records.
 *
 * ## What this is for
 *
 * Iteration A replaces `Laminas\Form\{Form, Fieldset, Collection}` and the validator and
 * filter classes behind them. Its contract is rendered-HTML parity — the site's CSS and
 * its selectize/markdown bundle are written against these exact bytes — and
 * `test/Form/form-markup.php` is what that parity is measured against. Until the swap
 * lands it is also a plain regression test on the forms: a label that stopped being
 * rendered, a select whose options emptied, a validation message that changed wording.
 *
 * ## Reading a failure
 *
 * The message names the element, the state and the helper whose bytes moved, with both
 * renderings truncated. If the change is intended, regenerate — deliberately, and read the
 * diff:
 *
 *     docker compose exec -T -u www-data app php -d memory_limit=1G \
 *         test/Form/regenerate-form-markup.php
 *
 * ## Why the comparison reconstructs the states
 *
 * The baseline records each state as its differences from the state before it, so
 * comparing state by state as recorded would compare two lists of differences and pass
 * whenever both had drifted the same way. Both sides are rebuilt into whole renderings
 * first — {@see reconstruct()} — and compared as those.
 */
final class FormMarkupTest extends TestCase
{
    /**
     * A floor on the number of rendered surfaces, so a discovery walk that broke reads as
     * a failure rather than as a baseline that suddenly agrees about nothing. 483 today.
     */
    private const SURFACE_FLOOR = 450;

    /** How many differences a failure message lists before it stops. */
    private const REPORTED = 25;

    public function testEveryFormRendersWhatTheBaselineRecords(): void
    {
        self::requireDatabase();

        /** @var array<string, array<string, array<string, string>>> $baseline */
        $baseline = require __DIR__ . '/../Form/form-markup.php';
        $actual   = FormMarkup::collect();

        self::assertGreaterThanOrEqual(
            self::SURFACE_FLOOR,
            count($actual),
            'almost no form surface was rendered, so this test proves nothing'
        );

        $missing = array_values(array_diff(array_keys($baseline), array_keys($actual)));
        $added   = array_values(array_diff(array_keys($actual), array_keys($baseline)));

        self::assertSame(
            [],
            $missing,
            "Surfaces the baseline records and this run did not render:\n  "
            . implode("\n  ", array_slice($missing, 0, self::REPORTED))
        );
        self::assertSame(
            [],
            $added,
            "Surfaces this run rendered that the baseline does not record. If they are meant "
            . "to exist, regenerate the baseline and read the diff:\n  "
            . implode("\n  ", array_slice($added, 0, self::REPORTED))
        );

        $differences = [];
        foreach ($baseline as $path => $states) {
            $expected = self::reconstruct($states);
            $got      = self::reconstruct($actual[$path] ?? []);

            foreach ($expected as $state => $helpers) {
                foreach ($helpers as $helper => $markup) {
                    //array_key_exists, not `??`: an empty string is a real answer — it is
                    //what `errors()` renders for an element with no messages — and `??`
                    //would report every one of them as missing.
                    $rendered = array_key_exists($state, $got) && array_key_exists($helper, $got[$state])
                        ? $got[$state][$helper]
                        : '<<absent>>';
                    if ($rendered === $markup) {
                        continue;
                    }

                    $differences[] = sprintf(
                        "%s [%s / %s]\n      was: %s\n      now: %s",
                        $path,
                        $state,
                        $helper,
                        self::brief($markup),
                        self::brief($rendered)
                    );
                }
            }
        }

        self::assertSame(
            [],
            $differences,
            "Forms render differently from the recorded baseline:\n  "
            . implode("\n  ", array_slice($differences, 0, self::REPORTED))
        );
    }

    /**
     * The whole rendering of every state, from the differences the baseline stores.
     *
     * A state inherits the state it is read against and overlays what it records. A state
     * whose path is absent inherits nothing: the element does not exist there.
     *
     * @param array<string, array<string, string>> $states
     * @return array<string, array<string, string>>
     */
    private static function reconstruct(array $states): array
    {
        $full = [];

        foreach (FormMarkup::STATES as $state) {
            if (! array_key_exists($state, $states)) {
                continue;
            }

            //Which state this one is read against comes from the recorder's own map
            //rather than from a copy of it here: two files that each decide that `invalid`
            //follows `pristine` and not `populated` are two files that can disagree, and
            //the disagreement would show up as a baseline that passes while comparing the
            //wrong pair.
            $against   = FormMarkup::BASELINE_STATE[$state] ?? null;
            $inherited = null !== $against ? $full[$against] ?? [] : [];

            $full[$state] = $states[$state] + $inherited;
        }

        return $full;
    }

    /** Skips rather than fails where there is no database — CI has none. */
    private static function requireDatabase(): void
    {
        try {
            /** @var Connection $adapter */
            $adapter = FormRepository::instance()->container()->get('SionModel\Db\Connection');
            $adapter->select('SELECT 1', []);
        } catch (Throwable $e) {
            self::markTestSkipped('no database: ' . $e->getMessage());
        }
    }

    private static function brief(string $markup): string
    {
        $collapsed = (string) preg_replace('/\s+/', ' ', $markup);

        return strlen($collapsed) > 160 ? substr($collapsed, 0, 157) . '...' : $collapsed;
    }
}
