<?php

declare(strict_types=1);

namespace App\Books;

use App\Laminas\ServiceBridge;
use Books\Form\BookForm;
use Books\Form\CollectionForm;
use Books\Form\LibraryForm;
use Books\Model\LibraryTable;
use Books\Model\PublicationsTable;
use SionModel\Form\Element\Select;
use SionModel\Form\FormInterface;
use Locale;
use RuntimeException;
use Schoenstatt\Model\SchoenstattTable;
use SionModel\I18n\LanguageSupport;

use function is_array;

/**
 * The three Books edit forms whose laminas factories cannot run on a Symfony-served route.
 *
 * ## The blocker
 *
 * `BookFormFactory`, `CollectionFormFactory` and `LibraryFormFactory` each open with
 *
 *     $routeMatch = $container->get('Application')->getMvcEvent()->getRouteMatch();
 *     $libraryId  = $routeMatch->getParam('library_id');
 *
 * to discover which library the form belongs to. A Symfony-served route has no MvcEvent,
 * so `getMvcEvent()` answers null and the factory dies with `Call to a member function
 * getRouteMatch() on null` — *after* the response has been assembled, which makes it the
 * fatal-200 wedge: HTTP 200, zero bytes, nothing logged where a visitor would see it.
 *
 * This is the MvcEvent limitation docs/laminas-exit.md records for **view helpers**, reaching
 * a **form factory** instead. Nothing in that document anticipated it, and it is worth
 * expecting again: any laminas factory may read the route match, and the failure it
 * produces names a helper rather than the route.
 *
 * ## What is reproduced here, and what deliberately is not
 *
 * **Not the form.** `BookForm`, `CollectionForm` and `LibraryForm` are constructed here,
 * exactly as the laminas factories construct them, so their elements, their labels, their
 * `column-size` options and — the part that matters — their `getInputFilterSpecification()`
 * are the application's own and cannot drift. A ported form that validated differently
 * from the laminas one would be the worst defect this batch could ship, and reusing the
 * class is what rules it out.
 *
 * **Only the factory's wiring**: the library id, and the `setValueOptions()` calls that
 * depend on it. Every one of those reads the same `LibraryTable` /
 * `PublicationsTable` / `SchoenstattTable` method its factory reads, in the same order, so
 * the options are the same rows rather than a second query shaped like them.
 *
 * The library id arrives as an argument rather than being rediscovered, because the caller
 * has already loaded the row — `App\Sion\EntityEdit::load()` ran before this — so the
 * factory's fallback branch (fetch the object to read `libraryId` off it) is a query this
 * side does not need to repeat.
 *
 * ## What this costs, stated plainly
 *
 * These three forms now have their value-option wiring described in two places, and no
 * test can compare them: the laminas factory cannot be built at all without an MvcEvent,
 * which is the whole reason this class exists, so there is nothing to drive it against.
 * The guarantee is the same one the rest of the batch rests on — the baseline capture in
 * `tools/port-baseline.php`, which renders both front controllers over the same rows and
 * diffs the markup, and would show a missing or differently-ordered `<option>` list.
 *
 * A change to any of the three laminas factories therefore has to be mirrored here by
 * hand. `test/Integration/LibraryScopedFormsTest` asserts the *elements* each form ends up
 * with are populated, which catches a rename or a dropped call, but it cannot catch the
 * factories growing a fifth `setValueOptions()` that this class does not know about.
 */
final class LibraryScopedForms
{
    public function __construct(private readonly ServiceBridge $laminas)
    {
    }

    /**
     * Whether this class, rather than the laminas container, has to build the entity's
     * form.
     *
     * Named rather than inferred from a `try`/`catch` around the container: the failure it
     * avoids is an `Error` thrown deep inside a factory, and catching that would also
     * swallow a genuine fault in one of the seven forms that *do* build.
     */
    public static function handles(string $entity): bool
    {
        return 'book' === $entity || 'collection' === $entity || 'library' === $entity;
    }

    /**
     * The form for one of the three, wired against a library named directly.
     *
     * The create surface's entry point, added in batch 9. An edit discovers the library
     * from the row being edited; a create has no row, and the library comes from the route
     * — `books/create/{library_id}` and `collections/create/{library_id}` carry it in the
     * path.
     *
     * **Null is a legitimate argument, not a caller's mistake**, and that is the reason
     * this is a second method rather than `form()` with a looser `libraryIdOf()`.
     * `libraries/create` has no library at all: the row being created *is* the library. The
     * laminas factory says the same thing in its own shape — `LibraryFormFactory` wraps its
     * collection lookup in `if (isset($libraryId))` and leaves `mainCollectionId` with no
     * options, because a library that does not exist yet has no collections. `book` and
     * `collection` cannot answer null, because the value-option lists that make those forms
     * mean anything are all scoped to one library; they throw, as their factories do.
     *
     * @return FormInterface<array<string, mixed>>
     */
    public function formForLibrary(string $entity, ?int $libraryId): FormInterface
    {
        if ('library' !== $entity && null === $libraryId) {
            throw new RuntimeException(
                "Cannot build the '$entity' create form: it can only be formed with reference to a "
                . 'particular library, and the route supplied none.'
            );
        }

        return match ($entity) {
            'book'       => $this->bookForm((int) $libraryId),
            'collection' => $this->collectionForm((int) $libraryId),
            'library'    => $this->libraryForm($libraryId),
            default      => throw new RuntimeException("LibraryScopedForms does not build '$entity'."),
        };
    }

