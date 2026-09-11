<?php

declare(strict_types=1);

namespace App\Sion;

use Books\Form\BookForm;
use SionModel\Form\Element\Select;
use SionModel\Form\FormInterface;

use function is_array;
use function is_int;
use function is_numeric;
use function is_object;
use function is_scalar;
use function is_string;
use function method_exists;
use function property_exists;

/**
 * The view variables a form page needs that do not come out of the form itself.
 *
 * Both `App\Controller\EntityEditController` and `App\Controller\EntityCreateController`
 * need these, for the same two entities and for the same reason: on laminas, `BooksController`
 * and `PublicationsController` override *both* `editAction()` and `createAction()` to inject
 * them, and the two overrides are the same code. Extracted here when the create surface was
 * ported (batch 9) rather than copied into the second controller — a copy would have been the
 * third and fourth places this logic lives, and the whole argument for one
 * `EntityEditController` over ten was that a dispatch table should not be duplicated.
 *
 * What each provider reproduces is documented on the method. Everything here is read-only:
 * it asks the form and the tables and returns arrays for Twig.
 */
final class FormViewVariables
{
    public function __construct(private readonly Entities $entities)
    {
    }

    /**
     * The library's next free within-library number, which the book form's "next" button
     * writes into the call-number field from an inline script.
     *
     * `fields-partial.phtml` reads it as `$form->getLibraryOptions()->nextWithinLibraryId`
     * — a method on `Books\Form\BookForm` rather than an element, which is why it needs a
     * provider at all rather than coming out of the form in the template.
     *
     * **Rendered into JavaScript as a bare literal**, exactly as the partial does. It is an
     * integer from `lib_libraries.options`, not user input, and it is interpolated into a
     * `$(...).val(…)` call — so a non-numeric value would be a script-injection hazard
     * rather than a cosmetic bug. Coerced to an int here for that reason, and `0` when the
     * form cannot answer, which renders a button that clears the field instead of one that
     * throws a syntax error into the page.
     *
     * @param FormInterface<array<string, mixed>> $form
     * @return array{next_within_library_id: int}
     */
    public function nextWithinLibraryId(FormInterface $form): array
    {
        $next = 0;
        if ($form instanceof BookForm) {
            /** @var mixed $options */
            $options = $form->getLibraryOptions();
            /** @var mixed $value */
            $value = is_object($options) && property_exists($options, 'nextWithinLibraryId')
                ? $options->nextWithinLibraryId
                : null;
            $next = is_numeric($value) ? (int) $value : 0;
        }

        return ['next_within_library_id' => $next];
    }

    /**
     * `PublicationsController::injectPublicationValueOptions()`, which the laminas
     * `editAction()` and `createAction()` both call after their parent.
     *
     * Three lists, each handed to selectize as JSON rather than rendered as `<option>`s —
     * that is what keeps the publication pages to half a megabyte instead of several.
     *
     * `$object` is the row on an edit and **empty on a create**, which is the only
     * difference between the two surfaces: a publication cannot be its own main edition, so
     * an edit removes its own id from that list and a create has no id to remove. The
     * original guards it the same way (`if (isset($publicationId) && isset($valueOptions[$publicationId]))`)
     * and `createAction()` passes the view's `entityId`, which is null on a create.
     *
     * @param array<string, mixed>                $object
     * @param FormInterface<array<string, mixed>> $form
     * @return array<string, mixed>
     */
    public function publicationValueOptions(array $object, FormInterface $form): array
    {
        $options = $this->valueOptions($form, 'mainPublicationId');

        /** @var mixed $ownId */
        $ownId = $object['publicationId'] ?? null;
        if (is_int($ownId) || is_string($ownId)) {
            unset($options[$ownId]);
        }

        return [
            'publication_value_options' => $this->selectizeOptions($options),
            'author_persons'            => $this->selectizeOptions($this->valueOptions($form, 'translatorsAll')),
            'author_associations'       => $this->selectizeOptions($this->authorAssociationOptions()),
        ];
    }

    /**
     * `PublicationsTable::getAuthorAssociationValueOptions()` — the associations flagged
     * `IsAuthor`, keyed `a<id>`.
     *
     * The one list of the three that does not come off the form, which is why it needs
     * the table here. It is fetched even though **the page never uses it**: the partial's
     * script says `authorPersons.concat(authorAssociations);` and throws the result away,
     * so no picker is ever given these options. That is a bug in the original — `concat`
     * does not mutate — and it is reproduced rather than fixed, because fixing it would
     * add association authors to three pickers that have never offered them, which is a
     * content change and not this port's to make. Filed in docs/BACKLOG.md.
     *
     * @return array<array-key, mixed>
     */
    private function authorAssociationOptions(): array
    {
        $table = $this->entities->table('publication');
        if (! method_exists($table, 'getAuthorAssociationValueOptions')) {
            return [];
        }

        /** @var mixed $options */
        $options = $table->getAuthorAssociationValueOptions();

        return is_array($options) ? $options : [];
    }

    /**
     * One select's value options, or an empty list when the element is absent or is not a
     * select. Absent rather than fatal because a form's element set is data here — the
     * factory builds it — and a missing element should not take the page down.
     *
     * @param FormInterface<array<string, mixed>> $form
     * @return array<array-key, mixed>
     */
    private function valueOptions(FormInterface $form, string $element): array
    {
        if (! $form->has($element)) {
            return [];
        }

        $select = $form->get($element);

        return $select instanceof Select ? $select->getValueOptions() : [];
    }

    /**
     * `transformValueOptionsObject()`: an associative map becomes a list of `{i, n}`
     * objects. The one-letter keys are the original's and the JavaScript reads them, so
     * they are not shortenable here without changing `gen-*.js`.
     *
     * **The key keeps its type.** PHP array keys are int for numeric strings, and
     * `transformValueOptionsObject()` passes `$key` through untouched, so laminas emits
     * `{"i":2154,…}` for a publication and `{"i":"a17",…}` for an author association.
     * Casting everything to string produced `{"i":"2154",…}` — 7,801 bytes of quotation
     * marks in the baseline diff, and a real hazard behind it: selectize matches a
     * `valueField` against the `<option value>` it is given, so a type mismatch is the
     * kind of thing that silently fails to preselect the current choice.
     *
     * @param array<array-key, mixed> $options
     * @return list<array{i: int|string, n: string}>
     */
    private function selectizeOptions(array $options): array
    {
        $list = [];
        foreach ($options as $key => $value) {
            $list[] = ['i' => $key, 'n' => is_scalar($value) ? (string) $value : ''];
        }

        return $list;
    }
}
