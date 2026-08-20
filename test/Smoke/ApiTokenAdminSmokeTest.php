<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use PDO;

use function preg_match;
use function sprintf;

/**
 * The admin token screen, measured as the loop it actually is.
 *
 * The point of `/users/:id/api-tokens` is not that it renders — it is that a token
 * an administrator mints in a browser is a token an agent can use against
 * `/api/v3`, and that revoking it in the browser stops the agent. Those two halves
 * live in different repositories (the screen is JUser, the check is
 * `App\Api\BotIdentity`) and talk only through the `user_api_token` table, so
 * nothing short of driving both proves they agree. That is what
 * testIssuedTokenWorksAgainstTheApiUntilItIsRevoked does; the rest of this file is
 * about who is allowed to press the button.
 *
 * ## Why the eligibility rule is tested twice
 *
 * `juser.api_token_roles` names `sch_api_bot` and nothing else, so an administrator
 * cannot mint a credential for an ordinary account — or for another administrator,
 * which is the case that matters: such a token would outlive the session that
 * created it by six months and no password or role change would touch it. The GET
 * decides whether to *draw* the button and the POST decides whether to *mint*, and
 * a hand-crafted POST never saw the GET. Both are asserted.
 */
class ApiTokenAdminSmokeTest extends SmokeTestCase
{
    use MagicLinkSignIn;

    /** Distinct from every other class's, or one tearDown deletes another's accounts mid-run. */
    private const EMAIL_PREFIX = 'api-token-admin-smoke-';

    private const BOT_ROLE = 'sch_api_bot';

    protected function emailPrefix(): string
    {
        return self::EMAIL_PREFIX;
    }

    protected function tearDown(): void
    {
        $this->purgeMail();
        $this->purgeAccounts();

        parent::tearDown();
    }

    // --------------------------------------------------------------- the loop

    public function testIssuedTokenWorksAgainstTheApiUntilItIsRevoked(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['administrator']);

        $botId = $this->accountHolding(self::BOT_ROLE);
        $page  = '/en/users/' . $botId . '/api-tokens';

        $form = $this->get($page, false, $jar);
        $this->assertSame(200, $form['status'], 'the token screen should render for an administrator');
        $this->assertStringContainsString('Issue token', $form['body']);

        $issued = $this->request('POST', $page, [], false, $jar, [
            'label'    => 'smoke test token',
            'security' => $this->extractCsrfToken($form['body']),
            'issue'    => 'Issue token',
        ]);
        $this->assertSame(302, $issued['status'], 'issuing should redirect back to the screen');

        //The JWT is shown once on the page after the redirect and never again — the
        //registry keeps only its jti.
        $after = $this->get($page, false, $jar);
        $this->assertSame(200, $after['status']);
        $this->assertStringContainsString('smoke test token', $after['body'], 'the label should be listed');

        $jwt = $this->extractJwt($after['body']);

        //It arrives in a well with a copy button, NOT in a flash message, and both halves
        //of that are asserted because both matter. A JWT is several hundred characters and
        //an alert box makes the reader select it by hand; and the flash pipeline
        //translates its messages, which is how a token once became a row in the phrase
        //table that any sch_api_translator account could read.
        $this->assertStringContainsString('juser-issued-token', $after['body'], 'shown in its own well');
        $this->assertStringContainsString('id="juser-copy-token"', $after['body'], 'with a copy button');
        $this->assertStringContainsString('Token issued.', $after['body'], 'the flash says only that');

        //The alert markup and the token must not appear in the same element. Checked by
        //slicing out every alert on the page and looking for the credential in them.
        $alerts = '';
        if (preg_match_all('/<div class="alert[^"]*">(.*?)<\/div>/s', $after['body'], $found) > 0) {
            $alerts = implode("\n", $found[1]);
        }
        $this->assertStringNotContainsString($jwt, $alerts, 'the token must not be inside a flash alert');

        //Shown exactly once: a refresh of the same page must not repeat it, because the
        //session slot it travelled in is read and cleared in one act.
        $refreshed = $this->get($page, false, $jar);
        $this->assertSame(200, $refreshed['status']);
        $this->assertStringNotContainsString(
            $jwt,
            $refreshed['body'],
            'the token is one-shot; a second render must not show it again'
        );

        //The half that lives in the other repository: BotIdentity must recognise it.
        $api = $this->request('GET', '/api/v3/associations', ['Authorization: Bearer ' . $jwt]);
        $this->assertSame(200, $api['status'], 'a freshly issued token should reach the v3 API');

        $revoked = $this->request(
            'POST',
            sprintf('/en/users/%d/api-tokens/%d/revoke', $botId, $this->latestTokenId($botId)),
            [],
            false,
            $jar,
            ['security' => $this->extractCsrfToken($after['body']), 'revoke' => 'Revoke']
        );
        $this->assertSame(302, $revoked['status'], 'revoking should redirect back to the screen');