    /**
     * The form for one of the three, wired against the library the row belongs to.
     *
     * @param array<string, mixed> $object the row `EntityEdit::load()` returned
     * @return FormInterface<array<string, mixed>>
     */
    public function form(string $entity, array $object): FormInterface
    {
        $libraryId = $this->libraryIdOf($entity, $object);

        return match ($entity) {
            'book'       => $this->bookForm($libraryId),
            'collection' => $this->collectionForm($libraryId),
            'library'    => $this->libraryForm($libraryId),
            default      => throw new RuntimeException("LibraryScopedForms does not build '$entity'."),
        };
    }

    /**
     * The library a row belongs to.
     *
     * **`libraryId` for all three**, which reads like an oversight and is not: a book and a
     * collection each carry a `libraryId` column, and a `library` row's own key field *is*
     * `libraryId` (`entity_key_field` in its spec). So one lookup covers the three.
     *
     * This is the branch the laminas factories reach by re-fetching the object from the
     * route parameter, and the row is already in hand here.
     *
     * @param array<string, mixed> $object
     */
    private function libraryIdOf(string $entity, array $object): int
    {
        /** @var mixed $value */
        $value = $object['libraryId'] ?? null;

        if (! is_numeric($value)) {
            //The laminas factories throw here too — "can only be formed with reference to a
            //particular library" — and for the same reason: every value-option list below
            //is scoped to one, so there is no sensible form without it.
            throw new RuntimeException(
                "Cannot build the '$entity' form: the row carries no usable libraryId."
            );
        }

        return (int) $value;
    }

    /**
     * `Books\Service\BookFormFactory::__invoke()` from `$table->setLibraryId()` onward.
     *
     * @return FormInterface<array<string, mixed>>
     */
    private function bookForm(int $libraryId): FormInterface
    {
        $table = $this->libraryTable();
        $table->setLibraryId($libraryId);

        /** @var PublicationsTable $publications */
        $publications = $this->laminas->get(PublicationsTable::class);

        $form = new BookForm();
        $this->setOptions($form, 'inLanguage', $this->languageNames());
        //`BookFormFactory` writes `getEditionValueOptions(false)`, and that argument goes
        //nowhere: the method is declared `getEditionValueOptions()` with no parameters, and
        //PHP discards extra positional arguments to a userland function without complaint.
        //Called correctly here — the result is identical, and reproducing a no-op argument
        //would only invite someone to look for the parameter it is supposed to set.
        $this->setOptions($form, 'publicationId', $publications->getEditionValueOptions());
        $this->setOptions($form, 'authors', $table->getAuthorsValueOptions($libraryId));
        $this->setOptions($form, 'collectionId', $table->getCollectionValueOptions($libraryId));
        $this->setOptions($form, 'keywords', $table->getKeywordsValueOptions());

        //the factory's `if (isset($publishers))`, which is not decoration: a library with
        //no books has no publishers, and setValueOptions(null) is a type error
        /** @var mixed $publishers */
        $publishers = $table->getPublishersValueOptions();
        if (is_array($publishers)) {
            $this->setOptions($form, 'publisher', $publishers);
        }

        $this->setOptions($form, 'category', $table->getCategoryValueOptions());
        $this->setOptions($form, 'adminTags', $table->getAdminKeywordsValueOptions());

        //read *after* setLibraryId(), which is what scopes it — the template's
        //next-call-number button reads nextWithinLibraryId off this
        $form->setLibraryOptions($table->getLibraryOptions());

        return $form;
    }

    /**
     * `Books\Service\CollectionFormFactory::__invoke()`.
     *
     * Its whole body is the library lookup plus two lines, and both are reproduced: the
     * table is scoped, and `libraryId` is set as the form's *value* rather than as options.
     *
     * @return FormInterface<array<string, mixed>>
     */
    private function collectionForm(int $libraryId): FormInterface
    {
        $this->libraryTable()->setLibraryId($libraryId);

        $form = new CollectionForm();
        $form->get('libraryId')->setValue($libraryId);

        return $form;
    }

