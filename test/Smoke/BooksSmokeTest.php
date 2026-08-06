<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Books module: publications, blog, music, timeline, the dictionary, the
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

    public function testBlogIndexRenders(): void
    {
        $response = $this->assertRendersOk('/en/blog');

        $this->assertStringContainsString('Posted', $response['body']);
        // Regression: parsedown 1.8 + parsedown-extra 0.7/0.8 mismatches used
        // to leak "Undefined index: text" / null-offset notices into the posts
        $this->assertStringNotContainsString('Notice</b>:', $response['body']);
        $this->assertStringNotContainsString('Warning</b>:', $response['body']);
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
            'borrowers' => ['/en/borrowers'],
        ];
    }
}
