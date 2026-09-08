<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use App\View\ServingNote;
use PHPUnit\Framework\Attributes\DataProvider;

//the smoke suite does not autoload by default; ServingNote's constants are the strings
//the serving note is asserted on, and restating them here would let the two drift
require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Batch 16: the last four publication routes — `publications/export`,
 * `publications/prime-authors`, `publication-create-new-edition` and
 * `publication-copy-to-main-corpus` — served by Symfony.
 *
 * `CopyToMainCorpusSmokeTest` already drives the copy action end to end (GET writes
 * nothing, POST with the token copies, POST without does not) and runs unchanged against
 * the ported route; this file does not repeat it. What it adds is the other three routes
 * and, for all four, the three outcomes docs/strangler.md asks of a restricted port:
 * anonymous, signed in without the role, signed in with it. A status-code-only test would
 * pass against a guard that never ran, so each 200 also reads the serving note to prove
 * the page came from a Twig template and not from the bridge.
 *
 * ## `create-new-edition` gets the write tests, because the write is what changed
 *
 * Until this batch the action created the new edition **on the GET**. So the assertions
 * mirror `CopyToMainCorpusSmokeTest`'s: the publication count is taken before and after
 * the GET, because "the confirmation rendered" and "the GET wrote nothing" are different
 * claims and only the second is the fix; then a POST carrying the rendered token must
 * create exactly one row and redirect to its edit form, and a POST without one must
 * create nothing and answer 400.
 *
 * ## The fixture
 *
 * SL202186L is publication 2186, `PublicationEditSymfonySmokeTest`'s fixture: a real,
 * unmerged, first-class publication with authors set. It does not need to be
 * data-sourced — that is the copy action's precondition, not this one's.
 *
 * ## Cleanup
 *
 * The new edition is removed by id range rather than by parsing the redirect, for the
 * reason `CopyToMainCorpusSmokeTest::undoTheCopy()` gives: cleanup must not depend on the
 * assertion under test passing. Unlike the copy, a new edition leaves no pointer on the
 * source row to repair — `createNewEdition()` writes one row and nothing else.
 */
class PublicationActionsSymfonySmokeTest extends SmokeTestCase
{
    use MagicLinkSignIn;

    private const SW_ID = 'SL202186L';

    private const NEW_EDITION_PATH = '/en/' . self::SW_ID . '/create-new-edition';
    private const COPY_PATH        = '/en/' . self::SW_ID . '/copy-to-main-corpus';
    private const EXPORT_PATH      = '/en/literature/export';
    private const AUTHORS_PATH     = '/en/literature/prime-authors';

    protected function emailPrefix(): string
    {
        return 'pub-actions-';
    }

    protected function tearDown(): void
    {
        $this->purgeAccounts();
        $this->purgeMail();
        parent::tearDown();
    }

    /** @return array<string, array{string}> */
    public static function paths(): array
    {
        return [
            'export'        => [self::EXPORT_PATH],
            'prime authors' => [self::AUTHORS_PATH],
            'new edition'   => [self::NEW_EDITION_PATH],
            'copy'          => [self::COPY_PATH],
        ];
    }

    #[DataProvider('paths')]
    public function testAnAnonymousVisitorIsSentToSignIn(string $path): void
    {
        $response = $this->get($path);

        $this->assertSame(302, $response['status'], "$path anonymously");
        $this->assertStringContainsString('/en/user/login', $response['redirect']);
        $this->assertStringContainsString('redirect=' . $path, $response['redirect']);
    }

