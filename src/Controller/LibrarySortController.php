<?php

declare(strict_types=1);

namespace App\Controller;

use App\Books\LibraryPage;
use App\Books\RefreshSortForm;
use App\Http\LocalePrefix;
use App\Laminas\RouteUrl;
use App\Laminas\SionResult;
use App\Laminas\ServiceBridge;
use Books\Model\LibraryTable;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;
use SionModel\Validator\RegularExpression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Twig\Environment;

use function array_keys;
use function count;
use function is_array;
use function is_numeric;
use function is_object;
use function is_string;
use function method_exists;
use function ob_get_clean;
use function ob_start;
use function preg_match;
use function print_r;
use function var_dump;

use const PREG_OFFSET_CAPTURE;

/**
 * The sort-text diagnostic and the sweep it links to.
 *
 * `sort-debugging` shows, per collection, the call-number regex and the sort-text format,
 * and how each book's call number matches them. `refresh-sort` recomputes and stores the
 * sort text for every book in the library. They are one controller because the debugging
 * page is the only place in the application that links to the sweep.
 *
 * ## `refresh-sort` no longer writes on a GET
 *
 * The one behaviour change in batch 11b, and it is deliberate. The laminas action did its
 * work on a bare GET with no confirmation, no token and — alone among fifteen actions in
 * that controller — no `administrate` check, behind a guard (`lib_user`) that every
 * registered account holds. See App\Books\RefreshSortForm for the measurement.
 *
 * Here: GET renders a confirmation, POST validates the token and does the work, and both
 * require `administrate`. A stale bookmark of the old URL therefore now shows a button
 * instead of silently rewriting a library, which is the failure mode worth having.
 *
 * ## The regex work is here rather than in the template
 *
 * Twig has no `preg_match`, and the page wants the capture groups. `var_dump` of the
 * filter object and `print_r` of the matches are captured here too — that is literally
 * what the original prints, and reproducing it is cheaper than deciding what a
 * developer diagnostic ought to say instead.
 */
final class LibrarySortController
{
    /** Route default marking the sweep rather than the diagnostic. */
    public const REFRESH = '_library_refresh_sort';

    /** `sortDebuggingAction()`'s own default, and its own fallback when ?limit is not numeric. */
    private const DEFAULT_LIMIT  = 25;
    private const FALLBACK_LIMIT = 10;

    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly Environment $twig,
        private readonly RouteUrl $urls,
        private readonly LibraryPage $page
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $isRefresh = true === $request->attributes->get(self::REFRESH);
        $libraryId = (int) $request->attributes->get('library_id');
        $routeName = $isRefresh ? 'libraries/library/refresh-sort' : 'libraries/library/sort-debugging';

        $redirect = LocalePrefix::redirect($request, $this->urls, $routeName, ['library_id' => $libraryId]);
        if (null !== $redirect) {
            return $redirect;
        }

        $library = $this->page->library($request);
        //`administrate` on both, including the diagnostic: it lists every book's call
        //number and sort text, which is the library's catalogue in a debugging shape.
        $refusal = $this->page->refuse($library, LibraryPage::ADMINISTRATE);
        if (null !== $refusal) {
            return $refusal;
        }
        /** @var array<string, mixed> $library */

        /** @var LibraryTable $table */
        $table = $this->laminas->get(LibraryTable::class);

