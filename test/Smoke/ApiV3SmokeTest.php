<?php

declare(strict_types=1);

namespace SchoenstattTest\Smoke;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;

use function base64_encode;
use function hash_hmac;
use function json_decode;
use function json_encode;
use function random_bytes;
use function rtrim;
use function str_replace;
use function strtr;
use function substr;
use function time;

/**
 * The v3 API, measured the way an automated agent will use it.
 *
 * The brief this was built for is "automated agents will augment and update the
 * shrines via the API", and the two properties that matter most are the two that a
 * casual read of the code cannot confirm:
 *
 * 1. **A bot's reach is the API and nothing else.** Holding a valid token is not
 *    enough; the account must hold `sch_api_bot`, which no ACL guard names. The test
 *    that earns its keep here is the one where a perfectly valid token belonging to an
 *    ordinary registered account is refused — because `sch_user`, which every
 *    registered account holds, *does* open the moderator edit form, and reusing it for
 *    bots would have made an automation credential a general-purpose site credential.
 * 2. **An agent is validated exactly as a moderator is.** The 422 below quotes the
 *    same message `Schoenstatt\Form\AssociationForm` produces for the same bad value.
 *    `test/Integration/AssociationValidationParityTest` proves the two filters are the
 *    same object graph; this proves it survives the HTTP layer.
 *
 * Tokens are minted directly from the configured key rather than through JUser's
 * email flow — ApiAuthSmokeTest already covers issuance end to end, and repeating it
 * here would test the mailer rather than the API.
 *
 * Fixtures: the test patches association 319 (`SL100319A`) and restores it, its change
 * log rows and its bot account in tearDown.
 */
class ApiV3SmokeTest extends SmokeTestCase
{
    use MagicLinkSignIn;

    private const EMAIL_PREFIX = 'api-v3-smoke-';

    private const SW_ID = 'SL100319A';
    private const ASSOCIATION_ID = 319;
    private const ITEM = '/api/v3/associations/' . self::SW_ID;

    /** @var array<string, string|null>|null */
    private ?array $original = null;

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

    // ----------------------------------------------------------- who gets in

    /** @return iterable<string, array{0: string}> */
    public static function botOnlyPaths(): iterable
    {
        yield 'collection' => ['/api/v3/associations'];
        yield 'item'       => [self::ITEM];
    }

    #[DataProvider('botOnlyPaths')]
    public function testAnonymousCallersAreRefused(string $path): void
    {
        $response = $this->get($path);

        $this->assertSame(401, $response['status'], $path);
        $this->assertStringContainsString('sch_api_bot', $response['body']);
    }

    /**
     * The test this file exists for. A real, unexpired, correctly signed token for a
     * real account — refused, because the account is not a bot. If this ever passes a
     * 200, an agent credential has become a site credential.
     */
    #[DataProvider('botOnlyPaths')]
    public function testAValidTokenWithoutTheBotRoleIsRefused(string $path): void
    {
        $userId = $this->accountWithoutBotRole();

        //Registered, so the missing role is the *only* thing wrong with it. An
        //unregistered token would now be refused on its own account and this test
        //would keep passing with the role check deleted.
        $response = $this->getWithBearer($this->registeredToken($userId, time() + 600), $path);

        $this->assertSame(401, $response['status'], $path);
    }

    #[DataProvider('botOnlyPaths')]
    public function testABotTokenGetsIn(string $path): void
    {
        $response = $this->getWithBearer($this->botToken(), $path);

        $this->assertSame(200, $response['status'], $path);
    }

    /** Garbage in the header is a 401, never a 500 — see App\Api\BotIdentity. */
    #[DataProvider('malformedTokens')]
    public function testAMalformedTokenIsRefusedWithoutAServerError(string $token): void
    {
        $response = $this->getWithBearer($token);

        $this->assertSame(401, $response['status'], $token);
    }

