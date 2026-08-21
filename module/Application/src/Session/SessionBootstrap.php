<?php

declare(strict_types=1);

namespace Application\Session;

use JUser\Session\SessionPruner;
use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ListenerAggregateInterface;
use Laminas\Mvc\MvcEvent;
use Laminas\Session\ManagerInterface as SessionManagerInterface;
use Throwable;

use function session_unset;

/**
 * Starts the session for a laminas-served request — **before any module's `onBootstrap()`
 * runs**, and that is the whole reason this is a listener aggregate rather than three lines
 * in `Application\Module::onBootstrap()`.
 *
 * ## Why the ordering is not a preference
 *
 * This moved out of `JUser\Module::onBootstrap()` on 2026-08-21, when that module stopped
 * hooking laminas-mvc at all: starting a session is an application's job, and JUser 3.0.0
 * has to be droppable into a host with no `MvcEvent` to hook.
 *
 * The obvious home was `Application\Module::onBootstrap()`, and it is wrong. Module
 * `onBootstrap` listeners are attached in `config/modules.config.php` order at one priority,
 * `JUser` sat at 42 and `Application` sits at 47 — and **`SionModel` is at 43, where its own
 * `onBootstrap()` calls `BjyAuthorize\Service\Authorize::getIdentity()`**. That call triggers
 * `Authorize::load()`, which resolves the identity's roles and **bakes them into the ACL for
 * the rest of the request**. Reached before the session exists, it bakes `guest` — and every
 * `isAllowed()` on that request then answers for an anonymous visitor, whoever is signed in.
 * Nothing fails; pages simply stop being reachable, or links stop being drawn.
 *
 * It would not have shown up in testing either: under the Symfony front controller
 * `App\Http\SessionListener` starts the session before `App\Http\LegacyBridge` builds the
 * laminas application at all, so the module order is invisible there — and the Symfony
 * kernel is what both the capsule and production run. The path this protects is
 * `SYMFONY_KERNEL=0`, which is the documented rollback, i.e. exactly the path that must work
 * when something else has already gone wrong.
 *
 * So it attaches at a priority above every module hook and is registered through
 * `config/application.config.php`'s `listeners` key, which `Application::init()` attaches
 * before it triggers `EVENT_BOOTSTRAP`. Order-independent, and it stays correct if anyone
 * reorders the module list.
 *
 * ## The two details that came with it
 *
 * **`start()` is wrapped.** A session that fails the `session_manager.validators` config
 * throws, and the right answer to a session we refuse is to discard it and carry on
 * anonymous rather than to fail the request. That was the whole job of the abandoned
 * BeaucalInvalidSession module.
 *
 * **Then it is pruned.** A session that *passes* validation can still hold a value whose
 * class no longer exists — written before a class-renaming migration — and it fatals at the
 * first container access rather than at `start()`, so nothing wraps it and the request dies
 * with no useful message. `JUser\Session\SessionPruner` exists for this and
 * `test/Unit/SessionPrunerTest` pins it.
 *
 * `App\Http\SessionListener` is the Symfony-side counterpart, with one addition this cannot
 * copy: it skips starting a session when the request carries no session cookie, so a page
 * that needs none gets no `Set-Cookie`. Here the laminas application is already being built
 * by the time anything can ask, so that saving is not available.
 */
final class SessionBootstrap implements ListenerAggregateInterface
{
    /**
     * Above every module `onBootstrap()`, which are attached at the default priority.
     * See the class docblock for what runs at 43 and why being after it is a defect.
     */
    public const PRIORITY = 10000;

    /** @var array<int, callable> */
    private array $listeners = [];

    public function __construct(private readonly SessionManagerInterface $sessions)
    {
    }

    public function attach(EventManagerInterface $events, $priority = self::PRIORITY): void
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_BOOTSTRAP, $this->onBootstrap(...), $priority);
    }

    public function detach(EventManagerInterface $events): void
    {
        foreach ($this->listeners as $index => $listener) {
            if ($events->detach($listener)) {
                unset($this->listeners[$index]);
            }
        }
    }

    public function onBootstrap(MvcEvent $event): void
    {
        try {
            $this->sessions->start();
            SessionPruner::pruneIncompleteClassValues($_SESSION);
        } catch (Throwable) {
            //a session we refuse is discarded, not a failed request — see the class docblock
            session_unset();
        }
    }
}
