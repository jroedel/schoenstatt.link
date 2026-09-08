<?php

declare(strict_types=1);

namespace App\Acl;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

use function is_string;

/**
 * The one voter: it answers "may these roles do <privilege> on <resource>?" against the
 * assembled {@see AclData}, expanding the token's roles through the role hierarchy first.
 *
 * The subject is the resource (a string like `route/admin`, `publication_public` or
 * `library_4`); the single attribute is the privilege, or {@see self::ANY} for the
 * Laminas "resource generally" question the route guard asks. It abstains on anything that
 * is not one of our resources, so it never interferes with a future ROLE_* check.
 */
final class AclVoter implements VoterInterface
{
    /** The privilege attribute standing in for a null privilege — see AclData. */
    public const ANY = "\0acl-any-privilege";

    public function __construct(
        private readonly AclData $data,
        private readonly RoleHierarchyInterface $roleHierarchy,
    ) {
    }

    public function vote(TokenInterface $token, mixed $subject, array $attributes, ?Vote $vote = null): int
    {
        if (! is_string($subject) || '' === $subject) {
            return self::ACCESS_ABSTAIN;
        }
        if ([] === $this->data->resources() || ! in_array($subject, $this->data->resources(), true)) {
            //an unknown resource is default-deny in Laminas, but a voter must abstain on
            //subjects it does not own so the decision manager's strategy decides — and with
            //this the only voter, abstain becomes deny, which is the same answer
            return self::ACCESS_ABSTAIN;
        }

        $reachable = $this->roleHierarchy->getReachableRoleNames($token->getRoleNames());

        foreach ($attributes as $attribute) {
            if (! is_string($attribute)) {
                continue;
            }
            $privilege = self::ANY === $attribute ? null : $attribute;
            if ($this->data->decide($reachable, $subject, $privilege)) {
                return self::ACCESS_GRANTED;
            }
        }

        return self::ACCESS_DENIED;
    }
}
