<?php
namespace Books\Controller;

use SionModel\Controller\SionController;
use Laminas\View\Model\ViewModel;
use Books\Model\EventTextTable;
use Books\Filter\KentenichPeriodFromDate;

/**
 * The Fr. Kentenich timeline.
 *
 * `indexAction()` is the whole of it, and production no longer reaches even that:
 * `/timeline` is served by App\Controller\TimelineController, which this class remains the
 * fallback for. The entity's other four routes — show, edit, delete, create — have no guard
 * entry in acl.global.php and are default-denied; see docs/timeline-and-corpus.md for why
 * that is deliberate rather than an oversight.
 *
 * `searchAction()` was deleted on 2026-08-15 with `EventsSearchForm` and `search.phtml`.
 * All three were unreachable and had been since 2020: no `search` child route existed under
 * `/timeline`, the entity spec's `controller_services` is `[]` so
 * `$this->services[EventsSearchForm::class]` was an undefined key, the form was a copy of a
 * *library* search (it had `libraryId` and `collectionId` fields and no event field at all),
 * and the template rendered nothing.
 */
class EventsController extends SionController
{
    public function indexAction()
    {
        $table = $this->getSionTable();
        $objects = $table->queryObjects('event');
        $periods = $this->groupEventsByEpochAndYear($objects);

        return new ViewModel([
            'objects' => $objects,
            'periods' => $periods,
        ]);
    }

    public function groupEventsByEpochAndYear(array $eventObjects)
    {
        $periods = [];
        /** @var EventTextTable $table */
        $table = $this->getSionTable();
        $periodFilter = new KentenichPeriodFromDate();
        foreach ($eventObjects as $object) {
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
