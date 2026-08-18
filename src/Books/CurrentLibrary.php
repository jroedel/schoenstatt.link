<?php

declare(strict_types=1);

namespace App\Books;

use App\Laminas\ServiceBridge;
use Books\Model\LibraryTable;

use function is_array;
use function is_numeric;
use function str_contains;

/**
 * Which library the page in front of the visitor belongs to.
 *
 * This is the replacement for the `libraryInfo` view helper, which the layout calls to
 * decide whether the navbar carries a library search box. The helper's factory does
 *
 *     $container->get('application')->getMvcEvent()->getRouteMatch()
 *
 * so it dies on a Symfony-served route the way every MvcEvent-dependent helper does —
 * and because the *layout* calls it, the failure would land on every ported page under
 * the four route prefixes rather than on one template. That is why the answer is
 * assembled here instead: the route name and its parameters are things a Symfony
 * request already has, and the rest is two table reads.
 *
 * ## The route prefixes are the contract, and they are prefixes of the *laminas* name
 *
 * `layout.phtml` decides a page is library-scoped by substring-matching the route name
 * against the four strings in {@see self::LIBRARY_ROUTE_PREFIXES}. A ported Symfony
 * route is named after the laminas route it shadows, so the same test works unchanged —
 * which is the whole reason ChromeExtension can ask this question without any controller
 * passing it down. Keep naming ported routes after their laminas twins.
 *
 * ## Three route parameters, in the helper's own order
 *
 * `library_id` names the library outright; `book_id` and `import_id` each name a record
 * that belongs to one. The order matters and is the helper's: a route carrying both
 * `library_id` and `book_id` is answered by the former without a query.
 *
 * ## One deliberate divergence: a bad id is not fatal here
 *
 * `SionTable::getObject()` throws `InvalidArgumentException` when the id matches no row,
 * so the laminas helper turns a stale bookmark into a 500 *from the layout* — after the
 * page's own content has rendered. This class passes `failSilently` and answers null
 * instead, which degrades to the site-wide contacts search box. The divergence is
 * unreachable from a ported route today (every one of them loads its own record first
 * and 404s if it is missing) and is the direction worth erring in regardless: the
 * chrome must never be the thing that breaks a page.
 */
final class CurrentLibrary
{
    /**
     * The four route-name fragments layout.phtml tests, verbatim and in its order.
     *
     * @var list<string>
     */
    public const LIBRARY_ROUTE_PREFIXES = [
        'libraries/library/',
        'books/',
        'checkouts/',
        'library-imports/',
    ];

    private ?LibraryTable $libraries = null;

    public function __construct(private readonly ServiceBridge $laminas)
    {
    }

    /** Whether this route's pages belong to a library at all. */
    public static function isLibraryRoute(string $routeName): bool
    {
        foreach (self::LIBRARY_ROUTE_PREFIXES as $prefix) {
            if (str_contains($routeName, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The `library` entity row this page belongs to, or null when it belongs to none.
     *
     * @param array<string, mixed> $routeParams the request's route parameters; only
     *        `library_id`, `book_id` and `import_id` are read
     * @return array<string, mixed>|null
     */
    public function forRoute(string $routeName, array $routeParams): ?array
    {
        if (! self::isLibraryRoute($routeName)) {
            return null;
        }

        $libraryId = $this->libraryId($routeParams);
        if (null === $libraryId) {
            return null;
        }

        return self::row($this->libraries()->getObject('library', $libraryId, true));
    }

    /** @param array<string, mixed> $routeParams */
    private function libraryId(array $routeParams): ?int
    {
        $libraryId = self::id($routeParams['library_id'] ?? null);
        if (null !== $libraryId) {
            return $libraryId;
        }

        $bookId = self::id($routeParams['book_id'] ?? null);
        if (null !== $bookId) {
            $book = self::row($this->libraries()->getObject('book', $bookId, true));

            return null === $book ? null : self::id($book['libraryId'] ?? null);
        }

        $importId = self::id($routeParams['import_id'] ?? null);
        if (null !== $importId) {
            //not getObject(): `library-import` reads through a cached list, and the helper
            //asks for it by the table's own accessor rather than the entity one.
            $import = self::row($this->libraries()->getLibraryImport($importId));

            return null === $import ? null : self::id($import['libraryId'] ?? null);
        }

        return null;
    }

    /**
     * A SionModel read, narrowed to what it can actually answer.
     *
     * `SionTable::getObject()` and `LibraryTable::getLibraryImport()` both return null
     * for a missing row while their PHPDoc promises an array, so PHPStan reads the null
     * check at each call site as dead code. Funnelling them through one `mixed`
     * parameter keeps the check — the runtime behaviour these depend on — without
     * three suppressions.
     *
     * @return array<string, mixed>|null
     */
    private static function row(mixed $value): ?array
    {
        return is_array($value) ? $value : null;
    }

    /** A route parameter as a positive row id, or null for anything that is not one. */
    private static function id(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }
        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    private function libraries(): LibraryTable
    {
        if (null === $this->libraries) {
            /** @var LibraryTable $table */
            $table           = $this->laminas->get(LibraryTable::class);
            $this->libraries = $table;
        }

        return $this->libraries;
    }
}
