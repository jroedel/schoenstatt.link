<?php

declare(strict_types=1);

namespace App\Controller;

use App\Books\EventTimeline;
use App\Http\LocalePrefix;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use Books\Filter\KentenichPeriodFromDate;
use Books\Model\EventTextTable;
use Locale;
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
 *
 * ## `title_field` — the one place this page deliberately differs from its .phtml original
 *
 * Every event carries a title in all six languages (En/Es/De/Pt/It 527 rows, Fr 526), and
 * index.phtml renders `titleEn` in every locale, so a Spanish reader got an English
 * timeline off data that was already there. This passes the locale's own column instead,
 * falling back to English where a translation is missing.
 *
 * That makes it the **first intentional divergence between a ported template and the
 * .phtml it transcribed**, so `tools/port-baseline.php` reports a difference on this page
 * and the report is correct. It is recorded in docs/strangler.md's known-differences
 * table; do not "fix" it back.
 *
 * The introductory paragraph below the heading is a *literal* in both renderings — not
 * passed through translate() — so it stays English in all five locales. Left alone on
 * purpose: making it translatable files a new phrase row per locale and belongs with the
 * translation pass in docs/timeline-and-corpus.md, not with a data-driven title fix.
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
            'title_field'  => self::titleField(),
        ]));
    }

    /**
     * The `title*` column for the active locale, or `titleEn` for a language this entity
     * has no column for.
     *
     * `Locale::getDefault()` is what the rest of the ported controllers read (see
     * MovementController, AssociationController); the primary-language reduction is
     * LiteratureController's, and it is what makes an `en_US` default resolve rather than
     * fall through. The six names are spelled out rather than built from `ucfirst($lang)`
     * because they are database columns, not a naming convention — an unrecognised locale
     * must land on English, not on a `titleXx` that does not exist.
     */
    private static function titleField(): string
    {
        return match (Locale::getPrimaryLanguage(Locale::getDefault())) {
            'es'    => 'titleEs',
            'de'    => 'titleDe',
            'pt'    => 'titlePt',
            'it'    => 'titleIt',
            'fr'    => 'titleFr',
            default => 'titleEn',
        };
    }
}