    /** @return iterable<string, array{0: string}> */
    public static function malformedTokens(): iterable
    {
        yield 'not a jwt'      => ['not-a-jwt-at-all'];
        yield 'two segments'   => ['a.b'];
        yield 'empty'          => [''];
        yield 'wrong key'      => ['eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOjF9.bm90LWEtcmVhbC1zaWduYXR1cmU'];
    }

    public function testAnExpiredBotTokenIsRefused(): void
    {
        $userId = $this->botUserId();

        //Registered and unrevoked: expiry is the only defect.
        $response = $this->getWithBearer($this->registeredToken($userId, time() - 60));

        $this->assertSame(401, $response['status']);
    }

    /**
     * Revocation is the whole reason the registry exists: a token that worked one
     * request ago stops working, without touching the account's role.
     */
    public function testARevokedBotTokenIsRefused(): void
    {
        $userId = $this->botUserId();
        $jti    = $this->uniqueJti();
        $token  = $this->registeredToken($userId, time() + 600, $jti);

        //Proves the token was good before the revocation, so the 401 below cannot
        //be blamed on anything else about it.
        $this->assertSame(200, $this->getWithBearer($token)['status']);

        $this->pdo()
            ->prepare('UPDATE user_api_token SET revoked_on = NOW() WHERE jti = :jti')
            ->execute(['jti' => $jti]);

        $this->assertSame(401, $this->getWithBearer($token)['status']);
    }

    /**
     * Deactivating the account refuses its tokens too.
     *
     * `user`.`state` means "may sign in" as of 2026-08-21. A bearer token is a sign-in
     * that skips the sign-in page, so it answers to the same switch — otherwise unticking
     * Active on `/users/{id}/edit` stops the account's human from getting a magic link and
     * leaves its six-month credential working, and "deactivate" means two different things
     * depending on which door the caller uses.
     *
     * Revoking the token stays the narrower instrument; this is the one that revokes
     * everything the account can do, in one place, without having to enumerate its
     * credentials.
     *
     * The 200 first is the same guard as the revocation test above: it proves the token was
     * good beforehand, so the 401 cannot be blamed on anything else about it. And the row
     * is put back in a `finally`, because leaving the shared bot account deactivated would
     * fail every other test in this class.
     */
    public function testADeactivatedBotAccountIsRefused(): void
    {
        $userId = $this->botUserId();
        $token  = $this->registeredToken($userId, time() + 600, $this->uniqueJti());

        $this->assertSame(200, $this->getWithBearer($token)['status']);

        $setState = $this->pdo()->prepare('UPDATE user SET state = :state WHERE user_id = :id');
        try {
            $setState->execute(['state' => 0, 'id' => $userId]);
            $this->assertSame(
                401,
                $this->getWithBearer($token)['status'],
                'a deactivated account must not act through a token either'
            );
        } finally {
            $setState->execute(['state' => 1, 'id' => $userId]);
        }

        $this->assertSame(
            200,
            $this->getWithBearer($token)['status'],
            'and reactivating restores it, so the check is on state and not on something else'
        );
    }

    /**
     * Fail closed. A correctly signed token for a real bot account, with a `jti`
     * that was never recorded — refused, because "we have no record of issuing
     * this" and "this was revoked" are the same answer.
     *
     * This is the test that would fail if anyone loosened the check to "reject
     * only what is explicitly revoked", which is the tempting and useless version.
     */
    public function testAnUnregisteredBotTokenIsRefused(): void
    {
        $token = $this->mintJwt([
            'sub' => $this->botUserId(),
            'exp' => time() + 600,
            'jti' => $this->uniqueJti(),
        ]);

        $this->assertSame(401, $this->getWithBearer($token)['status']);
    }

    /** A token predating the registry carries no jti at all; it cannot be vouched for. */
    public function testABotTokenWithoutAJtiClaimIsRefused(): void
    {
        $token = $this->mintJwt(['sub' => $this->botUserId(), 'exp' => time() + 600]);

        $this->assertSame(401, $this->getWithBearer($token)['status']);
    }