        return $isRefresh
            ? $this->refresh($request, $table, $library, $libraryId)
            : $this->debug($request, $table, $library, $libraryId);
    }

    /** @param array<string, mixed> $library */
    private function refresh(Request $request, LibraryTable $table, array $library, int $libraryId): Response
    {
        $form   = new RefreshSortForm();
        $action = $this->urls->path('libraries/library/refresh-sort', ['library_id' => $libraryId]);

        if ($request->isMethod('POST')) {
            /** @var array<string, mixed> $posted */
            $posted = $request->request->all();
            $form->setData($posted);
            if ($form->isValid()) {
                $table->refreshLibrarySort($libraryId);

                return new Response($this->twig->render('books/library-refresh-sort.html.twig', [
                    'page_title' => null,
                    'done'       => true,
                ]));
            }
            (new FlashMessenger())
                ->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                ->addMessage('Error in form submission, please review.');
        }

        return new Response($this->twig->render('books/library-refresh-sort.html.twig', [
            'page_title'   => 'Refresh sort text',
            'done'         => false,
            'form'         => $form,
            'form_action'  => $action,
            'library_name' => is_string($library['name'] ?? null) ? $library['name'] : '',
        ]));
    }

    /** @param array<string, mixed> $library */
    private function debug(Request $request, LibraryTable $table, array $library, int $libraryId): Response
    {
        $limit = $request->query->get('limit', (string) self::DEFAULT_LIMIT);
        //`(int)` then `is_numeric` on the *cast* value is what the original does, so a
        //non-numeric ?limit reaches the fallback and a numeric one reaches the cast.
        $limit = is_numeric($limit) ? (int) $limit : self::FALLBACK_LIMIT;

        $params              = $request->query->all();
        $params['libraryId'] = $libraryId;

        $options = $library['options'] ?? null;
        if (! is_object($options)) {
            $options = null;
        }

        $validator    = new RegularExpression();
        $libraryRegex = is_object($options) && is_string($options->callNumberRegex ?? null)
            ? $options->callNumberRegex
            : null;
        if (null !== $libraryRegex && ! $validator->isValid($libraryRegex)) {
            $libraryRegex = null;
        }

        $collections = is_object($options) && is_array($options->collections ?? null)
            ? $options->collections
            : [];
        $keys = array_keys($collections);
        //The null key is the "books without collection" group, and it is always last.
        $keys[] = null;

        $groups = [];
        foreach ($keys as $collectionId) {
            //**A broken sort-text format is reported, not thrown.** The laminas action
            //builds every filter in this loop with nothing around it, so a library whose
            //`SortTextFormat` does not parse answers 500 — and library 7's does not
            //(`%1{author}{title}`, which expands to three printf params against one
            //capture group). Verified as pre-existing by building the filters outside
            //both front controllers, not inferred from the port failing.
            //
            //Reproducing it would mean a diagnostic page that dies on exactly the
            //library whose configuration needs diagnosing, which is the one behaviour
            //here worth changing. The message takes the filter dump's place.
            $filterError = null;
            $filter      = null;
            try {
                $filter = $table->getCollectionSortTextFilter($collectionId, $libraryId);
            } catch (Throwable $e) {
                $filterError = $e->getMessage();
            }
            $theseParams  = $params;
            $theseParams['collectionId'] = $collectionId;
            $books        = $table->searchBooks($theseParams, ['limit' => $limit]);
            $collection   = $collections[$collectionId] ?? null;

            $regex = null;
            if (is_object($collection) && is_string($collection->callNumberRegex ?? null)) {
                $regex = $validator->isValid($collection->callNumberRegex) ? $collection->callNumberRegex : null;
            }
            if (null === $collection || null === ($collection->callNumberRegex ?? null)) {
                //"don't take on library regex if the collection one is invalid" — the
                //original's comment, and its condition: the library's regex is inherited
                //only when the collection declares none at all.
                $regex ??= $libraryRegex;
            }

            $groups[] = [
                'collection'       => is_object($collection) && method_exists($collection, 'getArrayCopy')
                    ? $collection->getArrayCopy()
                    : null,
                'regex_source'     => is_object($collection) ? ($collection->callNumberRegex ?? null) : null,
                'regex_invalid'    => is_object($collection)
                    && null !== ($collection->callNumberRegex ?? null)
                    && null === $regex,
                'sort_text_format' => is_object($collection) ? ($collection->sortTextFormat ?? null) : null,
                'filter_dump'      => null !== $filterError
                    ? $filterError
                    : (is_object($collection) ? $this->dump($filter) : ''),
                'filter_error'     => $filterError,
                'books'            => $this->books(SionResult::rows($books), $regex, $filter),
            ];
        }

        return new Response($this->twig->render('books/library-sort-debugging.html.twig', [
            'page_title'     => 'Sort text debugging',
            'options'        => $options,
            'groups'         => $groups,
            'refresh_form'   => new RefreshSortForm(),
            'refresh_action' => $this->urls->path(
                'libraries/library/refresh-sort',
                ['library_id' => $libraryId]
            ),
        ]));
    }

    /**
     * @param array<int|string, mixed> $books
     * @return list<array{book: mixed, matched: int|false, matches_dump: string, sort_text: string}>
     */
    private function books(array $books, ?string $regex, mixed $filter): array
    {
        $rows = [];
        foreach ($books as $book) {
            $matched = false;
            $matches = null;
            if (null !== $regex && is_array($book) && isset($book['callNumber'])) {
                $matched = preg_match($regex, (string) $book['callNumber'], $found, PREG_OFFSET_CAPTURE, 0);
                if (1 === $matched) {
                    $matches = [];
                    for ($i = 1; $i < count($found); $i++) {
                        $matches[] = $found[$i][0];
                    }
                }
            }

            $rows[] = [
                'book'         => $book,
                'matched'      => $matched,
                'matches_dump' => $this->printR($matches),
                'sort_text'    => is_object($filter) && method_exists($filter, 'filter') && is_array($book)
                    ? (string) $filter->filter($book)
                    : '',
            ];
        }

        return $rows;
    }

    /** `var_dump()`'s own output, which is what the original prints. */
    private function dump(mixed $value): string
    {
        ob_start();
        var_dump($value);

        return (string) ob_get_clean();
    }

    /** `print_r()`'s own output, likewise. */
    private function printR(mixed $value): string
    {
        return (string) print_r($value, true);
    }
}
