<?php

declare(strict_types=1);

namespace App\Sion;

use App\Books\LibraryScopedForms;
use App\Laminas\ServiceBridge;
use App\Acl\IsAllowed;
use Closure;
use SionModel\Form\FormInterface;
use SionModel\Form\DeleteEntityForm;
use RuntimeException;

use function count;
use function is_array;
use function is_scalar;
use function is_string;
use function ucfirst;

/**
 * The half of `SionModel\Controller\SionController::editAction()` that every entity edit
 * form shares — one reproduction serving the ten routes of batch 7 plus the
 * `association-edit` that was ported by hand before it existed.
 *
 * ## Why a copy, and what actually pins it
 *
 * docs/laminas-exit.md's rule: share where sharing does not mean *editing* a laminas action,
 * copy where it would, and pin the copy. `editAction()` is a 100-line method reaching
 * four plugins — `params`, `flashMessenger`, `nowMessenger`, `redirect` — none of which
 * resolve without an MvcEvent, and it is inherited by a dozen controllers this batch does
 * not touch. So it is copied.
 *
 * **What pins it is `tools/port-baseline.php`, not a parity test**, and that is worth
 * stating plainly because `App\Sion\EntityShow` claimed otherwise for a day: its docblock
 * cited a `test/Integration/EntityShowParityTest` that has never existed, and the only
 * mention of that name in the repository was the claim itself. A two-sided parity test is
 * not available here for the same reason it is not available for `formatEntity` — the
 * laminas action cannot be driven at all without an MvcEvent — so the honest guarantee is
 * the eleven edit paths captured from laminas in `PATHS` and compared per locale per
 * identity. That is the check that found six defects in batch 5.
 *
 * ## What it reproduces, in order
 *
 * `editAction()` does six things before it hands a view model back:
 *
 * 1. The **id**, from `getEntityIdParam('edit')`. Not reproduced here: a Symfony route
 *    hands its parameter over directly, and the two entities whose laminas controllers
 *    override that method (`text` and `composition`, translating `sw_id` → numeric id)
 *    already have `App\Sion\SiteWideIdentifier` for it. A falsy id becomes the same
 *    not-found answer as a missing row, which is `editAction()`'s first branch.
 * 2. The **row**, from `getEntityObject($id)` — `getObject($entity, $id, true)`, unless
 *    the controller overrides it. Null is not-found. See `$loader`.
 * 3. The **per-entity ACL check**, `isActionAllowed('edit')`. Four of this batch's ten
 *    entities declare an `acl_resource_id_field`: `library`, `book` and `collection` name
 *    `resourceId` with `acl_edit_permission => administrate`, and `publication` names it
 *    with `edit`. The other six declare neither and the check returns true before it looks
 *    at anything.
 * 4. The **form**, from the laminas container by `edit_action_form`. Out of the container
 *    rather than `new`, so its value options and its validation are the application's.
 * 5. On **POST**, `setData()` then `isValid()` then `SionTable::updateEntity()`; on a GET,
 *    `setData($entityObject)`.
 * 6. The **redirect after a successful write**, whose four-branch priority is
 *    `redirectTarget()` below.
 *
 * The existence checks come **before** the ACL check here as they do there, and the order
 * is observable: a moderator asking to edit a row that does not exist is told the row is
 * missing rather than that they lack a role.
 *
 * ## What stays in the controller
 *
 * Everything per-entity, because that is where it lives on the laminas side too:
 * `PublicationsController::editAction()` injects selectize value options after calling
 * `parent::editAction()`, `DictionaryController` and `TextsController` override
 * `redirectAfterEdit()`, and `AssociationsController::editAction()` narrows the time-zone
 * dropdown and relabels `publicNotes` for a shrine. This class is that parent.
 */
