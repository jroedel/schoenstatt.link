<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\LocalePrefix;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use Schoenstatt\Model\SchoenstattTable;
use Schoenstatt\Service\AssociationKindsService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * GET /associations — every association in the database, in one table.
 *
 * Guarded `route/associations`: `sch_basic` and `sch_user`, which between them cover
 * most signed-in movement roles but nobody anonymous. The first ported page whose
 * *whole* content is behind the guard rather than merely decorated by it.
 *
 * `Schoenstatt\Controller\AssociationsController` defines no indexAction, so this is
 * SionController's: `getObjects('association')` and a table. The table itself was
 * already reproduced — `templates/schoenstatt/_associations-table.html.twig` is what
 * both shrine indexes render — so porting this route is one controller and a two-line
 * template that reuses it. That reuse is the point: the shrine pages and this page show
 * the same six columns of the same rows, and docs/strangler.md asks two *ported* routes
 * to share rather than to copy.
 *
 * The `partial` variable the original supports (`$this->partial ?: 'associations-table'`)
 * is not reproduced, because nothing sets it: the index action passes no such variable
 * and no other action renders this template. Reproducing an unreachable indirection
 * would be porting a possibility rather than a behaviour.
 */
final class AssociationsController
{
    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly Environment $twig,
        private readonly RouteUrl $urls
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $redirect = LocalePrefix::redirect($request, $this->urls, 'associations');
        if (null !== $redirect) {
            return $redirect;
        }

        /** @var SchoenstattTable $table */
        $table = $this->laminas->get(SchoenstattTable::class);
        /** @var AssociationKindsService $kinds */
        $kinds = $this->laminas->get(AssociationKindsService::class);

        return new Response($this->twig->render('schoenstatt/associations.html.twig', [
            //index.phtml calls no headTitle(), and `associations` is not in the
            //`navigation` config, so there is neither a title prefix nor a trail
            'page_title'  => '',
            'objects'     => $table->getObjects('association'),
            'kind_labels' => $kinds->getValueOptions(),
        ]));
    }
}
