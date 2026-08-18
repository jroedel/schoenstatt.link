<?php

declare(strict_types=1);

namespace SchoenstattTest\Unit;

use App\Books\CurrentLibrary;
use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function in_array;
use function preg_match_all;
use function sort;

require_once __DIR__ . '/../../src/Books/CurrentLibrary.php';

/**
 * The four route-name fragments that make a page library-scoped, pinned against the
 * laminas layout they were copied from.
 *
 * `App\Books\CurrentLibrary` holds a **copy** of a list that lives in
 * `module/Application/view/layout/layout.phtml`, and a copy is exactly as good as the
 * thing that notices when it stops matching. The failure this guards against is silent
 * in both directions and invisible in any rendered page a reviewer would look at:
 *
 *  - a prefix added to the layout and not here → the Symfony rendering of those routes
 *    quietly shows the site-wide contacts search where laminas shows the library's own,
 *    which is precisely the regression that went unnoticed on three pages from
 *    2026-08-09 to 2026-08-18;
 *  - a prefix removed from the layout and not here → the Symfony rendering keeps a
 *    search box the original has dropped.
 *
 * Neither shows up as an error, a warning or a failed request. Both show up here.
 *
 * The layout's own test is `false !== strpos($routeName, '<prefix>')`, written four times
 * inside one `if` and then repeated verbatim in a second one; this reads every occurrence
 * and de-duplicates, so a fifth prefix added to only one of the two conditionals still
 * registers. Should the layout ever stop expressing the rule as literal `strpos` calls
 * — it goes away entirely once the four route clusters are ported — this test will fail
 * with an empty match rather than pass vacuously, which is the right way round.
 *
 * **The `false !==` in that pattern is not decoration.** The same conditional's *next*
 * branch asks `false === strpos($routeName, 'publication')`, which is the opposite
 * question — "this route is not about publications, so offer the contacts search" — and
 * a pattern matching bare `strpos` reads it as a fifth library prefix. Caught by this
 * test on its first run, which is the argument for extracting the rule rather than
 * eyeballing it: the two calls are nine lines apart and look identical at a glance.
 *
 * Requires the class file directly: nothing in it touches a Laminas class at class
 * level, so the vendor-free unit suite can hold it.
 */
class LibraryRoutePrefixesTest extends TestCase
{
    private const LAYOUT = __DIR__ . '/../../module/Application/view/layout/layout.phtml';

    /** @return list<string> */
    private function prefixesInTheLayout(): array
    {
        $layout = (string) file_get_contents(self::LAYOUT);
        preg_match_all("/false !== strpos\(\\\$routeName, '([^']+)'\)/", $layout, $matches);

        /** @var list<string> $found */
        $found = [];
        foreach ($matches[1] as $prefix) {
            if (! in_array($prefix, $found, true)) {
                $found[] = $prefix;
            }
        }
        sort($found);

        return $found;
    }

    public function testTheLayoutStillExpressesTheRuleAsStrposCalls(): void
    {
        $this->assertNotEmpty(
            $this->prefixesInTheLayout(),
            'layout.phtml no longer tests the route name with strpos(). Either the library search '
            . 'box moved, or the four clusters are ported and both this test and '
            . 'CurrentLibrary::LIBRARY_ROUTE_PREFIXES can go — but do not delete this assertion to '
            . 'make the next one pass.'
        );
    }

    public function testCurrentLibraryCarriesTheSamePrefixesAsTheLayout(): void
    {
        $copy = CurrentLibrary::LIBRARY_ROUTE_PREFIXES;
        sort($copy);

        $this->assertSame(
            $this->prefixesInTheLayout(),
            $copy,
            'App\Books\CurrentLibrary and layout.phtml disagree about which routes belong to a '
            . 'library. The two front controllers now render different navbars for the routes in '
            . 'the difference, and nothing else will report it.'
        );
    }

    /**
     * The prefixes are substrings, not `str_starts_with` prefixes, and the difference is
     * load-bearing: `books/create` matches `books/` while starting with neither
     * `libraries/library/` nor anything else in the list, and it is one of the three
     * pages the regression was found on.
     */
    public function testMatchingIsBySubstringTheWayTheLayoutDoesIt(): void
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
}
