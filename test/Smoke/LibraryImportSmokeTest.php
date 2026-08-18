<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use PHPUnit\Framework\Attributes\DataProvider;

use function html_entity_decode;
use function preg_match;
use function sprintf;
use function str_contains;
use function strlen;
use function substr;

use const ENT_HTML5;
use const ENT_QUOTES;

/**
 * The five routes of the spreadsheet-import surface, and the one that is a download.
 *
 * This is the surface where the failure modes are least visible from a status code, so
 * each test names the shape it is actually asserting:
 *
 *  1. **The template download must be a spreadsheet**, not the login page and not an HTML
 *     error rendered with a 200. It is the only route here that answers with bytes, and
 *     the four-byte `PK\x03\x04` at the front of an `.xlsx` is the cheapest proof it is
 *     one.
 *  2. **The configure page must never open a file this application did not store.** Two
 *     of the fourteen import rows name paths chosen by a person, one of them on somebody's
 *     Windows desktop, and both the page and `bin/console books:import` take an id and
 *     open whatever the row names.
 *  3. **An abandoned import offers no way to run itself.** `database/db8.3.sql` marks
 *     three, two of which would otherwise still retire a third of Colegio Mayor from a
 *     2017 spreadsheet.
 *  4. **The pages are restricted by the per-library check, not the route guard.** Every
 *     guard here names `lib_user`, which is `is_default = 1` — so an ordinary signed-in
 *     account passing the guard and being refused anyway is the assertion worth making.
 */
class LibraryImportSmokeTest extends SmokeTestCase
{
    use MagicLinkSignIn;

    /** Distinct from the other suites' prefixes, or one class's tearDown deletes another's accounts. */
    private const EMAIL_PREFIX = 'libimport-smoke-';

    /** Colegio Mayor holds all fourteen imports; every other library has none. */
    private const LIBRARY = 3;

    /** Bellavista: has no imports at all, which is the case that used to answer 500. */
    private const LIBRARY_WITHOUT_IMPORTS = 1;

    /** Import 14: `C:\Users\Ramon Vergara\Desktop\intento.xlsx`, pending since 2021. */
    private const IMPORT_WITH_A_DESKTOP_PATH = 14;

    /** Import 13: run in 2019, so its configure page is read-only. */
    private const IMPORT_ALREADY_RUN = 13;

    protected function emailPrefix(): string
    {
        return self::EMAIL_PREFIX;
    }

    /** @return array<string, array{0: string}> */
    public static function pages(): array
    {
        return [
            'the list'                => ['/en/library-imports/3'],
            'the list, empty'         => ['/en/library-imports/library/1'],
            'starting a new import'   => ['/en/library-imports/library/3/create'],
            'one import'              => ['/en/library-imports/13'],
            'configuring one'         => ['/en/library-imports/14/edit'],
        ];
    }

    /** @return array<string, array{0: string}> */
    public static function guardedPages(): array
    {
        $pages = self::pages();
        //`/en/library-imports/3` is the *detail* page of import 3, not the list; the list
        //is `/library/{id}`. Both are guarded, so this provider needs no special case —
        //the entry is here to stop the next reader "fixing" the path above.
        $pages['the template download'] = ['/en/library-imports/library/3/template'];
        $pages['the books export']      = ['/en/library-imports/library/3/template?books=1'];

        return $pages;
    }

    #[DataProvider('guardedPages')]
    public function testAnAnonymousVisitorIsSentToSignIn(string $path): void
    {
        $this->assertRequiresLogin($path);
    }

    #[DataProvider('guardedPages')]
    public function testAnOrdinaryAccountIsRefusedByThePerLibraryCheck(string $path): void
    {
        $jar = $this->newCookieJar();
        //No roles beyond the defaults. Registration grants `lib_user`, which is exactly
        //what every guard on this surface names — so the route guard lets this account
        //through and the library's own `administrate` check is what stops it.
        $this->signIn($jar);

        $response = $this->get($path, false, $jar);

        $this->assertContains(
            $response['status'],
            [302, 403],
            sprintf('%s must not render for an account holding only the default roles', $path)
        );
    }

