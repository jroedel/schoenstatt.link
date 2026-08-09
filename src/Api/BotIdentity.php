<?php

declare(strict_types=1);

namespace App\Api;

use App\Laminas\ServiceBridge;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;
use RuntimeException;
use Throwable;

use function is_array;
use function is_numeric;
use function is_object;
use function is_string;
use function preg_match;
use function sprintf;
use function strlen;
use function strncmp;
use function substr;

/**
 * Who is making an API request, and whether they may write.
 *
 * The v3 API is for automated agents, and an agent has no browser and no session —
 * so none of the site's ordinary authorization applies to it. `BjyAuthorize` asks
 * `JUser` for the identity, `JUser` reads the laminas session, and there is no
 * session behind an `Authorization: Bearer` header. That is why the v3 routes declare
 * themselves open in config/symfony/routes.php and gate here instead, exactly as the
 * maintenance endpoints gate on their maintenance key.
 *
 * ## The token is the site's own, not a new scheme
 *
 * `JUser\Controller\LoginV1ApiController` already issues JWTs — `{sub, exp, jti}`,
 * signed with `ApiRequest.jwtAuth.cypherKey` — and the mobile apps already use them.
 * A bot gets one the same way any other account does, so there is no second
 * credential store to build, leak or forget to rotate. `sub` is a `user.user_id`,
 * which is what makes an agent's edits attributable: it goes straight into
 * `SionTable::setActingUserId()`, and every field an agent changes lands in
 * `sch_changes` under that account.
 *
 * ## The role is the whole authorization model
 *
 * Holding a valid token is not enough. The account must also hold **`sch_api_bot`**,
 * a role introduced for this and named by nothing else on the site — see
 * database/db6.6.sql. Two consequences, both intended:
 *
 * - A leaked bot token reaches the v3 API and nothing else. It cannot open the edit
 *   form, /admin, or any other guarded page, because no other guard names the role.
 * - A human's token cannot write through the API by accident. `sch_user` — which
 *   every registered account holds, and which *does* open the edit form — is not
 *   `sch_api_bot`.
 *
 * Roles are read straight from `user_role_linker` rather than through BjyAuthorize's
 * identity provider, because that provider is the session-reading one. It is the same
 * table the provider reads, and the same table the moderator UI writes, so granting
 * and revoking a bot works through the existing screens.
 *
 * ## Failure is deliberately uninformative
 *
 * Missing token, malformed token, expired token, unknown user, missing role: all 401
 * with one message. Distinguishing them tells an attacker which half of a guess was
 * right, and no legitimate agent needs to be told — its token either works from the
 * first request or was issued wrongly.
 */
final class BotIdentity
{
    /** The role an account must hold to write through the v3 API. */
    public const REQUIRED_ROLE = 'sch_api_bot';

    public function __construct(private readonly ServiceBridge $laminas)
    {
    }

    /**
     * The acting user id behind a bearer token, or null if the request may not write.
     *
     * @param string|null $authorization the raw `Authorization` header
     */
    public function resolve(?string $authorization): ?int
    {
        $token = self::bearerToken($authorization);
        if (null === $token) {
            return null;
        }

        $userId = $this->userIdFrom($token);
        if (null === $userId) {
            return null;
        }

        return $this->holdsRequiredRole($userId) ? $userId : null;
    }

    /**
     * `Authorization: Bearer <token>`, or null.
     *
     * Only the Bearer scheme, and only a token shaped like a JWS — three
     * base64url segments. A token of some other shape reaching `JWT::decode()` is a
     * `DomainException` an anonymous caller could raise at will, which the exception
     * reporter would then email about; rejecting it here keeps a malformed credential
     * a 401 rather than an incident.
     */
    private static function bearerToken(?string $authorization): ?string
    {
        if (! is_string($authorization) || 0 !== strncmp($authorization, 'Bearer ', 7)) {
            return null;
        }

        $token = substr($authorization, 7);

        return 1 === preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $token) ? $token : null;
    }

    /** The `sub` claim of a token that verifies, or null for one that does not. */
    private function userIdFrom(string $token): ?int
    {
        /** @var array<string, mixed> $config */
        $config    = $this->laminas->get('config');
        $jwtConfig = $config['ApiRequest']['jwtAuth'] ?? [];
        $key       = is_array($jwtConfig) ? ($jwtConfig['cypherKey'] ?? null) : null;
        $algorithm = is_array($jwtConfig) ? ($jwtConfig['tokenAlgorithm'] ?? 'HS256') : 'HS256';

        if (! is_string($key) || '' === $key || ! is_string($algorithm)) {
            //Our misconfiguration, not the caller's. Raised rather than answered as a
            //401, so it reaches the exception reporter instead of looking to an agent
            //operator like a bad token — the distinction ApiController::
            //assertUsableCypherKey() exists to preserve.
            throw new RuntimeException(
                'ApiRequest.jwtAuth.cypherKey is missing or empty; the v3 API cannot verify tokens.'
            );
        }
        if (0 === strncmp($algorithm, 'HS', 2) && strlen($key) < 32) {
            throw new RuntimeException(sprintf(
                'ApiRequest.jwtAuth.cypherKey is %d bytes; %s requires at least 32 under php-jwt 7.',
                strlen($key),
                $algorithm
            ));
        }

        try {
            $payload = JWT::decode($token, new Key($key, $algorithm));
        } catch (Throwable) {
            //Expired, wrong signature, malformed: one answer for all of them.
            return null;
        }

        $subject = $payload->sub ?? null;

        return is_numeric($subject) ? (int) $subject : null;
    }

    /**
     * Whether the account holds `sch_api_bot`.
     *
     * `user_role_linker.role_id` is `user_role.id`, not the slug — the join is the
     * point, and reading the linker alone would compare an integer against a name and
     * silently answer no for everyone.
     */
    private function holdsRequiredRole(int $userId): bool
    {
        //`Adapter::class`, not `AdapterInterface::class`: the application registers the
        //concrete class and nothing aliases the interface, so asking for the interface
        //is a ServiceNotCreatedException rather than a missing-role answer.
        /** @var Adapter $adapter */
        $adapter = $this->laminas->get(Adapter::class);

        $sql    = new Sql($adapter);
        $select = $sql->select()
            ->from(['l' => 'user_role_linker'])
            ->columns(['user_id'])
            ->join(['r' => 'user_role'], 'l.role_id = r.id', [])
            ->where(['l.user_id' => $userId, 'r.role_id' => self::REQUIRED_ROLE]);

        $row = $sql->prepareStatementForSqlObject($select)->execute()->current();

        //`is_array`, not `null !== $row`: laminas-db's result returns **false** for an
        //empty set, and `false !== null` is true — so the first version of this line
        //answered "holds the role" for every account with a valid token, which is the
        //whole authorization model inverted. test/Smoke/ApiV3SmokeTest's
        //testAValidTokenWithoutTheBotRoleIsRefused is what caught it.
        return is_array($row) || is_object($row);
    }
}