    /**
     * Signed in is not privileged: registration grants `pub_user`, which none of the four
     * guards name. Three answer to `pub_moderator` and `prime-authors` to
     * `pub_administrator` alone.
     */
    #[DataProvider('paths')]
    public function testASignedInVisitorWithoutTheRoleIsForbidden(string $path): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar);

        $this->assertSame(403, $this->get($path, false, $jar)['status'], "$path without the role");
    }

    /**
     * And `pub_moderator` is not `pub_administrator`: the differential half of the
     * prime-authors guard, which would pass a test that only checked the administrator.
     */
    public function testAModeratorIsForbiddenThePrimeAuthorsReport(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['pub_moderator']);

        $this->assertSame(403, $this->get(self::AUTHORS_PATH, false, $jar)['status']);
    }

    public function testTheExportListsTheCorpusForAModerator(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['pub_moderator']);

        $response = $this->get(self::EXPORT_PATH, false, $jar);

        $this->assertSame(200, $response['status']);
        $this->assertServedByTwig($response['body'], self::EXPORT_PATH);

        //The four columns export.phtml asks the partial for, in its order.
        $this->assertMatchesRegularExpression(
            '#<th>Publication Id</th>\s*<th>Authors</th>\s*<th>Title</th>\s*<th>Edition</th>#',
            $response['body']
        );
        //The whole corpus, not a page of it: the fixture is somewhere in the middle of
        //10,166 rows and the count is a floor rather than an exact figure so that the
        //copy test's leftovers, or a new publication, do not fail this.
        $this->assertStringContainsString('/en/' . self::SW_ID . '/', $response['body']);
        $this->assertGreaterThan(
            9000,
            substr_count($response['body'], '<tr>'),
            'the export should list the entire corpus'
        );
    }

    public function testThePrimeAuthorsReportRendersForAnAdministrator(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['pub_administrator']);

        $response = $this->get(self::AUTHORS_PATH, false, $jar);

        $this->assertSame(200, $response['status']);
        $this->assertServedByTwig($response['body'], self::AUTHORS_PATH);
        $this->assertMatchesRegularExpression('#<th>Author</th>\s*<th>Publications</th>#', $response['body']);
        //Kentenich is the one author every corpus of Schoenstatt literature has.
        $this->assertStringContainsString('Kentenich', $response['body']);
    }

    public function testTheCopyConfirmationIsServedByTwig(): void
    {
        //CopyToMainCorpusSmokeTest fixes the fixture's data source; this only asks which
        //front controller rendered the confirmation, which that file predates.
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['pub_moderator']);

        $response = $this->get('/en/SL201727L/copy-to-main-corpus', false, $jar);

        $this->assertSame(200, $response['status']);
        $this->assertServedByTwig($response['body'], 'copy-to-main-corpus');
    }

    public function testAGetRendersTheNewEditionConfirmationAndWritesNothing(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['pub_moderator']);

        $before   = $this->publicationCount();
        $response = $this->get(self::NEW_EDITION_PATH, false, $jar);
        $after    = $this->publicationCount();

        $this->assertSame(200, $response['status'], 'the confirmation page should render');
        $this->assertSame(
            $before,
            $after,
            'a GET to create-new-edition must not insert a publication — it did until 2026-09-08, '
                . 'which made a link prefetch enough to create one'
        );
        $this->assertServedByTwig($response['body'], self::NEW_EDITION_PATH);

        $this->assertStringContainsString('name="security"', $response['body']);
        $this->assertMatchesRegularExpression('/<form[^>]+method="post"/i', $response['body']);
        //Cancel is a link back to the publication, never a form element.
        $this->assertMatchesRegularExpression(
            '#<a href="/en/' . self::SW_ID . '[^"]*" class="btn btn-default">Cancel</a>#',
            $response['body']
        );
    }

    public function testAPostWithTheRenderedTokenCreatesTheEditionAndOpensItForEditing(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['pub_moderator']);

        $form = $this->get(self::NEW_EDITION_PATH, false, $jar);
        $this->assertSame(200, $form['status']);
        $this->assertSame(
            1,
            preg_match('/name="security"[^>]*value="([^"]+)"/', $form['body'], $m),
            'could not read the CSRF token out of the confirmation form'
        );

        $before = $this->publicationCount();
        $posted = $this->request('POST', self::NEW_EDITION_PATH, [], false, $jar, [
            'security' => $m[1],
            'submit'   => 'Add another edition',
        ]);
        $after = $this->publicationCount();

        try {
            $this->assertSame(302, $posted['status'], 'a valid POST should redirect to the new edition');
            $this->assertMatchesRegularExpression(
                '#/en/SL2[0-9]{5}L/edit$#',
                $posted['redirect'],
                'and the redirect should open the new edition for editing, as the laminas action did'
            );
            $this->assertStringNotContainsString(
                self::SW_ID,
                $posted['redirect'],
                'the redirect must name the new row, not the one it was copied from'
            );
            $this->assertSame($before + 1, $after, 'a valid POST must create exactly one publication');
        } finally {
            $this->undoTheCreate($before);
        }
    }

    public function testAPostWithoutAValidTokenCreatesNothing(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['pub_moderator']);

        $before = $this->publicationCount();
        $posted = $this->request('POST', self::NEW_EDITION_PATH, [], false, $jar, [
            'security' => 'not-a-real-token',
            'submit'   => 'Add another edition',
        ]);
        $after = $this->publicationCount();

        try {
            $this->assertSame($before, $after, 'a forged or expired token must create nothing');
            $this->assertSame(
                400,
                $posted['status'],
                'an invalid confirmation should re-render the form, not redirect as though it worked'
            );
            $this->assertStringContainsString('Your confirmation expired', $posted['body']);
        } finally {
            $this->undoTheCreate($before);
        }
    }

    private function assertServedByTwig(string $body, string $what): void
    {
        $found = preg_match(
            '#<p class="[^"]*' . preg_quote(ServingNote::CSS_CLASS, '#') . '[^"]*">(.*?)</p>#s',
            $body,
            $matches
        );
        $this->assertSame(1, $found, "$what should carry a serving note");
        $this->assertStringContainsString(ServingNote::RENDERER_TWIG, $matches[1], "$what should be a Twig rendering");
        $this->assertStringNotContainsString('LegacyBridge', $matches[1], "$what must not have been bridged");
    }

    private function publicationCount(): int
    {
        return (int) $this->pdo()->query('SELECT COUNT(*) FROM sch_publications')->fetchColumn();
    }

    /**
     * Delete whatever the POST created: every row above the highest id that existed
     * before it. Keyed on the count taken before the request, not on the redirect, so
     * that a failing assertion still cleans up. No-op when nothing was created.
     */
    private function undoTheCreate(int $countBefore): void
    {
        $pdo  = $this->pdo();
        $keep = (int) $pdo->query(
            'SELECT PublicationId FROM sch_publications ORDER BY PublicationId ASC LIMIT 1 OFFSET '
                . ($countBefore - 1)
        )->fetchColumn();

        $pdo->prepare('DELETE FROM sch_publications WHERE PublicationId > :keep')->execute(['keep' => $keep]);
    }
}