final class EntityEdit
{
    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly Entities $entities,
        private readonly LibraryScopedForms $scopedForms
    ) {
    }

    /**
     * The row to be edited, or null when the page must redirect instead — which is both
     * not-found branches and the ACL denial, collapsed the way `EntityShow::load()`
     * collapses them, because the caller does the same thing in all three cases and
     * laminas does too. `deniedMessage()` answers which message.
     *
     * @param Closure(int): mixed|null $loader replaces `getObject()` for an entity whose
     *        laminas controller overrides `getEntityObject()`.
     *
     *        **Only `association` needs it, and none of batch 7's ten do.** An earlier
     *        draft of this docblock claimed four of them did, on the assumption that a
     *        spec naming a `get_object_function` had to be dispatched by hand. It does
     *        not: `SionTable::getObject()` reads `$entitySpec->getObjectFunction` and
     *        calls it, falling back to `tryGettingObject()` only when it is unset or
     *        would resolve to a `SionTable` method. So `getPublication`, `getPerson`,
     *        `getRole`, `getAssignment` and `getLibraryImport` are all reached by the
     *        plain call. `association` is the exception for the reason batch 5 recorded:
     *        its spec's `get_object_function` is *commented out*, so `getObject()` really
     *        does fall through to a single unlinked `SELECT`, and
     *        `AssociationsController::getEntityObject()` calls `getAssociation()` instead.
     *
     *        Worth stating because the failure mode is worse here than on the show page.
     *        There, a wrong loader dropped four panels off a read-only page; on an edit
     *        form it renders fields the row could not populate as empty, and saving that
     *        form writes the blanks back.
     * @return array<string, mixed>|null
     */
    public function load(string $entity, int $id, ?Closure $loader = null): ?array
    {
        if (0 === $id) {
            return null;
        }

        $table = $this->entities->table($entity);

        //`true` is $failSilently, which SionController::getEntityObject() passes: a
        //projection that cannot build this row answers null rather than throwing. Held as
        //mixed because SionTable::getObject() is annotated `@return mixed[]` and returns
        //null on that path, so the annotation is wrong and the guard below is the real one.
        /** @var mixed $object */
        $object = null === $loader ? $table->getObject($entity, $id, true) : $loader($id);
        if (! is_array($object) || [] === $object) {
            return null;
        }

        /** @var array<string, mixed> $object */
        if (! $this->isEditAllowed($entity, $object)) {
            return null;
        }

        return $object;
    }

    /**
     * The message `editAction()` flashes before redirecting, in the namespace it uses.
     *
     * `ucfirst($entity) . ' not found.'` is laminas' string and is reproduced including
     * its rough edge: the entity *key* is what gets capitalised, so `dictionary-entry`
     * flashes "Dictionary-entry not found." and `library-import` "Library-import not
     * found.". Ugly, untranslated, and what the page says today.
     *
     * ## The discriminator is `getObject()`, not `existsEntity()`
     *
     * This asked `existsEntity()` until the batch-7 baseline caught it, and the difference
     * is not academic. **`editAction()` never calls `existsEntity()` at all**: it branches
     * on `getEntityObject()` being null for "not found", and only then on
     * `isActionAllowed()` for "denied". So a row that exists in the table but does not
     * hydrate through its projection is *not found* on laminas — and there are 142 such
     * roles of 1,468, `/roles/1/edit` among them.
     *
     * Asking `existsEntity()` made that page flash "Access to entity denied." where laminas
     * flashes "Role not found.", which tells a moderator they lack a permission when the
     * truth is the record is unreachable. The wrong discriminator came from
     * `App\Sion\EntityShow::deniedMessage()`, where it is *correct* — `showAction()` really
     * does call `existsEntity()` first — which is exactly why copying it looked safe.
     *
     * The cost is one extra `getObject()` on the denial path, which `load()` has already
     * done. That is the same shape `EntityShow` accepts, and it is a redirect either way.
     */
    public function deniedMessage(string $entity, int $id): string
    {
        /** @var mixed $object */
        $object = $this->entities->table($entity)->getObject($entity, $id, true);

        return is_array($object) && [] !== $object
            ? 'Access to entity denied.'
            : ucfirst($entity) . ' not found.';
    }

    /**
     * Where a refused or not-found edit sends the visitor: the entity's `index_route`,
     * or null when it declares none and the caller should use
     * `sion_model.default_redirect_route`.
     *
     * Nine of the eleven declare one. `dictionary-entry` does not, and neither does
     * `text` — so both fall back, which is `editAction()`'s
     * `$entitySpec->indexRoute ? … : $this->getDefaultRedirectRoute()`.
     */
    public function indexRoute(string $entity): ?string
    {
        return $this->entities->stringField($entity, 'indexRoute');
    }

    /**
     * The form the laminas controller would render, out of the laminas container.
     *
     * **Not `new`**, and this is the whole reason a ported form route is trustworthy:
     * `AssociationFormFactory` and its nine siblings populate value options out of the
     * database and attach the input filter that *is* the validation. Constructing the
     * class directly yields a form that renders and accepts anything.
     *
     * ## The three that cannot come out of the container
     *
     * `book`, `collection` and `library` name factories that read the route match to
     * discover their library, which a Symfony-served route has no MvcEvent to answer — so
     * asking the container for them dies with `Call to a member function getRouteMatch() on
     * null`, *after* the response is assembled, i.e. as an empty HTTP 200.
     * `App\Books\LibraryScopedForms` builds those three instead, from the row this method is
     * handed, and its docblock carries the full account including what the arrangement
     * costs.
     *
     * The dispatch is by name rather than by catching the Error, because catching it here
     * would also swallow a genuine fault in one of the seven forms that do build.
     *
     * @param array<string, mixed> $object the row `load()` returned, for the three above
     * @return FormInterface
     */
    public function form(string $entity, array $object = []): FormInterface
    {
        if (LibraryScopedForms::handles($entity)) {
            return $this->scopedForms->form($entity, $object);
        }

        $service = $this->entities->stringField($entity, 'editActionForm');
        if (null === $service) {
            //laminas throws InvalidArgumentException here, with this same reasoning: an
            //entity whose spec has no edit_action_form cannot use the edit action at all.
            throw new RuntimeException(
                "Entity '$entity' declares no edit_action_form, so it has no edit form to render."
            );
        }

        /** @var mixed $instance */
        $instance = $this->laminas->get($service);
        if (! $instance instanceof FormInterface) {
            throw new RuntimeException("The container did not return a form for '$entity'.");
        }

        /** @var FormInterface $instance */
        return $instance;
    }

    /**
     * The delete-confirmation form `editAction()` puts in every view model.
     *
     * `new DeleteEntityForm()` is what laminas does — literally, with no container lookup —
     * so this is not a reproduction so much as the same line. Two of the batch's ten
     * templates render it, `person` and `assignment`, each inside a Bootstrap modal gated on
     * the visitor holding the entity's *delete* route permission. The other eight ignore it,
     * exactly as their .phtml do.
     *
     * It posts to the laminas delete route, which stays on laminas: this batch ports the
     * edit verb only. So the modal is a Symfony-rendered form whose action is a bridged
     * URL — and the CSRF token it carries is accepted there because both front controllers
     * read the same laminas session.
     *
     * @return FormInterface
     */
    public function deleteForm(): FormInterface
    {
        return new DeleteEntityForm();
    }

    /**
     * `SionTable::updateEntity()`, which is exactly what
     * `updateEntityPostFormValidation()` calls, and returns the updated row.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function update(string $entity, int $id, array $data): array
    {
        /** @var mixed $updated */
        $updated = $this->entities->table($entity)->updateEntity($entity, $id, $data);

        return is_array($updated) ? $updated : [];
    }

    /** The flash `updateEntityPostFormValidation()` sets, with laminas' capitalisation. */
    public function updatedMessage(string $entity): string
    {
        return ucfirst($entity) . ' successfully updated.';
    }

    /**
     * `redirectAfterEdit()`'s four-branch priority, as a route name and parameters.
     *
     *     1. showRoute + showRouteParams        (params read from the *updated* row)
     *     2. showRoute + showRouteKey/KeyField  (read from the row as it was *loaded*)
     *     3. showRoute + defaultRouteParams     (params read from the *updated* row)
     *     4. indexRoute
     *     5. null — the caller falls back to sion_model.default_redirect_route
     *
     * **Branches 1 and 3 fall through when a field is missing; branch 2 throws.** That
     * asymmetry is the original's, commented `@todo log this` in both places, and it is
     * reproduced rather than tidied: a spec whose `showRouteKeyField` names a column the
     * row does not have is a configuration error that should be loud, while the two
     * param maps are allowed to be partially satisfied and simply move on.
     *
     * **Branch 2 reads `$loaded`, not `$updated`, and that is not a bug either.** In
     * laminas, `editAction()` calls `getEntityObject($id)` before the write and
     * `redirectAfterEdit()` calls it again after — but `getEntityObject()` memoizes into
     * `$this->object[$id]`, so the second call returns the row *as it was before the
     * update*. For all eleven entities the key field is derived from the primary key and
     * cannot change, so the two agree today; reproducing the memoization keeps a future
     * editable key field behaving the same on both front controllers rather than
     * differing invisibly.
     *
     * @param array<string, mixed> $loaded  the row as `load()` returned it, pre-write
     * @param array<string, mixed> $updated the row `update()` returned
     * @return array{0: string, 1: array<string, string>}|null
     */
    public function redirectTarget(string $entity, array $loaded, array $updated): ?array
    {
        $showRoute = $this->entities->stringField($entity, 'showRoute');

        if (null !== $showRoute) {
            $fromParams = $this->routeParams($entity, 'showRouteParams', $updated);
            if (null !== $fromParams) {
                return [$showRoute, $fromParams];
            }

            $key      = $this->entities->stringField($entity, 'showRouteKey');
            $keyField = $this->entities->stringField($entity, 'showRouteKeyField');
            if (null !== $key && null !== $keyField) {
                if (! isset($loaded[$keyField])) {
                    throw new RuntimeException(
                        "show_route_key_field config for entity '$entity' refers to a key that doesn't exist"
                    );
                }

                return [$showRoute, [$key => $this->scalar($loaded[$keyField])]];
            }

            $fromDefaults = $this->routeParams($entity, 'defaultRouteParams', $updated);
            if (null !== $fromDefaults) {
                return [$showRoute, $fromDefaults];
            }
        }

        $index = $this->indexRoute($entity);

        return null === $index ? null : [$index, []];
    }

    /**
     * One of the two param maps, filled from a row — or null when the spec declares no
     * such map, or when the row cannot satisfy every entry of it.
     *
     * "Every entry" is `count($params) === count($entitySpec->showRouteParams)` in the
     * original: a partially satisfiable map is not used at all, it falls through to the
     * next branch.
     *
     * @param array<string, mixed> $row
     * @return array<string, string>|null
     */
    private function routeParams(string $entity, string $field, array $row): ?array
    {
        $map = $this->entities->mapField($entity, $field);
        if (null === $map || [] === $map) {
            return null;
        }

        $params = [];
        foreach ($map as $routeParam => $entityField) {
            if (isset($row[$entityField])) {
                $params[$routeParam] = $this->scalar($row[$entityField]);
            }
        }

        return count($params) === count($map) ? $params : null;
    }

    /**
     * `SionController::isActionAllowed('edit')`, which is a no-op for the six entities
     * that declare no `acl_resource_id_field`.
     *
     * The privilege argument matters here for the same reason
     * `EntityShow::isShowAllowed()` documents at length: `Acl::isAllowed()` with a null
     * privilege asks whether the role is allowed **every** privilege on the resource, so
     * dropping `administrate` or `edit` would refuse every visitor on the four entities
     * that name one. `Entity::$isActionAllowedPermissionProperties['edit']` is
     * `aclEditPermission`.
     *
     * @param array<string, mixed> $object
     */
    private function isEditAllowed(string $entity, array $object): bool
    {
        $resource = $this->entities->stringField($entity, 'aclResourceIdField');
        if (null === $resource) {
            return true;
        }

        if (! isset($object[$resource]) || ! is_string($object[$resource])) {
            //laminas would hand null to isAllowed(), which throws on an unknown resource.
            //A row with no resource id is a data problem, not a permission, and refusing
            //is the safe half of it — the same choice EntityShow makes.
            return false;
        }

        $permission = $this->entities->stringField($entity, 'aclEditPermission');

        /** @var IsAllowed $isAllowed */
        $isAllowed = $this->laminas->get(IsAllowed::class);

        return (bool) $isAllowed->__invoke($object[$resource], $permission);
    }

    /** Route parameters are strings; a row's value may be an int or a stringable. */
    private function scalar(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
