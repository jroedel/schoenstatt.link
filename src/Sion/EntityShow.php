<?php

declare(strict_types=1);

namespace App\Sion;

use App\Laminas\ServiceBridge;
use BjyAuthorize\View\Helper\IsAllowed;
use Closure;
use RuntimeException;
use SionModel\Db\Model\PredicatesTable;
use SionModel\Db\Model\SionTable;
use SionModel\Entity\Entity;
use SionModel\Form\CommentForm;
use SionModel\Service\EntitiesService;

use function is_array;
use function is_string;

/**
 * The half of `SionModel\Controller\SionController::showAction()` that every entity
 * show page shares — one reproduction serving `association`, `composition`, `text` and
 * `publication`.
 *
 * ## Why a copy, and what pins it
 *
 * docs/strangler.md's rule: share where sharing does not mean *editing* a laminas
 * action, copy where it would, and pin the copy with a parity test that drives both.
 * `showAction()` is a 90-line method on a controller that reaches four plugins —
 * `flashMessenger`, `isAllowed`, `url`, `redirect` — none of which resolve without an
 * MvcEvent, and it is inherited by twelve controllers this batch does not touch. So it
 * is copied, and `test/Integration/EntityShowParityTest` drives both over the same rows.
 * When the last of the four laminas routes goes, `showAction()` and that test go with it.
 *
 * ## What it reproduces, in order
 *
 * `showAction()` does seven things before it hands a view model back, and the order is
 * load-bearing in two places:
 *
 * 1. The **not-found** branches. `existsEntity()` and then a null `getObject()` — two
 *    checks, not one, because a row can exist and still hydrate to null for a visitor
 *    the projection filters. Both set a flash and redirect to the entity's index route.
 * 2. The **per-entity ACL check**, `isActionAllowed('show')`. This applies to exactly one
 *    of the four entities: `publication` declares `acl_resource_id_field => resourceId`
 *    *and* `acl_show_permission => show`, and the other three declare neither, in which
 *    case `isActionAllowed()` returns true before it looks at anything. The privilege
 *    matters — see `isShowAllowed()`, where dropping it silently 302s every publication
 *    page for every anonymous visitor.
 * 3. `getEntityChanges()`, the change log the page renders for a moderator.
 * 4. The **comment list and form**, when the entity has a comment predicate — see
 *    App\Sion\CommentPredicates for why that is a query rather than a list.
 * 5. `registerVisit()`, which **INSERTs a row on a GET**. Reproduced rather than
 *    quietly dropped: the visit counters are rendered at the foot of all four pages,
 *    and a ported page that stopped counting would show a number that silently stopped
 *    growing. It is also why tools/port-baseline.php normalizes visit digits — two
 *    captures of one URL disagree by construction.
 * 6. `getVisitCounts()`, defaulting to zeroes for an entity nobody has visited.
 *
 * The ACL check is **after** the existence checks here as it is there, and that
 * ordering is observable: a signed-in visitor asking for a publication that does not
 * exist gets the not-found flash rather than a denial, which is what tells them the
 * identifier is wrong rather than that they lack a role.
 *
 * ## What it does not do
 *
 * The per-page extras stay in their controllers, because that is where they are on the
 * laminas side too — `AssociationsController::showAction()` adds the schema.org object
 * and the anonymous-visitor rule, `PublicationsController::showAction()` adds the Drive
 * files, the library copies and the merged-edition 301. Each of those calls
 * `parent::showAction()` first and decorates the result, and this class is that parent.
 */
