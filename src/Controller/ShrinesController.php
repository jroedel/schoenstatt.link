<?php

declare(strict_types=1);

namespace App\Controller;

use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use App\Schoenstatt\ShrineDatasets;
use App\Schoenstatt\ShrineIndex;
use Schoenstatt\Model\SchoenstattTable;
use Schoenstatt\Service\AssociationKindsService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * GET /shrines (and /{_locale}/shrines) — the public collaborative shrine
 * database.
 *
 * The first *HTML* route on the Symfony kernel, which is the whole point of it:
 * the two ported before this one answer JSON, so nothing about the site's chrome,
 * its translations, its authorization-gated markup or its templating had to exist
 * yet. Now it does — templates/layout.html.twig and the extensions behind it are
 * what every later HTML port reuses, and this controller is the shape those ports
 * should copy.
 *
 * It could be ported at all only because it is public: a Symfony-served route gets
 * no BjyAuthorize route guard, so a route whose protection lives in the guard would
 * simply lose it. `shrines` is guarded `['null', 'guest', 'user']`, and a null role
 * means everyone. See docs/strangler.md before porting anything that is not.
 *
 * What it does *not* lose, contrary to what one would assume from "a ported route
 * has no session": the identity. Asking isAllowed() makes BjyAuthorize ask JUser,
 * which reads the session, which starts it — so a signed-in moderator still gets
 * the moderator table and the progress bars, and an anonymous visitor still gets
 * the plain one. The cookie that starts it is what App\Http\GdprCookieListener is
 * for.
 */
final class ShrinesController
{
    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly Environment $twig,
        private readonly RouteUrl $urls
    ) {
    }

    public function __invoke(Request $request): Response
    {
        /** @var SchoenstattTable $table */
        $table = $this->laminas->get(SchoenstattTable::class);
        /** @var AssociationKindsService $kinds */
        $kinds = $this->laminas->get(AssociationKindsService::class);

        $index = ShrineIndex::build($table->getShrines());

        return new Response($this->twig->render('schoenstatt/shrines.html.twig', [
            'page_title'    => 'Schoenstatt Shrines - Collaborative database',
            'breadcrumbs'   => [
                ['label' => 'Shrines', 'href' => $this->urls->path('shrines')],
                ['label' => 'World', 'href' => $this->urls->path('shrines')],
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
