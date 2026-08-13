<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use PHPUnit\Framework\Attributes\DataProvider;

use function preg_match_all;

/**
 * The contact search, ported 2026-08-13 — `/assignments/search` and
 * `/assignments/advanced-search`.
 *
 * `/assignments/search` is the destination of the navbar search box on every page of
 * the site (`App\View\SiteChrome::searchBox()`), so it is the busiest route in batch 6
 * and the one whose breakage would be most visible. Its guard is `sch_basic, sch_user`,
 * which registration satisfies — so unlike most authorization tests in this suite, the
 * positive case here needs no role grant.
 *
 * **The pencil assertions live here and not in AssignmentsTableTest**, and the reason is
 * a limitation rather than a preference: `edit_pencil()` renders nothing unless the
 * viewer may reach the edit route, the identity comes from the laminas session, and a
 * CLI process has none. So headlessly every pencil is absent and "the person cell has no
 * person pencil" passes just as well against a macro that lost the ability to render one.
 * Only a real session tells the two apart, and that is what this file has.
 */
class AssignmentsSearchSymfonySmokeTest extends SmokeTestCase
{
    use MagicLinkSignIn;

    /** Distinct from every other user of the trait, or one tearDown deletes another's accounts. */
    private const EMAIL_PREFIX = 'assignments-search-smoke-';

    /** A surname with several assignments in the 2021 dump, so a search for it is not empty. */
    private const QUERY = 'Walter';

    protected function emailPrefix(): string
    {
        return self::EMAIL_PREFIX;
    }

    // ------------------------------------------------------------ authorization

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function restrictedPaths(): iterable
    {
        yield 'search'          => ['/en/assignments/search'];
        yield 'advanced search' => ['/en/assignments/advanced-search'];
    }

    #[DataProvider('restrictedPaths')]
    public function testAnonymousVisitorIsRedirectedToSignIn(string $path): void
    {
        $response = $this->get($path);

        $this->assertSame(302, $response['status'], $path);
        $this->assertStringEndsWith('/en/user/login?redirect=' . $path, $response['redirect'], $path);
    }

