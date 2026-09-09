<?php

declare(strict_types=1);

namespace App\Sion;

use App\Laminas\ServiceBridge;
use App\Acl\IsAllowed;
use Laminas\Form\FormInterface;
use SionModel\Form\DeleteEntityForm;

use function is_array;
use function is_string;

/**
 * The half of `SionModel\Controller\SionController::deleteAction()` that every entity
 * delete confirmation shares — one reproduction serving batch 8's seven routes.
 *
 * Sibling of `App\Sion\EntityEdit`, written to the same rule and for the same reason: the
 * laminas action reaches four controller plugins that need an MvcEvent, and it is inherited
 * by a dozen controllers this batch does not touch, so it is copied rather than shared, and
 * the copy is pinned by `tools/port-baseline.php`.
 *
 * ## Seven routes, not twelve
 *
 * Twelve laminas route names contain `delete`. Nine of them reach *this* action; the batch
 * ports seven, and the two it leaves are **unreachable rather than skipped**:
 *
 *   - `event-delete` and `libraries/library/delete` have no entry in the route guard at all,
 *     and BjyAuthorize's Route guard is default-deny. Both answer **403 to an account
 *     holding every role** — measured 2026-08-14, with the all-roles identity
 *     `tools/form-regression.php` mints, not deduced from the config.
 *   - `libraries/library/delete` is dead twice over: `library` declares no
 *     `enable_delete_action`, so even past the guard it would only ever reach the
 *     configuration refusal below.
 *   - `sion-model/delete-entity` is a third dead route that does not reach this action:
 *     it names a `deleteEntity` action, and `SionModelController` has no such method.
 *
 * The remaining two delete routes — `juser/user/delete` and `jtranslate/phrase/delete` —
 * have their own controllers, their own forms and their own tables.
 *
 * ## What the action does, in order, and why the order is observable
 *
 *   1. **Is the entity deletable at all**, i.e. `enable_delete_action` plus a table name and
 *      key. A refusal here is a *configuration* message, not a permission one.
 *   2. **The per-row ACL check**, `isActionAllowed('delete')`. Of the seven, only
 *      `publication` declares an `acl_resource_id_field`, with `acl_delete_permission
 *      => delete`.
 *   3. **The deprecated second ACL check** on `delete_action_acl_resource`. Inert for all
 *      seven and reproduced anyway — see `deprecatedResourceAllows()`.
 *   4. **Existence**, `existsEntity()`.
 *   5. The **POST branch**: a cancellation, then the CSRF, then the delete.
 *
 * Step 4 coming last is the observable part, and it differs from `editAction()`, which
 * checks existence *first*. Two consequences, both real:
 *
 *   - A visitor without the per-row permission is told they lack permission even for a
 *     record that does not exist, where the edit form would have said "not found".
 *   - `existsEntity()` queries the table directly rather than through the entity
 *     projection, so a row that exists but cannot hydrate — 142 of the 1,468 rows in
 *     `sch_roles`, `/roles/1/delete` among them — renders a **confirmation** here while
 *     `/roles/1/edit` answers "Role not found." Measured, and captured in the baseline as
 *     a 200 for exactly that reason.
 *
 * ## What it deliberately does not reproduce: two wrong status codes
 *
 * `deleteAction()` sets a 401 on the not-found branch and then returns a redirect. The
 * redirect response replaces it, so the 401 never reaches the client and the observable
 * answer is a plain 302 — which is what this reproduces. (`JTranslateController` carries
 * the same dead line, with a comment recording the same finding.)
 *
 * The 401 on a *failed CSRF* is not dead: that branch renders the form, so the status is
 * observable, and it is reproduced verbatim even though 400 or 403 would be the right code.
 * Correcting either is filed in docs/BACKLOG.md rather than folded into a port.
 */
