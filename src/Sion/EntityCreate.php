<?php

declare(strict_types=1);

namespace App\Sion;

use App\Books\LibraryScopedForms;
use App\Laminas\ServiceBridge;
use Laminas\Form\FormInterface;
use RuntimeException;

use function count;
use function is_array;
use function is_scalar;
use function ucwords;

/**
 * The half of `SionModel\Controller\SionController::createAction()` that every entity
 * create form shares — batch 9's counterpart to `App\Sion\EntityEdit`.
 *
 * ## What createAction() does, and what is reproduced here
 *
 * 1. **Refuse without a form.** No `create_action_form` in the spec and laminas throws
 *    `InvalidArgumentException`. Reproduced in `form()`, and it is not hypothetical:
 *    `event`'s spec has the key commented out, which is one of the three reasons
 *    `/timeline/create` is unportable (see docs/strangler.md).
 * 2. **The form**, from the container by that key — except for the three library-scoped
 *    ones, exactly as on the edit surface.
 * 3. **On POST**, `setData()` then `isValid()` then `SionTable::createEntity()`.
 * 4. **The redirect**, whose priority `redirectTarget()` reproduces.
 *
 * ## The one thing createAction() does *not* do, and it is worth knowing
 *
 * **There is no ACL check.** `showAction()`, `editAction()` and `deleteAction()` each open
 * with `isActionAllowed(...)`, which consults the row's own `acl_resource_id_field`.
 * `createAction()` has no such call and `Entity::$isActionAllowedPermissionProperties` has
 * no `create` entry — there is no row yet to carry a resource id, so there is nothing for a
 * per-row rule to be about. What guards a create page is therefore the **route guard alone**,
 * plus a hand-written check in the two laminas controllers where the new row lands inside an
 * existing library: `BooksController::createAction()` and `CollectionsController::createAction()`
 * both ask `isAllowed('library_' . $library_id, 'administrate')` and throw
 * `UnAuthorizedException` before calling their parent. That check is a *route* concern here
 * and lives in `App\Controller\EntityCreateController`, declared per route rather than
 * inferred, for the reason `EntityEditController::deleteAction()` records at length.
 *
 * ## Why a copy rather than a call
 *
 * The same reason `EntityEdit` gives: `createAction()` reaches four MVC plugins that do not
 * resolve without an MvcEvent, and it is inherited by a dozen controllers this batch does
 * not touch. What pins the copy is `tools/port-baseline.php` plus the create-surface smoke
 * tests, not a parity test — the laminas action cannot be driven at all without an MvcEvent.
 */
