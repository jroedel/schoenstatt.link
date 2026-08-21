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
 * The site was already issuing JWTs of this shape — `{sub, exp, jti}`, signed with
 * `ApiRequest.jwtAuth.cypherKey` — before v3 existed, so this is not a new scheme;
 * `JUser\Service\ApiTokenService` is now the only signer, and the users screen the only
 * way to obtain one. (This used to add "and the mobile apps already use them" of
 * `JUser\Controller\LoginV1ApiController`, which issued them until /api/v1 was retired
 * on 2026-08-14. The access log says no mobile client ever signed in there — see
 * docs/strangler.md. The token *format* is what carried over, not a live caller.)
 * A bot gets one the same way any other account does, so there is no second
 * credential store to build, leak or forget to rotate. `sub` is a `user.user_id`,
 * which is what makes an agent's edits attributable: it goes straight into
 * `SionTable::setActingUserId()`, and every field an agent changes lands in
 * `sch_changes` under that account.
 *
 * ## A token must also be one we still vouch for
 *
 * A valid signature says the token came from us. It does not say the token is still
 * meant to work. Since db6.7 every issued token has a row in `user_api_token` keyed
 * by its `jti`, and this class refuses any token whose `jti` is missing from that
 * table or marked revoked — so revocation is per *token*, takes effect on the next
 * request, and does not require deleting the account's role.
 *
 * Fail closed, not fail open: an unregistered token is refused, not admitted. See
 * tokenIsLive().
 *
 * ## The role is the rest of the authorization model, and it is per resource
 *
 * Holding a live token is not enough. The account must also hold the role the
 * *endpoint* asks for — `sch_api_bot` for associations (database/db6.6.sql),
 * `sch_api_translator` for translation phrases (database/db6.8.sql) — each named by
 * nothing else on the site. Three consequences, all intended:
 *
 * - A leaked bot token reaches the v3 API and nothing else. It cannot open the edit
 *   form, /admin, or any other guarded page, because no other guard names the role.
 * - A human's token cannot write through the API by accident. `sch_user` — which
 *   every registered account holds, and which *does* open the edit form — is not
 *   `sch_api_bot`.
 * - **A leaked token of one agent does not reach another agent's resource.** The
 *   required role is a parameter of resolve() and not a constant of this class,
 *   because the first version made it a constant and the reach of every credential
 *   would then have widened silently each time v3 grew an endpoint. An account may
 *   hold both roles; the point is that somebody has to have granted both.
 *
 * Roles are read straight from `user_role_linker` rather than through BjyAuthorize's
 * identity provider, because that provider is the session-reading one. It is the same
 * table the provider reads, and the same table the moderator UI writes, so granting
 * and revoking a bot works through the existing screens.
 *
 * ## Failure is deliberately uninformative
 *
 * Missing token, malformed token, expired token, unknown user, missing role, revoked
 * or unregistered token: all 401 with one message. Distinguishing them tells an attacker which half of a guess was
 * right, and no legitimate agent needs to be told — its token either works from the
 * first request or was issued wrongly.
 */
final class BotIdentity
{
    /** The role an account must hold to read and write associations through v3. */
    public const REQUIRED_ROLE = 'sch_api_bot';

    /** The role an account must hold to read and write translation phrases through v3. */
    public const TRANSLATOR_ROLE = 'sch_api_translator';

    public function __construct(private readonly ServiceBridge $laminas)
    {
    }

    /**
     * The acting user id behind a bearer token, or null if the request may not proceed.
     *
     * @param string|null $authorization the raw `Authorization` header
     * @param string $requiredRole the role this endpoint's resource is gated on
     */
    public function resolve(?string $authorization, string $requiredRole = self::REQUIRED_ROLE): ?int
    {
        $token = self::bearerToken($authorization);
        if (null === $token) {
            return null;
        }

        $claims = $this->claimsFrom($token);
        if (null === $claims) {
            return null;
        }

        [$userId, $jti] = $claims;

        return $this->tokenIsLive($jti, $userId)
            && $this->accountIsActive($userId)
            && $this->holdsRole($userId, $requiredRole)
            ? $userId
            : null;
    }

