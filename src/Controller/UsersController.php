<?php

declare(strict_types=1);

namespace App\Controller;

use App\JUser\UserAdmin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * GET /users — the user-administration index.
 *
 * `JUser\Controller\UsersController::indexAction()`: every account, its roles, and the
 * person record it points at, with a pencil to `juser/user/edit` and a crown to
 * `juser/user/api-tokens` beside each username.
 *
 * ## The two links are permission-gated per row, and that is not redundant
 *
 * The laminas view asks `isAllowed('route/juser/user/edit')` and
 * `isAllowed('route/juser/user/api-tokens')` before drawing each icon, on a page whose own
 * guard is already `administrator`. Reproduced, because the three guards are separate
 * entries in `config/autoload/juser.global.php` and nothing makes them agree: narrowing
 * `juser/user/api-tokens` to a tighter role is a supported thing to do, and the icon has
 * to disappear when it happens. `is_allowed()` is the Twig side of the same helper, and
 * it answers from the same ACL both front controllers build.
 *
 * ## What the page costs, and why the number in an earlier draft of this docblock was wrong
 *
 * `UserTable::getUsers()` loads every account and links its roles in one further query,
 * then caches the whole result in APCu under `all-linked-users`. Measured against the
 * capsule: **292 accounts, 0.017s, 0.55 MiB serialized**, and a 144 KB page.
 *
 * The first measurement said 6,303 accounts and 14.03 MiB, which would have made this the
 * third-largest thing in the cache and a candidate for the hazard
 * `database/db6.5.sql` exists to avoid (`apc.ttl=0`, so a failed allocation expunges the
 * whole segment rather than evicting). It was an artefact of the test suites, not of the
 * data: **6,011 of those accounts were `@example.com` fixtures** that nine smoke classes
 * created and never deleted, and the capsule's own `MagicLinkSignIn` docblock had been
 * claiming for weeks that every user of it purges in tearDown. So the honest figure is the
 * one above, and the leak is fixed in the same change rather than reported as a page cost.
 *
 * The lesson generalises past this page: the capsule's *content* tracks production (see
 * the note in CLAUDE.md about the 2021 dump), but its `user` table is the one place the
 * test suites themselves are a majority of the rows, so a row count taken from it needs
 * the fixtures excluded before it means anything.
 */
final class UsersController
{
    public function __construct(
        private readonly UserAdmin $admin,
        private readonly Environment $twig
    ) {
    }

    public function __invoke(Request $request): Response
    {
        return new Response($this->twig->render('juser/users-index.html.twig', [
            'page_title' => 'User Management',
            'users'      => $this->admin->table()->getUsers(),
            //null when this application configures no person provider, which the
            //template's own guard reads as "render the column empty".
            'persons'    => $this->admin->persons(),
        ]));
    }
}