final class EntityCreate
{
    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly Entities $entities,
        private readonly LibraryScopedForms $scopedForms
    ) {
    }

    /**
     * The create form for an entity.
     *
     * `$libraryId` is for the three library-scoped forms and comes from the **route**, not
     * from a row — which is the whole difference between this and `EntityEdit::form()`. On
     * an edit the library is discovered from the record being edited; on a create there is
     * no record, and `books/create/{library_id}` and `collections/create/{library_id}` carry
     * it in the path. `libraries/create` carries nothing, and null is correct there rather
     * than exceptional: `Books\Service\LibraryFormFactory` guards its own lookup with
     * `if (isset($libraryId))` and simply leaves `mainCollectionId` without options, because
     * a library that does not exist yet has no collections.
     *
     * @return FormInterface<array<string, mixed>>
     */
    public function form(string $entity, ?int $libraryId = null): FormInterface
    {
        if (LibraryScopedForms::handles($entity)) {
            return $this->scopedForms->formForLibrary($entity, $libraryId);
        }

        $service = $this->entities->stringField($entity, 'createActionForm');
        if (null === $service) {
            throw new RuntimeException(
                "Entity '$entity' declares no create_action_form, so it has no create form to render."
            );
        }

        /** @var mixed $instance */
        $instance = $this->laminas->get($service);
        if (! $instance instanceof FormInterface) {
            throw new RuntimeException("The container did not return a form for '$entity'.");
        }

        /** @var FormInterface<array<string, mixed>> $instance */
        return $instance;
    }

    /**
     * `SionTable::createEntity()`, which is what `createEntityPostFormValidation()` calls.
     *
     * Returns the new id, or 0 when the write did not happen — laminas tests the return
     * value with a bare `if (! ($newId = …))` and shows "Error in form submission, please
     * review." rather than throwing, so a falsy id is a message and not an exception.
     *
     * @param array<string, mixed> $data
     */
    public function create(string $entity, array $data): int
    {
        /** @var mixed $newId */
        $newId = $this->entities->table($entity)->createEntity($entity, $data);

        return is_scalar($newId) ? (int) $newId : 0;
    }

    /**
     * The new row, for the redirect.
     *
     * `redirectAfterCreate()` calls `getEntityObject($newId)` — i.e. `getObject()` — for
     * every branch that needs a field off the record. An entity whose laminas controller
     * overrides `getEntityObject()` is not in this batch; `association` is the only one that
     * does and its create route reaches branch 4 below, which reads `identifier` — a column
     * the plain `getObject()` returns.
     *
     * @return array<string, mixed>
     */
    public function row(string $entity, int $id): array
    {
        /** @var mixed $object */
        $object = $this->entities->table($entity)->getObject($entity, $id, true);

        return is_array($object) ? $object : [];
    }

    /**
     * `SionTable::existsEntity()`, for the book form's `?copyBook=` prefill.
     *
     * Asked before the row is fetched because that is the order
     * `BooksController::createAction()` asks in, and the difference is observable: a
     * `copyBook` naming nothing at all leaves the form untouched rather than filling it
     * with the empty values a missing row would yield.
     */
    public function exists(string $entity, int $id): bool
    {
        return (bool) $this->entities->table($entity)->existsEntity($entity, $id);
    }

    /**
     * The flash `createEntityPostFormValidation()` sets.
     *
     * `ucwords`, not `ucfirst` — the create path and the update path disagree about this in
     * the original (`ucwords($entity) . ' successfully created.'` against
     * `ucfirst($entity) . ' successfully updated.'`) and the difference is visible for
     * `dictionary-entry`, whose message reads "Dictionary-entry successfully created."
     * either way, and for nothing else in this batch. Reproduced rather than harmonised:
     * the two messages are separate phrases in the translation table already.
     */
    public function createdMessage(string $entity): string
    {
        return ucwords($entity) . ' successfully created.';
    }

    /**
     * `redirectAfterCreate()`'s priority, as a route name and parameters.
     *
     *     1. createActionRedirectRoute + createActionRedirectRouteParams
     *     2. createActionRedirectRoute + defaultRouteParams
     *     3. createActionRedirectRoute + [createActionRedirectRouteKey => the new id]
     *     4. createActionRedirectRoute + [key => the new row's createActionRedirectRouteKeyField]
     *     5. null — the caller falls back to sion_model.default_redirect_route
     *
     * Branch 3 is the "the key field *is* the primary key" case, and its condition is
     * three-part in the original: no `create_action_redirect_route_key_field`, **or** that
     * field equals the spec's `entity_key_field`, **or** no
     * `create_action_redirect_route_key`. All three mean "there is nothing to look up", and
     * the third means the route takes no parameter at all.
     *
     * **Branches 1, 2 and 4 throw on a missing field**, where `EntityEdit::redirectTarget()`
     * falls through for the two param maps. That asymmetry is the original's — `createAction`
     * throws `Error while redirecting after a successful create. Missing param` in both map
     * branches — and it is reproduced rather than tidied. It is also unreachable for the
     * nine entities in this batch, all of which supply every field they name.
     *
     * @param array<string, mixed> $row the new row, as `row()` returned it
     * @return array{0: string, 1: array<string, string>}|null
     */
    public function redirectTarget(string $entity, int $newId, array $row): ?array
    {
        $route = $this->entities->stringField($entity, 'createActionRedirectRoute');
        if (null === $route) {
            return null;
        }

        foreach (['createActionRedirectRouteParams', 'defaultRouteParams'] as $field) {
            $map = $this->entities->mapField($entity, $field);
            if (null !== $map && [] !== $map) {
                return [$route, $this->fill($entity, $map, $row)];
            }
        }

        $key      = $this->entities->stringField($entity, 'createActionRedirectRouteKey');
        $keyField = $this->entities->stringField($entity, 'createActionRedirectRouteKeyField');

        $entityKey = $this->entities->stringField($entity, 'entityKeyField');

        if (null === $keyField || null === $key || $keyField === $entityKey) {
            return [$route, null === $key ? [] : [$key => (string) $newId]];
        }

        if (! isset($row[$keyField])) {
            throw new RuntimeException(
                "create_action_redirect_route_key_field is misconfigured for entity '$entity'."
            );
        }

        return [$route, [$key => $this->scalar($row[$keyField])]];
    }

    /**
     * A declared parameter map filled from the new row; every entry must be satisfiable.
     *
     * @param array<string, string> $map routeParam => entityField
     * @param array<string, mixed>  $row
     * @return array<string, string>
     */
    private function fill(string $entity, array $map, array $row): array
    {
        $params = [];
        foreach ($map as $routeParam => $field) {
            if (! isset($row[$field])) {
                throw new RuntimeException(
                    "Error while redirecting after creating a '$entity'. Missing param `$field`."
                );
            }
            $params[$routeParam] = $this->scalar($row[$field]);
        }

        if (count($params) !== count($map)) {
            throw new RuntimeException("Invalid route parameter configuration for '$entity'.");
        }

        return $params;
    }

    /** Route parameters are strings; a row's value may be an int or a stringable. */
    private function scalar(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
