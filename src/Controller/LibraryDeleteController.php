<?php

declare(strict_types=1);

namespace App\Controller;

use App\Books\LibraryDelete;
use App\Books\LibraryDeleteFailed;
use App\Books\LibraryDeleteForm;
use App\Books\LibraryPage;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use JTranslate\Controller\Plugin\NowMessenger;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

use function is_string;
use function number_format;
use function sprintf;

/**
 * Deleting a library, and everything in it.
 *
 * ## This route has never once worked, on either front controller
 *
 * `libraries/library/delete` has existed in `module/Books/config/module.config.php` since
 * 2020 and was reachable by nobody, for two independent reasons: it has no entry in
 * `acl.global.php`, so BjyAuthorize's Route guard default-denies it, and the `library`
 * entity leaves `enable_delete_action` commented out, so `SionController::deleteAction()`
 * would have answered "this entity cannot be deleted, please check the configuration" even
 * if the guard had let anyone through.
 *
 * That second reason is now permanent and deliberate — see `App\Books\LibraryDelete` for
 * why the generic delete stays disabled rather than being switched on. So this is not a
 * port of a laminas page. There is no laminas page. It is new work on a route name that
 * was reserved for it, which is why `tools/port-baseline.php` has nothing to compare and
 * why this file has no "reproduces the following behaviour" section.
 *
 * ## What the page is for
 *
 * Not tidying up a mistyped library. PUC (library 4) holds **16,383 books**, is documented
 * in docs/libraries.md as inactive, and has zero change-log rows against any of those
 * books — bulk-loaded in 2020 and never touched through the application. Retiring a
 * collection of that size is the actual use case, and it is why the confirmation is
 * stronger than the site's other seven deletes: it prints what will be destroyed and asks
 * for the library's name to be typed. `App\Books\LibraryDeleteForm` has that reasoning.
 *
 * ## Authorization: both gates name the same population, and that is worth knowing
 *
 * The new guard entry names `lib_administrator`, which unusually for this surface is
 * **not** `is_default = 1` — every one of its fourteen siblings names `lib_user`, which is,
 * so for them the route guard means little more than "signed in".
 *
 * `LibraryPage::refuse()` then asks `isAllowed('library_<id>', 'administrate')`, the same
 * per-row question the other admin actions ask. **Today that resolves to the same
 * population again**, and the tempting description — "an administrator of one library
 * cannot delete another" — is false. `LibraryTable::getRules()` emits
 * `[['lib_administrator'], $object['resourceId'], 'administrate']` for **every** library
 * row, unconditionally, and its own `@todo` says why: the table of per-library
 * administrators that would make it selective does not exist. So `lib_administrator`
 * administers all six libraries and the per-row check currently distinguishes nobody.
 *
 * Both are kept, for different reasons. The route guard is what `docs/acl-baseline.json` shows,
 * so naming the strong role there is what makes the ACL table readable. The per-row check
 * is the shape the whole surface uses, and it is the gate that *becomes* meaningful the day
 * that administrator table lands — at which point a delete route carrying only the route
 * guard would silently be the one page on the surface that ignored it.
 *
 * `show` and `checkout` genuinely are per-row, which is where docs/libraries.md §
 * "Default roles make guards toothless" applies. `administrate` is that section's
 * exception, not an instance of it.
 *
 * ## It renders breadcrumbs, and it is the third page on this surface that does
 *
 * `LibraryPage::breadcrumbs()` records that of the twenty-three library routes exactly two
 * render a trail — the library's own page and the book show page — and that adding one to
 * the other twenty-one would be twenty-one invented differences from the laminas pages they
 * transcribe. That argument does not reach this page, because there is no laminas page here
 * to differ from. A screen that destroys a library should make *which* library unmissable,
 * and the trail is the site's own mechanism for saying so.
 *
 * ## Two status codes on a rejected POST, and they mean different things
 *
 * A wrong library name is ordinary human error on a page designed to produce it: the form
 * re-renders with a message and **200**. A failed CSRF token is a request that should not
 * have been made — a stale form, a cross-site attempt — and answers **400**.
 *
 * Both are decisions rather than reproductions, since there is nothing to reproduce.
 * `EntityDeleteController` answers 401 to a failed CSRF because the laminas action it
 * ports did, and that is filed in docs/BACKLOG.md as wrong; JTranslate's ported delete
 * took the chance to fix it to 400 on 2026-09-08. This follows the fixed one.
 */
final class LibraryDeleteController
{
    private const TEMPLATE = 'books/library-delete.html.twig';

