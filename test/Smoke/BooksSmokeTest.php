<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Books module: publications, music, timeline, the dictionary, the
 * library show pages and the members-only text, library and borrower areas.
 */
class BooksSmokeTest extends SmokeTestCase
{
    public function testLiteratureIndexRenders(): void
    {
        $response = $this->assertRendersOk('/en/literature');

        $this->assertStringContainsString('Schoenstatt Literature Tools', $response['body']);
    }

    public function testPublicationsSearchPageRenders(): void
    {
        $response = $this->assertRendersOk('/en/literature/search');

        $this->assertStringContainsString('Publications search', $response['body']);
    }

    public function testMusicIndexRenders(): void
    {
        $response = $this->assertRendersOk('/en/music');

        $this->assertStringContainsString('Add new composition', $response['body']);
    }

    public function testTimelineRenders(): void
    {
        $response = $this->assertRendersOk('/en/timeline');

        $this->assertStringContainsString('Kentenich Timeline', $response['body']);
    }

    /**
     * The timeline lists every event, under 7 of the 8 Kentenich periods.
     *
     * **7, not 8, and the reason is the assertion's point.**
     * `KentenichPeriodFromDate` defines eight; the eighth, "After the Founder (1968-)",
     * begins on 1969-01-01 and the latest event in the table is 1968-09-20, so it has
     * never rendered. A test that asserted 8 would be asserting a bug, and a reader
     * counting `PERIOD_NAMES` and finding 7 headings would reasonably suspect one.
     *
     * The count is a lower bound rather than an equality: this suite runs against a
     * capsule loaded from a production export, and an event added on the live site
     * should not fail a test about page structure. What it catches is the failure that
     * matters — a grouping or template change that drops events, which is exactly what
     * App\Books\EventTimeline's docblock warns is easy to do (events are keyed by
     * `eventId` within a year, so a change that keyed them by anything less unique
     * would silently collapse rows and still render a plausible page).
     */
    public function testTimelineListsEveryEventUnderItsPeriod(): void
    {
        $response = $this->assertRendersOk('/en/timeline');

        $this->assertSame(
            7,
            substr_count($response['body'], '<h2>'),
            'expected 7 period headings — the 8th period starts in 1969 and no event reaches it'
        );

        $this->assertGreaterThanOrEqual(
            527,
            substr_count($response['body'], '</span><br>'),
            'the timeline lost events; see App\Books\EventTimeline on the keying that makes that silent'
        );
    }

    /**
     * The event title is rendered in the reader's language, not in English.
     *
     * Every event carries a title in all six languages and the page showed `titleEn` in
     * all five locales until 2026-08-15 — data that was already in the table, unread.
     * This is the one deliberate divergence between templates/books/timeline.html.twig
     * and the index.phtml it was transcribed from, so it is asserted rather than left to
     * tools/port-baseline.php, which correctly reports it as a difference.
     */
    public function testTimelineTitlesFollowTheLocale(): void
    {
        $german = $this->assertRendersOk('/de/timeline');
        $this->assertStringContainsString('Geburt von Pater Kentenich', $german['body']);

        $spanish = $this->assertRendersOk('/es/timeline');
        $this->assertStringContainsString('Nace el Padre Kentenich', $spanish['body']);

        $english = $this->assertRendersOk('/en/timeline');
        $this->assertStringContainsString('Birth of Father Kentenich', $english['body']);
    }

    /**
     * Regression: /en/dictionary answered 404 for years. The route matched
     * fine — it declared a controller and no default action, and
     * AbstractActionController::onDispatch reads that parameter with a default
     * of 'not-found', so it dispatched notFoundAction(). The action it wanted
     * is SionController's generic indexAction, and books/dictionary/index.phtml
     * was present the whole time.
     */
    public function testDictionaryIndexRenders(): void
    {
        $this->assertRendersOk('/en/dictionary');
    }

    /**
     * The locale child route is /:inLanguage constrained to two lowercase
     * letters, so the URL is /en/dictionary/es rather than anything containing
     * the route's name. Worth a test precisely because that is easy to get
     * wrong when reading the config, and because it shares its path segment
     * with the numeric /:entry_id sibling.
     */
    public function testDictionaryInLanguageRenders(): void
    {
        $this->assertRendersOk('/en/dictionary/es');
    }

    /**
     * Regression: every LibrariesController action was a hard 500 because
     * SearchFormFactory asked LibraryTable for library-specific collection
     * options while building the controller, before any route match existed.
     * Broken since 2017 (156c2f4 dropped the route-match priming of
     * LibraryTable::setLibraryId()); the options now come from the action.
     */
    public function testGuestVisibleLibraryRenders(): void
    {
        $response = $this->assertRendersOk('/en/libraries/1');

        // The show page lists the library's collections as filter links.
        $this->assertMatchesRegularExpression('/collectionId=\d+/', $response['body']);
    }

    /**
     * The other half of that regression: the collectionId element is a Select,
     * so its value options are what let the InArray validator accept a real
     * collection. With the options missing the form silently failed validation
     * and the filter returned the unfiltered page instead of book results.
     */
    public function testLibraryCollectionFilterReturnsBooks(): void
    {
        // Take a collection id off the page itself rather than hard-coding one,
        // so this survives a different dataset.
        $overview = $this->assertRendersOk('/en/libraries/1');
        $this->assertSame(
            1,
            preg_match('/collectionId=(\d+)/', $overview['body'], $matches),
            'Expected the library show page to link at least one collection'
        );
        $collectionId = $matches[1];

        $this->assertStringNotContainsString('href="/en/books/', $overview['body']);

        $filtered = $this->assertRendersOk('/en/libraries/1?collectionId=' . $collectionId);
        $this->assertMatchesRegularExpression(
            '#href="/en/books/\d+#',
            $filtered['body'],
            "Filtering library 1 by collection $collectionId should list books"
        );
    }

    /** A library whose ViewRole is not 'guest' bounces anonymous visitors to the index. */
    public function testMemberOnlyLibraryBouncesGuests(): void
    {
        $response = $this->get('/en/libraries/5');

        $this->assertSame(302, $response['status']);
        $this->assertStringContainsString('/en/libraries', $response['redirect']);
    }

    #[DataProvider('protectedPathProvider')]
    public function testProtectedPathRequiresLogin(string $path): void
    {
        $this->assertRequiresLogin($path);
    }

    /** @return array<string, array{0: string}> */
    public static function protectedPathProvider(): array
    {
        return [
            'texts'     => ['/en/texts'],
            'libraries' => ['/en/libraries'],
            //`/en/borrowers` was here until 2026-08-18 and is now a 404: the route
            //declared an `index` action BorrowersController does not have, so it answered
            //500 rather than the 302 this asserted — the guard redirected anonymous
            //visitors before the missing action could be reached, which is why an
            //anonymous check passed on a page no signed-in visitor could open. Retired in
            //strangler batch 11b; test/Smoke/LibrarySurfaceSmokeTest pins the 404.
            //`/en/borrowers/{person_id}` is unaffected and still redirects.
            'borrower'  => ['/en/borrowers/514'],
        ];
    }
}