    #[DataProvider('pages')]
    public function testEachPageRendersForALibraryAdministrator(string $path): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['lib_administrator']);

        $response = $this->get($path, false, $jar);

        $this->assertSame(200, $response['status'], sprintf('GET %s should render', $path));
        $this->assertStringContainsString('text/html', $response['contentType'], $path);
        //The fatal-200 wedge: a Twig error under strict_variables throws after the
        //response is assembled, so the failure is 200 with an empty body. Every page on
        //this surface hit it at least once while being written.
        $this->assertGreaterThan(2000, strlen($response['body']), sprintf('%s rendered an empty 200', $path));
        $this->assertStringNotContainsString('Fatal error', $response['body'], $path);
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function downloads(): array
    {
        return [
            'the blank template' => ['/en/library-imports/library/3/template', 'import-template.xlsx'],
            'the books export'   => ['/en/library-imports/library/3/template?books=1', '-books-'],
        ];
    }

    #[DataProvider('downloads')]
    public function testTheDownloadIsAnActualSpreadsheet(string $path, string $filenameFragment): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['lib_administrator']);

        $response = $this->get($path, false, $jar);

        $this->assertSame(200, $response['status'], $path);
        $this->assertStringContainsString(
            'spreadsheetml.sheet',
            $response['contentType'],
            'the download must announce itself as a spreadsheet'
        );
        $this->assertStringContainsString(
            $filenameFragment,
            $response['headers']['content-disposition'] ?? '',
            'the filename says which library, and for an export which day'
        );
        //An .xlsx is a zip. This is the assertion that separates "a spreadsheet" from
        //"an HTML error page served with a 200", which is what a Twig failure would give.
        $this->assertSame("PK\x03\x04", substr($response['body'], 0, 4), 'not a zip, so not an xlsx');
        $this->assertGreaterThan(5000, strlen($response['body']), 'too small to hold two sheets');
    }

    /**
     * The template must be per-library, because its collection dropdown and its
     * instructions are.
     */
    public function testTheTemplateNamesTheLibraryItIsFor(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['lib_administrator']);

        $colegio    = $this->get('/en/library-imports/library/3/template', false, $jar);
        $bellavista = $this->get(
            sprintf('/en/library-imports/library/%d/template', self::LIBRARY_WITHOUT_IMPORTS),
            false,
            $jar
        );

        $this->assertStringContainsString(
            'colegio-mayor',
            $colegio['headers']['content-disposition'] ?? ''
        );
        $this->assertStringContainsString(
            'bellavista',
            $bellavista['headers']['content-disposition'] ?? ''
        );
    }

    /**
     * An import that cannot be run says why, and offers nothing to press.
     *
     * Import 14 is the record of what the old form produced: a librarian typed the path to
     * the file on their own computer, because that is the only path a person has. It is
     * refused for **two** independent reasons and the assertion accepts either, because
     * which one applies depends on data the capsule and production do not share:
     * `database/db8.3.sql` marks the row `abandoned`, and the capsule's database is a
     * production export plus the `zz-db*.sql` migrations only — a `database/*.sql` written
     * here is not applied to it. Pinning one sentence would make this test pass locally
     * and fail after the deploy, or the reverse.
     *
     * What must hold either way is the part that matters: no mapping form and no run
     * button, so nothing on this page can start an import against a file nobody uploaded.
     */
    public function testAnImportThatCannotBeRunSaysSoAndOffersNothing(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['lib_administrator']);

        $response = $this->get(
            sprintf('/en/library-imports/%d/edit', self::IMPORT_WITH_A_DESKTOP_PATH),
            false,
            $jar
        );

        $this->assertSame(200, $response['status']);
        $explained = str_contains($response['body'], 'cannot be read')
            || str_contains($response['body'], 'was abandoned');
        $this->assertTrue($explained, 'the page must say why this import cannot be run');
        $this->assertStringNotContainsString('name="run_import"', $response['body']);
        $this->assertStringNotContainsString('name="import_mapping"', $response['body']);
    }

    public function testAnImportThatHasRunOffersNoWayToRunItAgain(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['lib_administrator']);

        $response = $this->get(
            sprintf('/en/library-imports/%d/edit', self::IMPORT_ALREADY_RUN),
            false,
            $jar
        );

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('already been run', $response['body']);
        $this->assertStringNotContainsString('name="run_import"', $response['body']);
    }

    /**
     * The list links to the template, which is how a library that has never imported
     * anything gets started — five of the six, until 2026.
     */
    public function testTheListOffersTheTemplateEvenWhenThereAreNoImports(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['lib_administrator']);

        $response = $this->get(
            sprintf('/en/library-imports/library/%d', self::LIBRARY_WITHOUT_IMPORTS),
            false,
            $jar
        );

        $this->assertSame(200, $response['status'], 'this answered 500 for five of the six libraries');
        $this->assertSame(
            1,
            preg_match('#/library-imports/library/1/template(?!\?)#', $response['body']),
            'the blank template link'
        );
        $this->assertSame(
            1,
            preg_match('#/library-imports/library/1/template\?books=1#', $response['body']),
            'the export link'
        );
    }

    /**
     * The upload form must carry `enctype`, or the file silently never arrives.
     *
     * `form_open()` emitted no `enctype` until this feature needed one — no form had. A
     * browser would post `application/x-www-form-urlencoded`, PHP would populate no
     * `$_FILES`, and the upload would simply not be there, with no error anywhere.
     */
    public function testTheUploadFormCanActuallyCarryAFile(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['lib_administrator']);

        $response = $this->get('/en/library-imports/library/3/create', false, $jar);

        //Decoded first: `escapeHtmlAttr()` escapes the slash to `&#x2F;`, as it does in
        //the `action` attribute beside it. A browser reads them the same; a substring
        //assertion does not.
        $decoded = html_entity_decode($response['body'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringContainsString('enctype="multipart/form-data"', $decoded);
        $this->assertStringContainsString('type="file"', $response['body']);
        //And no trace of the two free-text fields it replaces.
        $this->assertStringNotContainsString('name="filePath"', $response['body']);
        $this->assertStringNotContainsString('name="worksheet"', $response['body']);
    }
}
