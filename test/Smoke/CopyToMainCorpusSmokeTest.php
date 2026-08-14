<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

/**
 * `publication-copy-to-main-corpus`: who may see the button, and what a GET is allowed to do.
 *
 * Both properties asserted here came out of production's exception store on 2026-08-14,
 * and neither would fail any test that existed before this one.
 *
 * ## Why the button's visibility is worth a test
 *
 * The route accumulated **3,439 `UnAuthorizedException`s in eleven days** — the
 * next-noisiest fingerprint had 64 — because both front controllers rendered the button
 * to *every* visitor on *every* data-sourced publication. There are ~4,165 such rows and
 * five locales, so a `pub_moderator`-only action was advertised across roughly twenty
 * thousand public URLs, and crawlers dutifully walked them. Every hit paid a full
 * bootstrap and ACL load to answer with a redirect to a login form.
 *
 * That is a *cost* bug rather than an access bug — the guard always held — which is
 * exactly why it survived: nothing was broken enough to fail. So the assertion is
 * differential. It is not enough to check that an anonymous visitor lacks the button,
 * because a page that lost the button for everyone would pass that. Both halves run
 * against the same publication: absent for anonymous, **present** for a moderator.
 *
 * ## Why a GET must write nothing
 *
 * `copyToMainCorpusAction()` called `copyPublicationToMainCorpus()` — an INSERT — directly
 * on the GET, with no CSRF token and no confirmation. Nine effective roles hold
 * `pub_moderator`, and browsers prefetch links a signed-in moderator has merely hovered
 * over, so duplicating a publication took no mistake anybody could observe. The row count
 * is taken before and after the GET, because "the confirmation page rendered" and "the
 * GET wrote nothing" are different claims and only the second one is the fix.
 */
class CopyToMainCorpusSmokeTest extends SmokeTestCase
{
    use MagicLinkSignIn;

    /**
     * A data-sourced publication that has **not been merged** — the two conditions the
     * button's branch tests, and both have to hold or the test proves nothing.
     *
     * Choosing this fixture is where two earlier attempts went wrong, and the trap is worth
     * recording. Publications 1 and 1726 are both data-sourced and both **merged** (into
     * 10251 and 10254), and a merged row takes the `{% if merged_into_url %}` branch — so
     * nobody is ever offered the button on it. The symptom is a moderator not seeing the
     * button, which reads exactly like a broken permission check, and it cost a detour
     * through the ACL, the view-helper plumbing and the Twig extension before anyone
     * printed the entity array.
     *
     * Only **2,331 of the ~4,165** data-sourced publications are unmerged, so a fixture
     * picked by data source alone is more likely wrong than right.
     * `testTheFixtureStillQualifies` therefore asserts both halves against the rendered
     * page and **fails** rather than skips: a fixture that quietly stops matching turns
     * every visibility assertion below into a vacuous pass.
     */
    private const SW_ID = 'SL201727L';

    /**
     * The **other** rendering of the same button, and the reason this is a two-place
     * contract rather than one fix.
     *
     * `/en/books/{id}` is still served by `Books\Controller\BooksController` through the
     * LegacyBridge, and `books/show.phtml` includes the same
     * `books/publications/publication-info` partial that the Twig template replaced for
     * the publication page. So the `.phtml` copy of the visibility check is live traffic
     * today, not just the `sl_symfony_canary=0` rollback path — which is what makes it
     * assertable here instead of only against production.
     *
     * Book 48215 hangs off publication 1727 — the same row SW_ID names.
     */
    private const BOOK_ID = 48215;

    /**
     * Publication 1727's data source. Both constants above name the **same publication** —
     * SL201727L is publication 1727, and book 48215 hangs off it — which is deliberate: the
     * two renderings are then compared on identical data, so a difference between them is a
     * difference in the templates and cannot be a difference in the fixture.
     */
    private const DATA_SOURCE = 'b_bibprim_edition';

    private const BUTTON = 'Copy into main corpus';

    protected function emailPrefix(): string
    {
        return 'copy-corpus-';
    }

    protected function tearDown(): void
    {
        $this->purgeAccounts();
        $this->purgeMail();
        parent::tearDown();
    }

