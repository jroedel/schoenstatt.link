<?php

declare(strict_types=1);

namespace App\JUser\Host;

use App\Laminas\ServiceBridge;
use JUser\Host\IdentityInterface;
use JUser\Model\User;
use Laminas\Authentication\AuthenticationService;

/**
 * `JUser\Host\IdentityInterface` over this application's one authentication service.
 *
 * `zfcuser_auth_service` — a name, kept because renaming it breaks every guard entry and
 * factory that uses it; ZfcUser has not been installed here for years.
 *
 * ## Reading it costs a database row per request, and that is the feature
 *
 * The service's storage is `JUser\Authentication\Storage\SessionUser`, which keeps **only
 * the user id** in the session and resolves it against the `user` table on every read — so
 * `state = 0` takes effect on the next request of a session that is already open, rather
 * than at an invisible timeout. It also answers null for a deactivated account, which is
 * what {@see current()}'s contract asks for and is why this class does not check `state`
 * itself: doing it in two places would let them disagree.
 *
 * ## `establish()` writes an id, not an object
 *
 * That is what `SessionUser::write()` narrows a `User` to anyway, and passing the id makes
 * the shape of what lands in the session obvious at the call site. Session regeneration is
 * **not** done here: `JUser\Controller\VerifyController` calls
 * `SessionInterface::regenerateId()` immediately before this, deliberately, so that the
 * ordering is visible at the point that depends on it.
 */
final class Identity implements IdentityInterface
{
    public function __construct(private readonly ServiceBridge $laminas)
    {
    }

    public function current(): ?User
    {
        /** @var mixed $identity */
        $identity = $this->authService()->getIdentity();

        return $identity instanceof User ? $identity : null;
    }

    public function establish(User $user): void
    {
        $this->authService()->getStorage()->write((int) $user->getId());
    }

    public function clear(): void
    {
        $this->authService()->clearIdentity();
    }

    private function authService(): AuthenticationService
    {
        /** @var AuthenticationService $service */
        $service = $this->laminas->get('zfcuser_auth_service');

        return $service;
    }
}
