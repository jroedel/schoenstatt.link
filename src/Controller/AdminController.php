<?php

declare(strict_types=1);

namespace App\Controller;

use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use App\Schoenstatt\AdminIndex;
use JTranslate\Model\TranslationsTable;
use SionModel\Service\ProblemService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * GET /admin (and /{_locale}/admin) — the moderator's landing page.
 *
 * **The first restricted route on the Symfony kernel**, and ported for that reason
 * rather than for its own sake. Every port before this one was public, because a
 * Symfony-served route got no BjyAuthorize route guard and porting a guarded page
 * would have silently published it. `App\Authorization\RouteGuard` now runs that
 * check, and a bridge nothing restricted exercises is not a bridge anyone should
 * trust — so this page is the proof. `config/symfony/routes.php` declares it
 * `guardedBy('route/admin')`, i.e. the same guard entry
 * (`['sch_moderator', 'translator']`) that governs the laminas route, and
 * test/Smoke/AdminAuthorizationSmokeTest measures all three outcomes.
 *
 * Why this route and not another of the 149: it is the cheapest genuinely
 * restricted page on the site. Read-only, no forms, no writes, no route parameters,
 * and its whole body is a list of links plus two counters — thirteen lines of
 * .phtml. So what the smoke test measures is the guard, not a large port that
 * happens to contain one.
 *
 * The links are laminas routes and stay laminas routes: this page's job is to point
 * at ten pages that have *not* moved, which is exactly what App\Laminas\RouteUrl is
 * for. Each is filtered through the ACL first, as the original does — a translator
 * gets through the guard and then sees only "Manage Translations".
 */
final class AdminController
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
        //redirect to the negotiated language rather than serving the same body at two
        //URLs. Reproduced for the reason ShrinesController reproduces it, and placed
        //before anything is fetched so the redirect costs no queries.
        if (null === $request->attributes->get('_locale')) {
            return new RedirectResponse($this->urls->path('admin'), Response::HTTP_FOUND);
        }

        /** @var TranslationsTable $translations */
        $translations = $this->laminas->get(TranslationsTable::class);
        /** @var ProblemService $problems */
        $problems = $this->laminas->get(ProblemService::class);

        return new Response($this->twig->render('schoenstatt/admin.html.twig', [
            'page_title'  => 'Admin',
            //one crumb, which is what the laminas Navigation service produces here:
            //`admin` is a top-level navigation page, so the trail is itself alone and
            //renders as plain text rather than a link
            'breadcrumbs' => [
                ['label' => 'Admin', 'href' => $this->urls->path('admin')],
            ],
            'pages'       => AdminIndex::PAGES,
            'badges'      => AdminIndex::badges($translations, $problems),
        ]));
    }
}