final class EntityDelete
{
    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly Entities $entities
    ) {
    }

    /**
     * `Entity::isEnabledForEntityDelete()` — the configuration gate, which is about the
     * *entity* and not about the visitor.
     *
     * True for all seven of the batch. It is still asked, because the message it guards is
     * different in kind from a permission refusal and a future entity added to a delete
     * route without `enable_delete_action` should get that message rather than a working
     * delete button.
     */
    public function isDeletable(string $entity): bool
    {
        $spec = $this->entities->specification($entity);
        if (null === $spec) {
            return false;
        }

        return (bool) $spec->enableDeleteAction
            && null !== $this->entities->stringField($entity, 'tableName')
            && null !== $this->entities->stringField($entity, 'tableKey');
    }

    /**
     * `SionController::isActionAllowed('delete')`, a no-op for the six entities of the
     * seven that declare no `acl_resource_id_field`.
     *
     * The privilege argument is load-bearing and the reason is the one
     * `EntityEdit::isEditAllowed()` and `EntityShow::isShowAllowed()` both record:
     * `Acl::isAllowed()` with a null privilege asks whether the role holds **every**
     * privilege on the resource, so dropping `delete` here would refuse every visitor on
     * `publication`. `Entity::$isActionAllowedPermissionProperties['delete']` is
     * `aclDeletePermission`.
     *
     * Laminas reads the resource id off the entity object, which it fetches for this check
     * — so this method fetches it too, and a row that cannot hydrate refuses rather than
     * handing null to `isAllowed()`, which would throw on an unknown resource. Same choice
     * as the other two, and the safe half of a data problem.
     */
    public function isDeleteAllowed(string $entity, int $id): bool
    {
        $resource = $this->entities->stringField($entity, 'aclResourceIdField');
        if (null === $resource) {
            return true;
        }

        /** @var mixed $object */
        $object = $this->entities->table($entity)->getObject($entity, $id, true);
        if (! is_array($object) || ! isset($object[$resource]) || ! is_string($object[$resource])) {
            return false;
        }

        return $this->isAllowed($object[$resource], $this->entities->stringField($entity, 'aclDeletePermission'));
    }

    /**
     * The deprecated `delete_action_acl_resource` check, reproduced although **no entity
     * this batch serves declares one**.
     *
     * Reproducing dead code needs a reason, and here it is: the two entities that do declare
     * it, `checkout` and `borrower`, spell the resource `checkout_:id` and `event_:id` — a
     * placeholder the action never substitutes, so laminas asks the ACL about a resource
     * named literally `checkout_:id` and `Acl::isAllowed()` throws on an unknown resource.
     * Neither is reachable through a route today.
     *
     * Leaving the check out would mean that adding `delete_action_acl_resource` to one of
     * the seven silently produced a *more* permissive Symfony route than its laminas twin,
     * and nothing would fail: `tools/acl-table.php` compares route guards, and this is not
     * one. Six lines is cheaper than that failure mode.
     */
    public function deprecatedResourceAllows(string $entity): bool
    {
        $resource = $this->entities->stringField($entity, 'deleteActionAclResource');
        if (null === $resource) {
            return true;
        }

        return $this->isAllowed($resource, $this->entities->stringField($entity, 'deleteActionAclPermission'));
    }

    /**
     * `SionTable::existsEntity()` — a direct `SELECT` against the entity's own table, not a
     * read through its projection. See the class docblock on why that difference shows.
     */
    public function exists(string $entity, int $id): bool
    {
        return (bool) $this->entities->table($entity)->existsEntity($entity, $id);
    }

    /**
     * `SionTable::deleteEntity()` — a bare `DELETE ... WHERE <key> = ?`.
     *
     * **No cascade, and no foreign key in the schema either**: measured 2026-08-14, zero
     * constraints reference `sch_associations`. Deleting a parent leaves its children
     * naming a row that is gone, and the capsule already carries 142 roles and one
     * association in that state. That is characterized rather than changed — see
     * `test/Smoke/AssociationDeleteSmokeTest` and the product decision filed in
     * docs/BACKLOG.md — and it is worth knowing before adding a delete route to an entity
     * that has dependants.
     *
     * An `entryDeleted` row goes into `sch_changes`, which is why the change log is the
     * accurate modification time for everything else.
     */
    public function delete(string $entity, int $id): void
    {
        $this->entities->table($entity)->deleteEntity($entity, $id);
    }

    /**
     * `redirectAfterDelete()`'s priority, as a route name — and it is a *different* chain
     * from `redirectAfterEdit()`'s, with no show route in it, which makes sense: the record
     * being redirected to no longer exists.
     *
     *     1. delete_action_redirect_route
     *     2. index_route
     *     3. null — the caller falls back to sion_model.default_redirect_route
     *
     * All seven declare the first, so branch 2 and the fallback are unreached today. Branch 1
     * used to be a trap for `text`, whose value was `text-delete` — the delete route itself,
     * a Segment route needing `sw_id`, so assembling it threw `Missing parameter "sw_id"`
     * and *every* exit from the action was a 500, the successful one included, after the row
     * had already gone. Fixed to `texts` in the same change that ported these routes.
     */
    public function redirectRoute(string $entity): ?string
    {
        return $this->entities->stringField($entity, 'deleteActionRedirectRoute')
            ?? $this->entities->stringField($entity, 'indexRoute');
    }

    /**
     * The confirmation form. `new DeleteEntityForm()` is what laminas does, with no
     * container lookup, so this is the same line rather than a reproduction of one.
     *
     * Its Cancel button is a `type="button"` since 2026-08-14. Before that it was a bare
     * element, which `FormButton` renders as `type="submit"`, and `deleteAction()` validated
     * the CSRF token without looking at which button was pressed — so **clicking Cancel
     * deleted the record**. Both halves of that fix live in SionModel, which is why this
     * port inherits it rather than reimplementing it, and `EntityDeleteController` refuses a
     * POST naming `cancel` for the same belt-and-braces reason the laminas action now does.
     *
     * @return FormInterface<array<string, mixed>>
     */
    public function form(): FormInterface
    {
        return new DeleteEntityForm();
    }

    /** The flash `deleteAction()` sets after a successful delete, in its own words. */
    public function deletedMessage(): string
    {
        return 'Entity successfully deleted.';
    }

    /** `BjyAuthorize`'s view helper, the same one the other two reproductions ask. */
    private function isAllowed(string $resource, ?string $privilege): bool
    {
        /** @var IsAllowed $isAllowed */
        $isAllowed = $this->laminas->get(IsAllowed::class);

        return (bool) $isAllowed->__invoke($resource, $privilege);
    }
}
