<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use PHPUnit\Framework\Attributes\DataProvider;

use function sprintf;

/**
 * The library circulation surface after batch 11b: what each of its pages answers, and to
 * whom.
 *
 * `tools/port-baseline.php` compares the two renderings and is the stronger check, but it
 * only runs by hand and only against a capsule with a laminas capture beside it. This
 * runs in every suite, and it pins the four things the baseline cannot express:
 *
 *  1. **Three routes were retired and must stay gone.** Each answered an error before it
 *     was removed, so "it 404s" is not evidence the removal worked — the test states
 *     which error each one used to give.
 *  2. **`refresh-sort` writes only on POST.** A GET must render a confirmation and change
 *     nothing. This is the one contract this batch deliberately changed, and the reason
 *     is the reason to guard it: the old GET rewrote every book in the library for
 *     anybody signed in.
 *  3. **The per-library check, not the route guard, is what restricts these pages.**
 *     Every guard here names `lib_user`, which is `is_default = 1`.
 *  4. **The imports list renders for a library with no imports.** It answered 500 for
 *     five of the six libraries until this batch.
 */
class LibrarySurfaceSmokeTest extends SmokeTestCase
{
    use MagicLinkSignIn;

    /** Distinct from the other suites' prefixes, or one class's tearDown deletes another's accounts. */
    private const EMAIL_PREFIX = 'libsurface-smoke-';

    /** Bellavista: 4,604 books, four collections, and the subject of most of these. */
    private const LIBRARY = 1;

    /** Schoenstatt University Men: 126 books, small enough to sweep in a test. */
    private const SMALL_LIBRARY = 7;

    /** Colegio Mayor holds all 22 imports; every other library has none. */
    private const LIBRARY_WITH_IMPORTS = 3;

    protected function emailPrefix(): string
    {
        return self::EMAIL_PREFIX;
    }

    /**
     * The three routes this batch retired, with what each answered before it went.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function retiredRoutes(): array
    {
        return [
            '/borrowers was a 500'                  => ['/en/borrowers', 'an index action that does not exist'],
            '/library-imports/{id}/cancel was a 404' => ['/en/library-imports/2/cancel', 'no cancelAction anywhere'],
            'batch-operations was a blank page'     => [
                '/en/libraries/1/batch-operations',
                'its template was the four characters <?php',
            ],
        ];
    }

    #[DataProvider('retiredRoutes')]
    public function testARetiredRouteIsGone(string $path, string $why): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['lib_administrator']);

        $response = $this->get($path, false, $jar);

        $this->assertSame(
            404,
            $response['status'],
            sprintf('%s was retired in batch 11b (%s) and must not come back', $path, $why)
        );
    }

    /**
     * Every page in the batch renders for a library administrator.
     *
     * A blunt check, and the one that would have caught the fatal-200 wedge each of these
     * pages hit at least once while being written: a Twig error throws after the response
     * is assembled, so the failure is HTTP 200 with an empty body. `assertRendersOk` knows
     * that shape.
     *
     * @return array<string, array{0: string}>
     */
    public static function surfacePages(): array
    {
        $paths = [
            'the library itself'   => '/en/libraries/1',
            'its admin menu'       => '/en/libraries/1/admin',
            'its collections'      => '/en/libraries/1/collections',
            'the printable list'   => '/en/libraries/1/book-list',
            'the status JSON'      => '/en/libraries/1/book-list-json',
            'its data problems'    => '/en/libraries/1/data-problems',
            'label management'     => '/en/libraries/1/label-management',
            'the lending form'     => '/en/libraries/1/checkout',
            'check-in'             => '/en/libraries/1/checkin',
            'mass checkout'        => '/en/libraries/1/mass-checkout',
            'bulk inactivation'    => '/en/libraries/1/inactivate-books',
            'the sort diagnostic'  => '/en/libraries/1/sort-debugging',
            'all checkouts'        => '/en/checkouts/library/1',
            'current checkouts'    => '/en/checkouts/library/1/current',
            'overdue checkouts'    => '/en/checkouts/library/1/overdue',
            'one book'             => '/en/books/18370',
            'the imports list'     => '/en/library-imports/library/3',
            'a new import'         => '/en/library-imports/library/3/create',
            'one import'           => '/en/library-imports/2',
        ];

        $cases = [];
        foreach ($paths as $name => $path) {
            $cases[$name] = [$path];
        }

        return $cases;
    }