    public function testTheFixtureStillQualifies(): void
    {
        $response = $this->assertRendersOk('/en/' . self::SW_ID);

        //Both halves of the branch, asserted against the page rather than the table. The
        //rendering is `{% if merged_into_url %}…{% elseif copy_to_corpus_url %}`, so a
        //merged publication never reaches the button no matter who is asking, and a
        //publication with no data source has nothing to copy.
        $this->assertStringContainsString(
            self::DATA_SOURCE,
            $response['body'],
            self::SW_ID . ' must still be a data-sourced publication for this test to mean anything'
        );
        $this->assertStringNotContainsString(
            'Merged into',
            $response['body'],
            self::SW_ID . ' must not have been merged — a merged row takes the other branch, '
                . 'and the visibility assertions below would then pass for the wrong reason'
        );
    }

    public function testAnAnonymousVisitorIsNotOfferedTheButton(): void
    {
        $response = $this->assertRendersOk('/en/' . self::SW_ID);

        $this->assertStringNotContainsString(
            self::BUTTON,
            $response['body'],
            'the copy-to-main-corpus button is pub_moderator-only and must not be advertised '
                . 'to anonymous visitors — 3,439 crawler hits in eleven days is what that cost'
        );
    }

    public function testAModeratorIsOfferedTheButton(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['pub_moderator']);

