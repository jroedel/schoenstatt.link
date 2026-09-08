<?php

declare(strict_types=1);

namespace App\Controller;

use App\Laminas\RouteUrl;
use Schoenstatt\Filter\SchoenstattLinkIdentifier as IdentifierFilter;
use Schoenstatt\Filter\ToSchoenstattLinkIdentifier;
use Schoenstatt\Validator\SchoenstattLinkIdentifier as IdentifierValidator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function is_numeric;
use function is_string;

/**
 * GET /{sw_id}[/{slug}] for a **pre-April-2020** site-wide identifier — a permanent
 * redirect to the same record's current URL. `IndexController::redirectPreApril2020SlIdAction()`,
 * ported 2026-09-08 (Phase A of the laminas-mvc removal).
 *
 * ## What "pre-April-2020" means, and why it does not collide with anything
 *
 * In April 2020 site-wide ids grew from five digits to six. An old id is
 * `SL` + five digits + one of `A P L C` (association, person, publication, composition) —
 * **eight characters** — where a current id has six digits and nine characters. The route
 * requirement here is `SchoenstattLinkIdentifier::GENERAL_OLD_REGEX` without its anchors,
 * i.e. exactly that eight-character shape, so it is disjoint by length from the ported
 * `composition`/`text`/`publication`/`association` show routes, which all require six
 * digits. Both can be top-level `/{sw_id}/{slug}` routes without either swallowing the
 * other, exactly as the laminas router kept them apart by constraint.
 *
 * Declared just above `legacy`: it is broad, so everything more specific must match first,
 * and after it only the catch-all remains.
 *
 * ## The redirect
 *
 * Decode the old id to its number and entity type, re-encode as the current-format id,
 * and **301** to that entity's own route — reusing the slug the visitor arrived with, as
 * the laminas action does by passing `reuseMatchedParams: true`. The status is a permanent
 * redirect on purpose: these URLs are five years gone and search engines should update.
 *
 * ## Two branches the laminas action reached by throwing, answered here as 404
 *
 * The old regex admits `P` (person), whose `ENTITY_TYPE_ROUTES` entry is `null` — there is
 * no public person page — and it admits ids for which no row exists. The laminas action
 * would `toRoute(null, …)` or carry a bad id into a 404 downstream; here an unroutable
 * entity type is a clean 404 rather than a 500. Nothing exercises this branch (the pages
 * these ids named in 2019 are the same ones public today), so a faithful 500 would only
 * be faithful to a path no one takes.
 */
final class PreApril2020RedirectController
{
    public function __construct(private readonly RouteUrl $urls)
    {
    }

    public function __invoke(Request $request): Response
    {
        $swId = $request->attributes->get('sw_id');
        $slug = $request->attributes->get('slug');
        if (! is_string($swId)) {
            return $this->notFound();
        }

        //the general old-format filter, which reports the entity type it decoded through
        //getLastEntityType() — the same pair the laminas action builds
        $filter = new IdentifierFilter(null, true);
        try {
            $id         = $filter->filter($swId);
            $entityType = $filter->getLastEntityType();
        } catch (Throwable) {
            //the route constraint makes a malformed id unreachable; the filter is still
            //allowed to disagree, and a 404 is the honest answer if it does
            return $this->notFound();
        }

        if (! is_numeric($id) || ! is_string($entityType)) {
            return $this->notFound();
        }

        $route = IdentifierValidator::ENTITY_TYPE_ROUTES[$entityType] ?? null;
        if (! is_string($route)) {
            //person, or any type without a current public route
            return $this->notFound();
        }

        $newId = (new ToSchoenstattLinkIdentifier($entityType))->filter((int) $id);
        if (! is_string($newId) && ! is_numeric($newId)) {
            return $this->notFound();
        }

        $params = ['sw_id' => (string) $newId];
        if (is_string($slug) && '' !== $slug) {
            $params['slug'] = $slug;
        }

        return new RedirectResponse($this->urls->path($route, $params), Response::HTTP_MOVED_PERMANENTLY);
    }

    private function notFound(): Response
    {
        return new Response(
            'Not found.',
            Response::HTTP_NOT_FOUND,
            ['Content-Type' => 'text/plain; charset=utf-8']
        );
    }
}
