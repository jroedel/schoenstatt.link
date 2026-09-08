<?php

namespace SchoenstattTest\Unit;

use PHPUnit\Framework\TestCase;
use SionModel\Cache\EntityChangeListenerInterface;
use SionModel\Cache\EntityChangeListeners;

require_once __DIR__ . '/../../module/SionModel/src/Cache/EntityChangeListenerInterface.php';
require_once __DIR__ . '/../../module/SionModel/src/Cache/EntityChangeListeners.php';

/**
 * The registry SionModel notifies from `removeDependentCacheItems()`, i.e. from every
 * create, update and delete in every module.
 *
 * Small enough to look obviously right, and pinned anyway because of what hangs off it
 * here: an ACL-cache invalidator (removed with the ACL cutover; the new engine caches nothing across requests). A
 * notification that never arrives is not a stale page —
 * `Laminas\Permissions\Acl\Acl::addRole()` throws on a parent role it has never heard of,
 * so an ACL cached before a role existed is a 500 on every request by whoever was granted
 * it. The invalidator's own behaviour needs laminas-cache types and is covered in
 * test/Integration/AclCacheTest.php; this file needs no vendor autoload at all.
 */
class EntityChangeListenersTest extends TestCase
{
    public function testEveryListenerIsToldWhichEntityChanged(): void
    {
        $registry = new EntityChangeListeners();
        $first    = new RecordingEntityChangeListener();
        $second   = new RecordingEntityChangeListener();
        $registry->add($first);
        $registry->add($second);

        $registry->notify('library');

        $this->assertSame(['library'], $first->seen);
        $this->assertSame(['library'], $second->seen);
    }

    /**
     * Registering the same listener twice notifies once, for the same reason
     * CacheFlushQueue dedupes: a factory can run its wiring more than once, and a
     * listener that does real work would then do it twice.
     */
    public function testRegisteringTheSameListenerTwiceNotifiesOnce(): void
    {
        $registry = new EntityChangeListeners();
        $listener = new RecordingEntityChangeListener();
        $registry->add($listener);
        $registry->add($listener);

        $registry->notify('text');

        $this->assertSame(['text'], $listener->seen);
    }

    /** Two writes in one request are two notifications, not one deduplicated event. */
    public function testEachChangeIsItsOwnNotification(): void
    {
        $registry = new EntityChangeListeners();
        $listener = new RecordingEntityChangeListener();
        $registry->add($listener);

        $registry->notify('user-role');
        $registry->notify('user-role');
        $registry->notify('library');

        $this->assertSame(['user-role', 'user-role', 'library'], $listener->seen);
    }

    public function testARegistryWithNoListenersIsSilentRatherThanAnError(): void
    {
        (new EntityChangeListeners())->notify('anything');

        $this->addToAssertionCount(1);
    }
}

class RecordingEntityChangeListener implements EntityChangeListenerInterface
{
    /** @var list<string> */
    public array $seen = [];

    public function entityChanged(string $entity): void
    {
        $this->seen[] = $entity;
    }
}