    /**
     * The schema index is public: an agent author must be able to read the contract
     * first, and must be able to *find* it without being told which entities exist.
     *
     * `/api/v3/schema` answered the association contract directly until phrases became
     * the second entity. It is a directory now, and the contracts live one level down —
     * see App\Controller\Api\ApiSchemaController::index().
     */
    public function testTheSchemaIndexNeedsNoTokenAndNamesEveryEntity(): void
    {
        $response = $this->get('/api/v3/schema');

        $this->assertSame(200, $response['status']);
        $document = json_decode($response['body'], true);
        $this->assertSame(3, $document['version']);
        $this->assertSame(
            ['association', 'phrase'],
            array_keys($document['entities']),
            'an entity was added or removed without the index following it'
        );
        //The index says which role each needs, which is the fact that stopped being
        //uniform when phrases arrived with their own.
        $this->assertSame('sch_api_bot', $document['entities']['association']['requiredRole']);
        $this->assertSame('sch_api_translator', $document['entities']['phrase']['requiredRole']);
    }

    /** The association contract itself, still public and still generated. */
    public function testTheAssociationSchemaNeedsNoToken(): void
    {
        $response = $this->get('/api/v3/schema/association');

        $this->assertSame(200, $response['status']);
        $document = json_decode($response['body'], true);
        $this->assertSame(3, $document['version']);
        $this->assertSame('association', $document['entity']);
        $this->assertArrayHasKey('openingHoursHuman', $document['fields']);
        //Generated from the specification, so the bound and the enum are the live ones.
        $this->assertSame(500, $document['fields']['openingHoursHuman']['maxLength']);
        $this->assertContains('sch-shrine', $document['fields']['kind']['enum']);
        $this->assertArrayNotHasKey('associationId', $document['fields']);
    }

    /** An entity nobody exposes is a 404, not an empty contract someone might trust. */
    public function testAnUnknownSchemaEntityIsNotFound(): void
    {
        $this->assertSame(404, $this->get('/api/v3/schema/nonesuch')['status']);
    }

    /** A wrong verb is a 405 with an Allow header, not laminas' redirect to sign-in. */
    public function testAnUnsupportedMethodIsRefusedWithAllow(): void
    {
        $response = $this->request('DELETE', self::ITEM);

        $this->assertSame(405, $response['status']);
        $this->assertSame('GET, PATCH', $response['headers']['allow'] ?? null);
    }

    // ------------------------------------------------------------ reading

    public function testTheCollectionFiltersByKind(): void
    {
        $response = $this->getWithBearer($this->botToken(), '/api/v3/associations?kind=sch-shrine&limit=5');

        $this->assertSame(200, $response['status']);
        $document = json_decode($response['body'], true);

        $this->assertSame(207, $document['total'], 'the dump holds 207 shrines');
        $this->assertCount(5, $document['items']);
        foreach ($document['items'] as $item) {
            $this->assertSame('sch-shrine', $item['kind']);
        }
    }

    /**
     * The representation an agent reads is the one it can write back: every key of
     * `fields` is a field the schema accepts.
     */
    public function testTheRepresentationRoundTrips(): void
    {
        $item   = json_decode($this->getWithBearer($this->botToken())['body'], true);
        $schema = json_decode($this->get('/api/v3/schema/association')['body'], true);

        $this->assertSame(self::SW_ID, $item['identifier']);
        $this->assertSame([], array_diff(array_keys($item['fields']), array_keys($schema['fields'])));
        $this->assertNotEmpty($item['meta']['etag']);
    }

    // ------------------------------------------------------------ writing

    public function testAPatchWritesAndIsAttributedToTheBot(): void
    {
        $this->rememberAssociation();
        $token  = $this->botToken();
        $marker = 'Open daily, agent ' . time();

        $response = $this->patch($token, ['openingHoursHuman' => $marker]);

        $this->assertSame(200, $response['status'], $response['body']);
        $document = json_decode($response['body'], true);
        $this->assertSame(['openingHoursHuman'], $document['changed']);
        $this->assertSame($marker, $this->column('OpeningHoursHuman'));
        $this->assertSame(
            (string) $this->botUserId(),
            $this->column('UpdatedBy'),
            'an agent edit must be attributed to the bot account, or the change log cannot say who did it'
        );
    }

