<?php

declare(strict_types=1);

namespace App\JUser\Host;

use App\Session\HttpSession;
use JUser\Host\SessionInterface;

/**
 * `JUser\Host\SessionInterface` over this application's session.
 *
 * The session is already started by the time any of this runs — `App\Http\SessionListener`
 * does it — so the bags below read and write whatever the request arrived with.
 *
 * Until 2026-09-21 this sat on `Laminas\Session\Container` and a `SessionManager`, and the
 * docblock here recorded a narrowing that mattered: `ManagerInterface::regenerateId()`
 * declared no parameters, so `regenerateId(true)` — retire the old session server-side,
 * which is the entire point at a privilege change — did not type-check against it, and
 * this class depended on the concrete class to get the argument through.
 * {@see HttpSession::regenerateId()} takes it, so the narrowing is gone with the package.
 *
 * ## One session object per request
 *
 * Registered in the laminas container as `JUser\Host\SessionInterface` and handed the same
 * {@see HttpSession} the kernel started, so the Symfony kernel and the laminas container
 * share one session and one identity. Two would each memoize their own answer, and a
 * sign-in through one would leave the other reporting an anonymous visitor.
 */
final class Session implements SessionInterface
{
    public function __construct(private readonly HttpSession $session)
    {
    }

    public function get(string $namespace, string $key): mixed
    {
        return $this->session->bag($namespace)->get($key);
    }

    public function set(string $namespace, string $key, mixed $value): void
    {
        $this->session->bag($namespace)->set($key, $value);
    }

    public function remove(string $namespace, string $key): void
    {
        $this->session->bag($namespace)->remove($key);
    }

    public function regenerateId(bool $destroyOld = true): void
    {
        $this->session->regenerateId($destroyOld);
    }

    public function forgetMe(): void
    {
        $this->session->forgetMe();
    }
}
