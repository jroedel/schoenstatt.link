<?php

declare(strict_types=1);

namespace App\Sitemap;

use App\Controller\AssociationController;
use App\Laminas\ServiceBridge;
use BjyAuthorize\Service\Authorize;
use Laminas\Permissions\Acl\Acl;
use Throwable;

/**
 * Whether an anonymous visitor can actually reach a page — the filter the sitemap never had.
 *
 * ## What was in the sitemap that should not have been
 *
 * `SitemapController` used to say, deliberately, that "no page was ever ACL-filtered out of
 * the sitemap … a sitemap is for a crawler, which is always anonymous anyway". The second
 * half is true and is exactly why the first half was wrong. Measured against production on
 * 2026-08-13, the published sitemap contained:
 *
 *  - **`/admin`**, in five languages, guarded `sch_moderator`/`translator`.
 *  - **`/movement`** (route `schoenstatt`), guarded `sch_moderator`.
 *  - **`/libraries`** and one library, guarded `lib_administrator`.
 *
 * All of them answer 302 to `/user/login`, which is Google's "URLs not followed" error, and
 * advertising an admin URL to every crawler on the internet is poor hygiene independent of
 * any report. 20 entries, so this is about correctness rather than volume — the volume is in
 * `publicKinds()` below.
 *
 * ## Two rules, because one of them is not in the ACL
 *
 * `isRoutePublic()` asks the ACL, which catches every guarded route including ones added
 * later. It cannot catch `AssociationController`'s rule: `route/association` is guarded
 * `guest` and therefore public, and the *controller* then redirects an anonymous visitor to
 * the home page for every association whose kind is not `sch-shrine` or
 * `sch-wayside-shrine`. That is 248 of 498 associations — 1,240 sitemap entries pointing at
 * a redirect to `/en/`, which is also the shape Google reads as a soft 404. `publicKinds()`
 * is the second rule, and it reads the constant on the controller itself so the two cannot
 * drift.
 */
final class GuestAccess
{
    /**
     * The role an unauthenticated visitor has. BjyAuthorize's `default_role`, and the role
     * a crawler is, always.
     */
    private const ANONYMOUS_ROLE = 'guest';

    private ?Acl $acl = null;

    /** @var array<string, bool> */
    private array $decided = [];

    public function __construct(private readonly ServiceBridge $laminas)
    {
    }

    /**
     * May `guest` reach this laminas route?
     *
     * An unguarded route is public — that is what BjyAuthorize's route guard does with a
     * route it has no rule for, and `literature` is one of them. A route the ACL has never
     * heard of is therefore *allowed*, not denied: refusing what we cannot find would empty
     * the sitemap the first time a resource name changed shape, and an over-broad sitemap is
     * a recoverable mistake where an empty one is a Google error in its own right.
     *
     * Failing to build the ACL at all is treated the same way, and for the same reason.
     */
    public function isRoutePublic(string $routeName): bool
    {
        if ('' === $routeName) {
            return true;
        }
        if (isset($this->decided[$routeName])) {
            return $this->decided[$routeName];
        }

        $acl = $this->acl();
        if (null === $acl) {
            return $this->decided[$routeName] = true;
        }

        $resource = 'route/' . $routeName;

        try {
            if (! $acl->hasResource($resource)) {
                return $this->decided[$routeName] = true;
            }

            //asked of `guest` explicitly rather than of the current identity: this runs in a
            //console process with no identity at all, and even in a request the answer has
            //to be the crawler's, not the administrator's who triggered the rebuild
            return $this->decided[$routeName] = $acl->isAllowed(self::ANONYMOUS_ROLE, $resource);
        } catch (Throwable) {
            return $this->decided[$routeName] = true;
        }
    }

    /**
     * May `guest` do `$privilege` to `$resource`?
     *
     * For the ACL resources that are *not* routes. `Books\Model\LibraryTable` is both a
     * resource and a rule provider: every library becomes a `library_<id>` resource whose
     * `show` privilege is granted to that row's `ViewRole`. So `/libraries/1` is public and
     * `/libraries/5` is not, from one route with one guard entry — a distinction
     * `isRoutePublic()` cannot make and `docs/acl-baseline.json` records as a dynamic
     * provider rather than a rule.
     *
     * Measured: library 5 is `lib_user` and answered 302 to the login page while sitting in
     * the sitemap.
     */
    public function isAllowedAsGuest(string $resource, ?string $privilege = null): bool
    {
        $acl = $this->acl();
        if (null === $acl) {
            return true;
        }

        try {
            if (! $acl->hasResource($resource)) {
                //an unknown resource is not a denial, for the same reason an unguarded route
                //is not: this filter fails open, and the sitemap is over-broad rather than
                //empty when the ACL and the navigation disagree about what exists
                return true;
            }

            return $acl->isAllowed(self::ANONYMOUS_ROLE, $resource, $privilege);
        } catch (Throwable) {
            return true;
        }
    }

    /**
     * The association kinds an anonymous visitor is allowed to see.
     *
     * Read from `AssociationController::PUBLIC_KINDS` so that the sitemap and the controller
     * enforcing the rule are the same list. If the controller ever gains a third public
     * kind, the sitemap publishes it on the next build with nothing to change here.
     *
     * @return list<string>
     */
    public function publicAssociationKinds(): array
    {
        return AssociationController::PUBLIC_KINDS;
    }

    private function acl(): ?Acl
    {
        if (null !== $this->acl) {
            return $this->acl;
        }

        try {
            $authorize = $this->laminas->get(Authorize::class);
            if (! $authorize instanceof Authorize) {
                return null;
            }
            $acl = $authorize->getAcl();
        } catch (Throwable) {
            return null;
        }

        if (! $acl instanceof Acl) {
            return null;
        }

        try {
            if (! $acl->hasRole(self::ANONYMOUS_ROLE)) {
                //no guest role means every isAllowed() below would throw; treat the whole
                //check as unavailable rather than answering "denied" 7,000 times
                return null;
            }
        } catch (Throwable) {
            return null;
        }

        return $this->acl = $acl;
    }
}
