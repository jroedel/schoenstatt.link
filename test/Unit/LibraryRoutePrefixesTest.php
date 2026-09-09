<?php

declare(strict_types=1);

namespace SchoenstattTest\Unit;

use App\Books\CurrentLibrary;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/Books/CurrentLibrary.php';

/**
 * The route-name fragments that make a page library-scoped, and how they match.
 *
 * `App\Books\CurrentLibrary::LIBRARY_ROUTE_PREFIXES` decides whether the navbar shows
 * a library's own search box or the site-wide contacts search. It began as a copy of a
 * list in the laminas layout, and this test compared the two until that layout was
 * deleted (laminas-exit.md, step 0); the list is now the only copy. What remains to pin
 * is the matching rule, because a rewrite to `str_starts_with` would pass every obvious
 * case and silently drop the library search box from three pages — which is exactly the
 * regression that went unnoticed from 2026-08-09 to 2026-08-18.
 *
 * Requires the class file directly: nothing in it touches a Laminas class at class
 * level, so the vendor-free unit suite can hold it.
 */
class LibraryRoutePrefixesTest extends TestCase
{
    /**
     * The prefixes are substrings, not `str_starts_with` prefixes, and the difference is
     * load-bearing: `books/create` matches `books/` while starting with neither
     * `libraries/library/` nor anything else in the list, and it is one of the three
     * pages the regression was found on.
     */
    public function testMatchingIsBySubstring(): void
    {
        $this->assertTrue(CurrentLibrary::isLibraryRoute('books/create'));
        $this->assertTrue(CurrentLibrary::isLibraryRoute('books/book/edit'));
        $this->assertTrue(CurrentLibrary::isLibraryRoute('libraries/library/edit'));
        $this->assertTrue(CurrentLibrary::isLibraryRoute('checkouts/library'));
        $this->assertTrue(CurrentLibrary::isLibraryRoute('library-imports/library-import'));

        //`libraries/library` itself is *not* library-scoped for this purpose — it is the
        //library's own page, and NO_SEARCH_ROUTES drops the search box there entirely.
        $this->assertFalse(CurrentLibrary::isLibraryRoute('libraries/library'));
        $this->assertFalse(CurrentLibrary::isLibraryRoute('libraries/create'));
        $this->assertFalse(CurrentLibrary::isLibraryRoute('assignments/search'));
        $this->assertFalse(CurrentLibrary::isLibraryRoute(''));
    }

    /** A renamed or emptied constant must fail here, not pass every case above vacuously. */
    public function testTheListStillNamesTheFourClusters(): void
    {
        $this->assertCount(4, CurrentLibrary::LIBRARY_ROUTE_PREFIXES);
    }
}
