<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use PHPUnit\Framework\Attributes\DataProvider;

use function strlen;

/**
 * The rest of batch 6: `/movement`, `/persons`, `/persons/search`, `/persons/{id}` and
 * `/texts`. The contact search has its own file, which is larger because the form
 * renderer grew five things for it.
 *
 * Three distinct guards are covered here, which is most of why these routes are one
 * batch: `sch_moderator` for the first four and `texts_user` for the last — a role
 * granted to Schoenstatt fathers by email match, held by nobody a registration creates.
 */
class Batch6SymfonySmokeTest extends SmokeTestCase
{
    use MagicLinkSignIn;

    /** Distinct from every other user of the trait, or one tearDown deletes another's accounts. */
    private const EMAIL_PREFIX = 'batch6-smoke-';

    /** A person who exists in the 2021 dump and is the subject of the show-page cases. */
    private const PERSON_ID = 494;

    protected function emailPrefix(): string
    {
        return self::EMAIL_PREFIX;
    }

    // ------------------------------------------------------------ authorization

    /** @return iterable<string, array{0: string}> */
    public static function moderatorPaths(): iterable
    {
        yield 'movement'       => ['/en/movement'];
        yield 'persons'        => ['/en/persons'];
        yield 'persons search' => ['/en/persons/search'];
        yield 'person'         => ['/en/persons/' . self::PERSON_ID];
    }

    /** @return iterable<string, array{0: string}> */
    public static function allPaths(): iterable
    {
        yield from self::moderatorPaths();
        yield 'texts' => ['/en/texts'];
    }

    #[DataProvider('allPaths')]
    public function testAnonymousVisitorIsRedirectedToSignIn(string $path): void
    {
        $response = $this->get($path);

        $this->assertSame(302, $response['status'], $path);
        $this->assertStringEndsWith('/en/user/login?redirect=' . $path, $response['redirect'], $path);
    }

