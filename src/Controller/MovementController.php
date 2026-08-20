<?php

declare(strict_types=1);

namespace App\Controller;

use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use Locale;
use RuntimeException;
use Schoenstatt\Form\SearchForm;
use Schoenstatt\Model\SchoenstattTable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

use function is_array;
use function is_numeric;

/**
 * GET /movement — the movement's leadership at a glance.
 *
 * The laminas route is named `schoenstatt` while its path is `/movement`; the *name*
 * is what has to be kept, since the layout recognises the current navigation item by
 * it. Same disagreement as `events` → `/timeline`.
 *
 * Two panels over the shared assignments table: the General Presidium's own
 * assignments, and the leaders of every national movement. Guarded `sch_moderator`.
 *
 * ## The unreachable identity check is not reproduced
 *
 * `SchoenstattController::indexAction()` opens with
 * `if (! $this->zfcUserAuthentication()->hasIdentity()) return $this->redirect()->toRoute('welcome');`
 * — and that branch cannot run. `route/schoenstatt` admits `sch_moderator` alone, so
 * `BjyAuthorize\Guard\Route` has already refused an anonymous visitor before the
 * controller is constructed; measured, the anonymous rendering of /en/movement is a 302
 * to the sign-in page, not to `welcome`. App\Authorization\RouteGuard runs at the same
 * point on this side, so the branch would be equally unreachable here. Left out for the
 * reason AssociationsController states about its `partial` variable: reproducing an
 * unreachable branch is porting a possibility rather than a behaviour. It is genuinely
 * different from AssociationController's anonymous rule, which *is* reproduced —
 * that guard is public, so its branch really does fire.
 *
 * ## The two panel headings are not symmetrical, and that is the original
 *
 * The first is a **link** to the presidium's own association page, carrying the
 * association name through `translate()`. The second is the bare string
 * `'National movements'`, passed to the partial without a `translate()` call — so it
 * stays English in every locale. Measured: /es/movement says "Presidium General" in the
 * first heading and "National movements" in the second. Both are reproduced as they
 * are; translating the second would be a fix, and a fix that files a phrase belongs in
 * its own change rather than inside a port.
 */
final class MovementController
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

        $presidiumId = $this->generalPresidiumId();
        /** @var array<string, mixed>|null $presidium */
        $presidium = $table->getAssociation($presidiumId);
        if (! is_array($presidium) || [] === $presidium) {
            //the laminas action does not check, and would fatal on a null array access.
            //Raising names the configuration key instead, which is the same class of
            //failure the id check below produces.
            throw new RuntimeException(
                'schoenstatt.general_presidium_id points at association ' . $presidiumId
                . ', which does not exist'
            );
        }

        $locale = Locale::getDefault();

        return new Response($this->twig->render('schoenstatt/movement.html.twig', [
            //indexAction() calls no headTitle(), so the title is the bare site name
            'page_title'       => '',
            //`schoenstatt` *is* in the navigation config, so laminas renders one crumb
            //for it — no href of its own beyond the current page, and translated
            //("Movimiento", "Bewegung")
            'breadcrumbs'      => [
                ['label' => 'Movement', 'href' => $this->urls->path('schoenstatt')],
            ],
            'form'             => new SearchForm(),
            'presidium'        => $presidium,
            'presidium_href'   => $this->urls->path('association', [
                'sw_id' => $presidium['identifier'] ?? '',
                'slug'  => $presidium['slugByLocale'][$locale] ?? '',
            ]),
            'national_leaders' => $table->getNationalMovementsLeaders(),
        ]));
    }

    /**
     * `schoenstatt.general_presidium_id` from the merged config, with the laminas
     * action's own check — it throws on a missing or non-numeric value rather than
     * quietly rendering an empty page, and so does this.
     */
    private function generalPresidiumId(): int
    {
        $config = $this->laminas->config();
        $id     = $config['schoenstatt']['general_presidium_id'] ?? null;

        //`is_numeric` alone is the laminas check — it already rejects null, '' and any
        //non-numeric string, so the extra emptiness test this used to carry was dead
        if (! is_numeric($id)) {
            throw new RuntimeException('Please set the "general_presidium_id" configuration.');
        }

        return (int) $id;
    }
}
