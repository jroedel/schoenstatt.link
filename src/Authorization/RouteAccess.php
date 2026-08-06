<?php

declare(strict_types=1);

namespace App\Authorization;

/**
 * What a Symfony-served route says about who may reach it.
 *
 * One instance per route, carried in the route's own defaults under
 * {@see self::ATTRIBUTE} and therefore visible in `config/symfony/routes.php`
 * beside the path it governs. That placement is the point: that file already *is*
 * the migration status of the site read top to bottom, and authorization is the
 * other half of the same status.
 *
 * ## It names an existing ACL resource — it does not invent one
 *
 * `guardedBy()` takes a resource string the laminas side already uses:
 * `BjyAuthorize\Guard\Route` keys on `route/<laminas-route-name>`, so
 * `guardedBy('route/admin')` consults exactly the guard entry in
 * `config/autoload/acl.global.php` (or a module config) that governs the laminas
 * route. Both front controllers then read one source of truth, which is what keeps
 * `tools/acl-table.php` an oracle rather than half a picture: tighten the guard
 * entry and the ported route tightens with it, in the same commit, with the
 * snapshot diff to prove it.
 *
 * A parallel `symfony_acl` config block would have been easier to write and is the
 * thing to refuse. Two authorization configurations for one site is the state where
 * a page is protected on one front controller and open on the other and nothing
 * fails.
 *
 * ## Public is a statement, not a default
 *
 * `openToEveryone()` requires a reason, and the reason is stored, printed by
 * `tools/acl-table.php` and cross-checked against the laminas guard it shadows. A
 * route that declares nothing at all is not treated as public: it raises
 * {@see UndeclaredRouteAccess}. Silence has to be an error rather than an
 * admission, because the whole hazard of this migration is that a route which lost
 * its guard keeps working.
 */
final class RouteAccess
{
    /**
     * Route default / request attribute the declaration travels in.
     *
     * Underscore-prefixed like Symfony's own (`_controller`, `_locale`, `_route`)
     * because it is framework plumbing rather than a routing placeholder, and
     * ArgumentResolver will never bind it to a controller parameter of that name.
     */
    public const ATTRIBUTE = '_access';

    private function __construct(
        /** The ACL resource to ask about, or null when this route is deliberately open. */
        public readonly ?string $resource,
        /** Why this route needs no check. Null exactly when $resource is set. */
        public readonly ?string $openReason,
        /** Never consulted when $resource is null: an open route has nothing to refuse. */
        public readonly DenialStyle $denialStyle
    ) {
    }

    /**
     * Check this route against an ACL resource the laminas guards already define.
     *
     * @param string $resource `route/<laminas-route-name>` for anything a
     *        BjyAuthorize route guard covers, which is every guarded page on the
     *        site. Any other resource the ACL knows works too, and a resource the
     *        ACL has never heard of denies everyone — Authorize::isAllowed() catches
     *        the registry's InvalidArgumentException and answers false. That is the
     *        safe direction for a typo, and the reason tools/acl-table.php warns
     *        when a declared resource matches no guard entry: default deny is right,
     *        but silently denying everyone is still a bug.
     */
    public static function guardedBy(string $resource, DenialStyle $denialStyle = DenialStyle::Html): self
    {
        return new self($resource, null, $denialStyle);
    }

    /**
     * No check at all, on purpose.
     *
     * Reserved for a route with no ACL resource to name — `/_health` shadows no
     * laminas route, so there is nothing to consult — and for one whose real gate
     * lives in the controller and whose shadowed guard is public anyway. Both are
     * cases where `guardedBy()` would cost a session and several database queries
     * to reach a foregone conclusion.
     *
     * @param string $reason recorded and printed by tools/acl-table.php. Write it
     *        for the reviewer who is asking "why is this one not checked?".
     */
    public static function openToEveryone(string $reason): self
    {
        return new self(null, $reason, DenialStyle::Html);
    }

    public function isOpen(): bool
    {
        return null === $this->resource;
    }
}
