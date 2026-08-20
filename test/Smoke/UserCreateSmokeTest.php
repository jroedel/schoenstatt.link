<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use PDO;

/**
 * An administrator creates an account and sees it in the list.
 *
 * This had no coverage, which is how 2026-08-09 happened: the account was
 * created, the row was in the database, and the list went on showing the world
 * without it. The cause was in the cache layer and is pinned properly in
 * test/Unit/SionCacheDependencyMapTest — a serial suite against a freshly
 * started capsule cannot reproduce an expired dependency map. What this file
 * pins is the part that suite cannot reach: that the whole path works at all,
 * end to end, through the real form, the real ACL and the real cache.
 *
 * Two failures it would have caught on its own, both found while tracking that
 * incident down:
 *
 *  - a POST missing a checkbox that carries no hidden element sends `null` into
 *    a NOT NULL column, and the controller reported the resulting SQLSTATE as
 *    "Error in form submission, please review." with nothing in the log;
 *  - the roles select is keyed on `user_role.id`, not on the role name, so a
 *    caller that posts the name gets "The input was not found in the haystack"
 *    against a field it thought it had filled in.
 */
class UserCreateSmokeTest extends SmokeTestCase
{
    use MagicLinkSignIn;

    /** Distinct from every other class's, or one tearDown deletes another's accounts mid-run. */
    private const EMAIL_PREFIX = 'user-create-smoke-';

    /** @var string[] usernames created by a test, removed in tearDown */
    private $createdUsernames = [];

    protected function emailPrefix(): string
    {
        return self::EMAIL_PREFIX;
    }

    protected function tearDown(): void
    {
        foreach ($this->createdUsernames as $username) {
            $statement = $this->pdo()->prepare('DELETE FROM user WHERE username = ?');
            $statement->execute([$username]);
        }
        $this->createdUsernames = [];

        $this->purgeMail();
        $this->purgeAccounts();

        parent::tearDown();
    }

    public function testAnAdministratorCreatesAnAccountAndSeesItListed(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['administrator']);

        $username = $this->uniqueUsername();
        $created = $this->submitCreateForm($jar, $username, ['active' => '1', 'emailVerified' => '1']);

        $this->assertSame(302, $created['status'], 'a valid submission should redirect to the list');
        $this->assertNotNull($this->userIdOf($username), 'the row should be in the database');

