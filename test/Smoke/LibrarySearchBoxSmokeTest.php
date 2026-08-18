<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

/**
 * The navbar search box on a library page, on both front controllers.
 *
 * A page belonging to a library searches *that library*; every other page offers the
 * site-wide contacts search. `layout.phtml` decides that with the `libraryInfo` view
 * helper — an MvcEvent-dependent helper a Symfony route cannot call — so the Symfony
 * layout simply had no library branch, and from the first library route ported
 * (2026-08-09) until 2026-08-18 three pages showed the wrong search box:
 * `books/book/edit`, `books/create` and `libraries/library/edit`. Nothing failed; the
 * page rendered, the form worked, and it searched the wrong corpus.
 *
 * That is the class of defect this file exists for, and it is why the assertions below
 * are **cross-front-controller comparisons rather than fixed strings**. A ported page and
 * an unported page in the same cluster are fetched with one identity in one locale, and
 * their search boxes must agree. Pinning the literal `Search Bellavista` instead would
 * pass just as happily if the *laminas* side broke, and would need editing every time a
 * library is renamed.
 *
 * Strategy from strangler batch 11b onwards: this is the guard that lets ~30 more library
 * routes be ported without re-deriving the chrome each time.
 *
 * Every assertion here was checked by mutation, and one of them only exists because the
 * first version survived: see PORTED_NON_LIBRARY_PAGE.
 */
class LibrarySearchBoxSmokeTest extends SmokeTestCase
{
    use MagicLinkSignIn;

    /** Distinct from the other suites' prefixes, or one class's tearDown deletes another's accounts. */
    private const EMAIL_PREFIX = 'libsearch-smoke-';

    /** Bellavista. `ViewRole = guest`, so every signed-in account may see it. */
    private const LIBRARY = 1;

    /**
     * `libraries/library/edit` — Symfony-served, and library-scoped through `library_id`.
     * Its per-row check is `library_<id>` + `administrate`, hence lib_administrator below.
     */
    private const PORTED_LIBRARY_PAGE = '/libraries/1/edit';

    /**
     * `libraries/library/checkout` — still laminas-served, and the reference rendering.
     * Deliberately a *different* route in the same cluster: comparing a page with itself
     * across front controllers is impossible while only one of them serves it.
     */
    private const LAMINAS_LIBRARY_PAGE = '/libraries/1/checkout';

    /**
     * `collections/create` — Symfony-served, outside all four library prefixes, and
     * **carrying `library_id` anyway**. It must still offer the contacts search.
     *
     * The parameter is the point. An obvious choice here would be a page with no library
     * parameter at all — `/roles/create` was the first draft — but that passes whatever
     * the prefix rule does, because there is no library to find either way: mutating
     * `CurrentLibrary::isLibraryRoute()` to return true for everything left it green.
     * Creating a collection *inside* library 1 is the case where the two halves of the
     * rule disagree, so it is the only kind of page that tests the prefixes at all.
     *
     * That laminas shows the contacts search on a page scoped to a library reads as an
     * oversight in the original. It is reproduced rather than corrected: this batch ports
     * the rule, it does not redesign it, and the comparison below would have to be
     * abandoned to "fix" it.
     */
    private const PORTED_NON_LIBRARY_PAGE = '/collections/create/1';

    protected function emailPrefix(): string
    {
        return self::EMAIL_PREFIX;
    }

    /**
     * The navbar search form's `action` and `placeholder`, or null when the page has none.
     *
     * Read off the form that posts to a search route rather than off the first
     * `placeholder=` in the document: a library edit form carries six of its own, and one
     * of them is the first in source order.
     *
     * @return array{action: string, placeholder: string}|null
     */
    private function searchBox(string $body): ?array
    {
        $found = preg_match(
            '#<form class="navbar-form"\s+action="([^"]*)"[^>]*>.*?placeholder="([^"]*)"#s',
            $body,
            $matches
        );

        return 1 === $found ? ['action' => $matches[1], 'placeholder' => $matches[2]] : null;
    }

