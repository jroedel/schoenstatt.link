<?php

declare(strict_types=1);

namespace App\Controller;

use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use App\Schoenstatt\ShrineDatasets;
use App\Schoenstatt\ShrineIndex;
use Schoenstatt\Model\SchoenstattTable;
use Schoenstatt\Service\AssociationKindsService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * GET /wayside-shrines (and /{_locale}/wayside-shrines) — the same index as
 * /shrines, over the `sch-wayside-shrine` associations instead.
 *
 * Everything structural here is ShrinesController's, deliberately: the two laminas
 * actions this pair replaces are line-for-line duplicates of each other, and
 * reproducing that duplication on the Symfony side would be porting the defect
 * along with the feature. What actually differs is three things — the table method,
 * the template's header block and the route name the unprefixed form redirects to —
 * so those are what this file contains and the rest is shared:
 * App\Schoenstatt\ShrineIndex builds the index, App\Schoenstatt\ShrineDatasets the
 * schema.org payload, and templates/schoenstatt/_shrine-index.html.twig the body.
 *
 * Public, like /shrines: `route/wayside-shrines` is guarded `['user', 'guest',
 * null]` and a null role means everyone. It is still checked rather than declared
 * open — see the route declaration in config/symfony/routes.php.
 *
 * One difference from getShrines() worth knowing, though it changes nothing about
 * the port: SchoenstattTable::getWaysideShrines() has no cache layer, while
 * getShrines() reads and writes one. Both front controllers have always paid that,
 * and 43 rows is not why anyone would notice.
 */
final class WaysideShrinesController
{
    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly Environment $twig,
        private readonly RouteUrl $urls
    ) {
    }

    public function __invoke(Request $request): Response
    {
        //no prefix means no locale was asked for, and SlmLocale answers that with a
        //redirect to the negotiated language rather than by serving two URLs with
        //the same body — App\Http\LocaleListener has already settled which language
        //that is. See ShrinesController, which reproduces it for the same reason.
        if (null === $request->attributes->get('_locale')) {
            return new RedirectResponse($this->urls->path('wayside-shrines'), Response::HTTP_FOUND);
        }

        /** @var SchoenstattTable $table */
        $table = $this->laminas->get(SchoenstattTable::class);
        /** @var AssociationKindsService $kinds */
        $kinds = $this->laminas->get(AssociationKindsService::class);

        $index = ShrineIndex::build($table->getWaysideShrines());

        return new Response($this->twig->render('schoenstatt/wayside-shrines.html.twig', [
            'page_title'    => 'Schoenstatt Wayside Shrines',
            //what Laminas\Navigation builds from the `navigation` config for this
            //page: it sits under Shrines, which links to the world index
            'breadcrumbs'   => [
                ['label' => 'Shrines', 'href' => $this->urls->path('shrines')],
                ['label' => 'Wayside shrines', 'href' => $this->urls->path('wayside-shrines')],
            ],
            'shrines'       => $index['shrines'],
            'regions'       => $index['regions'],
            'region_stats'  => $index['regionStats'],
            'total_percent' => $index['totalPercent'],
            'kind_labels'   => $kinds->getValueOptions(),
            'datasets'      => ShrineDatasets::build(),
        ]));
    }
}
