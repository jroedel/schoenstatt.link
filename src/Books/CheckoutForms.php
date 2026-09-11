<?php

declare(strict_types=1);

namespace App\Books;

use App\Laminas\ServiceBridge;
use Books\Form\CheckoutForm;
use Books\Form\MassCheckoutForm;
use Books\Model\LibraryTable;
use SionModel\Form\Collection;
use SionModel\Form\Element\Select;
use SionModel\Form\Fieldset;
use RuntimeException;

use function array_key_exists;
use function is_array;
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
 * A library's `checkoutPersonListKind` names a provider in
 * `schoenstatt.person_value_options_providers`, and that provider decides who appears in
 * the borrower dropdown. Bellavista and Colegio Mayor use `patres-sion`, which reaches
 * the Schoenstatt Fathers' own database; the open libraries use a local list. Getting
 * this wrong does not fail — it offers the wrong people, which is a worse outcome than
 * an error, so the three `throw`s the factory makes are reproduced rather than softened.
 *
 * ## Mass checkout is deliberately different, and looks like a bug
 *
 * `massCheckoutAction()` populates its `personId` options from
 * `Schoenstatt\FathersValueOptions` **unconditionally** — not from the library's own
 * provider. So the mass-checkout page offers Schoenstatt Fathers at every library,
 * including the two in Austin that lend to anyone. Reproduced, because it is what the
 * page does today and because "which people may this library lend to" is a question with
 * a real answer that nobody has written down; changing it here would guess. Filed.
 */
final class CheckoutForms
{
    public function __construct(private readonly ServiceBridge $laminas)
    {
    }

    /**
     * `CheckoutFormFactory`, with the library id handed in rather than read off a route
     * match.
     */
    public function forLibrary(int $libraryId): CheckoutForm
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

        $kind   = $options->checkoutPersonListKind ?? null;
        $target = is_string($kind) && isset($providers[$kind]['target']) ? $providers[$kind]['target'] : null;
        if (! is_string($target) || ! $this->laminas->has($target)) {
            throw new RuntimeException('Improper checkout person list kind configuration');
        }

        $form    = new CheckoutForm();
        $persons = $this->laminas->get($target);
        $select  = $form->get('personId');
        if ($select instanceof Select) {
            $select->setValueOptions(is_array($persons) ? $persons : []);
        }

        return $form;
    }

    /**
     * `MassCheckoutForm` with its collection's `personId` options populated — see the
     * class docblock for why this list is not the library's.
     */
    public function mass(): MassCheckoutForm
    {
        $form = new MassCheckoutForm();
        $this->populateMassOptions($form, $this->fathers());

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

    /** @return array<int|string, mixed> */
    public function fathers(): array
    {
        $options = $this->laminas->get('Schoenstatt\FathersValueOptions');

        return is_array($options) ? $options : [];
    }

    /** The selectize payload — objects with id/name, not the value-options map. */
    public function fatherObjects(): mixed
    {
        return $this->laminas->get('Books\FathersObjects');
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
