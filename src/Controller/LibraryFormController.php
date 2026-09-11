<?php

declare(strict_types=1);

namespace App\Controller;

use App\Books\LibraryPage;
use App\Laminas\HostMessages;
use App\Laminas\RouteUrl;
use App\Laminas\ServiceBridge;
use Books\Form\CheckinForm;
use Books\Form\InactivationForm;
use Books\Model\LibraryTable;
use Exception;
use SionModel\Form\FormInterface;
use RuntimeException;
use SionModel\Messaging\FlashMessages;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

use function array_key_exists;
use function implode;
use function is_array;
use function is_string;
use function sprintf;

/**
 * The two bulk book operations that are a form, a table call and a redirect.
 *
 * | page | form | writes | on success |
 * |---|---|---|---|
 * | `checkin` | `CheckinForm` | `checkinWithinLibraryBooks()` | `checkouts/library` |
 * | `inactivate-books` | `InactivationForm` | `inactivateWithinLibraryBooks()` | `libraries/library/admin` |
 *
 * Both are `administrate`-gated, both are GET-renders-POST-writes, and both are driven
 * by a barcode scanner: the visitor scans into one text field, JavaScript accumulates the
 * ids in `#book-list`, and the form posts the accumulated list. That is why neither has a
 * per-book confirmation step.
 *
 * `mass-checkout` is *not* here despite fitting the description, because its form is a
 * `Collection` of fieldsets with a client-side row template — a different enough problem
 * to be its own class.
 *
 * ## Two messengers, and they are not interchangeable
 *
 * A **flash** survives a redirect and is read on the next page; a **now** message is
 * rendered by the page currently being returned. The laminas actions use flash for
 * success (they redirect) and now for failure (they re-render the form). Swapping them
 * loses the message entirely in both directions, which is silent.
 */
final class LibraryFormController
{
    /** Route default naming which of the two forms this route is. */
    public const FORM = '_library_form';

    private const CHECKIN    = 'checkin';
    private const INACTIVATE = 'inactivate-books';

    /** form => [laminas route, template, heading] */
    private const PAGES = [
        self::CHECKIN    => [
            'libraries/library/checkin',
            'books/library-checkin.html.twig',
            'Book Check-in',
        ],
        self::INACTIVATE => [
            'libraries/library/inactivate-books',
            'books/library-inactivate-books.html.twig',
            'Inactivate books',
        ],
    ];

    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly Environment $twig,
        private readonly RouteUrl $urls,
        private readonly LibraryPage $page,
        private readonly HostMessages $messages
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $which = $request->attributes->get(self::FORM);
        if (! is_string($which) || ! array_key_exists($which, self::PAGES)) {
            throw new RuntimeException('A library form route declared no known form.');
        }
        [$routeName, $template, $heading] = self::PAGES[$which];

        $libraryId = (int) $request->attributes->get('library_id');

        $library = $this->page->library($request);
        $refusal = $this->page->refuse($library, LibraryPage::ADMINISTRATE);
        if (null !== $refusal) {
            return $refusal;
        }

        $form = self::CHECKIN === $which ? new CheckinForm() : new InactivationForm();

        if ($request->isMethod('POST')) {
            $done = $this->handle($which, $form, $request, $libraryId);
            if (null !== $done) {
                return $done;
            }
        }

        return new Response($this->twig->render($template, [
            'page_title' => $heading,
            'form'       => $form,
            'self_url'   => $this->urls->path($routeName, ['library_id' => $libraryId]),
        ]));
    }

    /**
     * The POST branch: a RedirectResponse on success, null to re-render with errors.
     *
     * @param FormInterface<array<string, mixed>> $form
     */
    private function handle(string $which, FormInterface $form, Request $request, int $libraryId): ?RedirectResponse
    {
        /** @var array<string, mixed> $posted */
        $posted = $request->request->all();
        $form->setData($posted);

        if (! $form->isValid()) {
            $this->now(FlashMessages::NAMESPACE_ERROR, 'Error in form submission, please review.');

            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $form->getData();
        /** @var LibraryTable $table */
        $table = $this->laminas->get(LibraryTable::class);

        if (self::CHECKIN === $which) {
            if (! $table->checkinWithinLibraryBooks($libraryId, $data['withinLibraryIds'])) {
                //The laminas action reports the same "Error in form submission" here as it
                //does for an invalid form, which is misleading — the form was fine and a
                //book id was not. Reproduced: changing it is a message change on a page
                //whose diff should be readable, and it is filed rather than fixed.
                $this->now(FlashMessages::NAMESPACE_ERROR, 'Error in form submission, please review.');

                return null;
            }

            $this->flash(FlashMessages::NAMESPACE_SUCCESS, 'Books successfully checked in.');

            return new RedirectResponse($this->urls->path('checkouts/library', ['library_id' => $libraryId]));
        }

        try {
            $bad = $table->inactivateWithinLibraryBooks($libraryId, $data);
        } catch (Exception $e) {
            $this->now(FlashMessages::NAMESPACE_ERROR, $e->getMessage());

            return null;
        }

        if (is_array($bad)) {
            $this->now(FlashMessages::NAMESPACE_ERROR, sprintf(
                'There was a problem with one or more of the books: (%s) Please try again.',
                implode(', ', $bad)
            ));

            return null;
        }

        $this->flash(FlashMessages::NAMESPACE_SUCCESS, 'Books successfully inactivated.');

        return new RedirectResponse($this->urls->path('libraries/library/admin', ['library_id' => $libraryId]));
    }

    /** Survives a redirect; read by the next page. */
    private function flash(string $namespace, string $message): void
    {
        $this->messages->flash($namespace, $message);
    }

    /** Rendered by the response being returned now — see the class docblock. */
    private function now(string $namespace, string $message): void
    {
        $this->messages->now($namespace, $message);
    }
}