    /**
     * A PATCH naming one field must not disturb the rest — the merge-then-validate
     * step is what makes a partial update safe, and a regression there would rewrite
     * every other column with whatever the merge produced.
     */
    public function testAPatchLeavesUnmentionedFieldsAlone(): void
    {
        $this->rememberAssociation();
        $before = $this->column('AssociationName');

        $this->patch($this->botToken(), ['openingHoursHuman' => 'partial update']);

        $this->assertSame($before, $this->column('AssociationName'));
    }

    /** Sending a value that is already stored changes nothing and says so. */
    public function testAPatchThatChangesNothingReportsNoChanges(): void
    {
        $this->rememberAssociation();
        $token = $this->botToken();
        $name  = $this->column('AssociationName');

        $document = json_decode($this->patch($token, ['name' => $name])['body'], true);

        $this->assertSame([], $document['changed']);
    }

    /**
     * The validation-parity assertion, over HTTP. `not-a-real-kind` is refused with
     * the InArray message the moderator form produces for the same value.
     */
    public function testAnInvalidValueIsRefusedWithTheFormsOwnMessage(): void
    {
        $this->rememberAssociation();
        $before = $this->column('Kind');

        $response = $this->patch($this->botToken(), ['kind' => 'not-a-real-kind']);

        $this->assertSame(422, $response['status']);
        $document = json_decode($response['body'], true);
        $this->assertArrayHasKey('kind', $document['error']['messages']);
        $this->assertStringContainsString('was not found in the haystack', json_encode($document['error']['messages']));
        $this->assertSame($before, $this->column('Kind'), 'a refused PATCH must write nothing');
    }

    /** A field name the API does not know is refused rather than silently dropped. */
    public function testAnUnknownFieldIsRefused(): void
    {
        $response = $this->patch($this->botToken(), ['openingHours' => 'misspelled']);

        $this->assertSame(422, $response['status']);
        $document = json_decode($response['body'], true);
        $this->assertSame(['openingHours'], $document['error']['unknownFields']);
    }

    /**
     * The concurrency contract: a matching If-Match proceeds, a stale one is refused,
     * and no If-Match at all is permitted.
     */
    public function testIfMatchIsHonouredWhenSent(): void
    {
        $this->rememberAssociation();
        $token = $this->botToken();

        $etag = $this->getWithBearer($token)['headers']['etag'] ?? '';
        $this->assertNotSame('', $etag);

        $first = $this->patch($token, ['openingHoursHuman' => 'first write'], $etag);
        $this->assertSame(200, $first['status'], $first['body']);

        //The tag the client actually received carries mod_deflate's `-gzip` suffix,
        //because curl asks for gzip. That it still matches is the point: see
        //AssociationsV3Controller::normalizeEtag().
        $this->assertStringContainsString('-gzip', $etag, 'this test is only meaningful against a compressed response');

        //The same tag is now stale, because the first write moved it.
        $second = $this->patch($token, ['openingHoursHuman' => 'second write'], $etag);
        $this->assertSame(412, $second['status']);
        $this->assertSame('first write', $this->column('OpeningHoursHuman'), 'the refused write must not land');

        //And a blind write is still allowed.
        $third = $this->patch($token, ['openingHoursHuman' => 'third write']);
        $this->assertSame(200, $third['status']);
    }

    /** v1 and v2 are untouched by this, and still refuse writes. */
    public function testTheOlderApisAreStillReadOnly(): void
    {
        foreach (['/en/api/v1/associations/SL10319A', '/en/api/v2/associations/SL100319A'] as $path) {
            $response = $this->request('POST', $path, [], false, null, ['name' => 'x']);
            $this->assertNotSame(200, $response['status'], $path . ' must not accept a write');
        }
    }