final class EntityShow
{
    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly CommentPredicates $predicates
    ) {
    }

    /**
     * The entity's row plus everything the template needs around it, or null when the
     * page should redirect instead — which is both of the not-found branches and the
     * ACL denial.
     *
     * Null rather than three distinct results because the caller does the same thing in
     * all three cases and laminas does too: flash, then redirect to the index. What
     * differs is only the message, and `deniedMessage()` answers that.
     *
     * ## `$loader`, and the bug that made it necessary
     *
     * `SionController::getEntityObject()` calls `SionTable::getObject()`, and two of the
     * twelve controllers that inherit it **override that method** —
     * `AssociationsController` calls `SchoenstattTable::getAssociation()` instead. That
     * is not a refinement: `getObject('association', …)` falls through to
     * `tryGettingObject()`, a single unlinked `SELECT`, because the association entity
     * spec's `get_object_function` is commented out. `getAssociation()` goes through
     * `getAssociations()` → `linkAssociations()`, which is what attaches
     * `childAssociations`, `parent`, `roles` and `assignments`.
     *
     * So a shared reproduction that always calls `getObject()` renders an association
     * page **missing its entire "Associated organizations" panel, its parent link, its
     * role list and its contact people** — and renders perfectly happily while doing it,
     * because every one of those is an `is not empty` away from simply not being drawn.
     * Caught by diffing against the laminas baseline: the ported page was 7 KB smaller
     * and one `panel-title` short. A status-code test would never have seen it.
     *
     * Hence the hook, rather than a special case inside this class: the override lives
     * in the controller on the laminas side, and it lives in the controller here.
     *
     * @param Closure(int): mixed $loader replaces `getObject()` for an entity whose
     *        laminas controller overrides `getEntityObject()`
     * @param string|null $selfUrl where a posted comment should come back to — the
     *        `redirect` field of the CommentForm, which `showAction()` fills with
     *        `url(null, [], [], true)`, i.e. the page the visitor is on. **Not optional
     *        in practice**: leaving it empty makes every comment fall through to
     *        App\Controller\CommentCreateController's referer fallback, which is a
     *        different URL whenever a visitor arrives from anywhere but the page itself.
     *        Caught by the baseline diff as thirteen missing bytes in a hidden input.
     */
    public function load(
        string $entity,
        int $id,
        ?Closure $loader = null,
        ?string $selfUrl = null
    ): ?EntityShowData {
        $table = $this->table($entity);

        if (! $table->existsEntity($entity, $id)) {
            return null;
        }

        //`true` is $failSilently — SionController::getEntityObject() passes it, so a
        //projection that cannot build this row answers null rather than throwing.
        //Held as mixed on purpose: SionTable::getObject() is annotated `@return mixed[]`
        //and returns null on that path, so the annotation is wrong and the guard below
        //is the one that matters. Typing the local would make PHPStan believe the
        //docblock and call the check redundant.
        /** @var mixed $object */
        $object = null === $loader ? $table->getObject($entity, $id, true) : $loader($id);
        if (! is_array($object) || [] === $object) {
            return null;
        }
        /** @var array<string, mixed> $object */

        if (! $this->isShowAllowed($entity, $object)) {
            return null;
        }

        $spec       = $this->specification($entity);
        $keyField   = null !== $spec && is_string($spec->entityKeyField) ? $spec->entityKeyField : null;
        $predicate  = $this->predicates->forEntity($entity);
        $comments   = [];
        $commentForm = null;

        if (null !== $predicate) {
            /** @var PredicatesTable $predicateTable */
            $predicateTable = $this->laminas->get(PredicatesTable::class);
            $found          = $predicateTable->getComments([
                'predicateKind'  => $predicate,
                'objectEntityId' => $id,
                'status'         => PredicatesTable::COMMENT_STATUS_PUBLISHED,
            ]);
            $comments = is_array($found) ? $found : [];

            //see App\Controller\CommentCreateController::form() for why `new` is the
            //application's own answer here rather than a container lookup
            $commentForm = new CommentForm();
            $commentForm->get('redirect')->setValue($selfUrl ?? '');
        }

        //registerVisit() takes the *key field's* value, not the route id — they are the
        //same number for all four of these entities, but showAction() reads it out of
        //the row and so does this
        $visitId = null !== $keyField && isset($object[$keyField]) ? $object[$keyField] : $id;
        $table->registerVisit($entity, $visitId);

        $counts = $table->getVisitCounts($entity, [$id]);
        $visits = is_array($counts) && isset($counts[$id]) && is_array($counts[$id])
            ? $counts[$id]
            : ['total' => 0, 'pastMonth' => 0];

        return new EntityShowData(
            $object,
            $table->getEntityChanges($entity, $id),
            $comments,
            $commentForm,
            $visits
        );
    }

    /**
     * The message `showAction()` flashes before redirecting, in the namespace it uses.
     *
     * Two different strings for what `load()` reports as one null, and the distinction
     * is the visitor's: `ucfirst($entity) . ' not found.'` when the row is missing,
     * 'Access to entity denied.' when the ACL refused it. Since `load()` has already
     * collapsed them, the caller asks here and this re-runs the cheap half of the
     * question — the existence check — rather than having `load()` return a tri-state
     * nobody else needs.
     */
    public function deniedMessage(string $entity, int $id): string
    {
        return $this->table($entity)->existsEntity($entity, $id)
            ? 'Access to entity denied.'
            : ucfirst($entity) . ' not found.';
    }

    /**
     * `SionController::isActionAllowed('show')`, which is a no-op for every entity that
     * declares no `acl_resource_id_field` — three of this batch's four.
     *
     * **The privilege argument is the whole of this method.** `publication` declares
     * both `acl_resource_id_field => resourceId` and `acl_show_permission => show`, and
     * passing the second is not optional decoration: `publication_public` is allowed to
     * `guest` *for the `show` privilege only*, and `Laminas\Permissions\Acl::isAllowed()`
     * with a null privilege asks whether the role is allowed **every** privilege on the
     * resource. Measured against the capsule's ACL:
     *
     *     isAllowed('publication_public', null) = false
     *     isAllowed('publication_public', 'show') = true
     *
     * So dropping the privilege turns every publication page into a redirect for every
     * anonymous visitor — a 302 that looks exactly like "the publication does not
     * exist". This method was written without it and that is what it did; the live
     * laminas page answering 200 anonymously is what proved it wrong.
     *
     * @param array<string, mixed> $object
     */
    private function isShowAllowed(string $entity, array $object): bool
    {
        $spec     = $this->specification($entity);
        $resource = null !== $spec && is_string($spec->aclResourceIdField) ? $spec->aclResourceIdField : null;
        if (null === $resource) {
            return true;
        }

        if (! isset($object[$resource]) || ! is_string($object[$resource])) {
            //laminas would pass null straight to isAllowed(), which throws on an
            //unknown resource. A row with no resource id is a data problem, not a
            //permission, and refusing is the safe half of it.
            return false;
        }

        //Entity::$isActionAllowedPermissionProperties['show'], and the isset() around it
        //is SionController's: an entity may declare a resource field and no permission,
        //in which case the one-argument form really is what laminas calls.
        //Entity::$isActionAllowedPermissionProperties['show']. The guard is
        //SionController's `isset($entitySpec->$permissionProperty)`: an entity may
        //declare a resource field and no permission, in which case the one-argument
        //form really is what laminas calls.
        $permission = null !== $spec && is_string($spec->aclShowPermission)
            ? $spec->aclShowPermission
            : null;

        /** @var IsAllowed $isAllowed */
        $isAllowed = $this->laminas->get('ViewHelperManager')->get('isAllowed');

        return (bool) $isAllowed->__invoke($object[$resource], $permission);
    }

    private function specification(string $entity): ?Entity
    {
        /** @var EntitiesService $entities */
        $entities = $this->laminas->get(EntitiesService::class);
        /** @var mixed $all */
        $all = $entities->getEntities();
        if (! is_array($all) || ! isset($all[$entity]) || ! $all[$entity] instanceof Entity) {
            return null;
        }

        return $all[$entity];
    }

    /**
     * The entity's own table service, named by its `sion_model_class`.
     */
    private function table(string $entity): SionTable
    {
        $spec  = $this->specification($entity);
        $class = null !== $spec && is_string($spec->sionModelClass) ? $spec->sionModelClass : null;
        if (null === $class) {
            throw new RuntimeException("Entity '$entity' declares no sion_model_class.");
        }

        /** @var SionTable $table */
        $table = $this->laminas->get($class);

        return $table;
    }
}