    /** @return array{action: string, placeholder: string} */
    private function searchBoxOn(string $path, string $locale, array $roles): array
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, $roles);

        $response = $this->get('/' . $locale . $path, false, $jar);
        $this->assertSame(200, $response['status'], $path . ' should render for this identity');

        $box = $this->searchBox($response['body']);
        $this->assertNotNull($box, 'no navbar search form on ' . $locale . $path);

        return $box;
    }

    /**
     * The ported page and its unported neighbour offer the same library search box.
     *
     * This is the whole contract in one assertion. It compares the rendering of
     * `App\View\SiteChrome::searchBox()` with the rendering of the layout.phtml branch it
     * reproduces, so it fails if either side changes without the other.
     */
    public function testAPortedLibraryPageOffersTheSameSearchBoxAsAnUnportedOne(): void
    {
        $laminas = $this->searchBoxOn(self::LAMINAS_LIBRARY_PAGE, 'en', ['lib_administrator']);
        $symfony = $this->searchBoxOn(self::PORTED_LIBRARY_PAGE, 'en', ['lib_administrator']);

        $this->assertSame(
            $laminas,
            $symfony,
            'the two front controllers disagree about the library search box — see this class\'s '
            . 'docblock; App\Books\CurrentLibrary is the Symfony side of it'
        );
    }

    /**
     * It searches the library, not the site's contacts.
     *
     * Asserted on the `action` rather than on the placeholder text, because the action is
     * the part that decides what a visitor's query actually does. The placeholder is
     * checked in the locale test below, where it carries different information.
     */
    public function testTheLibrarySearchBoxPostsToTheLibrary(): void
    {
        $box = $this->searchBoxOn(self::PORTED_LIBRARY_PAGE, 'en', ['lib_administrator']);

        $this->assertStringEndsWith(
            '/libraries/' . self::LIBRARY,
            $box['action'],
            'a library page must search its own library'
        );
    }

    /**
     * A ported page outside the four route prefixes still gets the contacts search.
     *
     * The scoping half. `CurrentLibrary::isLibraryRoute()` returning true for everything
     * would satisfy every other test in this file.
     */
    public function testAPortedNonLibraryPageStillOffersTheContactsSearch(): void
    {
        $box = $this->searchBoxOn(self::PORTED_NON_LIBRARY_PAGE, 'en', ['lib_administrator']);

        $this->assertStringEndsWith(
            '/assignments/search',
            $box['action'],
            'a route outside the four prefixes searches contacts even when it names a library'
        );
        $this->assertSame('Search contacts', $box['placeholder']);
    }

    /**
     * The library placeholder is translated, and matches laminas in a non-English locale.
     *
     * The reason this test is not English-only: in English a missing translation *is* the
     * source string, so a placeholder that is never translated renders correctly on `/en`
     * and in English only. Dropping the `translate()` call fails this test and nothing
     * else.
     *
     * **What it does not catch, measured rather than assumed:** naming the wrong text
     * domain. laminas asks `translate("Search %s", 'Application')` and this side asks the
     * same, but `module/Books/language/*.lang.php` carries the identical phrase, so
     * swapping `Application` for `Books` leaves every rendering byte-identical. The domain
     * still matters — a *third* domain without the phrase would answer in English and file
     * a row for it on every render — and the parity assertion above would catch that, but
     * only because it compares against laminas. There is no assertion here that pins the
     * domain by name, because there is currently no observable difference to pin.
     */
    public function testTheLibraryPlaceholderIsTranslatedTheSameWayOnBothSides(): void
    {
        $laminas = $this->searchBoxOn(self::LAMINAS_LIBRARY_PAGE, 'es', ['lib_administrator']);
        $symfony = $this->searchBoxOn(self::PORTED_LIBRARY_PAGE, 'es', ['lib_administrator']);

        $this->assertSame($laminas['placeholder'], $symfony['placeholder']);
        $this->assertNotSame(
            'Search ' . 'Bellavista',
            $symfony['placeholder'],
            'the Spanish rendering came back in English — the text domain is wrong, and every '
            . 'render is filing a phrase row'
        );
    }
}
