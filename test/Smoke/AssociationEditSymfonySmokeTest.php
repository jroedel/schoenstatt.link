<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use PDO;

/**
 * The first *form* route on the Symfony kernel, measured end to end.
 *
 * Every earlier port answered a GET and rendered. This one accepts a POST, validates
 * it against the same rules the API uses, and writes to the database — so the things
 * that can go wrong are different in kind, and three of them would be invisible to a
 * status-code assertion:
 *
 * - **CSRF spanning both front controllers.** The token is minted by
 *   `SionModel\Validator\Csrf` out of the laminas session, which on a Symfony-served
 *   route exists only because App\Http\SessionListener started it. If that stopped
 *   working the page would still render and every save would fail, which looks like a
 *   validation bug rather than a session one.
 * - **A save that silently drops a field.** `SionTable::updateHelper()` writes only
 *   fields present in the submitted data, so a field the ported template forgot to
 *   render is not an error — it is a field that keeps its old value forever. The
 *   round-trip below therefore changes a value and reads it back out of the database
 *   rather than trusting the redirect.
 * - **A save that clobbers a field nobody touched.** The mirror image, and the one
 *   that actually happened: `eventsJson` was in the input filter specification with
 *   no element on the form, so every submission wrote a null over it. That is fixed
 *   in App\Schoenstatt\Association\AssociationInputFilterSpec and pinned here against
 *   a real HTTP save.
 *
 * Authorization is asserted too, because a ported route gets no BjyAuthorize guard and
 * `route/association-edit` is the first *write* surface to depend on
 * App\Authorization\RouteGuard instead.
 *
 * Fixtures: the test edits association 319 — `SL100319A`, the Original Schoenstatt
 * Shrine — and restores every column it touched in tearDown, so the imported data is left
 * as it was found.
 */
class AssociationEditSymfonySmokeTest extends SmokeTestCase
{
    use MagicLinkSignIn;
    use FormRoundTrip;

    private const EMAIL_PREFIX = 'association-edit-smoke-';

    /** The Original Schoenstatt Shrine: association 319, identifier SL100319A. */
    private const ASSOCIATION_ID = 319;
    private const SW_ID = 'SL100319A';
    private const PATH = '/en/' . self::SW_ID . '/edit';

    protected function table(): string
    {
        return 'sch_associations';
    }

    /** @return array{0: string, 1: int} */
    protected function row(): array
    {
        return ['AssociationId', self::ASSOCIATION_ID];
    }

    protected function formXPath(): string
    {
        return '//form[@id="edit_association"]';
    }

    protected function emailPrefix(): string
    {
        return self::EMAIL_PREFIX;
    }

    protected function tearDown(): void
    {
        $this->restoreAssociation();
        $this->purgeMail();
        $this->purgeAccounts();

        parent::tearDown();
    }

    // ------------------------------------------------------------ who may reach it

    public function testAnonymousVisitorIsSentToSignIn(): void
    {
        $response = $this->get(self::PATH);

        $this->assertSame(302, $response['status']);
        $this->assertStringEndsWith('/en/user/login?redirect=' . self::PATH, $response['redirect']);
        $this->assertStringNotContainsString('edit_association', $response['body']);
    }

    /**
     * Registration grants `sch_user`, and `route/association-edit` names it, so an
     * ordinary account gets in. That is the *existing* rule — this test records it
     * rather than endorsing it, and it is the ceiling docs/api-v3.md refers to when it
     * says a bot must not simply reuse `sch_user`.
     */
    public function testAnOrdinaryAccountReachesTheForm(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar);

        $response = $this->get(self::PATH, false, $jar);

