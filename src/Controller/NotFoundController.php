<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * The catch-all: any path no route above it matched is a 404, rendered in the shared
 * layout through `error/404.html.twig`.
 *
 * ## Why a route rather than an exception listener
 *
 * `App\Kernel` is hand-wired (no FrameworkBundle) and nothing listens on
 * `kernel.exception` — an uncaught throwable rethrows to `SionModel\Error\FatalErrorHandler`
 * by design, one error path for the whole app. So a `NotFoundHttpException` from an empty
 * `UrlMatcher` would reach the *fatal* handler and render a 500-shaped page, not a 404.
 * A last-declared catch-all route that always matches keeps the 404 an ordinary, rendered
 * response instead. It is the direct replacement for `App\Http\LegacyBridge`, which held
 * this slot until it was deleted (Phase B step 2, 2026-09-08): the bridge used to boot a
 * per-request `Laminas\Mvc\Application`, let it 404, and re-render that through this same
 * template. There are no unported routes left for it to reach, so the laminas round-trip
 * is gone and the 404 is rendered directly.
 *
 * ## No locale hop, and the 404 renders in the default locale
 *
 * The bridge inherited SlmLocale, so an unprefixed unknown path (`/no-such`) used to 302
 * to `/en/no-such` before 404-ing, and a prefixed one localised the error page. This
 * answers a 404 **directly**, prefixed or not — a genuine 404 is not a route with a
 * localised twin to negotiate to, and a redirect-to-404 is a wasted round trip. The
 * consequence is that the error page renders in the application default locale rather than
 * the one a `/es/…` prefix implies; for a two-line "page not found" that is an acceptable
 * simplification and it is stated here rather than left to be discovered.
 */
final class NotFoundController
{
    public function __construct(private readonly Environment $twig)
    {
    }

    public function __invoke(Request $request): Response
    {
        return new Response(
            //`page_title => ''` is the layout's "no prefix" case; omitting it is the
            //fatal-200 wedge (strict_variables turns a missing variable into a blank 200)
            $this->twig->render('error/404.html.twig', ['page_title' => '']),
            Response::HTTP_NOT_FOUND,
            ['Content-Type' => 'text/html; charset=utf-8']
        );
    }
}
