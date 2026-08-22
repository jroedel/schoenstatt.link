<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use PDO;

use function array_map;
use function bin2hex;
use function implode;
use function random_bytes;
use function sprintf;

/**
 * The JUser user-administration surface, Symfony-served since batch 12.
 *
 * `UserCreateSmokeTest` already covers `/users/create` end to end and
 * `ApiTokenAdminSmokeTest` covers the credential screen; what had no coverage at all was
 * the index, the edit form, the delete confirmation and the role form. This file is those
 * four, and the reason it exists rather than being folded into either of the others is the
 * first test below.
 *
 * ## The test that had to exist
 *
 * `testSavingAnAccountKeepsEveryRoleItHad` guards a defect that the port introduced and
 * that nothing else here could have caught. `JUser\Form\EditUserForm` declares its roles
 * select as `'multiple' => 'multiple'` — the string, which is what the HTML attribute's
 * value is — and `SionModel\Form\BootstrapFormRenderer` appended the `[]` a multiple select's
 * name needs only when the attribute was the boolean `true`. Without the brackets a browser
 * posts `rolesList=1&rolesList=41&…`, PHP keeps **only the last one**, and pressing Submit
 * on an unchanged form reduces the account to a single role. Nothing fails, nothing is
 * logged, and the page comes back saying "User successfully updated."
 *
 * The renderer is fixed and `test/Integration/SelectRenderingParityTest` pins the markup;
 * this pins the consequence, because the markup and the outcome are different claims and it
 * is the outcome that matters to somebody's account.
 */
class JUserAdminSmokeTest extends SmokeTestCase
{
    use MagicLinkSignIn;

    /** Distinct from every other class's, or one tearDown deletes another's accounts mid-run. */
    private const EMAIL_PREFIX = 'juser-admin-smoke-';

    /** Three roles, none of them a default one, so the count is unambiguous. */
    private const ROLES = ['sch_api_bot', 'sch_api_translator', 'view_changes'];

    /** @var list<string> usernames a test created, removed in tearDown */
    private array $createdUsernames = [];

    /** @var list<string> `user_role.role_id` values a test created, removed in tearDown */
    private array $createdRoles = [];

    protected function emailPrefix(): string
    {
        return self::EMAIL_PREFIX;
    }

    protected function tearDown(): void
    {
        foreach ($this->createdUsernames as $username) {
            $statement = $this->pdo()->prepare(
                'DELETE FROM user_role_linker WHERE user_id IN (SELECT user_id FROM user WHERE username = ?)'
            );
            $statement->execute([$username]);
            $this->pdo()->prepare('DELETE FROM user WHERE username = ?')->execute([$username]);
        }
        $this->createdUsernames = [];

        foreach ($this->createdRoles as $roleId) {
            $this->pdo()->prepare('DELETE FROM user_role WHERE role_id = ?')->execute([$roleId]);
        }
        $this->createdRoles = [];

        $this->purgeMail();
        $this->purgeAccounts();

        parent::tearDown();
    }

    /**
     * The whole point of the batch: pressing Submit on a form nobody edited changes nothing.
     *
     * Three roles in, three roles out. Asserted against `user_role_linker` rather than
     * against the page, because the page said "User successfully updated." while it was
     * deleting two of them.
     */
    public function testSavingAnAccountKeepsEveryRoleItHad(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['administrator']);

        $username = $this->createAccountWithRoles($jar);
        $userId    = $this->userIdOf($username);
        $this->assertNotNull($userId, 'the account should have been created');
        $this->assertSame(self::ROLES, $this->rolesOf($userId), 'precondition: three roles');

        $saved = $this->resubmitEditForm($jar, $userId, $username);
        $this->assertSame(302, $saved['status'], 'a successful save redirects to the index');
        $this->assertStringEndsWith('/en/users', $saved['redirect']);