    /**
     * Whether the account may act at all.
     *
     * `user`.`state` means "may sign in" as of 2026-08-21, and a bearer token is a
     * sign-in that skips the sign-in page — so it has to answer to the same switch.
     * Without this, unticking Active on `/users/{id}/edit` stopped a bot's *human* from
     * getting a magic link and left its six-month credential working, which makes
     * "deactivate" mean two different things depending on which door the caller uses.
     * That is the confusion the state/email_verified split exists to remove.
     *
     * It is a **third** check rather than a clause bolted onto `holdsRole()`, and
     * deliberately so: a deactivated account and an account that was never granted the
     * role are different facts, and folding them into one query makes the log and the
     * next reader unable to tell them apart. Revoking the token itself remains the
     * narrower instrument — this is the one that revokes everything at once.
     *
     * Both API roles belong to accounts nobody signs in to, so this switch had no
     * observable effect on them before today.
     */
    private function accountIsActive(int $userId): bool
    {
        /** @var Adapter $adapter */
        $adapter = $this->laminas->get(Adapter::class);

        $sql    = new Sql($adapter);
        $select = $sql->select()
            ->from('user')
            ->columns(['user_id'])
            ->where(['user_id' => $userId, 'state' => 1]);

        $row = $sql->prepareStatementForSqlObject($select)->execute()->current();

        //`is_array || is_object`, matching holdsRole() below: laminas-db answers **false**
        //for an empty set, and every `!== null` test against that inverts the answer.
        return is_array($row) || is_object($row);
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

    /**
     * The `sub` and `jti` claims of a token that verifies, or null for one that does not.
     *
     * Both are required. A token with no `jti` cannot be looked up in the registry
     * and therefore cannot be revoked, so it is refused rather than trusted — see
     * tokenIsLive() on why that direction is the only one that means anything.
     *
     * @return array{0: int, 1: string}|null
     */
    private function claimsFrom(string $token): ?array
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
        $jti     = $payload->jti ?? null;

        if (! is_numeric($subject) || ! is_string($jti) || '' === $jti) {
            return null;
        }

        return [(int) $subject, $jti];
    }

    /**
     * Whether this exact token is one we issued to this account and have not revoked.
     *
     * **Fail closed.** Not "refuse if revoked" but "refuse unless positively
     * vouched for": a token whose `jti` has no row is refused just as firmly as
     * one whose row is revoked. The difference matters because those are the same
     * thing from the outside — an unregistered token is indistinguishable from a
     * forged one at the point of use, and a registry that only rejects what it has
     * explicitly heard of protects nothing.
     *
     * This is affordable to switch on because it shipped before any bot account
     * existed in production. It does not touch the v1 API, which the mobile apps
     * authenticate against with tokens minted before the registry existed: v1
     * never consults this table.
     *
     * The user id is part of the query rather than compared afterwards, so a valid
     * `jti` belonging to a different account cannot vouch for this one.
     */
    private function tokenIsLive(string $jti, int $userId): bool
    {
        /** @var Adapter $adapter */
        $adapter = $this->laminas->get(Adapter::class);

        $sql    = new Sql($adapter);
        $select = $sql->select('user_api_token')
            ->columns(['token_id'])
            ->where([
                'jti'        => $jti,
                'user_id'    => $userId,
                'revoked_on' => null,
            ]);

        $row = $sql->prepareStatementForSqlObject($select)->execute()->current();

        //Expiry is deliberately not re-checked here: php-jwt has already rejected
        //an expired token on its own `exp` claim before this method is reached, and
        //a second copy of the rule is a second thing to get wrong.
        //`is_array`, not `null !== $row` — laminas-db answers **false** for an empty
        //result set. See holdsRole() below; the same trap inverted the whole
        //authorization model once already.
        return is_array($row) || is_object($row);
    }

    /**
     * Whether the account holds the named role.
     *
     * `user_role_linker.role_id` is `user_role.id`, not the slug — the join is the
     * point, and reading the linker alone would compare an integer against a name and
     * silently answer no for everyone.
     *
     * Roles do not inherit here. `user_role.parent_id` builds a hierarchy that
     * BjyAuthorize walks, and walking it would mean an administrator's account
     * satisfying an API role it was never granted. Both API roles are deliberately
     * parentless (database/db6.6.sql, database/db6.8.sql), so an exact match is the
     * whole question.
     */
    private function holdsRole(int $userId, string $requiredRole): bool
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
            ->where(['l.user_id' => $userId, 'r.role_id' => $requiredRole]);

        $row = $sql->prepareStatementForSqlObject($select)->execute()->current();

        //`is_array`, not `null !== $row`: laminas-db's result returns **false** for an
        //empty set, and `false !== null` is true — so the first version of this line
        //answered "holds the role" for every account with a valid token, which is the
        //whole authorization model inverted. test/Smoke/ApiV3SmokeTest's
        //testAValidTokenWithoutTheBotRoleIsRefused is what caught it.
        return is_array($row) || is_object($row);
    }
}
