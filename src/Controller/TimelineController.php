<?php

declare(strict_types=1);

namespace App\Controller;

use App\Books\EventTimeline;
use App\Http\LocalePrefix;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use Books\Filter\KentenichPeriodFromDate;
use Books\Model\EventTextTable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * GET /timeline — the Fr. Kentenich timeline, every event grouped by period and year.
 *
 * Guarded `['user', 'guest']`, i.e. everyone who is not explicitly excluded; the laminas
 * route it shadows is `events`, and the ported route keeps that name so the layout can
 * still recognise the current page.
 *
 * The path and the route name disagree — the route is `events`, the URL is `/timeline` —
 * and that is the laminas route's own doing, not a porting choice. Reproduced as-is.
 *
 * The whole page is one query plus a grouping: `queryObjects('event')` and
 * App\Books\EventTimeline. No permission-gated markup at all, which makes it the
 * cheapest possible check that the Twig layer handles a page whose body is nothing but
 * translated headings and escaped text.
 */
final class TimelineController
{
    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly Environment $twig,
        private readonly RouteUrl $urls
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $redirect = LocalePrefix::redirect($request, $this->urls, 'events');
        if (null !== $redirect) {
            return $redirect;
        }

        /** @var EventTextTable $table */
        $table = $this->laminas->get(EventTextTable::class);

        return new Response($this->twig->render('books/timeline.html.twig', [
            //index.phtml calls no headTitle(), so laminas renders the site name alone.
            //`events` is not in the `navigation` config either, so there is no trail —
            //measured on the laminas rendering, not assumed.
            'page_title'   => '',
            'periods'      => EventTimeline::group($table->queryObjects('event')),
            'period_names' => KentenichPeriodFromDate::PERIOD_NAMES,
        ]));
    }
}
