<?php

declare(strict_types=1);

namespace App\Controller;

use App\Laminas\ServiceBridge;
use SionModel\Service\ProblemService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * GET /sm/data-problems — the current data-integrity problems, one row per problem.
 *
 * Guarded by `route/sion-model/data-problems`, which admits `sch_general_moderator`
 * and its descendants: 2 effective roles of 43.
 *
 * ## Only the read-only half moved, and the other half is gone
 *
 * `data-problems.phtml` used to serve two laminas routes. This one lists problems;
 * `sion-model/auto-fix-data-problems` rendered the same template with `isSimulation` set
 * plus a CSRF-protected confirm form, and stayed on laminas when this was ported. It was
 * **retired on 2026-09-08** rather than ported: measured, the only fix any provider
 * implements is backfilling `lib_books.sort_text` (9,764 books, all in one library), and
 * `LibraryTable::refreshLibrarySort()` — behind the ported, `administrate`-gated
 * `refresh-sort` confirmation — recomputes every book's sort text, a strict superset.
 *
 * This controller ignores POST because it never had one to handle. The route is declared
 * without a method constraint, exactly as the laminas route is, so a POST renders the list.
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
        private readonly Environment $twig
    ) {
    }

    public function __invoke(Request $request): Response
    {
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
