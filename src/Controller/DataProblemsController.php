<?php

declare(strict_types=1);

namespace App\Controller;

use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use SionModel\Service\ProblemService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * GET /sm/data-problems — the current data-integrity problems, one row per problem.
 *
 * Guarded by `route/sion-model/data-problems`, which admits `sch_general_moderator`
 * and its descendants: 2 effective roles of 43.
 *
 * ## Only the read-only half moves
 *
 * `data-problems.phtml` serves two laminas routes. This one lists problems;
 * `sion-model/auto-fix-data-problems` renders the same template with `isSimulation`
 * set plus a CSRF-protected confirm form, and **stays on laminas** — there is no form
 * layer on the Symfony side, and inventing one for a single admin button is not what
 * this batch is for. So the ported template drops the simulation branch rather than
 * half-reproducing it; the .phtml keeps serving both routes until that one moves too.
 *
 * That is also why this controller ignores POST: it never had a POST to handle. The
 * route is declared without a method constraint, exactly as the laminas route is, so a
 * POST to it renders the list — which is what happens today.
 *
 * ## The entity cell is the interesting part
 *
 * Each row formats its entity through `formatEntity`, which is the most-reused of the
 * five view helpers a Symfony-served route cannot call. App\Laminas\EntityFormatter is
 * the reproduction of its general path; the dispatch to the two types laminas
 * special-cases lives in templates/schoenstatt/_entity-format.html.twig. Both are
 * needed here and neither is specific to this page, which is the point — this is the
 * port that unblocks the remaining admin pages rather than just itself.
 *
 * `getCurrentProblems()` is bounded by the `problem_specifications` config, not by the
 * size of a table, which is what makes this page portable at all — unlike
 * `sion-model/view-changes`, whose collector loads all 137,321 change rows and
 * exhausts a 512 MB limit before rendering anything.
 */
final class DataProblemsController
{
    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly Environment $twig,
        private readonly RouteUrl $urls
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (null === $request->attributes->get('_locale')) {
            return new RedirectResponse(
                $this->urls->path('sion-model/data-problems'),
                Response::HTTP_FOUND
            );
        }

        /** @var ProblemService $problems */
        $problems = $this->laminas->get(ProblemService::class);

        return new Response($this->twig->render('sion-model/data-problems.html.twig', [
            //data-problems.phtml calls no headTitle(), so laminas renders the site name
            //alone and there is no breadcrumb trail either — measured on the signed-in
            //laminas rendering, not assumed
            'page_title' => '',
            'problems'   => $problems->getCurrentProblems(),
        ]));
    }
}
