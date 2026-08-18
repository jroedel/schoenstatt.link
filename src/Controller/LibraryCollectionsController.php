<?php

declare(strict_types=1);

namespace App\Controller;

use App\Books\LibraryPage;
use App\Http\LocalePrefix;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use Books\Model\LibraryTable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

use function is_array;

/**
 * GET /libraries/{library_id}/collections — the collections defined inside one library.
 *
 * `Books\Controller\CollectionsController::indexAction()`, which is nine lines and makes
 * **no** authorization check of its own beyond the route guard — and the guard is
 * `lib_user`, a default role, so every signed-in account can list any library's
 * collections today. The port adds the per-library `show` check its sibling pages make,
 * which is a deliberate difference and the only one on this page: a collection name is
 * not sensitive, but "which libraries exist and how they are organised" is exactly what
 * the `library_<id>` resource is for, and every other read page in the batch asks.
 *
 * The `create` button is rendered unconditionally, as the original does. It leads to a
 * route that checks `administrate` itself, so a viewer who may not create sees a button
 * that refuses them — laminas behaviour, reproduced rather than corrected, because
 * hiding it would be a second difference on a page whose diff is meant to be readable.
 */
final class LibraryCollectionsController
{
    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly Environment $twig,
        private readonly RouteUrl $urls,
        private readonly LibraryPage $page
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $libraryId = (int) $request->attributes->get('library_id');
        $redirect  = LocalePrefix::redirect(
            $request,
            $this->urls,
            'libraries/library/collections',
            ['library_id' => $libraryId]
        );
        if (null !== $redirect) {
            return $redirect;
        }

        $library = $this->page->library($request);
        $refusal = $this->page->refuse($library, LibraryPage::SHOW);
        if (null !== $refusal) {
            return $refusal;
        }

        /** @var LibraryTable $table */
        $table       = $this->laminas->get(LibraryTable::class);
        $collections = $table->queryObjects('collection', ['libraryId' => $libraryId]);

        return new Response($this->twig->render('books/library-collections.html.twig', [
            //index.phtml calls no headTitle(), so the layout's own "Schoenstatt Link"
            //stands alone. Passed explicitly because layout.html.twig runs with
            //strict_variables and an absent `page_title` is a fatal, not a blank.
            'page_title' => null,
            'library_id' => $libraryId,
            'objects'    => is_array($collections) ? $collections : [],
        ]));
    }
}
