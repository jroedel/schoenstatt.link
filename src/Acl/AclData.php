<?php

declare(strict_types=1);

namespace App\Acl;

use function array_key_exists;

/**
 * The assembled authorization model as flat data: the role hierarchy and the allow-rules,
 * indexed by resource. Immutable, cacheable, and free of any framework — the decision
 * itself is {@see decide()}, and App\Acl\AclVoter feeds it through symfony/security-core.
 *
 * This is the replacement for what BjyAuthorize built on top of Laminas\Permissions\Acl.
 * The model it reproduces, exactly:
 *
 * - **Roles inherit.** Each role has at most one parent (the `user_role.parent_id` chain);
 *   a role "has" itself plus every ancestor. `$hierarchy` maps a role to its **direct
 *   parent** only — the transitive closure is symfony's RoleHierarchy job.
 * - **A rule is `allow(roles, resource, privileges)`.** Empty `privileges` means *all*
 *   privileges, which is what a route guard and a resource-wide grant produce.
 * - **Default deny.** No matching allow rule → denied.
 * - **Privilege semantics, the load-bearing subtlety.** `isAllowed(resource, null)` — the
 *   form the route guard uses — is granted only by an all-privileges rule; a resource that
 *   carries only specific-privilege rules (e.g. `publication_public` allows `show`) is
 *   **not** allowed for the null privilege. `isAllowed(resource, 'show')` is granted by a
 *   `show` rule or an all-privileges rule. Reproduced from Laminas\Permissions\Acl, and
 *   pinned by test/Integration/AclParityTest against the live BjyAuthorize ACL.
 *
 * @phpstan-type AclRule array{roles: list<string>, privileges: list<string>, allRoles: bool}
 * @phpstan-type RulesByResource array<string, list<AclRule>>
 */
final class AclData
{
    /**
     * @param array<string, string> $hierarchy       role => direct parent role
     * @param RulesByResource         $rulesByResource resource => allow rules; an empty
     *        `privileges` list means all privileges, and `allRoles` true means the rule
     *        grants every role (a Laminas `null` role entry)
     */
    public function __construct(
        private readonly array $hierarchy,
        private readonly array $rulesByResource,
    ) {
    }

    /**
     * role => direct parent, for constructing symfony's RoleHierarchy.
     *
     * @return array<string, list<string>>
     */
    public function hierarchyMap(): array
    {
        //RoleHierarchy wants role => list of roles it grants. A role grants (inherits) its
        //parent, so one-element lists give the transitive ancestor closure.
        $map = [];
        foreach ($this->hierarchy as $role => $parent) {
            $map[$role] = [$parent];
        }

        return $map;
    }

    /**
     * Every resource the model knows a rule for — the set AclParityTest enumerates.
     *
     * @return list<string>
     */
    public function resources(): array
    {
        return array_values(array_keys($this->rulesByResource));
    }

    /**
     * Every distinct privilege named in any rule — the set AclParityTest sweeps each
     * resource against, together with the null "resource generally" query.
     *
     * @return list<string>
     */
    public function privileges(): array
    {
        $seen = [];
        foreach ($this->rulesByResource as $rules) {
            foreach ($rules as $rule) {
                foreach ($rule['privileges'] as $p) {
                    $seen[$p] = true;
                }
            }
        }

        return array_keys($seen);
    }

    /**
     * Is any of `$reachableRoles` (a role plus its ancestors, already expanded by the
     * caller) allowed `$privilege` on `$resource`? `null` privilege asks the Laminas
     * "the resource generally" question — see the class docblock.
     *
     * @param list<string> $reachableRoles
     */
    public function decide(array $reachableRoles, string $resource, ?string $privilege): bool
    {
        if (! array_key_exists($resource, $this->rulesByResource)) {
            return false;
        }

        $reachable = [];
        foreach ($reachableRoles as $r) {
            $reachable[$r] = true;
        }

        foreach ($this->rulesByResource[$resource] as $rule) {
            $roleMatches = $rule['allRoles'];
            if (! $roleMatches) {
                foreach ($rule['roles'] as $ruleRole) {
                    if (isset($reachable[$ruleRole])) {
                        $roleMatches = true;
                        break;
                    }
                }
            }
            if (! $roleMatches) {
                continue;
            }

            //an all-privileges rule (empty list) grants any privilege, including the null
            //"resource generally" query; a specific-privilege rule grants only that
            //privilege, and never the null query
            if ([] === $rule['privileges']) {
                return true;
            }
            if (null !== $privilege && in_array($privilege, $rule['privileges'], true)) {
                return true;
            }
        }

        return false;
    }
}