    /**
     * `route/assignments/advanced-search` is `sch_user` and `route/assignments/search` is
     * `sch_basic, sch_user` — registration grants `sch_user`, so an ordinary account
     * reaches both. Asserted because it is the half a refusal test cannot see: every
     * other authorization case in this file would pass against a guard refusing everyone.
     */
    #[DataProvider('restrictedPaths')]
    public function testAnOrdinaryAccountReachesBothPages(string $path): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar);

        $response = $this->get($path, false, $jar);

        $this->assertSame(200, $response['status'], $path);
    }

    // ------------------------------------------------- served by Symfony, not bridged

    /**
     * The discriminator Batch4SymfonySmokeTest established: laminas sets
     * `slm_locale=en_US` on every response and a ported route never runs SlmLocale, so
     * that cookie coming back means the request went through LegacyBridge and every
     * other assertion here is measuring the laminas page.
     */
    #[DataProvider('restrictedPaths')]
    public function testThePathIsServedBySymfonyAndNotBridgedToLaminas(string $path): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar);

        $response = $this->get($path, false, $jar);

        $this->assertSame(200, $response['status'], $path);
        $this->assertStringNotContainsString(
            'slm_locale=en_US',
            $response['headers']['set-cookie'] ?? '',
            $path . ': a slm_locale cookie means SlmLocale ran, i.e. laminas-mvc served this — '
            . 'is the route still above the catch-all?'
        );
    }

    /** @return iterable<string, array{0: string, 1: string}> */
    public static function unprefixedPaths(): iterable
    {
        yield 'search'          => ['/assignments/search', '/en/assignments/search'];
        yield 'advanced search' => ['/assignments/advanced-search', '/en/assignments/advanced-search'];
    }

    /**
     * Signed in, because these paths are guarded: anonymously the *guard* answers first
     * and the locale redirect never runs. That ordering is the documented divergence
     * between the two front controllers, and testing it anonymously would measure the
     * guard rather than the redirect this asserts.
     */
    #[DataProvider('unprefixedPaths')]
    public function testTheUnprefixedFormRedirectsToTheNegotiatedLanguage(string $path, string $target): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar);

        $response = $this->get($path, false, $jar);

        $this->assertSame(302, $response['status'], $path);
        $this->assertStringEndsWith($target, $response['redirect'], $path);
    }

    // -------------------------------------------------------------- the page itself

    /**
     * A query with matches renders the five-column table. The `association` column is
     * the one worth naming: it is the column this page adds over the association show
     * page's use of the same partial.
     */
    public function testASearchWithMatchesRendersTheFiveColumnTable(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['sch_moderator']);

        $body = $this->get('/en/assignments/search?search=' . self::QUERY, false, $jar)['body'];

        foreach (['Association', 'Role', 'Contact person', 'Email', 'Telephone'] as $header) {
            $this->assertStringContainsString('<th>' . $header . '</th>', $body);
        }
        $this->assertStringContainsString('<table class="table sortable">', $body);
    }

    /**
     * **The regression this batch fixed**, and the assertion that needs a real identity.
     *
     * `_assignments-table.html.twig` passes `displayEditPencil: false` for the person
     * cell, because the row already carries the *assignment's* pencil immediately after
     * the name. Until 2026-08-13 the `person()` macro took no options, so that was
     * dropped and every row offered a `…/persons/{id}/edit` link the laminas page does
     * not have.
     *
     * Two-sided on purpose: the assignment pencil must be there and the person pencil
     * must not, so a change that removes both fails rather than looking like the fix.
     */
    public function testThePersonCellCarriesTheAssignmentPencilAndNotThePersonPencil(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['sch_moderator']);

        $body = $this->get('/en/assignments/search?search=' . self::QUERY, false, $jar)['body'];

        $this->assertMatchesRegularExpression(
            '#href="/en/assignments/\d+/edit"#',
            $body,
            'the assignment edit pencil is missing — has the viewer lost sch_moderator, '
            . 'or has edit_pencil stopped rendering at all?'
        );
        $this->assertSame(
            0,
            preg_match_all('#href="/en/persons/\d+/edit"#', $body),
            'a person edit pencil is back in the person cell; the laminas partial suppresses it '
            . 'and the ported one must too'
        );
    }

    /**
     * A blank query returns **every** entity rather than none, because the laminas action
     * passes `bypassRequiredParams`. Measured at 595,300 bytes on laminas, so the
     * assertion is that the table is there and large rather than a byte count that would
     * drift with the data.
     */
    public function testABlankQueryReturnsTheWholeTableRatherThanNothing(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar);

        $body = $this->get('/en/assignments/search', false, $jar)['body'];

        $this->assertStringContainsString('<table class="table sortable">', $body);
        $this->assertStringNotContainsString('No results found.', $body);
        $this->assertGreaterThan(
            100000,
            strlen($body),
            'a blank query should return every entity; a small page means the guard that '
            . 'the laminas action has commented out has been reintroduced'
        );
    }

    /** A query that matches nothing says so, through the nowMessenger the layout renders. */
    public function testAQueryWithNoMatchesSaysSo(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar);

        $body = $this->get('/en/assignments/search?search=zzzznosuchcontact', false, $jar)['body'];

        $this->assertStringContainsString('No results found.', $body);
        $this->assertStringNotContainsString('<table class="table sortable">', $body);
    }

    /**
     * The advanced form's own markup. Three facts, each of which the renderer had to
     * grow something for:
     *
     * - `method="GET"` — `open()` hardcoded POST while the only ported form was an edit
     *   form, which would have turned this into a form posting to a route with no POST.
     * - **no `action`** — the .phtml never sets one, and `action=""` is not the same
     *   thing to a browser resolving a relative reference.
     * - the `clear` button is `type="button"` with its declared classes intact, while
     *   the submit gets ` btn` appended to `btn-primary`. That asymmetry is
     *   TwbBundleFormButton's class rule, and one button of each shape is exactly what
     *   this form has.
     */
    public function testTheAdvancedFormOpensAsAGetFormWithNoAction(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar);

        $body = $this->get('/en/assignments/advanced-search', false, $jar)['body'];

        $this->assertStringContainsString(
            '<form method="GET" name="advanced-search" class="form-horizontal" id="advanced-search">',
            $body
        );
        $this->assertStringContainsString(
            '<button type="submit" name="submit" class="btn-primary&#x20;btn" value="">Search</button>',
            $body
        );
        $this->assertStringContainsString(
            '<button type="button" name="clear" id="clear" class="btn&#x20;btn-default" value="">Clear form</button>',
            $body
        );
    }

    /**
     * The `roleTitle` row, which is where three separate renderer gaps met — each one
     * invisible until a form declared the thing that triggers it, and the association
     * form declares none of the three.
     *
     * - `multiple` needs `[]` on the name, or a browser posts one value where the
     *   visitor picked several. That much was already right, from the batch-5 fix.
     * - `id` is a **global** attribute, so `FormSelect` renders it even though it is not
     *   in that helper's own `$validTagAttributes`. Dropping it also cost this field the
     *   selectize widget, since `gen-schoenstatt-advanced-search.js` hooks `#roleTitleSelect`.
     * - a row label carries `for` **only when the element has an id**, which is the test
     *   TwbBundleFormRow makes.
     */
    public function testTheRoleSelectKeepsItsBracketsItsIdAndItsLabelFor(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar);

        $body = $this->get('/en/assignments/advanced-search', false, $jar)['body'];

        $this->assertStringContainsString(
            '<label for="roleTitleSelect">Role</label>'
            . '<select name="roleTitle&#x5B;&#x5D;" multiple id="roleTitleSelect" class="form-control">',
            $body
        );
    }

    /**
     * `column-size` reaches the row's class, and **with the double space the original
     * emits** — TwbBundleFormRow concatenates `' col-md-4'` onto an empty state class
     * and interpolates the result into `'<div class="form-group %s">'`. Asserted on the
     * raw bytes, because the first reading of this markup collapsed the whitespace runs
     * and made it look like one space.
     */
    public function testASizedRowCarriesItsColumnClass(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar);

        $body = $this->get('/en/assignments/advanced-search', false, $jar)['body'];

        $this->assertSame(
            4,
            substr_count($body, '<div class="form-group  col-md-4">'),
            'the four sized rows of the advanced search'
        );
    }

    /**
     * The contact search bar posts to `assignments/search`, and its input carries the
     * placeholder and the `input-lg` class the .phtml declares. This is the partial
     * /movement shares, so it is asserted once here rather than on both pages.
     */
    public function testTheContactSearchBarPostsToTheSearchRoute(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar);

        $body = $this->get('/en/assignments/search', false, $jar)['body'];

        $this->assertStringContainsString(
            '<form method="GET" name="search" action="&#x2F;en&#x2F;assignments&#x2F;search"'
            . ' class="form-horizontal" id="search">',
            $body
        );
        $this->assertStringContainsString('class="input-lg&#x20;form-control" placeholder="Search"', $body);
    }
}