        $list = $this->get('/en/users', false, $jar);
        $this->assertSame(200, $list['status']);
        $this->assertStringContainsString(
            $username,
            $list['body'],
            'an account that exists must be listed on the very next request'
        );
    }

    /**
     * The bot accounts this screen exists for are created with every checkbox
     * clear: no password to change, no mailbox to verify. Those checkboxes carry
     * no hidden element, so an unticked one is simply absent from the POST —
     * which is what used to reach a NOT NULL column as null.
     */
    public function testAnAccountCreatesWithEveryOptionalBoxUnticked(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['administrator']);

        $username = $this->uniqueUsername();
        $created = $this->submitCreateForm($jar, $username, []);

        $this->assertSame(302, $created['status']);
        $this->assertNotNull($this->userIdOf($username));
    }

    /**
     * The case that actually broke: a POST carrying none of the four checkboxes
     * at all.
     *
     * A browser never does this — isMultiPersonUser renders a hidden element, so
     * something is always posted for it — which is exactly why nothing caught it.
     * Anything that is not a browser (a script, a future template that drops the
     * hidden input, an agent) sent null into a NOT NULL column, and the SQLSTATE
     * came back to the admin as "Error in form submission, please review." with
     * nothing written to the log.
     *
     * The column that actually broke was `must_change_password`, which no longer
     * exists (db8.5). `multi_person_user` is the same shape and still does, which is
     * why this test is still the right test.
     */
    public function testAnAccountCreatesWhenNoCheckboxIsPostedAtAll(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['administrator']);

        $form = $this->get('/en/users/create', false, $jar);
        $this->assertSame(200, $form['status']);

        $username = $this->uniqueUsername();
        $created = $this->request('POST', '/en/users/create', [], false, $jar, [
            'username'    => $username,
            'email'       => $username . '@example.org',
            'displayName' => 'Smoke ' . $username,
            'rolesList'   => [(string) $this->roleId('sch_api_bot')],
            'personId'    => '',
            'security'    => $this->extractCsrfToken($form['body']),
            'submit'      => 'Submit',
        ]);

        $this->assertSame(302, $created['status'], 'an absent checkbox means unchecked, not missing');
        $this->assertNotNull($this->userIdOf($username));
    }

    /** The account is created holding the role that was selected, not the default set. */
    public function testTheSelectedRoleIsLinkedToTheNewAccount(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['administrator']);

        $username = $this->uniqueUsername();
        $this->submitCreateForm($jar, $username, ['active' => '1']);

        $userId = $this->userIdOf($username);
        $this->assertNotNull($userId);

        $statement = $this->pdo()->prepare(
            'SELECT r.role_id FROM user_role_linker l JOIN user_role r ON r.id = l.role_id WHERE l.user_id = ?'
        );
        $statement->execute([$userId]);
        $roles = $statement->fetchAll(PDO::FETCH_COLUMN);

        $this->assertContains('sch_api_bot', $roles);
    }

    /**
     * A rejected submission re-renders the form rather than redirecting, and says
     * so — the account must not exist afterwards.
     */
    public function testADuplicateUsernameIsRefusedWithoutCreatingAnything(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['administrator']);

        $username = $this->uniqueUsername();
        $this->submitCreateForm($jar, $username, ['active' => '1']);
        $this->assertNotNull($this->userIdOf($username));

        $again = $this->submitCreateForm($jar, $username, ['active' => '1'], 'second-');

        $this->assertSame(200, $again['status'], 'a refused submission re-renders the form');
        $this->assertStringContainsString('already exists in database', $again['body']);
    }

    // ------------------------------------------------------------------ helpers

    private function uniqueUsername(): string
    {
        $username = 'smokebot' . time() . random_int(1000, 9999);
        $this->createdUsernames[] = $username;

        return $username;
    }

    /**
     * @param array<string, string> $extra the checkboxes a browser would only
     *        send when ticked
     * @return array{status: int, redirect: string, body: string, contentType: string,
     *               headers: array<string, string>}
     */
    private function submitCreateForm(string $jar, string $username, array $extra, string $emailPrefix = ''): array
    {
        $form = $this->get('/en/users/create', false, $jar);
        $this->assertSame(200, $form['status'], 'the create form should render for an administrator');

        return $this->request('POST', '/en/users/create', [], false, $jar, array_merge([
            'username'    => $username,
            'email'       => $emailPrefix . $username . '@example.org',
            'displayName' => 'Smoke ' . $username,
            //Carries a hidden element, so a browser always posts it. `mustChangePassword`
            //sat beside it until 2026-08-20, when the field and its column went: nothing
            //had ever read it and no row carried it.
            'isMultiPersonUser'  => '0',
            //The select is keyed on user_role.id, not on the role name.
            'rolesList'   => [(string) $this->roleId('sch_api_bot')],
            'personId'    => '',
            'security'    => $this->extractCsrfToken($form['body']),
            'submit'      => 'Submit',
        ], $extra));
    }

    private function roleId(string $roleId): int
    {
        $statement = $this->pdo()->prepare('SELECT id FROM user_role WHERE role_id = ?');
        $statement->execute([$roleId]);
        $id = $statement->fetchColumn();

        $this->assertNotFalse($id, "the $roleId role should exist — see database/db6.6.sql");

        return (int) $id;
    }

    private function userIdOf(string $username): ?int
    {
        $statement = $this->pdo()->prepare('SELECT user_id FROM user WHERE username = ?');
        $statement->execute([$username]);
        $id = $statement->fetchColumn();

        return false === $id ? null : (int) $id;
    }
}