        $this->assertSame(200, $response['status']);
        $this->assertStringContainsString('id="edit_association"', $response['body']);
        $this->assertStringContainsString('name="associationId" value="319"', $response['body']);
    }

    /**
     * Symfony served it, rather than the catch-all handing it to laminas. The
     * discriminator is ShrinesSymfonySmokeTest's: laminas sends
     * `Set-Cookie: slm_locale=en_US` on every response and a ported route never does.
     */
    public function testSymfonyServedIt(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar);

        $response = $this->get(self::PATH, false, $jar);

        $this->assertSame(200, $response['status']);
        $this->assertStringNotContainsString(
            'slm_locale',
            $response['headers']['set-cookie'] ?? '',
            'the laminas locale cookie means the legacy bridge answered, so this route is not ported'
        );
    }

    /** The unprefixed form redirects to the negotiated language, as SlmLocale would. */
    public function testTheUnprefixedPathRedirects(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar);

        $response = $this->get('/' . self::SW_ID . '/edit', false, $jar);

        $this->assertSame(302, $response['status']);
        $this->assertStringEndsWith(self::PATH, $response['redirect']);
    }

    /**
     * A publication id must keep falling through to laminas: `/SL200001L/edit` is
     * still a laminas route, and a `sw_id` constraint that matched it would 404 a
     * working page.
     */
    public function testAPublicationIdIsNotSwallowed(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['sch_administrator']);

        $response = $this->get('/en/SL200001L/edit', false, $jar);

        $this->assertNotSame(404, $response['status'], '/en/SL200001L/edit must still reach laminas');
    }

    // -------------------------------------------------------------- saving

    /**
     * The round trip: GET the form, change one field, POST it, follow the redirect,
     * and read the value back out of the database.
     */
    public function testAModeratorCanSaveAChange(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['sch_moderator']);

        $this->rememberAssociation();
        $marker = 'Smoke test ' . time();

        $post = $this->submit($jar, ['openingHoursHuman' => $marker]);

        $this->assertSame(302, $post['status'], 'a valid submission redirects to the association page');
        $this->assertStringContainsString('/' . self::SW_ID, $post['redirect']);
        $this->assertSame($marker, $this->column('OpeningHoursHuman'), 'the change did not reach the database');
    }

    /**
     * The bug that made this worth pinning: `eventsJson` had a specification entry and
     * no element, so `getData()` contributed a null and `updateHelper()` wrote it over
     * whatever was stored. Four associations hold an `EventsJson`; this asserts a save
     * leaves one of them alone.
     */
    public function testSavingDoesNotEraseFieldsTheFormDoesNotRender(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['sch_moderator']);

        $this->rememberAssociation();
        $this->pdo()->exec(sprintf(
            "UPDATE sch_associations SET EventsJson = '%s' WHERE AssociationId = %d",
            '{\"@type\":\"Event\"}',
            self::ASSOCIATION_ID
        ));

        $post = $this->submit($jar, ['openingHoursHuman' => 'untouched-events-json']);

        $this->assertSame(302, $post['status']);
        $this->assertSame(
            '{"@type":"Event"}',
            $this->column('EventsJson'),
            'a field the form does not render was overwritten by saving the form'
        );
    }

    /**
     * An invalid submission re-renders the form with the message rather than saving —
     * and the message is the one the shared specification produces, which is what
     * `test/Integration/AssociationValidationParityTest` compares the API against.
     */
    public function testAnInvalidSubmissionIsRefusedAndNothingIsWritten(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['sch_moderator']);

        $this->rememberAssociation();
        $before = $this->column('Kind');

        $post = $this->submit($jar, ['kind' => 'not-a-real-kind']);

        $this->assertSame(200, $post['status'], 'an invalid submission re-renders rather than redirecting');
        $this->assertStringContainsString('id="edit_association"', $post['body']);
        $this->assertStringContainsString('was not found in the haystack', $post['body']);
        $this->assertSame($before, $this->column('Kind'), 'a refused submission must write nothing');
    }

    /** No token, no save — the CSRF element is live on the ported route too. */
    public function testASubmissionWithoutACsrfTokenIsRefused(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['sch_moderator']);

        $this->rememberAssociation();
        $fields = $this->fields($this->get(self::PATH, false, $jar)['body']);
        $fields['security'] = 'not-a-valid-token';
        $fields['openingHoursHuman'] = 'should-never-be-stored';

        $post = $this->request('POST', self::PATH, [], false, $jar, $fields);

        $this->assertSame(200, $post['status']);
        $this->assertNotSame('should-never-be-stored', $this->column('OpeningHoursHuman'));
    }

    // ------------------------------------------------------------------ helpers

    /**
     * GET the form, apply the given overrides to the fields it rendered, and POST it
     * back. Submitting the *whole* form is the point: a partial POST would drop every
     * field it omitted, which is what a browser never does and what would make these
     * assertions meaningless.
     *
     * @param array<string, string> $overrides
     * @return array{status: int, redirect: string, body: string, contentType: string,
     *               headers: array<string, string>}
     */
    /** @param array<string, string> $overrides */
    private function submit(string $jar, array $overrides): array
    {
        return $this->submitForm(self::PATH, $jar, $overrides);
    }

    /** The columns a save here can touch, so tearDown can put them back. */
    private const RESTORED_COLUMNS = [
        'OpeningHoursHuman',
        'EventsJson',
        'Kind',
        'AssociationName',
        'PublicNotes',
        'UpdatedOn',
        'UpdatedBy',
    ];

    private function rememberAssociation(): void
    {
        $this->remember(self::RESTORED_COLUMNS);
    }

    private function restoreAssociation(): void
    {
        $this->restore();
    }
}
