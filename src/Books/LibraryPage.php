<?php

declare(strict_types=1);

namespace App\Books;

use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use App\Laminas\ViewHelpers;
use Books\Model\LibraryTable;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

use function is_array;
use function is_numeric;
use function is_string;

/**
 * The four things every library-scoped page does before it renders anything.
 *
 * Read `library_id` off the route · load the row · check the per-library ACL · build the
 * breadcrumb trail back to the library. Every one of the eighteen laminas actions under
 * `/libraries/{id}/…`, `/checkouts/library/{id}` and `/library-imports/library/{id}`
 * opens with some subset of that, spelled slightly differently each time, and the
 * differences are where the defects are:
 *
 *  - `LibrariesController::getLibraryId()` and `CheckoutsController::getLibraryId()` are
 *    the same nine lines duplicated, and both **return a redirect object where an int is
 *    declared** when the id is missing. Callers assign it to `$libraryId` and carry on.
 *  - `refreshSortAction()` and `dataProblemsAction()` skip the per-library check their
 *    fourteen siblings make. One of those two writes.
 *
 * So this is one implementation with one shape: a `Response` back means stop and return
 * it, null means proceed. Nothing here can be mistaken for a library id.
 *
 * ## The permission argument is not a default
 *
 * `show` and `administrate` are genuinely different populations — `show` is what a
 * library's `ViewRole` grants (`guest` on four of the six, i.e. everyone) and
 * `administrate` is `lib_administrator` alone. Every caller states which it needs, because
 * a default here would be a silent answer to the question this class exists to ask.
 */
final class LibraryPage
{
    public const SHOW        = 'show';
    public const ADMINISTRATE = 'administrate';

    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly ViewHelpers $helpers,
        private readonly RouteUrl $urls,
        private readonly Environment $twig
    ) {
    }

    /**
     * The library this request names, or null when the route carries no usable id.
     *
     * @return array<string, mixed>|null
     */
    public function library(Request $request): ?array
    {
        $raw = $request->attributes->get('library_id');
        if (! is_numeric($raw)) {
            return null;
        }

        /** @var LibraryTable $table */
        $table = $this->laminas->get(LibraryTable::class);
        //failSilently: getObject() throws InvalidArgumentException for a missing row, and
        //a stale bookmark is a 404, not a 500.
        $library = $table->getObject('library', (int) $raw, true);

        return is_array($library) && [] !== $library ? $library : null;
    }

    /**
     * The refusal this page owes the visitor, or null when they may proceed.
     *
     * Three outcomes, and they are deliberately not one: **no library** is the laminas
     * flash-and-redirect to `/libraries`, **not permitted** is the 403 template every
     * other ported route renders, and null is "carry on".
     *
     * @param array<string, mixed>|null $library
     */
    public function refuse(?array $library, string $permission): ?Response
    {
        if (null === $library) {
            //`getLibraryId()` flashes 'Library not found.' and redirects to the libraries
            //index. The flash is not reproduced: it is written into a laminas session
            //namespace that the ported index page does not read, so carrying it across
            //would put a message nowhere rather than somewhere else.
            return new RedirectResponse($this->urls->path('libraries'));
        }

        $resourceId = $library['resourceId'] ?? null;
        if (! is_string($resourceId) || ! $this->isAllowed($resourceId, $permission)) {
            return new Response(
                $this->twig->render('error/403.html.twig', ['page_title' => 'Access denied']),
                Response::HTTP_FORBIDDEN
            );
        }

        return null;
    }

    /**
     * `Literature › Libraries › <name>` — the trail the library's own page carries.
     *
     * **Only that page.** Measured against the laminas captures rather than assumed:
     * of the twenty-three routes in this batch exactly two render a breadcrumb at all,
     * this one and the book show page, and the book's is a *different* trail
     * (`Literature › <library> › <title>`, with no "Libraries" crumb). Everything else —
     * the admin menu, the checkout lists, collections, data problems, the imports —
     * renders none, because laminas builds these from the Navigation service and nothing
     * hangs those routes off it. Adding a sensible-looking trail to the other twenty-one
     * would be twenty-one invented differences, each of which reads as an improvement.
     *
     * Every crumb carries an href, the last one included: layout.html.twig compares each
     * against the current path to decide which is active, and a crumb without one is a
     * Twig error rather than a missing link. The library's name is `translate: false` —
     * it is record content, and a translator miss is how a phrase row gets filed.
     *
     * @param array<string, mixed> $library
     * @return list<array{label: string, href: string, translate?: bool}>
     */
    public function breadcrumbs(array $library): array
    {
        $libraryId = $library['libraryId'] ?? null;
        $name      = $library['name'] ?? '';

        return [
            ['label' => 'Literature', 'href' => $this->urls->path('publications')],
            ['label' => 'Libraries', 'href' => $this->urls->path('libraries')],
            [
                'label'     => is_string($name) ? $name : '',
                'href'      => $this->urls->path('libraries/library', ['library_id' => $libraryId]),
                'translate' => false,
            ],
        ];
    }

    public function isAllowed(string $resource, ?string $permission = null): bool
    {
        return (bool) $this->helpers->isAllowed()->__invoke($resource, $permission);
    }
}