    #[DataProvider('surfacePages')]
    public function testEveryPortedPageRenders(string $path): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['lib_administrator']);

        $response = $this->get($path, false, $jar);
        $this->assertSame(200, $response['status'], $path . ' should render for a library administrator');
        $this->assertNotSame('', $response['body'], $path . ' rendered an empty body');
    }

    /**
     * The imports list renders for a library that has none.
     *
     * `SionController::indexAction()` reached `getObject()` on a path that throws
     * `InvalidArgumentException: No entity provided.` when the list is empty, so this
     * answered 500 for libraries 1, 4, 5, 6 and 7 — every library except Colegio Mayor.
     * An empty list is not an error.
     */
    public function testTheImportsListRendersForALibraryWithNoImports(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['lib_administrator']);

        $response = $this->get('/en/library-imports/library/' . self::LIBRARY, false, $jar);

        $this->assertSame(200, $response['status'], 'a library with no imports must render an empty list, not a 500');
        $this->assertStringContainsString('Start new import', $response['body']);
    }

    /**
     * A GET of `refresh-sort` renders a confirmation and writes nothing.
     *
     * The contract this batch changed. The laminas action rewrote `sort_text` for every
     * book in the library on a bare GET — 16,383 statements for the largest one — with no
     * token, no confirmation and no per-library check, behind a guard every registered
     * account holds. A link prefetcher was enough to trigger it.
     *
     * Asserted on the *form*, not on the absence of writes: the sweep is idempotent, so a
     * test that checked the data would pass whether or not it ran. What must be true is
     * that a GET does not perform it, and the presence of a submit button is the
     * observable form of that.
     */
    public function testRefreshSortAsksBeforeItWrites(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['lib_administrator']);

        $response = $this->get('/en/libraries/' . self::SMALL_LIBRARY . '/refresh-sort', false, $jar);

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString(
            'name="refresh_sort"',
            $response['body'],
            'a GET must render the confirmation form — see App\Books\RefreshSortForm'
        );
        $this->assertStringNotContainsString(
            'Done.',
            $response['body'],
            'a GET answered as though it had done the work'
        );
    }

    /**
     * The sort diagnostic survives a library whose sort-text format does not parse.
     *
     * Library 7's `SortTextFormat` is `%1{author}{title}`, which expands to three printf
     * parameters against one capture group, and building its filter throws. The laminas
     * page has no guard around that call, so it answers 500 — on precisely the library
     * whose configuration a developer would come here to diagnose.
     */
    public function testTheSortDiagnosticSurvivesABrokenFormat(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['lib_administrator']);

        $response = $this->get('/en/libraries/' . self::SMALL_LIBRARY . '/sort-debugging', false, $jar);

        $this->assertSame(200, $response['status'], 'a diagnostic that dies on a bad configuration cannot diagnose one');
        $this->assertStringContainsString('Sort filter could not be built', $response['body']);
    }

    /**
     * A default account cannot read another library's loans.
     *
     * `route/checkouts/library` names `lib_user`, which registration grants, so the route
     * guard admits every signed-in visitor. What refuses them is the `administrate` check
     * inside the page — the same shape as the borrower page's per-checkout filter, and
     * for the same reason: who has which book is a person's reading.
     */
    public function testADefaultAccountIsRefusedTheCheckoutList(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar);

        $response = $this->get('/en/checkouts/library/' . self::LIBRARY, false, $jar);

        $this->assertNotSame(
            200,
            $response['status'],
            'the route guard is lib_user, a default role — the per-library check is the real gate'
        );
    }

    /** And an administrator can. Without this, "nobody can" would look like "the check works". */
    public function testALibraryAdministratorReachesTheCheckoutList(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['lib_administrator']);

        $response = $this->get('/en/checkouts/library/' . self::LIBRARY, false, $jar);

        $this->assertSame(200, $response['status']);
    }

    /**
     * The imports list is per library, not site-wide.
     *
     * All 22 imports belong to Colegio Mayor. A page that read the table's shared
     * `libraryId` without setting it first would show them under every library — which is
     * what the admin menu's badge did, reading 14 where laminas reads 0.
     */
    public function testTheImportsListShowsOnlyItsOwnLibrarysImports(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['lib_administrator']);

        $withImports = $this->get('/en/library-imports/library/' . self::LIBRARY_WITH_IMPORTS, false, $jar);
        $without     = $this->get('/en/library-imports/library/' . self::LIBRARY, false, $jar);

        $this->assertStringContainsString('Google docs', $withImports['body'], 'library 3 holds every import');
        $this->assertStringNotContainsString(
            'Google docs',
            $without['body'],
            "library 1 has no imports and must not show another library's"
        );
    }
}
