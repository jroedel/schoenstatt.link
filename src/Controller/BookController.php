<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\LocalePrefix;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use App\Sion\EntityShow;
use Books\Model\LibraryTable;
use Books\Model\PublicationsTable;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

use function dirname;
use function file_exists;
use function in_array;
use function is_array;
use function is_object;
use function is_string;
use function sprintf;

/**
 * GET /books/{book_id} — one physical copy in one library.
 *
 * `Books\Controller\BooksController::showAction()`: `SionController::showAction()` plus
 * the book's own `getBook()` row, the publication it belongs to, the borrower list when
 * it is on loan, and a breadcrumb trail built in the controller rather than from the
 * navigation config.
 *
 * ## Its breadcrumb is not the library page's
 *
 * `Literature › <library> › [category or collection] › <title>` — no "Libraries" crumb,
 * and a middle crumb that is present only when the library's `MainShowDisplay` says to
 * show categories or collections *and* this book has one. That is why this controller
 * builds its own rather than calling App\Books\LibraryPage::breadcrumbs(): they are
 * genuinely different trails, and only two pages in this batch have one at all.
 *
 * ## Authorization
 *
 * The route guard admits `guest`, so an anonymous visitor reaches a book page. What they
 * see is narrowed twice inside: the `book` spec's `aclResourceIdField` gates the row
 * itself through App\Sion\EntityShow, and three fields plus the change log are gated on
 * `administrate`, `show-admin-info` and `view_checkout_person`.
 */
final class BookController
{
    private const ENTITY = 'book';

    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly Environment $twig,
        private readonly RouteUrl $urls,
        private readonly EntityShow $show
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $bookId   = (int) $request->attributes->get('book_id');
        $redirect = LocalePrefix::redirect($request, $this->urls, 'books/book', ['book_id' => $bookId]);
        if (null !== $redirect) {
            return $redirect;
        }

        /** @var LibraryTable $table */
        $table = $this->laminas->get(LibraryTable::class);

        //getBook() rather than the generic getObject(): showAction() re-reads the row
        //through it to pick up `currentCheckout` and the nested `library`, neither of
        //which the projection behind getObject() carries.
        $data = $this->show->load(self::ENTITY, $bookId, static fn (int $id): mixed => $table->getBook($id));
        if (null === $data) {
            return new RedirectResponse($this->urls->path('libraries'));
        }

        $entity  = $data->entity;
        $library = is_array($entity['library'] ?? null) ? $entity['library'] : [];

        $borrowerName = null;
        $checkout     = $entity['currentCheckout'] ?? null;
        if (is_array($checkout)) {
            $borrowers = $this->laminas->get('Books\BorrowersValueOptions');
            $personId  = $checkout['personId'] ?? null;
            if (is_array($borrowers) && null !== $personId && isset($borrowers[$personId])) {
                $borrowerName = $borrowers[$personId];
            }
        }

        $publicationId = $entity['publicationId'] ?? null;
        if (null !== $publicationId) {
            /** @var PublicationsTable $publications */
            $publications          = $this->laminas->get(PublicationsTable::class);
            $entity['publication'] = $publications->getPublication($publicationId);
        }

