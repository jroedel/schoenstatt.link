<?php

declare(strict_types=1);

namespace App\Provenance;

use function array_key_exists;
use function array_unique;
use function array_values;
use function is_array;
use function is_string;
use function sort;
use function str_ends_with;

/**
 * Which field group a given field belongs to, derived from the entity's own configuration.
 *
 * ## Why derived and not listed
 *
 * Because the alternative was tried and failed silently for years. `sch_associations`
 * declared ten field-group stamp pairs, and the config that fed them listed the seven
 * email/phone field names twice inside one array literal — once mapping to `emails`/`phones`
 * and once to `contactInfo`. PHP keeps the last value for a duplicate key without a word, so
 * two of the ten groups were unreachable, `Emails/PhonesUpdatedOn` were never written across
 * 498 associations and 325 persons, and the one piece of UI that displayed a verification
 * date was dead the whole time. `database/db8.9.sql` cleaned that up.
 *
 * A hand-maintained copy of this mapping would be the same mistake with a fresh coat of
 * paint. So the grouping is read out of `sion_model.entities.<entity>` at runtime and there
 * is exactly one place it can be wrong.
 *
 * ## The two rules
 *
 * A field's group is:
 *
 *   1. its `many_to_one_update_columns` entry, if it has one — this is how eleven contact
 *      fields collapse into `contactInfo`; otherwise
 *   2. **the field itself**, when the entity has a `<field>UpdatedOn` column — this is how
 *      `openingHoursHuman`, `eventsHuman`, `publicNotes` and `adminNotes` each form a group
 *      of one, which is what the schema already says about them.
 *
 * A field matching neither is **ungrouped**, and `groupFor()` answers null. That is not an
 * error: `name`, `kind`, `country` and `parentId` are identity rather than contact detail,
 * nobody verifies them on a schedule, and inventing a group for them would put rows in
 * `sch_provenance` that answer no question.
 */
final class FieldGroups
{
    /** @var array<string, string> field name => group name */
    private readonly array $map;

    /**
     * @param array<string, mixed> $entitySpec one entry of `sion_model.entities`, i.e. the
     *                                         merged config for a single entity
     */
    public function __construct(array $entitySpec)
    {
        $this->map = self::derive($entitySpec);
    }

    /** @param array<string, mixed> $config the whole merged config */
    public static function forEntity(array $config, string $entity): self
    {
        $entities = $config['sion_model']['entities'] ?? [];
        $spec     = is_array($entities) && is_array($entities[$entity] ?? null)
            ? $entities[$entity]
            : [];

        return new self($spec);
    }

    /** The group `$field` belongs to, or null when it belongs to none. */
    public function groupFor(string $field): ?string
    {
        return $this->map[$field] ?? null;
    }

    /**
     * The distinct groups the fields in `$fields` touch, with ungrouped fields dropped.
     *
     * This is what a write path actually asks: a PATCH changed four fields, so which groups
     * did it just say something about?
     *
     * @param list<string> $fields
     * @return list<string>
     */
    public function groupsFor(array $fields): array
    {
        $groups = [];
        foreach ($fields as $field) {
            $group = $this->groupFor($field);
            if (null !== $group) {
                $groups[] = $group;
            }
        }
        $groups = array_values(array_unique($groups));
        sort($groups);

        return $groups;
    }

    /** @return list<string> every group this entity has, sorted */
    public function all(): array
    {
        $groups = array_values(array_unique(array_values($this->map)));
        sort($groups);

        return $groups;
    }

    /** @return array<string, string> the whole mapping, for tests and diagnostics */
    public function toArray(): array
    {
        return $this->map;
    }

    /**
     * @param array<string, mixed> $entitySpec
     * @return array<string, string>
     */
    private static function derive(array $entitySpec): array
    {
        $updateColumns = is_array($entitySpec['update_columns'] ?? null)
            ? $entitySpec['update_columns']
            : [];
        $manyToOne     = is_array($entitySpec['many_to_one_update_columns'] ?? null)
            ? $entitySpec['many_to_one_update_columns']
            : [];

        $map = [];

        //Rule 2 first, so that an explicit many-to-one entry wins over a field that also
        //happens to own its own stamp columns. Nothing in this application is both today,
        //but the precedence should be decided here rather than by iteration order.
        //
        //A stamp column is skipped rather than grouped: `openingHoursHumanUpdatedOn` is
        //bookkeeping *about* `openingHoursHuman`, and letting it form a group would invite a
        //caller to claim provenance over a timestamp. Nothing in the current config reaches
        //that branch — it would need an `…UpdatedOnUpdatedOn` column — but the exclusion is
        //cheap and states the intent.
        foreach ($updateColumns as $field => $_column) {
            if (! is_string($field)) {
                continue;
            }
            if (str_ends_with($field, 'UpdatedOn') || str_ends_with($field, 'UpdatedBy')) {
                continue;
            }
            if (array_key_exists($field . 'UpdatedOn', $updateColumns)) {
                $map[$field] = $field;
            }
        }

        foreach ($manyToOne as $field => $group) {
            if (is_string($field) && is_string($group)) {
                $map[$field] = $group;
            }
        }

        return $map;
    }
}