        $response = $this->get('/en/' . self::SW_ID, false, $jar);

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString(
            self::BUTTON,
            $response['body'],
            'a pub_moderator must still be offered the button — otherwise the visibility fix '
                . 'removed the feature rather than scoping it'
        );
        $this->assertStringContainsString(
            self::SW_ID . '/copy-to-main-corpus',
            $response['body'],
            'and the button must still point at the route'
        );
    }

    /**
     * The same two assertions against the `.phtml` rendering.
     *
     * Both front controllers must agree, and this is the half that would rot silently:
     * the Twig template is what most traffic sees, so a `.phtml` that kept the unguarded
     * button would go on filing crawler exceptions from `/en/books/*` while the
     * publication page looked fixed.
     */
    public function testThePhtmlRenderingAgreesWithTheTwigOne(): void
    {
        $path = '/en/books/' . self::BOOK_ID;

        $anonymous = $this->assertRendersOk($path);
        $this->assertStringContainsString(
            self::DATA_SOURCE,
            $anonymous['body'],
            'book ' . self::BOOK_ID . ' must still hang off a data-sourced publication'
        );
        $this->assertStringNotContainsString(
            self::BUTTON,
            $anonymous['body'],
            'the .phtml partial must hide the button from anonymous visitors too'
        );

        $jar = $this->newCookieJar();
        $this->signIn($jar, ['pub_moderator']);
        $moderator = $this->get($path, false, $jar);

        $this->assertSame(200, $moderator['status']);
        $this->assertStringContainsString(
            self::BUTTON,
            $moderator['body'],
            'and must still show it to a pub_moderator — otherwise the .phtml lost the feature'
        );
    }

    public function testAGetRendersAConfirmationAndWritesNothing(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['pub_moderator']);

        $before = $this->publicationCount();
        $response = $this->get('/en/' . self::SW_ID . '/copy-to-main-corpus', false, $jar);
        $after = $this->publicationCount();

        $this->assertSame(200, $response['status'], 'the confirmation page should render');
        $this->assertSame(
            $before,
            $after,
            'a GET to copy-to-main-corpus must not insert a publication — it did until 2026-08-14, '
                . 'which made a link prefetch enough to duplicate a record'
        );

        //A CSRF field, not merely a form tag: the point of the confirmation is that the
        //POST carries a token, and a form rendered without one would still look right.
        $this->assertStringContainsString('name="security"', $response['body']);
        $this->assertStringContainsString('type="hidden"', $response['body']);
        $this->assertMatchesRegularExpression(
            '/<form[^>]+method="post"/i',
            $response['body'],
            'the confirmation form must POST'
        );
    }

    public function testAPostWithTheRenderedTokenPerformsTheCopy(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['pub_moderator']);

        $path = '/en/' . self::SW_ID . '/copy-to-main-corpus';
        $form = $this->get($path, false, $jar);
        $this->assertSame(200, $form['status']);

        $this->assertSame(
            1,
            preg_match('/name="security"[^>]*value="([^"]+)"/', $form['body'], $m),
            'could not read the CSRF token out of the confirmation form'
        );

        $before = $this->publicationCount();
        $posted = $this->request('POST', $path, [], false, $jar, [
            'security' => $m[1],
            'submit'   => 'Copy into main corpus',
        ]);
        $after = $this->publicationCount();

        $this->assertSame(
            302,
            $posted['status'],
            'a valid POST should redirect to the new publication (got ' . $posted['status'] . ')'
        );
        $this->assertSame(
            $before + 1,
            $after,
            'a valid POST must actually perform the copy — the confirmation step must not have '
                . 'turned a working feature into a dead end'
        );

        $this->undoTheCopy($before);
    }

    public function testAPostWithoutAValidTokenCopiesNothing(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['pub_moderator']);

        $before = $this->publicationCount();
        $posted = $this->request('POST', '/en/' . self::SW_ID . '/copy-to-main-corpus', [], false, $jar, [
            'security' => 'not-a-real-token',
            'submit'   => 'Copy into main corpus',
        ]);
        $after = $this->publicationCount();

        $this->assertSame(
            $before,
            $after,
            'a forged or expired token must copy nothing'
        );
        $this->assertSame(
            400,
            $posted['status'],
            'an invalid confirmation should re-render the form, not redirect as though it worked'
        );

        if ($after !== $before) {
            $this->undoTheCopy($before);
        }
    }

    private function publicationCount(): int
    {
        return (int) $this->pdo()->query('SELECT COUNT(*) FROM sch_publications')->fetchColumn();
    }

    /**
     * Undo the copy — **both halves of it.**
     *
     * `PublicationsTable::copyPublicationToMainCorpus()` does two writes, not one: it
     * inserts the duplicate *and* then `updateEntity()`s the source row's
     * `mergedIntoPublicationId` to point at it. A cleanup that only deletes the insert
     * therefore leaves the fixture **marked as merged, pointing at a row that no longer
     * exists** — and a merged publication takes the other branch of the template, so it
     * stops offering the button to anybody.
     *
     * That is not a hypothetical. The first three fixtures this test used (publications 1,
     * 1726 and 1727) were each unmerged when chosen and merged by the run that used them,
     * which made `testAModeratorIsOfferedTheButton` pass once and fail on every subsequent
     * run against the same capsule. The failure looks exactly like a broken permission
     * check, and the test that caused it is the last place anyone looks.
     *
     * The insert is keyed on being above every pre-existing id rather than on the id in the
     * redirect's `Location`: parsing that out would make cleanup depend on the assertion
     * under test still passing, and a test that leaks when it fails poisons every later run.
     */
    private function undoTheCopy(int $countBefore): void
    {
        $pdo = $this->pdo();
        $keep = (int) $pdo->query(
            'SELECT PublicationId FROM sch_publications ORDER BY PublicationId ASC LIMIT 1 OFFSET '
                . ($countBefore - 1)
        )->fetchColumn();

        $pdo->prepare('DELETE FROM sch_publications WHERE PublicationId > :keep')
            ->execute(['keep' => $keep]);

        //Unconditional, and keyed on the dangling pointer rather than on this test's own
        //fixture: if an earlier run left one behind, this repairs that too.
        $pdo->exec(
            'UPDATE sch_publications p
                LEFT JOIN sch_publications t ON t.PublicationId = p.MergedIntoPublicationId
                SET p.MergedIntoPublicationId = NULL
              WHERE p.MergedIntoPublicationId IS NOT NULL AND t.PublicationId IS NULL'
        );
    }

    /**
     * A merge pointer aimed at a row that does not exist is either this test failing to
     * clean up or the copy feature half-completing in production. Either way it is worth
     * knowing about, and asserting it here costs one query.
     */
    public function testNoPublicationPointsAtAMergeTargetThatDoesNotExist(): void
    {
        $dangling = (int) $this->pdo()->query(
            'SELECT COUNT(*) FROM sch_publications p
                LEFT JOIN sch_publications t ON t.PublicationId = p.MergedIntoPublicationId
              WHERE p.MergedIntoPublicationId IS NOT NULL AND t.PublicationId IS NULL'
        )->fetchColumn();

        $this->assertSame(
            0,
            $dangling,
            'a publication is marked merged into a row that does not exist — most likely a '
                . 'copy-to-main-corpus test that deleted the duplicate without clearing the pointer'
        );
    }
}