        $this->assertSame(
            self::ROLES,
            $this->rolesOf($userId),
            'saving an unchanged form must not remove roles — see the class docblock'
        );
    }

    /** The account's own row is on the index, with its roles and its two action links. */
    public function testTheIndexListsAnAccountWithItsRolesAndLinks(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['administrator']);

        //An account created through the form rather than by INSERT, because the index is
        //served from the `all-linked-users` cache and only a write through the table
        //clears it. A raw INSERT would be found by /users/{id}/edit and missing here.
        $username = $this->createAccountWithRoles($jar);
        $userId   = $this->userIdOf($username);
        $this->assertNotNull($userId);

        $page = $this->get('/en/users', false, $jar);
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString($username, $page['body'], 'the new account should be listed');
        $this->assertStringContainsString(
            sprintf('/en/users/%d/edit', $userId),
            $page['body'],
            'the pencil should link to the edit form'
        );
        $this->assertStringContainsString(
            sprintf('/en/users/%d/api-tokens', $userId),
            $page['body'],
            'the crown should link to the token screen'
        );
        foreach (self::ROLES as $role) {
            $this->assertStringContainsString($role, $page['body'], "the $role role should be listed");
        }
    }

    /**
     * A POST whose hidden `userId` names a different account is refused outright.
     *
     * The check is what stops an edit of account A writing to account B, and it is not a
     * validation error: it redirects to the index. Asserted on the *other* account being
     * untouched, which is the thing that would actually hurt.
     */
    public function testAPostNamingAnotherAccountIsRefusedAndWritesNothing(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['administrator']);

        $username = $this->createAccountWithRoles($jar);
        $userId   = $this->userIdOf($username);
        $this->assertNotNull($userId);

        $form = $this->get(sprintf('/en/users/%d/edit', $userId), false, $jar);
        $this->assertSame(200, $form['status']);

        $refused = $this->request('POST', sprintf('/en/users/%d/edit', $userId), [], false, $jar, [
            //the form's own id, edited by hand — the only way to reach this branch
            'userId'            => (string) ($userId + 1),
            'username'          => $username . '-renamed',
            'email'             => $username . '@example.com',
            'displayName'       => 'Renamed',
            'rolesList'         => [(string) $this->roleId('sch_api_bot')],
            'personId'          => '',
            'isMultiPersonUser' => '0',
            'security'          => $this->extractCsrfToken($form['body']),
            'submit'            => 'Submit',
        ]);

        $this->assertSame(302, $refused['status']);
        $this->assertStringEndsWith('/en/users', $refused['redirect']);
        $this->assertSame(
            $username,
            $this->usernameOf($userId),
            'the account named in the URL must not have been renamed'
        );
        $this->assertSame(self::ROLES, $this->rolesOf($userId), 'nor its roles rewritten');
    }

    /**
     * The delete confirmation says whose account it is.
     *
     * `delete.phtml` read `$this->user->username` — a property on an array — so it asked
     * whether to permanently delete `''` for as long as it existed. The one behaviour this
     * batch deliberately did not reproduce; see JUser\Controller\UserDeleteController.
     */
    public function testTheDeleteConfirmationNamesTheAccount(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['administrator']);

        $username = $this->createAccountWithRoles($jar);
        $userId   = $this->userIdOf($username);
        $this->assertNotNull($userId);

        $page = $this->get(sprintf('/en/users/%d/delete', $userId), false, $jar);
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString($username, $page['body'], 'the confirmation must name the account');
        $this->assertStringNotContainsString(
            "delete ''",
            $page['body'],
            'the empty-quotes rendering is the defect this test exists for'
        );
    }

    /** The POST deletes the account and its role links with it. */
    public function testDeletingAnAccountRemovesItAndItsRoleLinks(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['administrator']);

        $username = $this->createAccountWithRoles($jar);
        $userId   = $this->userIdOf($username);
        $this->assertNotNull($userId);

        $page = $this->get(sprintf('/en/users/%d/delete', $userId), false, $jar);
        $deleted = $this->request('POST', sprintf('/en/users/%d/delete', $userId), [], false, $jar, [
            'userId'   => (string) $userId,
            'security' => $this->extractCsrfToken($page['body']),
            'delete'   => 'Delete',
        ]);

        $this->assertSame(302, $deleted['status']);
        $this->assertStringEndsWith('/en/users', $deleted['redirect']);
        $this->assertNull($this->usernameOf($userId), 'the account should be gone');
        $this->assertSame([], $this->rolesOf($userId), 'and its role links with it');
    }

    /** An id that names no account redirects to the index rather than confirming a deletion. */
    public function testDeletingAnUnknownAccountRedirectsToTheIndex(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['administrator']);

        //Five digits, the route's own constraint, and far past the highest id the capsule
        //carries — a shape the route matches and the table does not.
        $page = $this->get('/en/users/99999/delete', false, $jar);

        $this->assertSame(302, $page['status']);
        $this->assertStringEndsWith('/en/users', $page['redirect']);
    }

    /** The role form writes a row, and the new role is then selectable on the account form. */
    public function testCreatingARoleAddsItToTheSelectOnTheAccountForm(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['administrator']);

        $form = $this->get('/en/users/roles/create', false, $jar);
        $this->assertSame(200, $form['status']);

                //Underscores only: CreateRoleForm validates the name against
        //`/\A[0-9A-Za-z_]+\z/`, so a hyphenated name is refused and the page re-renders.
        $roleId = 'juser_smoke_role';
        $created = $this->request('POST', '/en/users/roles/create', [], false, $jar, [
            'name'      => $roleId,
            'parentId'  => '',
            'isDefault' => '0',
            'security'  => $this->extractCsrfToken($form['body']),
            'submit'    => 'Submit',
        ]);
        $this->createdRoles[] = $roleId;

        $this->assertSame(302, $created['status']);
        $this->assertStringEndsWith('/en/users', $created['redirect']);

        $statement = $this->pdo()->prepare('SELECT is_default FROM user_role WHERE role_id = ?');
        $statement->execute([$roleId]);
        $isDefault = $statement->fetchColumn();
        $this->assertNotFalse($isDefault, 'the role row should exist');
        $this->assertSame(
            0,
            (int) $isDefault,
            'an unticked "give to new users" must not create a default role — see '
            . 'test/Integration/AclGuardRouteDriftTest on what a default role does to every guard'
        );
    }

    /**
     * A role created a moment ago can be granted, and the account holding it still gets a page.
     *
     * The assembled BjyAuthorize ACL is cached in APCu (2026-08-22). What makes that
     * dangerous rather than merely stale is `Laminas\Permissions\Acl\Acl::addRole()`:
     * `Authorize::load()` hands it the identity's roles as *parent* roles, and it throws on
     * a parent it has never heard of. So an ACL cached before this role existed is not a
     * missing permission — it is a **500 on every request** by whoever holds the role, until
     * the item's TTL runs out.
     *
     * Nothing about that is visible from reading the cache config, and the TTL means it
     * heals itself before anyone can reproduce it by hand. Hence an end-to-end test: create
     * the role through the form (which is what must expire the cache, via
     * `SionCacheTrait::removeDependentCacheItems()` and `App\Acl\AclCacheInvalidator`), grant
     * it with a raw INSERT — deliberately, because granting a role is a `user-role-link`
     * write and must *not* expire anything — and then load a page as that account.
     */
    public function testAnAccountGrantedABrandNewRoleStillGetsAPage(): void
    {
        $admin = $this->newCookieJar();
        $this->signIn($admin, ['administrator']);

        $form = $this->get('/en/users/roles/create', false, $admin);
        $this->assertSame(200, $form['status']);

        $roleId  = 'juser_smoke_fresh_role';
        $created = $this->request('POST', '/en/users/roles/create', [], false, $admin, [
            'name'      => $roleId,
            'parentId'  => '',
            'isDefault' => '0',
            'security'  => $this->extractCsrfToken($form['body']),
            'submit'    => 'Submit',
        ]);
        $this->createdRoles[] = $roleId;
        $this->assertSame(302, $created['status'], 'precondition: the role was created');

        //A second visitor, signed in after the role existed, so the only thing that can
        //make the next request fail is an ACL assembled before it.
        $holder = $this->newCookieJar();
        $email  = $this->signIn($holder, [$roleId]);

        $page = $this->get('/en/', false, $holder);
        $this->assertSame(
            200,
            $page['status'],
            sprintf(
                'the home page 500s for an account holding a role the cached ACL predates '
                . '(account %s, role %s)',
                $email,
                $roleId
            )
        );
        $this->assertStringNotContainsString('Sign in</a>', $page['body'], 'and the session is real');
    }

    /** A signed-in visitor without `administrator` is refused; the guard is the whole protection. */
    public function testASignedInVisitorWithoutTheRoleIsRefused(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar);

        foreach (['/en/users', '/en/users/create', '/en/users/roles/create', '/en/users/5/edit'] as $path) {
            $response = $this->get($path, false, $jar);
            $this->assertSame(403, $response['status'], "$path should be refused without the role");
        }
    }

    /** An anonymous visitor is sent to sign in, and the destination travels in the link. */
    public function testAnAnonymousVisitorIsSentToSignInWithTheDestination(): void
    {
        $response = $this->get('/en/users');

        $this->assertSame(302, $response['status']);
        $this->assertStringContainsString('/en/user/login', $response['redirect']);
        $this->assertStringContainsString(
            'redirect=/en/users',
            $response['redirect'],
            'the magic link has to come back here — see JUser\Controller\LoginController::verifyAction'
        );
    }

    // ------------------------------------------------------------------ helpers

    /**
     * An account with {@see ROLES}, created through the form so the index cache is cleared.
     *
     * @return string its username
     */
    private function createAccountWithRoles(string $jar): string
    {
        $username = 'juser-admin-' . bin2hex(random_bytes(4));

        $form = $this->get('/en/users/create', false, $jar);
        $this->assertSame(200, $form['status'], 'the create form should render for an administrator');

        $created = $this->request('POST', '/en/users/create', [], false, $jar, [
            'username'          => $username,
            'email'             => self::EMAIL_PREFIX . $username . $this->emailDomain(),
            'displayName'       => 'Smoke ' . $username,
            'rolesList'         => array_map(fn (string $r): string => (string) $this->roleId($r), self::ROLES),
            'personId'          => '',
            'isMultiPersonUser' => '0',
            'security'          => $this->extractCsrfToken($form['body']),
            'submit'            => 'Submit',
        ]);
        $this->assertSame(302, $created['status'], 'creating the fixture account should redirect');
        $this->createdUsernames[] = $username;

        return $username;
    }

    /**
     * GET the edit form and POST it straight back, changing nothing.
     *
     * The roles are re-posted as the array a browser sends for a multiple select, which is
     * the whole subject of the first test: the *markup* decides whether a browser can send
     * one, and this decides what happens when it does.
     *
     * @return array{status: int, redirect: string, body: string, contentType: string,
     *               headers: array<string, string>}
     */
    private function resubmitEditForm(string $jar, int $userId, string $username): array
    {
        $path = sprintf('/en/users/%d/edit', $userId);
        $form = $this->get($path, false, $jar);
        $this->assertSame(200, $form['status'], 'the edit form should render for an administrator');
        //Either escaping, because the escaper writes `&#x5B;&#x5D;` for the brackets and
        //this test is about their presence rather than about how they were escaped.
        $this->assertMatchesRegularExpression(
            '/name="rolesList(\[\]|&#x5B;&#x5D;)"/',
            $form['body'],
            'without the brackets a browser posts one role, not all of them'
        );

        return $this->request('POST', $path, [], false, $jar, [
            'userId'            => (string) $userId,
            'username'          => $username,
            'email'             => self::EMAIL_PREFIX . $username . $this->emailDomain(),
            'displayName'       => 'Smoke ' . $username,
            'rolesList'         => array_map(fn (string $r): string => (string) $this->roleId($r), self::ROLES),
            'personId'          => '',
            'isMultiPersonUser' => '0',
            'emailVerified'     => '1',
            'active'            => '1',
            'security'          => $this->extractCsrfToken($form['body']),
            'submit'            => 'Submit',
        ]);
    }

    /**
     * The role names linked to an account, sorted the way {@see ROLES} is declared so the
     * comparison is about membership and not about join order.
     *
     * @return list<string>
     */
    private function rolesOf(int $userId): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT r.role_id FROM user_role_linker l JOIN user_role r ON r.id = l.role_id'
            . ' WHERE l.user_id = ? ORDER BY FIELD(r.role_id, ' . $this->rolePlaceholders() . '), r.role_id'
        );
        $statement->execute([$userId]);

        /** @var list<string> $roles */
        $roles = $statement->fetchAll(PDO::FETCH_COLUMN);

        return $roles;
    }

    /** `FIELD()` arguments spelling out ROLES' own order. */
    private function rolePlaceholders(): string
    {
        return implode(', ', array_map(
            fn (string $role): string => $this->pdo()->quote($role),
            self::ROLES
        ));
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

    private function usernameOf(int $userId): ?string
    {
        $statement = $this->pdo()->prepare('SELECT username FROM user WHERE user_id = ?');
        $statement->execute([$userId]);
        $username = $statement->fetchColumn();

        return false === $username ? null : (string) $username;
    }
}