    /**
     * `Books\Service\LibraryFormFactory::__invoke()`.
     *
     * @return FormInterface<array<string, mixed>>
     */
    /**
     * `$libraryId` is nullable here alone, and only the create surface passes null: the
     * laminas factory reads the route match and guards the collection lookup with
     * `if (isset($libraryId))`, so `/libraries/create` renders `mainCollectionId` with no
     * options. Reproduced by skipping the same call.
     *
     * @return FormInterface<array<string, mixed>>
     */
    private function libraryForm(?int $libraryId): FormInterface
    {
        /** @var SchoenstattTable $schoenstatt */
        $schoenstatt = $this->laminas->get(SchoenstattTable::class);
        $persons     = $schoenstatt->getPersonValueOptions();

        /** @var array<string, mixed> $booksConfig */
        $booksConfig = $this->laminas->get('Books\Config');
        /** @var array<string, mixed> $schoenstattConfig */
        $schoenstattConfig = $this->laminas->get('Schoenstatt\Config');

        //`person_value_options_providers` keyed to its labels. The laminas factory writes
        //this loop with `$options` as the value variable, which shadows its own
        //`?array $options` parameter — harmless there because nothing reads it afterwards,
        //and not reproduced here.
        $providerLabels = [];
        /** @var mixed $providers */
        $providers = $schoenstattConfig['person_value_options_providers'] ?? [];
        if (is_array($providers)) {
            foreach ($providers as $key => $provider) {
                if (! is_array($provider) || ! isset($provider['label'])) {
                    throw new RuntimeException(
                        'person_value_options_providers must have a \'label\' key set'
                    );
                }
                $providerLabels[(string) $key] = $provider['label'];
            }
        }

        $form = new LibraryForm();

        if (null !== $libraryId) {
            $table = $this->libraryTable();
            $this->setOptions($form, 'mainCollectionId', $table->getCollectionValueOptions($libraryId));
        }

        /** @var mixed $filiations */
        $filiations = $booksConfig['library_filiation_options'] ?? [];
        $this->setOptions($form, 'filiationId', is_array($filiations) ? $filiations : []);
        $this->setOptions($form, 'contactPersonId', $persons);
        $this->setOptions($form, 'mainShowDisplay', LibraryTable::MAIN_SHOW_DISPLAY_VALUE_OPTIONS);
        $this->setOptions($form, 'defaultCheckoutPersonId', $persons);
        $this->setOptions($form, 'checkoutPersonListKind', $providerLabels);
        $this->setOptions($form, 'checkoutBooksRole', LibraryTable::LIBRARY_GENERAL_ROLE_OPTIONS);
        $this->setOptions($form, 'viewRole', LibraryTable::LIBRARY_GENERAL_ROLE_OPTIONS);

        return $form;
    }

    /**
     * `$form->get($name)->setValueOptions($options)`, with the two narrowings PHPStan
     * needs at level 8 and a laminas factory does without.
     *
     * A missing element is a *throw* rather than a skip: it means the form class and this
     * wiring have drifted, which is exactly the failure this class's docblock says no test
     * can catch, so it should be loud the first time it happens rather than quietly
     * rendering a select with no options.
     *
     * @param FormInterface<array<string, mixed>> $form
     * @param array<array-key, mixed> $options
     */
    private function setOptions(FormInterface $form, string $name, array $options): void
    {
        if (! $form->has($name)) {
            throw new RuntimeException(
                "The form has no '$name' element — App\\Books\\LibraryScopedForms has drifted "
                . 'from the laminas factory it reproduces.'
            );
        }

        $element = $form->get($name);
        if (! $element instanceof Select) {
            throw new RuntimeException("'$name' is not a select and cannot take value options.");
        }

        $element->setValueOptions($options);
    }

    /**
     * The language names in the request's primary language, which is what
     * `BookFormFactory` builds with `SionModel\I18n\LanguageSupport`.
     *
     * `Locale::getDefault()` is set by App\Http\LocaleListener before the controller runs,
     * so this is the visitor's language on both front controllers.
     *
     * @return array<array-key, mixed>
     */
    private function languageNames(): array
    {
        //`getPrimaryLanguage()` is `string|null`, so the fallback is needed — and
        //`getLanguageNames()` is annotated to return an array, so *that* is what does not
        //need a guard. The first draft here had these two the wrong way round.
        $primary = Locale::getPrimaryLanguage(Locale::getDefault());

        return (new LanguageSupport())->getLanguageNames(null === $primary ? 'en' : $primary);
    }

    private function libraryTable(): LibraryTable
    {
        /** @var LibraryTable $table */
        $table = $this->laminas->get(LibraryTable::class);

        return $table;
    }
}
