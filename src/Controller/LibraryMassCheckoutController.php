<?php

declare(strict_types=1);

namespace App\Controller;

use App\Books\CheckoutForms;
use App\Books\LibraryPage;
use App\Http\LocalePrefix;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use Books\Model\LibraryTable;
use Laminas\Form\Element\Collection;
use JTranslate\Controller\Plugin\NowMessenger;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

use function implode;
use function is_array;
use function json_encode;
use function preg_replace;
use function sprintf;
use function trim;

use const JSON_HEX_AMP;
use const JSON_HEX_APOS;
use const JSON_HEX_QUOT;
use const JSON_HEX_TAG;

/**
 * GET/POST /libraries/{library_id}/mass-checkout — record several loans in one form.
 *
 * `CheckoutsController::massCheckoutAction()`. A `Collection` of fieldsets, each one a
 * date, a borrower and a list of barcodes; every fieldset that names both a person and at
 * least one book is checked out, and the ones that name neither are skipped rather than
 * refused.
 *
 * **Partial success is the contract.** If one fieldset fails, the others are still
 * written and the page says which failed. That is why the loop collects bad values rather
 * than stopping, and why the message says "Any other books have been checked out".
 *
 * ## The row template
 *
 * The original builds it with `ob_start()` around the collection's *target* element and
 * then rewrites `name="x"` to `name="checkout[__index__][x]"` with a regex, so the "Add 4
 * rows" button has something to clone. Reproduced here, with the same regex, because the
 * rendering is the same fragment the table rows use — the template is
 * `_mass-checkout-row.html.twig` in both cases rather than a second copy of the markup.
 */
final class LibraryMassCheckoutController
{
    /** The original's own pattern and replacement, unchanged. */
    private const NAME_PATTERN     = '/name="([A-Za-z]+)"/';
    private const NAME_REPLACEMENT = 'name="checkout[__index__][$1]"';

    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly Environment $twig,
        private readonly RouteUrl $urls,
        private readonly LibraryPage $page,
        private readonly CheckoutForms $forms
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $libraryId = (int) $request->attributes->get('library_id');
        $redirect  = LocalePrefix::redirect(
            $request,
            $this->urls,
            'libraries/library/mass-checkout',
            ['library_id' => $libraryId]
        );
        if (null !== $redirect) {
            return $redirect;
        }

        $refusal = $this->page->refuse($this->page->library($request), LibraryPage::ADMINISTRATE);
        if (null !== $refusal) {
            return $refusal;
        }

        $form = $this->forms->mass();

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
                //Emptying the options is what the laminas action does on a failed
                //validation: the page ships them once as JSON below, so leaving them on
                //the elements would repeat the whole person list per row.
                $this->forms->clearMassOptions($form);
                $this->now(NowMessenger::NAMESPACE_ERROR, 'Error in form submission, please review.');
            }
        }

        //`prepare()` before rendering, which mass-checkout.phtml also does and which no
        //other ported form has needed. On a `Collection` it is not cosmetic: it is what
        //materialises the `count => 4` fieldsets, so without it the table renders zero
        //rows and the page is unusable. Measured — the first version rendered one `<tr>`
        //where laminas renders five.
        $form->prepare();
        $collection = $form->get('checkout');
        $target     = $collection instanceof Collection ? $collection->getTargetElement() : null;

        return new Response($this->twig->render('books/library-mass-checkout.html.twig', [
            'page_title'     => 'Book Checkout',
            'form'           => $form,
            'first_row'      => $target,
            'row_template'   => $this->rowTemplate($target),
            'person_options' => (string) json_encode(
                $this->forms->fatherObjects(),
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            ),
            'self_url'       => $this->urls->path(
                'libraries/library/mass-checkout',
                ['library_id' => $libraryId]
            ),
        ]));
    }

    /** @param array<string, mixed> $data */
    private function checkout(int $libraryId, array $data): ?RedirectResponse
    {
        /** @var LibraryTable $table */
        $table = $this->laminas->get(LibraryTable::class);

        $bad = [];
        foreach ((array) ($data['checkout'] ?? []) as $checkout) {
            if (! is_array($checkout)) {
                continue;
            }
            //A row with no person or no books is an empty row the librarian did not use.
            if (null === ($checkout['personId'] ?? null) || empty($checkout['withinLibraryIds'])) {
                continue;
            }
            $result = $table->checkoutWithinLibraryBooks($libraryId, $checkout);
            if (true !== $result) {
                $bad = [...$bad, ...(is_array($result) ? $result : [])];
            }
        }

        if ([] !== $bad) {
            $this->now(NowMessenger::NAMESPACE_ERROR, sprintf(
                'There was a problem checking out one or more of the books: (%s) Any other '
                . 'books have been checked out. Please try again.',
                implode(', ', $bad)
            ));

            return null;
        }

        (new FlashMessenger())
            ->setNamespace(FlashMessenger::NAMESPACE_SUCCESS)
            ->addMessage('Books successfully checked out.');

        return new RedirectResponse(
            $this->urls->path('checkouts/library/current', ['library_id' => $libraryId])
        );
    }

    /** The blank row the "Add 4 rows" button clones, with `__index__` where the index goes. */
    private function rowTemplate(mixed $target): string
    {
        $rendered = $this->twig->render(
            'books/_mass-checkout-row.html.twig',
            ['fieldset' => $target, 'with_errors' => false]
        );

        //trim(): the template file ends with a newline, which would be escaped into the
        //`data-template` attribute and then cloned into the DOM. `ob_get_clean()` in the
        //original captures no trailing newline because the .phtml's last line is `<?php`.
        return trim((string) preg_replace(self::NAME_PATTERN, self::NAME_REPLACEMENT, $rendered));
    }

    private function now(string $namespace, string $message): void
    {
        /** @var NowMessenger $messenger */
        $messenger = $this->laminas->get('ControllerPluginManager')->get('nowMessenger');
        $messenger->setNamespace($namespace)->addMessage($message);
    }
}