    public function __construct(
        private readonly LibraryPage $page,
        private readonly LibraryDelete $delete,
        private readonly Environment $twig,
        private readonly RouteUrl $urls,
        private readonly ServiceBridge $laminas,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $library = $this->page->library($request);
        $refusal = $this->page->refuse($library, LibraryPage::ADMINISTRATE);
        if (null !== $refusal) {
            return $refusal;
        }
        /** @var array<string, mixed> $library */

        $libraryId = (int) $library['libraryId'];
        $name      = is_string($library['name'] ?? null) ? $library['name'] : '';

        if ('' === $name) {
            //Unreachable today — `LibraryName` is NOT NULL and populated in all six rows —
            //and refused rather than handled, because the confirmation this page rests on
            //is "type the name". A library with no name would be deletable by submitting an
            //empty field, which is the one thing the form exists to prevent.
            $this->flash(
                FlashMessenger::NAMESPACE_ERROR,
                'This library has no name, so it cannot be confirmed for deletion. '
                . 'Give it a name first.'
            );

            return new RedirectResponse($this->urls->path('libraries'));
        }

        $form   = new LibraryDeleteForm($name);
        $action = $this->urls->path('libraries/library/delete', ['library_id' => $libraryId]);
        $status = Response::HTTP_OK;

        if ($request->isMethod('POST')) {
            //Checked before the token, for the same reason SionModel\Form\DeleteEntityForm's
            //action checks it there: a cancellation is a no-op, so a stale token on one
            //should send the visitor back rather than raise a form error about a thing they
            //asked not to do.
            //
            //No browser produces this request — the template's Cancel is a link, and the
            //form declares no cancel element at all. It exists for the hand-crafted POST,
            //and it fails safe: naming `cancel` can never delete anything.
            if ($request->request->has('cancel')) {
                return new RedirectResponse(
                    $this->urls->path('libraries/library/admin', ['library_id' => $libraryId])
                );
            }

            /** @var array<string, mixed> $posted */
            $posted = $request->request->all();
            $form->setData($posted);

            if ($form->isValid()) {
                return $this->destroy($libraryId, $name);
            }

            $tokenFailed = [] !== ($form->get('security')->getMessages());
            if ($tokenFailed) {
                $this->nowMessage(
                    NowMessenger::NAMESPACE_ERROR,
                    'This form has expired. Nothing was deleted — please confirm again.'
                );
                $status = Response::HTTP_BAD_REQUEST;
            } else {
                //The name field carries its own message from the Identical validator, so
                //this is the summary line rather than a repetition of it.
                $this->nowMessage(NowMessenger::NAMESPACE_ERROR, 'Nothing was deleted.');
            }
        }

        return new Response(
            $this->twig->render(self::TEMPLATE, [
                'page_title'   => 'Delete library',
                'breadcrumbs'  => $this->page->breadcrumbs($library),
                'form'         => $form,
                'form_action'  => $action,
                'library'      => $library,
                'library_name' => $name,
                'library_id'   => $libraryId,
                'cancel_url'   => $this->urls->path(
                    'libraries/library/admin',
                    ['library_id' => $libraryId]
                ),
                'dependents'   => $this->delete->dependents($libraryId),
            ]),
            $status
        );
    }

    /**
     * The POST that succeeded validation.
     *
     * Logged before and after, at info, because this is the most destructive single action
     * the application can perform and the change log records an aggregate rather than a row
     * per book. If something goes wrong halfway, the log is the only account of what was
     * attempted — and note that `error_log()` would be useless for this: `log_errors` is
     * `Off` in the capsule, so it goes nowhere. That is why this takes a PSR-3 logger.
     *
     * @param non-empty-string $name carries __invoke()'s empty-name guard across this
     *                               boundary, where the narrowing would otherwise be lost;
     *                               LibraryDelete::delete() requires it, because a library
     *                               with no name cannot be confirmed by typing its name
     */
    private function destroy(int $libraryId, string $name): RedirectResponse
    {
        $this->logger->info('Library delete requested', ['libraryId' => $libraryId, 'name' => $name]);

        try {
            $counts = $this->delete->delete($libraryId, $name);
        } catch (LibraryDeleteFailed $e) {
            //LibraryDelete rolls back before throwing this, which is what lets the message
            //promise that nothing was deleted. An unexpected throwable is deliberately NOT
            //caught here: it reaches the error handler and is reported, because this method
            //cannot honestly make that promise about an exception it does not recognise.
            $this->logger->error('Library delete failed and was rolled back', [
                'libraryId' => $libraryId,
                'error'     => $e->getMessage(),
            ]);
            $this->flash(
                FlashMessenger::NAMESPACE_ERROR,
                'The library could not be deleted, and nothing was changed. ' . $e->getMessage()
            );

            return new RedirectResponse(
                $this->urls->path('libraries/library/admin', ['library_id' => $libraryId])
            );
        }

        $this->logger->info('Library deleted', ['libraryId' => $libraryId, 'name' => $name] + $counts);

        $this->flash(FlashMessenger::NAMESPACE_SUCCESS, sprintf(
            '“%s” was deleted, along with %s books, %s collections and %s checkout records.',
            $name,
            number_format($counts['books']),
            number_format($counts['collections']),
            number_format($counts['checkouts'])
        ));

        return new RedirectResponse($this->urls->path('libraries'));
    }

    /** A flash message in the laminas session, where the page redirected *to* looks for it. */
    private function flash(string $namespace, string $message): void
    {
        (new FlashMessenger())->setNamespace($namespace)->addMessage($message);
    }

    /**
     * @see EntityDeleteController::nowMessage() — the same shared plugin the layout renders
     *      from. Not the flash messenger: this message belongs on the page being
     *      re-rendered, and a flash would surface it on some later page instead.
     */
    private function nowMessage(string $namespace, string $message): void
    {
        /** @var NowMessenger $messenger */
        $messenger = $this->laminas->get('ControllerPluginManager')->get('nowMessenger');
        $messenger->setNamespace($namespace)->addMessage($message);
    }
}