        $refused = $this->request('GET', '/api/v3/associations', ['Authorization: Bearer ' . $jwt]);
        $this->assertSame(401, $refused['status'], 'a revoked token must stop working on its next request');
    }

    // ------------------------------------------------------- who may press it

    /**
     * An account without the bot role gets the screen but no button — and the POST
     * is refused even when it is sent anyway.
     */
    public function testAnAccountWithoutTheBotRoleCannotBeIssuedAToken(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar, ['administrator']);

        $ordinaryId = $this->accountHolding('sch_user');
        $page       = '/en/users/' . $ordinaryId . '/api-tokens';

        $form = $this->get($page, false, $jar);
        $this->assertSame(200, $form['status']);
        $this->assertStringNotContainsString('Issue token', $form['body'], 'no button for an ineligible account');
        $this->assertStringContainsString(self::BOT_ROLE, $form['body'], 'the page should say what is required');

        //Hand-crafted, and deliberately carrying a *valid* CSRF token — lifted from
        //the same form on an eligible account's page, which is the same element in
        //the same session and therefore validates here. Without that the POST would
        //be refused for the wrong reason and this test would pass with the
        //eligibility check deleted.
        $eligiblePage = $this->get('/en/users/' . $this->accountHolding(self::BOT_ROLE) . '/api-tokens', false, $jar);
        $this->assertStringContainsString('Issue token', $eligiblePage['body']);

        $forged = $this->request('POST', $page, [], false, $jar, [
            'label'    => 'should not exist',
            'security' => $this->extractCsrfToken($eligiblePage['body']),
            'issue'    => 'Issue token',
        ]);
        $this->assertSame(302, $forged['status']);

        $this->assertSame(
            0,
            $this->tokenCount($ordinaryId),
            'no token may be recorded for an account that holds no eligible role'
        );
    }

    /** The screen is administrators-only, like the rest of user management. */
    public function testANonAdministratorCannotReachTheScreen(): void
    {
        $jar = $this->newCookieJar();
        $this->signIn($jar);

        $botId = $this->accountHolding(self::BOT_ROLE);

        $response = $this->get('/en/users/' . $botId . '/api-tokens', false, $jar);

        $this->assertSame(403, $response['status']);
    }

    public function testAnAnonymousVisitorIsSentToSignIn(): void
    {
        $botId = $this->accountHolding(self::BOT_ROLE);

        $response = $this->get('/en/users/' . $botId . '/api-tokens');

        $this->assertSame(302, $response['status']);
        $this->assertStringContainsString('/en/user/login', $response['redirect']);
    }

    // ------------------------------------------------------------- fixtures

    /**
     * A fresh account holding exactly one named role.
     *
     * Inserted directly rather than through the sign-up flow: this account is the
     * *subject* of the screen, never its operator, so it never needs to sign in.
     */
    private function accountHolding(string $role): int
    {
        $email = $this->uniqueEmail();
        $pdo   = $this->pdo();

        $insert = $pdo->prepare(
            'INSERT INTO user (username, email, display_name, state, create_datetime, update_datetime)'
            . " VALUES (:email, :email2, 'Token subject', 1, NOW(), NOW())"
        );
        $insert->execute(['email' => $email, 'email2' => $email]);
        $userId = (int) $pdo->lastInsertId();

        $link = $pdo->prepare(
            'INSERT INTO user_role_linker (user_id, role_id)'
            . ' SELECT :user_id, id FROM user_role WHERE role_id = :role'
        );
        $link->execute(['user_id' => $userId, 'role' => $role]);

        return $userId;
    }

    /** The JWT out of the one-time flash message. */
    private function extractJwt(string $body): string
    {
        $found = preg_match('/([A-Za-z0-9_-]+\.[A-Za-z0-9_-]{20,}\.[A-Za-z0-9_-]+)/', $body, $matches);
        $this->assertSame(1, $found, 'the issued token should be shown once on the page after issuing');

        return $matches[1];
    }

    private function latestTokenId(int $userId): int
    {
        $statement = $this->pdo()->prepare(
            'SELECT token_id FROM user_api_token WHERE user_id = :user_id ORDER BY token_id DESC LIMIT 1'
        );
        $statement->execute(['user_id' => $userId]);
        $id = $statement->fetchColumn();

        $this->assertNotFalse($id, 'issuing should have recorded a row');

        return (int) $id;
    }

    private function tokenCount(int $userId): int
    {
        $statement = $this->pdo()->prepare('SELECT COUNT(*) FROM user_api_token WHERE user_id = :user_id');
        $statement->execute(['user_id' => $userId]);

        return (int) $statement->fetchColumn();
    }
}
