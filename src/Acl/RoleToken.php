<?php

declare(strict_types=1);

namespace App\Acl;

use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;

/**
 * A minimal security token that carries a role-name set and nothing else.
 *
 * The application's identity is JUser's (a session holding a user id), not a Symfony
 * authenticated token, so authorization needs only the role *names* — which is exactly
 * what BjyAuthorize consulted too (it added the identity's role names to the ACL). This
 * wraps a role list so `AccessDecisionManager` and {@see AclVoter} can be the standard
 * symfony/security-core stack without dragging in Symfony authentication.
 */
final class RoleToken extends AbstractToken
{
    /** @param list<string> $roles */
    public function __construct(array $roles)
    {
        parent::__construct($roles);
    }
}
