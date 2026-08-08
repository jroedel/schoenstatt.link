<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\LocalePrefix;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use Books\Model\LibraryTable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * GET /libraries — the Schoenstatt Library System landing page and its library list.
 *
 * Guarded `route/libraries`: `lib_administrator` alone. The *rows* are gated a second
 * time and independently — `library` is the one entity in this batch whose spec sets an
 * `aclResourceIdField`, so `_library-list.html.twig` asks `isAllowed(row.resourceId,
 * 'show')` per library and drops the ones the viewer may not see. Both gates are
 * reproduced; dropping the second would show an administrator of one library the
 * existence of every other.
 *
 * The list partial is deliberately its own template. `books/libraries/library-list.phtml`
 * is rendered from two places on the laminas side — here and from the literature home
 * page — and literature home is a later port, so the Twig partial is written now to be
 * reused then rather than inlined and copied later.
 */
final class LibrariesController
{
    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly Environment $twig,
        private readonly RouteUrl $urls
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $redirect = LocalePrefix::redirect($request, $this->urls, 'libraries');
        if (null !== $redirect) {
            return $redirect;
        }

        /** @var LibraryTable $table */
        $table = $this->laminas->get(LibraryTable::class);

        return new Response($this->twig->render('books/libraries.html.twig', [
            //index.phtml calls headTitle() with the raw English string and lets the helper
            //translate it, which is the shape every ported page has. The layout does the
            //translating — see templates/layout.html.twig — so what is passed here is the
            //untranslated key, not a rendered title.
            'page_title'  => 'Schoenstatt Library System',
            'breadcrumbs' => [
                ['label' => 'Literature', 'href' => $this->urls->path('publications')],
                ['label' => 'Libraries', 'href' => $this->urls->path('libraries')],
            ],
            'objects'     => $table->getObjects('library'),
        ]));
    }
}
