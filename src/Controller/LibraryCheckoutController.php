<?php

declare(strict_types=1);

namespace App\Controller;

use App\Books\CheckoutForms;
use App\Books\LibraryPage;
use App\Laminas\HostMessages;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use Books\Model\LibraryTable;
use Exception;
use JTranslate\I18n\TranslatableMessage;
use SionModel\Messaging\FlashMessages;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

use function array_key_exists;
use function implode;
use function is_array;
use function sprintf;

/**
 * GET/POST /libraries/{library_id}/checkout — lend books to a person.
 *
 * `CheckoutsController::createAction()` plus its `createCheckouts()` handler, which
 * `SionController::createAction()` reaches through the spec's
 * `create_action_valid_data_handler`. Reproduced here rather than routed through
 * App\Controller\EntityCreateController, because the handler is not a validation rule —
 * it is the whole persist path, and the shared create action has no hook shaped like it.
 *
 * ## The permission is `checkout`, and it is meant to be easy to hold
 *
 * Not `administrate`. Four of the six libraries grant it to `guest`, which
 * `LibraryTable::getRules()` expands to include `user`, so every signed-in account can
 * lend at them. **That is deliberate** — see docs/libraries.md — and
 * test/Smoke/LibraryCheckoutAuthorizationSmokeTest pins both halves of it. A port that
 * "tightens" this is a port that has misread the feature.
 *
 * ## Two failure paths, two different messages
 *
 * An unknown barcode and a failed checkout are distinct and the original says so. The
 * first uses `TranslatableMessage` rather than a concatenated
 * string, and that is load-bearing: the messengers translate the finished message, so
 * concatenating the bad ids into it filed one permanent phrase row per distinct set of
 * ids — ten of them before it was fixed, none of which anyone could translate.
 */
final class LibraryCheckoutController
{
    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly Environment $twig,
        private readonly RouteUrl $urls,
        private readonly LibraryPage $page,
        private readonly CheckoutForms $forms,
        private readonly HostMessages $messages
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $libraryId = (int) $request->attributes->get('library_id');

        $refusal = $this->page->refuse($this->page->library($request), 'checkout');
        if (null !== $refusal) {
            return $refusal;
        }

        $form = $this->forms->forLibrary($libraryId);

        if ($request->isMethod('POST')) {
            /** @var array<string, mixed> $posted */
            $posted = $request->request->all();
            $form->setData($posted);
            if ($form->isValid()) {
                /** @var array<string, mixed> $data */
                $data = $form->getData();
                $done = $this->checkout($libraryId, $data);
                if (null !== $done) {
                    return $done;
                }
            } else {
                $this->now(FlashMessages::NAMESPACE_ERROR, 'Error in form submission, please review.');
            }
        }

        return new Response($this->twig->render('books/library-checkout.html.twig', [
            'page_title' => 'Book checkout',
            'form'       => $form,
            'self_url'   => $this->urls->path('libraries/library/checkout', ['library_id' => $libraryId]),
        ]));
    }

    /**
     * `createCheckouts()` — a redirect on success, null to re-render with a message.
     *
     * @param array<string, mixed> $data
     */
    private function checkout(int $libraryId, array $data): ?RedirectResponse
    {
        /** @var LibraryTable $table */
        $table  = $this->laminas->get(LibraryTable::class);
        $lookup = $table->getLibraryBookLookup($libraryId);

        $bad = [];
        foreach ((array) ($data['withinLibraryIds'] ?? []) as $value) {
            if (! is_array($lookup) || ! array_key_exists($value, $lookup)) {
                $bad[] = $value;
            }
        }
        if ([] !== $bad) {
            $this->now(FlashMessages::NAMESPACE_ERROR, new TranslatableMessage(
                'The following book id\'s are invalid: %s Please try again.',
                [implode(', ', $bad)]
            ));

            return null;
        }

        try {
            $result = $table->checkoutWithinLibraryBooks($libraryId, $data);
        } catch (Exception $e) {
            $this->now(FlashMessages::NAMESPACE_ERROR, $e->getMessage());

            return null;
        }

        if (true !== $result) {
            $this->now(FlashMessages::NAMESPACE_ERROR, sprintf(
                'There was a problem checking out one of the books: (%s) Any other books '
                . 'have been checked out. Please try again.',
                implode(', ', is_array($result) ? $result : [])
            ));

            return null;
        }

        $this->messages->flash(FlashMessages::NAMESPACE_SUCCESS, 'Books successfully checked out.');

        return new RedirectResponse(
            $this->urls->path('borrowers/borrower', ['person_id' => $data['personId']])
        );
    }

    /**
     * A TranslatableMessage where the first failure path needs one: the message must
     * reach the translator with its parameter still separate.
     */
    private function now(string $namespace, string|TranslatableMessage $message): void
    {
        $this->messages->now($namespace, $message);
    }
}
