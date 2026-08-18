<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\LocalePrefix;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use App\Laminas\SionResult;
use App\Laminas\ViewHelpers;
use App\Sion\EntityShow;
use Books\Model\LibraryTable;
use Books\Model\PublicationsTable;
use Schoenstatt\Filter\ToSchoenstattLinkIdentifier;
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
        private readonly EntityShow $show,
        private readonly ViewHelpers $helpers
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
            'publication_edit_url' => $this->publicationUrl($entity, 'publication-edit'),
            //The publication panel's own two conditional links. They are **not** decoration:
            //the "Copy into main corpus" button is how a moderator promotes a data-sourced
            //publication, and it renders on the book page as well as on the publication's
            //own, because both include the same partial. Passing null here silently removed
            //it from every book page — caught by test/Smoke/CopyToMainCorpusSmokeTest,
            //which drives both renderings of one book precisely so that a port cannot lose
            //it on one side. Nothing in the baseline capture would have seen it: the book
            //in PATHS hangs off a publication that is not data-sourced.
            'merged_into_url'      => $this->mergedIntoUrl($entity),
            'copy_to_corpus_url'   => $this->copyToCorpusUrl($entity),
            'other_editions'       => $this->otherEditions($entity),
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

    /**
     * A route on the book's publication, keyed by its site-wide identifier.
     *
     * @param array<string, mixed> $entity
     */
    private function publicationUrl(array $entity, string $route): ?string
    {
        $publication = $entity['publication'] ?? null;
        if (! is_array($publication) || ! is_string($publication['identifier'] ?? null)) {
            return null;
        }

        return $this->urls->path($route, ['sw_id' => $publication['identifier']]);
    }

    /**
     * The surviving edition's URL when this book's publication has been merged away.
     *
     * Same rule as App\Controller\PublicationController::mergedIntoUrl(); the id has to
     * go through the identifier filter because the route takes `sw_id`, not a row id.
     *
     * @param array<string, mixed> $entity
     */
    private function mergedIntoUrl(array $entity): ?string
    {
        $publication = $entity['publication'] ?? null;
        $mergedInto  = is_array($publication) ? ($publication['mergedIntoPublicationId'] ?? null) : null;
        if (null === $mergedInto) {
            return null;
        }

        $swId = (new ToSchoenstattLinkIdentifier('publication'))->filter($mergedInto);

        return is_string($swId) ? $this->urls->path('publication', ['sw_id' => $swId]) : null;
    }

    /**
     * "Copy into main corpus", or null when the button does not belong on this page.
     *
     * Three conditions, all of them the publication page's: the row must be data-sourced,
     * it must not already have been merged into another, and the viewer must hold
     * `route/publication-copy-to-main-corpus`. The third is what keeps the button off an
     * anonymous rendering — a crawler that followed it filed an exception per hit, which
     * is why the smoke test exists.
     *
     * @param array<string, mixed> $entity
     */
    private function copyToCorpusUrl(array $entity): ?string
    {
        $publication = $entity['publication'] ?? null;
        if (! is_array($publication)) {
            return null;
        }
        if (isset($publication['mergedIntoPublicationId']) || ! isset($publication['dataSource'])) {
            return null;
        }
        if (! (bool) $this->helpers->isAllowed()->__invoke('route/publication-copy-to-main-corpus')) {
            return null;
        }
        $identifier = $publication['identifier'] ?? null;
        if (! is_string($identifier)) {
            return null;
        }

        $params = ['sw_id' => $identifier];
        $slug   = $publication['slug'] ?? null;
        if (is_string($slug)) {
            $params['slug'] = $slug;
        }

        return $this->urls->path('publication-copy-to-main-corpus', $params);
    }

    /**
     * `subEditions` + `translations`, as the publication page merges them.
     *
     * @param array<string, mixed> $entity
     * @return array<int|string, mixed>
     */
    private function otherEditions(array $entity): array
    {
        $publication = $entity['publication'] ?? null;
        if (! is_array($publication)) {
            return [];
        }

        return [
            ...SionResult::rows($publication['subEditions'] ?? null),
            ...SionResult::rows($publication['translations'] ?? null),
        ];
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
