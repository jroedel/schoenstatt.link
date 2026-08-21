<?php

declare(strict_types=1);

namespace App\Controller;

use App\JUser\UserAdmin;
use App\Laminas\RouteUrl;
use JUser\Form\DeleteUserForm;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Error\Error as TwigError;

use function is_array;
use function is_scalar;
use function is_string;

/**
 * GET|POST /users/{user_id}/delete — delete one account.
 *
 * The GET renders a confirmation; the POST deletes. Both are reached: the edit form
 * carries a modal that posts here, and the GET page is what a typed URL answers.
 *
 * `deleteUser()` rather than `SionTable::deleteEntity()`, so this is not
 * `App\Controller\EntityDeleteController` either — the row has role links hanging off it
 * and the table's own method is what removes them.
 *
 * ## Exactly one of three outcomes is reported
 *
 * Worth stating because it was not always true: the laminas action's predecessor ran the
 * error branch and then appended 'User deleted.' unconditionally, so a failed delete and a
 * refused CSRF token both ended on a green success message contradicting the red one above
 * it. The three branches — refused, failed, done — are mutually exclusive here and each
 * redirects to the index.
 *
 * A refused token and an unknown account report the same 'User not found.', which is
 * misleading but reproduced: it is the laminas message and changing it would put a
 * different sentence on a path the baseline diff compares.
 *
 * ## One deliberate difference: the confirmation says whose account it is
 *
 * `delete.phtml` asks *"This change is permanent, are you sure you want to delete '%s'?"*
 * and fills the `%s` from `$this->user->username` — **property access on an array**.
 * `UserTable::getUser()` answers an array, so on every render since it was written the
 * page has asked whether to permanently delete `''`, with a PHP warning where the name
 * should be. Measured 2026-08-21 against the capsule; in production `display_errors` is
 * off, so the warning is invisible and only the empty quotes show.
 *
 * Not reproduced, and this is the one place in the batch where reproducing would have been
 * wrong: a destructive confirmation that names nobody is a safety defect, not a cosmetic
 * one, and it is the entire content of the page. The same reasoning retires the other half
 * of it — an id that names no account now redirects to the index with 'User not found.'
 * instead of rendering a confirmation for an account that does not exist, which is what
 * every sibling route on this surface already does. Both are recorded in
 * docs/strangler.md's known-differences table.
 */
final class UserDeleteController
{
    public function __construct(
        private readonly UserAdmin $admin,
        private readonly Environment $twig,
        private readonly RouteUrl $urls
    ) {
    }

    /** @throws TwigError */
    public function __invoke(Request $request): Response
    {
        $user = $this->admin->user($request);
        if (null === $user) {
            $this->admin->flash(FlashMessenger::NAMESPACE_ERROR, 'User not found.');

            return $this->toIndex();
        }
        $id = (int) $user['userId'];

        $form = new DeleteUserForm($this->admin->adapter());

        if ($request->isMethod('POST')) {
            /** @var array<string, mixed> $posted */
            $posted = $request->request->all();
            $form->setData($posted);

            if (! $form->isValid() || ! $this->confirms($form, $id)) {
                $this->admin->flash(FlashMessenger::NAMESPACE_ERROR, 'User not found.');
            } else {
                $result = $this->admin->table()->deleteUser($id);
                if (1 !== $result) {
                    $this->admin->flash(
                        FlashMessenger::NAMESPACE_ERROR,
                        $this->admin->writeFailure('deleting a user', null, [
                            'userId' => $id,
                            'result' => $result,
                        ])
                    );
                } else {
                    $this->admin->flash(FlashMessenger::NAMESPACE_SUCCESS, 'User deleted.');
                }
            }

            return $this->toIndex();
        }

        $form->setData(['userId' => $id]);

        return new Response($this->twig->render('juser/user-delete.html.twig', [
            //`<h1>Warning...</h1>` is a literal in the laminas view with no `headTitle()`
            //at all, so the browser tab shows the site name alone. Reproduced with an
            //**empty** page_title rather than by omitting it: `layout.html.twig` reads
            //`page_title` without an `is defined` guard, and Twig runs with
            //strict_variables, so leaving it out is a fatal — which arrives as a 200 with
            //an empty body, exactly the wedge CLAUDE.md warns about. Measured before this
            //comment existed.
            'page_title' => '',
            'user_id'  => $id,
            'username' => $this->username($user),
            'form'     => $form,
            'self_url' => $this->urls->path('juser/user/delete', ['user_id' => $id]),
        ]));
    }

    /**
     * Whether the validated form names the account in the URL.
     *
     * `$form->getData()['userId'] != $id` in the original — loose, against a string. The
     * hidden field is the form's own, so tripping this means editing it, and what it stops
     * is a confirmation for account A deleting account B.
     *
     * @param DeleteUserForm $form validated
     */
    private function confirms(DeleteUserForm $form, int $id): bool
    {
        /** @var mixed $data */
        $data = $form->getData();
        /** @var mixed $confirmed */
        $confirmed = is_array($data) ? ($data['userId'] ?? null) : null;

        return is_scalar($confirmed) && (string) $confirmed === (string) $id;
    }

    /**
     * The name the confirmation shows — see the class docblock on why this is a key read
     * and not a property read.
     *
     * `username` is nullable in the table (`varchar(255) NULL`), which is the case the
     * fallback is for: an account created from an email whose local part filtered down to
     * nothing gets one from `makeUniqueUsername()`, but nothing enforces it at the column.
     * Showing the address beats showing empty quotes on a page asking for a deletion.
     *
     * @param array<string, mixed> $user
     */
    private function username(array $user): string
    {
        /** @var mixed $username */
        $username = $user['username'] ?? null;
        if (is_string($username) && '' !== $username) {
            return $username;
        }

        /** @var mixed $email */
        $email = $user['email'] ?? null;

        return is_string($email) ? $email : '';
    }

    private function toIndex(): RedirectResponse
    {
        return new RedirectResponse($this->urls->path('juser'), Response::HTTP_FOUND);
    }
}