    /**
     * A registered account holds `sch_user`, which none of these guards name, so every
     * one of them answers 403 — not a redirect to sign in, which the visitor has already
     * done. This is App\Authorization\Denial's second branch.
     */
    #[DataProvider('allPaths')]
    public function testAnOrdinaryAccountIsForbidden(string $path): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar);

        $response = $this->get($path, false, $jar);

        $this->assertSame(403, $response['status'], $path . ': a signed-in visitor must not be sent to sign in');
        $this->assertStringContainsString('<h1>403 Forbidden</h1>', $response['body'], $path);
    }

    #[DataProvider('moderatorPaths')]
    public function testAModeratorReachesTheModeratorPages(string $path): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['sch_moderator']);

        $response = $this->get($path, false, $jar);

        $this->assertSame(200, $response['status'], $path);
        $this->assertStringNotContainsString(
            'slm_locale=en_US',
            $response['headers']['set-cookie'] ?? '',
            $path . ': a slm_locale cookie means SlmLocale ran, i.e. laminas served this'
        );
    }

    /** @return iterable<string, array{0: string, 1: string}> */
    public static function unprefixedPaths(): iterable
    {
        yield 'movement'       => ['/movement', '/en/movement'];
        yield 'persons'        => ['/persons', '/en/persons'];
        //the two persons routes must redirect to *themselves*, not to each other —
        //one controller serves both and reads which from the request
        yield 'persons search' => ['/persons/search', '/en/persons/search'];
        yield 'person'         => ['/persons/' . self::PERSON_ID, '/en/persons/' . self::PERSON_ID];
    }

    #[DataProvider('unprefixedPaths')]
    public function testTheUnprefixedFormRedirectsToItsOwnPrefixedForm(string $path, string $target): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['sch_moderator']);

        $response = $this->get($path, false, $jar);

        $this->assertSame(302, $response['status'], $path);
        $this->assertStringEndsWith($target, $response['redirect'], $path);
    }

    // ---------------------------------------------------------------- /movement

    /**
     * Two panels, two tables, and the asymmetry between their headings: the first is a
     * link carrying the presidium's translated name, the second the bare English string
     * the .phtml passes without a translate() call.
     */
    public function testMovementRendersBothPanels(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['sch_moderator']);

        $body = $this->get('/en/movement', false, $jar)['body'];

        $this->assertStringContainsString(
            '<div class="panel-heading"><a href="/en/SL100071A/general-presidium">General Presidium</a></div>',
            $body
        );
        $this->assertStringContainsString('<div class="panel-heading">National movements</div>', $body);
        $this->assertStringContainsString('<th>Role</th><th>Contact person</th><th>Email</th><th>Telephone</th>', $body);
        $this->assertStringContainsString(
            '<th>Association</th><th>Contact person</th><th>Email</th><th>Telephone</th>',
            $body
        );
    }

    /**
     * The one breadcrumb `/movement` has, translated — `schoenstatt` is in the navigation
     * config, unlike everything else in this batch. Asserted in Spanish, because in
     * English a missing translation *is* the source string and this would pass against a
     * layout that had stopped translating crumbs entirely.
     */
    public function testTheMovementBreadcrumbIsTranslated(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['sch_moderator']);

        $body = $this->get('/es/movement', false, $jar)['body'];

        $this->assertStringContainsString('Movimiento', $body);
    }

    // ----------------------------------------------------------------- /persons

    /**
     * **The repair, and the reason this port deviates.** The laminas page renders an
     * "Add person" link and nothing else for every query — see App\Controller\PersonsController
     * — so a table here is a difference from laminas on purpose.
     */
    public function testThePersonsSearchRendersResults(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['sch_moderator']);

        $body = $this->get('/en/persons/search?search=Walter', false, $jar)['body'];

        foreach (['Name', 'Community', 'Email', 'Cell phone'] as $header) {
            $this->assertStringContainsString('<th>' . $header . '</th>', $body);
        }
        $this->assertStringContainsString('<table class="table">', $body);
        $this->assertStringContainsString('/en/persons/1"', $body, 'Heinrich Walter is not in the results');
    }

    /** And the search box, which the .phtml builds and never draws. */
    public function testThePersonsSearchRendersItsSearchBox(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['sch_moderator']);

        $body = $this->get('/en/persons', false, $jar)['body'];

        $this->assertStringContainsString('action="&#x2F;en&#x2F;persons&#x2F;search"', $body);
        $this->assertStringNotContainsString(
            'Advanced search',
            $body,
            'the advanced form searches assignments, not people'
        );
    }

    /** A query matching nothing says so and draws no table. */
    public function testThePersonsSearchWithNoMatchesSaysSo(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['sch_moderator']);

        $body = $this->get('/en/persons/search?search=zzzznosuchperson', false, $jar)['body'];

        $this->assertStringContainsString('No results found.', $body);
        $this->assertStringNotContainsString('<table class="table">', $body);
    }

    // ------------------------------------------------------------ /persons/{id}

    /**
     * The show page's panels, and the one that must **not** be there: `assignmentsPanel`
     * is unreachable in the original — `getPersons()` never fills the key its condition
     * tests — and reproducing it would have meant reproducing a helper chain that ends in
     * an unregistered `formatScope()`.
     */
    public function testThePersonPageRendersItsPanelsAndNotTheDeadOne(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['sch_moderator']);

        $body = $this->get('/en/persons/' . self::PERSON_ID, false, $jar)['body'];

        $this->assertStringContainsString('id="contactInfoPanel"', $body);
        $this->assertStringContainsString('id="personalInfoPanel"', $body);
        $this->assertStringNotContainsString('id="assignmentsPanel"', $body);
        //the title is data and must not be translated — a translator miss files a phrase,
        //and here that would be one row per person
        $this->assertStringContainsString('<title>Sr. M. Aleja Slaughter - Schoenstatt Link</title>', $body);
    }

    /** An id no person has goes back to the index with a flash, as showAction() does. */
    public function testAnUnknownPersonRedirectsToTheIndex(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['sch_moderator']);

        $response = $this->get('/en/persons/99999', false, $jar);

        $this->assertSame(302, $response['status']);
        $this->assertStringEndsWith('/en/persons', $response['redirect']);
    }

    // ------------------------------------------------------------------- /texts

    /**
     * `texts_user` gets in, and with no query the page is the explanatory markdown rather
     * than an empty result list — the .phtml branches on whether `$objects` is set at all,
     * not on whether it is empty.
     *
     * The trailing space inside `format. </p>` is asserted deliberately: CommonMark keeps
     * a single trailing space at the end of a paragraph and strips one inside it, so this
     * is the byte an editor trimming trailing whitespace would silently take out of
     * templates/books/texts-index.html.twig.
     */
    public function testTheTextsPageExplainsItselfWhenThereIsNoQuery(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['texts_user']);

        $response = $this->get('/en/texts', false, $jar);

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('<h2>To whomever may stumble across this page...</h2>', $response['body']);
        $this->assertStringContainsString(
            'in a little nicer format. </p>',
            $response['body'],
            'the trailing space CommonMark keeps at the end of that paragraph has been trimmed'
        );
        $this->assertStringNotContainsString('<mark>', $response['body']);
    }

    /**
     * A matching query replaces the explanation with results, each carrying a highlighted
     * excerpt. The size assertion is loose on purpose — laminas measures 1,014,522 bytes
     * for this query and the exact figure drifts with the corpus — but it is the check
     * that would catch excerpting silently returning nothing.
     */
    public function testATextsSearchHighlightsItsMatches(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['texts_user']);

        $body = $this->get('/en/texts?search=Bund', false, $jar)['body'];

        $this->assertStringContainsString('<mark>', $body);
        $this->assertStringNotContainsString('To whomever may stumble across this page', $body);
        $this->assertGreaterThan(100000, strlen($body), 'the corpus search returned almost nothing');
    }

    /**
     * The search form is `required` with a three-character minimum, so a two-character
     * query fails validation and the page falls back to the explanation rather than
     * running a `LIKE %ab%` over the whole corpus.
     */
    public function testATooShortTextsQueryRunsNoSearch(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['texts_user']);

        $body = $this->get('/en/texts?search=ab', false, $jar)['body'];

        $this->assertStringContainsString('To whomever may stumble across this page', $body);
        $this->assertStringNotContainsString('<mark>', $body);
    }
}
