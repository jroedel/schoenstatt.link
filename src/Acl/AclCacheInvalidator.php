<?php

declare(strict_types=1);

namespace App\Acl;

use Laminas\Cache\Storage\StorageInterface;
use SionModel\Cache\EntityChangeListenerInterface;
use Throwable;

use function in_array;

/**
 * Drops the cached BjyAuthorize ACL when a row it was assembled from changes.
 *
 * ## Why the ACL is cached at all
 *
 * `Authorize::load()` costs **~6 ms on every request**, anonymous ones included —
 * measured in the web SAPI, in-request, 2026-08-22. Half of `/en/developers`, whose
 * whole render is 12 ms. It resolves four provider collections, builds two SionTables
 * to get at their resources, and assembles an `Acl` of 45 roles, 132 resources and 34
 * rules that is byte-identical for every visitor. Reading it back instead costs
 * `unserialize()` on 100 kB: **0.37 ms**. BjyAuthorize already supports this; it was
 * switched off here because the package's default adapter is `memory`, which caches
 * nothing across requests, and turning it on without an APCu adapter behind it would
 * have looked like a change and done nothing.
 *
 * ## Why it is invalidated here rather than from the write paths
 *
 * Three entities feed the ACL, and each reaches it by a different provider:
 *
 * | entity | what it contributes | where from |
 * |---|---|---|
 * | `user-role` | the 45 roles and their hierarchy | `BjyAuthorize\Provider\Role\LaminasDb` |
 * | `library` | one resource and three rules per library | `Books\Model\LibraryTable` |
 * | `text` | one resource per distinct `texts.AclResourceId` | `Books\Model\EventTextTable` |
 *
 * Writes to those arrive from at least four places across two repositories — the
 * spec-driven `App\Sion\Entity{Create,Edit,Delete}` trio and JUser's own role-create
 * controller — and a fifth is one feature away. `SionCacheTrait::removeDependentCacheItems()`
 * is the one point all of them pass through, so that is where the notification comes from;
 * see `SionModel\Cache\EntityChangeListeners`.
 *
 * That is a correctness decision, not tidiness. **A missed role invalidation is not a stale
 * page.** `Acl::addRole()` throws on a parent role it has never heard of, and `Authorize::load()`
 * adds the identity's roles as exactly that — so an ACL that predates a role creation is a
 * **500 on every request by whoever was granted that role**, until the item expires. The TTL
 * in `acl.global.php` is the backstop for a listener that somehow never runs; it is not the
 * plan.
 *
 * Deploys need no entry here: config guards and rules only change with a release, and
 * `tools/deploy.sh` flushes APCu as its last step.
 */
final class AclCacheInvalidator implements EntityChangeListenerInterface
{
    /**
     * Entities the assembled ACL is built from. Anything else is none of this class's
     * business — `user-role-link`, in particular, is *not* here: which roles an account
     * holds is read per request by the identity provider and never enters the cached ACL.
     */
    private const ENTITIES = ['user-role', 'library', 'text'];

    public function __construct(
        private readonly StorageInterface $cache,
        private readonly string $cacheKey
    ) {
    }

    public function entityChanged(string $entity): void
    {
        if (! in_array($entity, self::ENTITIES, true)) {
            return;
        }

        try {
            $this->cache->removeItem($this->cacheKey);
        } catch (Throwable) {
            //This runs inside the write that triggered it. A cache that cannot be
            //reached is a slow next request; letting it out of here would fail the
            //write itself, which is the worse of the two by a wide margin. The item
            //has a TTL for exactly this case.
        }
    }
}
