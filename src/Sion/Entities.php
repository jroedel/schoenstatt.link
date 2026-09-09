<?php

declare(strict_types=1);

namespace App\Sion;

use App\Laminas\ServiceBridge;
use RuntimeException;
use SionModel\Db\Model\SionTable;
use SionModel\Entity\Entity;
use SionModel\Service\EntitiesService;

use function is_array;
use function is_string;

/**
 * The entity specification and table lookups every `App\Sion\*` reproduction needs.
 *
 * Extracted from `App\Sion\EntityShow` when `App\Sion\EntityEdit` arrived and would
 * otherwise have carried a second copy of both methods. docs/laminas-exit.md's rule is
 * explicit that the licence to copy stops at the laminas boundary — between two *ported*
 * pieces of code, share — and two answers to "which table holds this entity" is precisely
 * the divergence that rule exists to prevent.
 *
 * `EntitiesService::getEntities()` is asked once per instance and the result memoized:
 * it merges 24 specs out of the module configuration, and an edit request asks about its
 * entity at least four times (the id, the object, the ACL check, the redirect).
 */
final class Entities
{
    /** @var array<string, Entity>|null */
    private ?array $specifications = null;

    public function __construct(private readonly ServiceBridge $laminas)
    {
    }

    /**
     * The entity's specification, or null when the name is not one of the 24.
     *
     * Null rather than a throw because a caller reading an *optional* spec field wants to
     * ask without guarding, and `EntityShow` was written that way. `table()` below is the
     * one place where absence is fatal, and it throws there.
     */
    public function specification(string $entity): ?Entity
    {
        if (null === $this->specifications) {
            /** @var EntitiesService $entities */
            $entities = $this->laminas->get(EntitiesService::class);
            /** @var mixed $all */
            $all = $entities->getEntities();

            $specifications = [];
            if (is_array($all)) {
                foreach ($all as $name => $specification) {
                    if (is_string($name) && $specification instanceof Entity) {
                        $specifications[$name] = $specification;
                    }
                }
            }

            $this->specifications = $specifications;
        }

        return $this->specifications[$entity] ?? null;
    }

    /**
     * The entity's own table service, named by its `sion_model_class`.
     *
     * Six of the entities in this batch share one: `library`, `book`, `collection` and
     * `library-import` all name `Books\Model\LibraryTable`, and `person`, `role`,
     * `assignment` and `association` all name `Schoenstatt\Model\SchoenstattTable`. The
     * ServiceBridge memoizes services, so asking per entity costs one lookup rather than
     * one construction.
     */
    public function table(string $entity): SionTable
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

    /**
     * A string-valued field of an entity's specification, or null.
     *
     * `Entity`'s properties are untyped (it predates typed properties and is populated
     * from an array), so every read of one needs the same `is_string` narrowing to satisfy
     * PHPStan at level 8. Doing it here keeps that noise out of the callers, which read a
     * dozen of these between them.
     */
    public function stringField(string $entity, string $field): ?string
    {
        $spec = $this->specification($entity);
        if (null === $spec) {
            return null;
        }

        /** @var mixed $value */
        $value = $spec->$field ?? null;

        return is_string($value) && '' !== $value ? $value : null;
    }

    /**
     * An array-valued field of an entity's specification, or null.
     *
     * For the four route-parameter maps `redirectAfterEdit()` consults —
     * `showRouteParams`, `editRouteParams`, `defaultRouteParams` — each of which is a
     * `routeParam => entityField` map when set and absent otherwise.
     *
     * @return array<string, string>|null
     */
    public function mapField(string $entity, string $field): ?array
    {
        $spec = $this->specification($entity);
        if (null === $spec) {
            return null;
        }

        /** @var mixed $value */
        $value = $spec->$field ?? null;
        if (! is_array($value)) {
            return null;
        }

        $map = [];
        foreach ($value as $routeParam => $entityField) {
            if (is_string($routeParam) && is_string($entityField)) {
                $map[$routeParam] = $entityField;
            }
        }

        return $map;
    }
}
