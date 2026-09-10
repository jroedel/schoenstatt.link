<?php

declare(strict_types=1);

namespace App\Books;

use SionModel\Form\Form;
use Laminas\InputFilter\InputFilterProviderInterface;
use SionModel\Form\CsrfSpec;

/**
 * The confirmation that stands between a visitor and 16,383 UPDATE statements.
 *
 * ## Why this form exists at all
 *
 * `LibrariesController::refreshSortAction()` recomputed `sort_text` for every book in a
 * library **on a bare GET**: no POST, no token, no confirmation, and — unlike the
 * fourteen sibling actions in the same controller — no `isAllowed('library_<id>',
 * 'administrate')` check either. Its route guard names `lib_user`, which is
 * `is_default = 1`, so every registered account could trigger it, for any library, by
 * following a link. Proven by experiment rather than read off the code: a book's
 * `sort_text` was set to `ZZZ-PROBE` and the URL fetched with an empty body; the row
 * came back restored.
 *
 * The write is idempotent — each row is rewritten to what the current algorithm says it
 * should be — so this was never data loss. What it was is an unauthenticated writer and
 * a load amplifier: one link, one statement per book, and nothing in the response saying
 * so. A crawler or a link prefetcher was enough.
 *
 * So the ported route answers GET with this form and does the work only on POST, with
 * the `administrate` check its siblings make. That is a deliberate change of contract
 * for one route in a batch that otherwise reproduces behaviour, decided 2026-08-18.
 *
 * The 900-second CSRF timeout matches `SionModel\Form\DeleteEntityForm`, which is the
 * other confirmation form in the application and the one this is modelled on.
 *
 * @extends Form<array<string, mixed>>
 */
final class RefreshSortForm extends Form implements InputFilterProviderInterface
{
    public function __construct()
    {
        parent::__construct('refresh_sort');

        $this->setAttribute('method', 'POST');

        $this->add([
            'name'    => 'security',
            'type'    => 'csrf',
            'options' => [
                'csrf_options' => ['timeout' => 900],
            ],
        ]);
        $this->add([
            'name'       => 'submit',
            'type'       => 'Submit',
            'attributes' => [
                'value' => 'Refresh all library sort text',
                'id'    => 'submit',
                'class' => 'btn-warning',
            ],
        ]);
    }

    /**
     * The CSRF token, restated as data.
     *
     * `Laminas\Form\Element\Csrf` supplies this validator itself, so the form was
     * protected without it — but only for as long as `Laminas\InputFilter` assembles the
     * filter. `SionModel\Form\Validation\InputFilter` reads the specification and
     * nothing else, and this form's entire defence is that one token: the route it guards
     * rewrites `sort_text` for every book in a library. See SionModel\Form\CsrfSpec for
     * why the element has to be read rather than a constant written out.
     *
     * @return array<string, mixed>
     */
    public function getInputFilterSpecification(): array
    {
        return [
            'security' => CsrfSpec::forElement($this->get('security')),
        ];
    }
}