        return new Response($this->twig->render('books/book.html.twig', [
            'page_title'           => is_string($entity['title'] ?? null) ? $entity['title'] : '',
            'page_title_translate' => false,
            'meta_description'     => $entity['title'] ?? null,
            'breadcrumbs'          => $this->breadcrumbs($entity, $library),
            'entity'               => $entity,
            'changes'              => $data->changes,
            'cover_url'            => $this->cover($publicationId, ''),
            'cover_thumb_url'      => $this->cover($publicationId, '-200px'),
            'edit_url'             => $this->urls->path('books/book/edit', ['book_id' => $bookId]),
            'collection_name'      => $this->collectionName($entity, $library),
            'collection_url'       => $this->urls->path(
                'libraries/library',
                ['library_id' => $entity['libraryId'] ?? null],
                ['query' => ['collectionId' => $entity['collectionId'] ?? null]]
            ),
            'category_url'         => $this->urls->path(
                'libraries/library',
                ['library_id' => $entity['libraryId'] ?? null],
                ['query' => ['category' => $entity['category'] ?? null]]
            ),
            'borrower_name'        => $borrowerName,
            'copy_url'             => $this->urls->path(
                'books/create',
                ['library_id' => $entity['libraryId'] ?? null],
                ['query' => ['copyBook' => $bookId]]
            ),
            'publication_edit_url' => $this->publicationEditUrl($entity),
        ]));
    }

    /**
     * `public/covers/{id}{suffix}.jpg` when the file is on disk, else null.
     *
     * The path is relative in the original — `file_exists('public/covers/…')` — which
     * works only because laminas' entry point chdir()s to the project root. Anchored
     * here rather than relied upon.
     */
    private function cover(mixed $publicationId, string $suffix): ?string
    {
        if (null === $publicationId) {
            return null;
        }
        $file = sprintf('%s/public/covers/%s%s.jpg', dirname(__DIR__, 2), $publicationId, $suffix);

        return file_exists($file) ? sprintf('/covers/%s%s.jpg', $publicationId, $suffix) : null;
    }

    /**
     * @param array<string, mixed> $entity
     * @param array<string, mixed> $library
     */
    private function collectionName(array $entity, array $library): ?string
    {
        $collectionId = $entity['collectionId'] ?? null;
        $options      = $library['options'] ?? null;
        if (null === $collectionId || ! is_object($options) || ! isset($options->collections[$collectionId])) {
            return null;
        }

        $collection = $options->collections[$collectionId];

        return is_object($collection) && isset($collection->name) ? (string) $collection->name : null;
    }

    /** @param array<string, mixed> $entity */
    private function publicationEditUrl(array $entity): ?string
    {
        $publication = $entity['publication'] ?? null;
        if (! is_array($publication) || ! isset($publication['identifier'])) {
            return null;
        }

        return $this->urls->path('publication-edit', ['sw_id' => $publication['identifier']]);
    }

    /**
     * `Literature › <library> › [category|collection] › <title>` — the trail
     * `showAction()` assembles into `$this->layout()->breadcrumbsList`.
     *
     * @param array<string, mixed> $entity
     * @param array<string, mixed> $library
     * @return list<array{label: string, href: string, translate?: bool}>
     */
    private function breadcrumbs(array $entity, array $library): array
    {
        $libraryId = $library['libraryId'] ?? null;
        $crumbs    = [
            ['label' => 'Literature', 'href' => $this->urls->path('publications')],
            [
                'label'     => is_string($library['name'] ?? null) ? $library['name'] : '',
                'href'      => $this->urls->path('libraries/library', ['library_id' => $libraryId]),
                'translate' => false,
            ],
        ];

        $display  = is_object($library['options'] ?? null) ? ($library['options']->mainShowDisplay ?? null) : null;
        $category = $entity['category'] ?? null;
        if (
            in_array($display, [
                LibraryTable::MAIN_SHOW_DISPLAY_SHOW_COLLECTIONS_CATEGORIES,
                LibraryTable::MAIN_SHOW_DISPLAY_SHOW_CATEGORIES,
            ], true)
            && null !== $category && '' !== $category
        ) {
            $crumbs[] = [
                'label'     => (string) $category,
                'href'      => $this->urls->path(
                    'libraries/library',
                    ['library_id' => $libraryId],
                    ['query' => ['category' => $category]]
                ),
                'translate' => false,
            ];
        } elseif (
            LibraryTable::MAIN_SHOW_DISPLAY_SHOW_COLLECTIONS === $display
            && null !== ($name = $this->collectionName($entity, $library))
        ) {
            $crumbs[] = [
                'label'     => $name,
                'href'      => $this->urls->path(
                    'libraries/library',
                    ['library_id' => $libraryId],
                    ['query' => ['collectionId' => $entity['collectionId'] ?? null]]
                ),
                'translate' => false,
            ];
        }

        $crumbs[] = [
            'label'     => is_string($entity['title'] ?? null) ? $entity['title'] : '',
            'href'      => $this->urls->path('books/book', ['book_id' => $entity['bookId'] ?? null]),
            'translate' => false,
        ];

        return $crumbs;
    }
}
