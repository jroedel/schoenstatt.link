<?php

declare(strict_types=1);

namespace App\Http;

use App\Laminas\ServiceBridge;
use Closure;
use JUser\Session\SessionPruner;
use Laminas\Session\ManagerInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Throwable;

use function function_exists;
use function session_name;
use function session_status;
use function session_unset;

use const PHP_SESSION_ACTIVE;

/**
 * Starts and repairs the session for Symfony-served routes, which is what
 * JUser\Module::onBootstrap() does for every other request and nothing did for these.
 *
 * The bug this fixes was live on every ported HTML route, and measured on 2026-08-07:
 * a visitor holding a session written before the Zend → Laminas class renames got an
 * **empty 200** from /en/, /en/shrines, /en/wayside-shrines and /en/admin, while
 * every laminas-served page served them fine. The chain is:
 *
 *   templates/layout.html.twig calls flash_messages() on every page
 *     → Laminas\Session\AbstractContainer::verifyNamespace()
 *     → "Container cannot write to storage due to type mismatch"
 *        because the stored value unserialized to __PHP_Incomplete_Class
 *     → Twig\Error\RuntimeError, recorded by SionModel\Error\FatalErrorHandler,
 *        which emits no body
 *
 * JUser\Session\SessionPruner exists precisely for this and is documented as
 * self-healing on the next hit — but only `onBootstrap` ever called it, and a
 * Symfony-served route never boots laminas-mvc. So the "self-healing" never happened
 * and the visitor stayed broken for the 30-day life of the cookie. Latent in
 * production only because SYMFONY_KERNEL is still unset there.
 *
 * Two properties are deliberate:
 *
 * **It only acts when the request already carries a session cookie.** No cookie means
 * no stored session, so there is nothing to prune and nothing to validate — and
 * starting one anyway would put a session, a Set-Cookie and the laminas module load
 * on /_health and the two maintenance endpoints, which docs/strangler.md is explicit
 * about not doing. An HTML page pays nothing extra either way: its layout already
 * reaches laminas for translate(), is_allowed() and the navbar.
 *
 * **It starts the session through Laminas\Session\ManagerInterface**, not
 * session_start(), so the `session_manager.validators` config applies here exactly as
 * it does on a bridged request. That also means the session is already active by the
 * time BjyAuthorize asks JUser for the identity, so the bare session_start() that
 * call would otherwise perform becomes a no-op — the ported page keeps the identity
 * it has today, and gains the validation it did not.
 *
 * The try/catch reproduces onBootstrap's, including `session_unset()`: a session that
 * fails validation is discarded rather than allowed to fatal the request. The bridge
 * is resolved through a closure for the same reason App\Http\AuthorizationListener
 * does it — listeners are registered before the request exists.
 */
final class SessionListener
{
    /** Below RouterListener's 32, because isPorted() needs the matched route, and above the guard, which reads the identity. */
    public const PRIORITY = 16;

    /** @var Closure(): ServiceBridge */
    private Closure $bridge;

    /** @param Closure(): ServiceBridge $bridge */
    public function __construct(Closure $bridge)
    {
        $this->bridge = $bridge;
    }

    public function __invoke(RequestEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (! SymfonyRoute::isPorted($request)) {
            return;
        }
        //no cookie, no stored session: nothing to repair, and nothing to start
        if (null === $request->cookies->get(self::cookieName())) {
            return;
        }

        try {
            /** @var ManagerInterface $manager */
            $manager = ($this->bridge)()->get(ManagerInterface::class);
            $manager->start();
            if (PHP_SESSION_ACTIVE === session_status()) {
                SessionPruner::pruneIncompleteClassValues($_SESSION);
            }
        } catch (Throwable) {
            //exactly what JUser\Module::onBootstrap() does with a session that fails
            //validation: drop its contents rather than fatal the request
            if (PHP_SESSION_ACTIVE === session_status()) {
                session_unset();
            }
        }
    }

    /**
     * The name PHP will look for, read from the ini rather than hardcoded so a
     * `session.name` change cannot make this listener quietly stop firing.
     */
    private static function cookieName(): string
    {
        $name = function_exists('session_name') ? session_name() : '';

        return false === $name || '' === $name ? 'PHPSESSID' : $name;
    }
}
