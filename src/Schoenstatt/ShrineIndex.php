<?php

declare(strict_types=1);

namespace App\Schoenstatt;

use function floor;

/**
 * The shrine index's data assembly: group by region, and score each region's
 * completeness.
 *
 * A line-for-line port of the body of
 * Schoenstatt\Controller\SchoenstattController::shrinesAction(), which
 * waysideShrinesAction() duplicates verbatim. docs/strangler.md asks a route
 * answering from two places to *share* the code that builds the response, and this
 * would be that code — but the laminas action is deliberately left untouched, so
 * that both front controllers keep answering exactly as they do today. What pins
 * the agreement instead is
 * test/Integration/ShrineIndexParityTest.php, which drives the laminas action and
 * this class off the same rows and asserts the two produce identical arrays. When
 * the laminas route is finally deleted, this stays and the duplication goes with it.
 *
 * Two things about the arithmetic, reproduced rather than corrected because
 * "reproduce its output exactly" is the contract for this port:
 *
 *  - `maxScore` grows by a flat 10 per shrine, so it is an assumption that
 *    `dataScore` is scored out of ten and not a value read from anywhere.
 *  - the per-region percentage guards against a zero denominator and the total does
 *    not, so an empty shrine list is a DivisionByZeroError rather than 0%. Only
 *    reachable if every shrine were deleted, which is why it has never fired.
 */
final class ShrineIndex
{
    /**
     * @param array<int|string, array<string, mixed>> $shrines keyed by association id,
     *                                                         as SchoenstattTable::getShrines() returns
     * @return array{
     *     shrines: array<int|string, array<string, mixed>>,
     *     regions: array<string, array<int|string, array<string, mixed>>>,
     *     regionStats: array<string, array{id: string, maxScore: int, score: int|float, percent: int|float}>,
     *     totalPercent: float
     * }
     */
    public static function build(array $shrines): array
    {
        $regions     = [];
        $regionStats = [];
        foreach ($shrines as $associationId => $object) {
            $region = (string) $object['countryRegion'];
            if (! isset($regions[$region])) {
                $regions[$region] = [];
            }
            if (! isset($regionStats[$region])) {
                $regionStats[$region] = [
                    'id'       => $region . '-progress-bar',
                    'maxScore' => 0,
                    'score'    => 0,
                ];
            }
            $regions[$region][$associationId]  = $object;
            $regionStats[$region]['maxScore'] += 10;
            $regionStats[$region]['score']    += $object['dataScore'];
        }

        $totalScore    = 0;
        $totalMaxScore = 0;
        foreach ($regionStats as $region => $stats) {
            $totalScore                     += $stats['score'];
            $totalMaxScore                  += $stats['maxScore'];
            $regionStats[$region]['percent'] = self::percent($stats['score'], $stats['maxScore']);
        }

        return [
            'shrines'      => $shrines,
            'regions'      => $regions,
            'regionStats'  => $regionStats,
            'totalPercent' => floor($totalScore / $totalMaxScore * 100),
        ];
    }

    /**
     * The per-region percentage, guard included. A method rather than a ternary in
     * the loop only so that the guard survives static analysis: inline, PHPStan can
     * see that maxScore grew by tens and calls `> 0` always true, and an inline
     * suppression would then itself be an unmatched-ignore error at the level the
     * project actually commits to.
     */
    private static function percent(int|float $score, int $maxScore): int|float
    {
        return $score > 0 && $maxScore > 0 ? floor($score / $maxScore * 100) : 0;
    }
}
