<?php

declare(strict_types=1);

namespace App\Acl;

use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Role\RoleHierarchy;

/**
 * The authorization decision, on symfony/security-core, replacing BjyAuthorize's
 * `Authorize::isAllowed()` over Laminas\Permissions\Acl.
 *
 * It wires the standard stack — a {@see RoleHierarchy} from the assembled model, the one
 * {@see AclVoter}, and an {@see AccessDecisionManager} (affirmative strategy, the default:
 * one voter, so its GRANTED/DENIED is the answer). {@see AclAssembler} builds the model;
 * test/Integration/AclParityTest proves this agrees with the live BjyAuthorize ACL for
 * every role, resource and privilege before anything is cut over to it.
 *
 * The decision is expressed over a **role-name set** rather than an identity, because that
 * is the whole of what authorization needs and it is what BjyAuthorize used too. Turning
 * the current JUser identity into that set (and into route-guard and `isAllowed()` call
 * sites) is the cutover, and lands separately.
 */
final class Authorizer
{
    private readonly AccessDecisionManager $accessDecisionManager;

    public function __construct(private readonly AclData $data)
    {
        $hierarchy = new RoleHierarchy($data->hierarchyMap());
        $this->accessDecisionManager = new AccessDecisionManager([new AclVoter($data, $hierarchy)]);
    }

    /**
     * Whether any of `$roleNames`, plus every role they inherit, is allowed `$privilege`
     * on `$resource`. A null privilege asks the "resource generally" question the route
     * guard asks — see {@see AclData}.
     *
     * @param list<string> $roleNames
     */
    public function isAllowedForRoles(array $roleNames, string $resource, ?string $privilege = null): bool
    {
        return $this->accessDecisionManager->decide(
            new RoleToken($roleNames),
            [$privilege ?? AclVoter::ANY],
            $resource
        );
    }

    /**
     * Whether the model carries any rule for `$resource`. A caller that must fail *open* on
     * an unknown resource (the sitemap) asks this first; the ordinary path does not need it,
     * because {@see isAllowedForRoles()} already denies an unknown resource.
     */
    public function hasResource(string $resource): bool
    {
        return $this->data->hasResource($resource);
    }

    /**
     * The distinct privileges the model names — for test/Integration/AclParityTest to
     * sweep. Not part of the runtime decision; exposed here so the assembled data stays
     * encapsulated behind this facade.
     *
     * @return list<string>
     */
    public function privilegesForTest(mixed $unused = null): array
    {
        return $this->data->privileges();
    }
}