    // ------------------------------------------------------------------ helpers

    /**
     * @param array<string, mixed> $patch
     * @return array{status: int, redirect: string, body: string, contentType: string,
     *               headers: array<string, string>}
     */
    private function patch(string $token, array $patch, ?string $ifMatch = null): array
    {
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ];
        if (null !== $ifMatch) {
            $headers[] = 'If-Match: ' . $ifMatch;
        }

        return $this->requestWithBody('PATCH', self::ITEM, $headers, (string) json_encode($patch));
    }

    /**
     * @param list<string> $headers
     * @return array{status: int, redirect: string, body: string, contentType: string,
     *               headers: array<string, string>}
     */
    private function requestWithBody(string $method, string $path, array $headers, string $body): array
    {
        $responseHeaders = [];
        $ch = curl_init($this->baseUrl() . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$responseHeaders): int {
                $parts = explode(':', $line, 2);
                if (2 === count($parts)) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return strlen($line);
            },
        ]);
        $responseBody = curl_exec($ch);
        $status       = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return [
            'status'      => $status,
            'redirect'    => '',
            'body'        => is_string($responseBody) ? $responseBody : '',
            'contentType' => $responseHeaders['content-type'] ?? '',
            'headers'     => $responseHeaders,
        ];
    }

    /**
     * @return array{status: int, redirect: string, body: string, contentType: string,
     *               headers: array<string, string>}
     */
    private function getWithBearer(string $token, string $path = self::ITEM): array
    {
        return $this->request('GET', $path, ['Authorization: Bearer ' . $token]);
    }

    /**
     * Every NOT NULL column of `user` that has no default: email, create_datetime,
     * update_datetime, state.
     *
     * `password` was in this list until db8.5 dropped the column, with the note that it
     * "outlived the feature" and was left empty because nothing may authenticate as a
     * bot account except its token. That is now true by construction rather than by
     * convention: there is no column to leave empty.
     */
    private const INSERT_USER = 'INSERT INTO user '
        . '(username, email, display_name, state, create_datetime, update_datetime) '
        . "VALUES (%s, %s, 'API bot', 1, NOW(), NOW())";

    private ?int $botUserId = null;

    /** A freshly registered account holding sch_api_bot, created once per test. */
    private function botUserId(): int
    {
        if (null !== $this->botUserId) {
            return $this->botUserId;
        }

        $email = $this->uniqueEmail();
        $pdo   = $this->pdo();
        $pdo->exec(sprintf(
            self::INSERT_USER,
            $pdo->quote($email),
            $pdo->quote($email)
        ));
        $userId = (int) $pdo->lastInsertId();

        $pdo->exec(sprintf(
            'INSERT INTO user_role_linker (user_id, role_id) '
            . "SELECT %d, id FROM user_role WHERE role_id = 'sch_api_bot'",
            $userId
        ));

        return $this->botUserId = $userId;
    }

    /** A registered account with the roles registration grants and nothing more. */
    private function accountWithoutBotRole(): int
    {
        $email = $this->uniqueEmail();
        $pdo   = $this->pdo();
        $pdo->exec(sprintf(
            self::INSERT_USER,
            $pdo->quote($email),
            $pdo->quote($email)
        ));
        $userId = (int) $pdo->lastInsertId();

        $pdo->exec(sprintf(
            'INSERT INTO user_role_linker (user_id, role_id) '
            . "SELECT %d, id FROM user_role WHERE role_id = 'sch_user'",
            $userId
        ));

        return $userId;
    }

    private function botToken(): string
    {
        return $this->registeredToken($this->botUserId(), time() + 600);
    }

    /**
     * A token that is both correctly signed **and** recorded in `user_api_token`.
     *
     * Since db6.7 those are two separate requirements: BotIdentity refuses a token
     * whose `jti` it cannot find, so a signature alone no longer gets in. Almost
     * every test here wants a token that is wrong in exactly one way, which means
     * the registry row has to be right — otherwise a test asserting "refused
     * because the account lacks the role" would pass without the role check
     * existing at all.
     *
     * @return string the JWT; the row is inserted as a side effect
     */
    private function registeredToken(int $userId, int $expiresAt, ?string $jti = null): string
    {
        $jti ??= $this->uniqueJti();

        $this->pdo()->prepare(
            'INSERT INTO user_api_token (jti, user_id, label, issued_on, expires_on)'
            . ' VALUES (:jti, :user_id, :label, NOW(), FROM_UNIXTIME(:expires))'
        )->execute([
            'jti'     => $jti,
            'user_id' => $userId,
            'label'   => 'smoke test',
            'expires' => $expiresAt,
        ]);

        return $this->mintJwt(['sub' => $userId, 'exp' => $expiresAt, 'jti' => $jti]);
    }

    /** 43 chars of base62, the shape JUser\Service\ApiTokenService issues. */
    private function uniqueJti(): string
    {
        return substr(str_replace(['+', '/', '='], 'a', base64_encode(random_bytes(48))), 0, 43);
    }

    /** @param array<string, mixed> $payload */
    private function mintJwt(array $payload): string
    {
        $key       = $this->cypherKey();
        $header    = $this->base64Url((string) json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $claims    = $this->base64Url((string) json_encode($payload));
        $signature = $this->base64Url(hash_hmac('sha256', $header . '.' . $claims, $key, true));

        return $header . '.' . $claims . '.' . $signature;
    }

    private function cypherKey(): string
    {
        /** @var array<string, mixed> $config */
        $config = require dirname(__DIR__, 2) . '/config/autoload/local.php';
        $key    = $config['ApiRequest']['jwtAuth']['cypherKey'] ?? null;

        if (! is_string($key) || '' === $key) {
            $this->markTestSkipped('ApiRequest.jwtAuth.cypherKey is not configured here');
        }

        return $key;
    }

    private function base64Url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private function pdo(): PDO
    {
        return new PDO(
            'mysql:host=db;dbname=ourlink_db1;charset=utf8mb4',
            'schoenstatt',
            'schoenstatt',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    private function column(string $column): ?string
    {
        $value = $this->pdo()->query(sprintf(
            'SELECT `%s` FROM sch_associations WHERE AssociationId = %d',
            $column,
            self::ASSOCIATION_ID
        ))->fetchColumn();

        return false === $value || null === $value ? null : (string) $value;
    }

    private const RESTORED_COLUMNS = ['OpeningHoursHuman', 'AssociationName', 'Kind', 'UpdatedOn', 'UpdatedBy'];

    private function rememberAssociation(): void
    {
        if (null !== $this->original) {
            return;
        }

        /** @var array<string, string|null> $row */
        $row = $this->pdo()->query(sprintf(
            'SELECT `%s` FROM sch_associations WHERE AssociationId = %d',
            implode('`, `', self::RESTORED_COLUMNS),
            self::ASSOCIATION_ID
        ))->fetch(PDO::FETCH_ASSOC);

        $this->original = $row;
    }

    private function restoreAssociation(): void
    {
        $pdo = $this->pdo();

        if (null !== $this->original) {
            $assignments = [];
            foreach ($this->original as $column => $value) {
                $assignments[] = sprintf(
                    '`%s` = %s',
                    $column,
                    null === $value ? 'NULL' : $pdo->quote((string) $value)
                );
            }
            $pdo->exec(sprintf(
                'UPDATE sch_associations SET %s WHERE AssociationId = %d',
                implode(', ', $assignments),
                self::ASSOCIATION_ID
            ));
            $this->original = null;
        }

        if (null !== $this->botUserId) {
            $pdo->exec(sprintf(
                'DELETE FROM sch_changes WHERE ChangedEntity = %s AND ChangedIDValue = %d AND UpdatedBy = %d',
                $pdo->quote('association'),
                self::ASSOCIATION_ID,
                $this->botUserId
            ));
            $this->botUserId = null;
        }
    }
}
