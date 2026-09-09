<?php

declare(strict_types=1);

namespace App\Books;

use Books\Filter\KentenichPeriodFromDate;

/**
 * The Fr. Kentenich timeline's grouping: events by period, then by year.
 *
 * A line-for-line port of the laminas `EventsController::groupEventsByEpochAndYear()`,
 * which is deleted; this is the only copy. While both existed,
 * EventTimelineParityTest drove them off the same 1,000-odd rows and asserted the same
 * nested structure, which is what makes the two properties below trustworthy.
 *
 * Two properties of the original are load-bearing and easy to lose in a rewrite:
 *
 * - **Insertion order is the output order.** Periods appear in the order their first
 *   event is met, not in period number order, and years likewise. The rows arrive from
 *   `queryObjects('event')` already sorted by the entity spec, so the result reads
 *   chronologically — but nothing here sorts, and adding a sort would change the page.
 * - **Events are keyed by `eventId` within a year**, not appended, so two events sharing
 *   an id would collapse to one. Reproduced; the column is a primary key, so it cannot.
 *
 * The filter is constructed once per build rather than per row, exactly as the original
 * does — it parses eight dates in its constructor.
 */
final class EventTimeline
{
    /**
     * @param array<int|string, array<string, mixed>> $events as SionTable::queryObjects('event') returns
     * @return array<int, array<int|string, array<int|string, array<string, mixed>>>>
     *         period => year => eventId => event
     */
    public static function group(array $events): array
    {
        $periods      = [];
        $periodFilter = new KentenichPeriodFromDate();

        foreach ($events as $object) {
            $period = $periodFilter->filter($object['startDate']);
            if (! isset($periods[$period])) {
                $periods[$period] = [];
            }
            $year = $object['startDate']->format('Y');
            if (! isset($periods[$period][$year])) {
                $periods[$period][$year] = [];
            }
            $periods[$period][$year][$object['eventId']] = $object;
        }

        return $periods;
    }
}
