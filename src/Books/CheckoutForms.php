<?php

declare(strict_types=1);

namespace App\Books;

use App\Laminas\ServiceBridge;
use Books\Form\CheckoutForm;
use Books\Form\MassCheckoutForm;
use Books\Model\LibraryTable;
use Schoenstatt\Model\SchoenstattTable;
use SionModel\Form\Collection;
use SionModel\Form\Element\Select;
use SionModel\Form\Fieldset;
use RuntimeException;

use function array_key_exists;
use function is_array;
use function is_numeric;
use function is_object;
use function is_string;

/**
 * The two lending forms whose laminas factories cannot run on a Symfony-served route.
 *
 * `CheckoutFormFactory` opens with `$container->get('Application')->getMvcEvent()
 * ->getRouteMatch()` to discover the library, exactly as the three edit-form factories
 * App\Books\LibraryScopedForms exists for. Same blocker, same answer: the *form* is the
 * application's own class so its validation cannot drift, and only the factory's wiring
 * is reproduced here.
 *
 * ## The person list is per library, and that is the whole point of the factory
 *
 * A library's `checkoutPersonListKind` names an entry in
 * `schoenstatt.person_value_options_providers`, and that decides who appears in the
 * borrower dropdown: `all-borrowers` is a container service, `library-borrowers` is built
 * here because it depends on the library. Getting this wrong does not fail — it offers the
 * wrong people, which is a worse outcome than an error, so an unknown kind throws.
 *
 * Mass checkout offers the same list as the single form. It used to offer the Schoenstatt
 * Fathers' roster from Patres at every library, whatever the library's own list was; that
 * roster went with the Patres connection (2026-10-25).
 */
final class CheckoutForms
{
    public const LIBRARY_BORROWERS = 'library-borrowers';

    public function __construct(private readonly ServiceBridge $laminas)
    {
    }

    /**
     * `CheckoutFormFactory`, with the library id handed in rather than read off a route
     * match.
     */
    public function forLibrary(int $libraryId): CheckoutForm
    {
        $form   = new CheckoutForm();
        $select = $form->get('personId');
        if ($select instanceof Select) {
            $select->setValueOptions($this->borrowers($libraryId));
        }

        return $form;
    }

    /**
     * `MassCheckoutForm` with its collection's `personId` options populated from
     * {@see borrowers()}, which the caller also needs for the selectize payload.
     *
     * @param array<int, string> $borrowers
     */
    public function mass(array $borrowers): MassCheckoutForm
    {
        $form = new MassCheckoutForm();
        $this->populateMassOptions($form, $borrowers);

        return $form;
    }

    /**
     * Empty the collection's options again, which `massCheckoutAction()` does when the
     * form fails validation: the page then ships the list once as JSON for selectize
     * instead of once per `<select>`, and on a form with many rows that is most of the
     * response.
     */
    public function clearMassOptions(MassCheckoutForm $form): void
    {
        $this->populateMassOptions($form, []);
    }

    /**
     * The selectize payload — objects with id/name, not the value-options map.
     *
     * @param array<int, string> $borrowers
     * @return list<array{id: int, name: string}>
     */
    public static function personObjects(array $borrowers): array
    {
        $objects = [];
        foreach ($borrowers as $personId => $name) {
            $objects[] = ['id' => $personId, 'name' => $name];
        }

        return $objects;
    }

    /**
     * The people this library may lend to, as person id => name, from the list its
     * `checkoutPersonListKind` names.
     *
     * @return array<int, string>
     */
    public function borrowers(int $libraryId): array
    {
        /** @var LibraryTable $table */
        $table     = $this->laminas->get(LibraryTable::class);
        $libraries = $table->getObjects('library');
        if (! is_array($libraries) || ! array_key_exists($libraryId, $libraries)) {
            throw new RuntimeException('Library not found');
        }

        $options = $libraries[$libraryId]['options'] ?? null;
        if (! is_object($options)) {
            throw new RuntimeException('Library not found');
        }

        $config    = $this->laminas->get('Schoenstatt\Config');
        $providers = is_array($config) ? ($config['person_value_options_providers'] ?? null) : null;
        if (! is_array($providers)) {
            throw new RuntimeException('No person_value_options_providers set');
        }

        $kind = $options->checkoutPersonListKind ?? null;
        if (self::LIBRARY_BORROWERS === $kind && isset($providers[$kind])) {
            return $this->libraryBorrowers($table, $libraryId, $options->defaultCheckoutPersonId ?? null);
        }

        $target = is_string($kind) && isset($providers[$kind]['target']) ? $providers[$kind]['target'] : null;
        if (! is_string($target) || ! $this->laminas->has($target)) {
            throw new RuntimeException('Improper checkout person list kind configuration');
        }

        $persons = $this->laminas->get($target);

        return is_array($persons) ? $persons : [];
    }

    /**
     * `library-borrowers`: everyone ticked as a borrower who has borrowed at this library
     * or has not borrowed anywhere yet, plus the library's default checkout person.
     *
     * The second half is how a new borrower appears: a moderator adds the person with
     * "borrower" ticked, and their first loan then ties them to the library it was made
     * at. The cost of reading membership off history is that someone who has borrowed only
     * elsewhere cannot be offered here; at the decision (2026-10-25) two of 71 borrowers
     * had ever used a second library. Unticking "borrower" takes a person off every list.
     *
     * The default checkout person is the row a library's unmatched check-ins are written
     * against (`CreateCheckoutsIfCheckingInANonCheckedOutBook`), not a borrower, and is
     * offered whether ticked or not.
     *
     * @return array<int, string>
     */
    private function libraryBorrowers(LibraryTable $table, int $libraryId, mixed $defaultPersonId): array
    {
        /** @var SchoenstattTable $schoenstatt */
        $schoenstatt = $this->laminas->get(SchoenstattTable::class);
        $persons     = $schoenstatt->getUnlinkedPersons();

        return self::eligibleBorrowers(
            is_array($persons) ? $persons : [],
            $table->getBorrowingHistory($libraryId),
            is_numeric($defaultPersonId) ? (int) $defaultPersonId : null
        );
    }

    /**
     * The rule of {@see libraryBorrowers()}, apart from the data it reads, so it can be
     * tested without a database. Keeps the order `$persons` arrive in.
     *
     * @param array<int|string, mixed> $persons  person entities keyed by id
     * @param array<int, bool>         $history  {@see LibraryTable::getBorrowingHistory()}
     * @return array<int, string>
     */
    public static function eligibleBorrowers(array $persons, array $history, ?int $defaultPersonId): array
    {
        $borrowers = [];
        foreach ($persons as $personId => $person) {
            $personId = (int) $personId;
            if (! is_array($person)) {
                continue;
            }
            $eligible = (! empty($person['isBorrower']) && ($history[$personId] ?? true))
                || $personId === $defaultPersonId;
            if ($eligible && is_string($person['fullName'] ?? null)) {
                $borrowers[$personId] = $person['fullName'];
            }
        }

        return $borrowers;
    }

    /** @param array<int|string, mixed> $options */
    private function populateMassOptions(MassCheckoutForm $form, array $options): void
    {
        $collection = $form->get('checkout');
        if (! $collection instanceof Collection) {
            return;
        }
        $target = $collection->getTargetElement();
        if (! $target instanceof Fieldset || ! $target->has('personId')) {
            return;
        }
        $select = $target->get('personId');
        if ($select instanceof Select) {
            $select->setValueOptions($options);
        }
    }
}
