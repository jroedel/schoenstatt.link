<?php

declare(strict_types=1);

namespace App\Schoenstatt\Association;

use DateTimeInterface;

use function array_key_exists;
use function array_map;
use function is_bool;
use function is_object;
use function is_scalar;
use function json_encode;
use function ksort;
use function md5;
use function method_exists;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * One association as v3 shows it, and as v3 accepts it back.
 *
 * ## Why v1 and v2 could not be extended
 *
 * Both existing APIs answer a schema.org projection — `getAssociationSchemaV2()`
 * builds a `CatholicChurch`, with `address`, `geo`, `openingHoursSpecification` and
 * so on. That is the right shape for a consumer drawing a map and the wrong shape for
 * an agent making an edit: nothing in it maps back onto the columns
 * `SionTable::updateEntity()` writes, so an agent could not GET a shrine, change a
 * field and PUT it. There is no way to add writes to that representation without
 * inventing a second, contradictory vocabulary inside it.
 *
 * So v3 speaks the *editable* vocabulary instead: exactly the field names the edit
 * form uses, which are exactly the keys of the entity's `update_columns` map. An
 * agent that can read this document can write it back, and the field it changes is
 * the field a moderator would have changed.
 *
 * ## The field list is not written down here
 *
 * It comes from {@see AssociationInputFilterSpec::fieldNames()}, so the set of
 * readable fields, the set of writable fields and the set of validated fields are one
 * set. A field added to the specification appears in the API and in `/api/v3/schema`
 * with no further edit; a field removed from it disappears from all three. The
 * alternative — a list here and a list there — is the arrangement that produces an
 * API documenting a field it silently ignores.
 *
 * ## ETags
 *
 * The tag is a hash of the representation itself, not of a timestamp: `UpdatedOn` has
 * second resolution and two agents can easily write inside one second, which is the
 * exact case an ETag is supposed to catch. Hashing the document means the tag changes
 * when and only when something an agent can see changed.
 */
final class AssociationResource
{
    /**
     * @param array<string, mixed> $entity a row as SchoenstattTable hydrates it
     * @param list<string> $fields the writable field set, from AssociationInputFilterSpec
     * @return array<string, mixed>
     */
    public static function represent(array $entity, array $fields): array
    {
        $document = [];
        foreach ($fields as $field) {
            //`associationId` is in the specification because the form carries a hidden
            //input for it, but it is an internal key: the identifier is what a caller
            //addresses a shrine by, and offering both invites an agent to send a
            //mismatched pair.
            if ('associationId' === $field) {
                continue;
            }
            $document[$field] = self::scalarize($entity[$field] ?? null);
        }

        ksort($document);

        return [
            'identifier' => $entity['identifier'] ?? null,
            'kind'       => $entity['kind'] ?? null,
            'fields'     => $document,
            'meta'       => [
                'updatedOn' => self::scalarize($entity['updatedOn'] ?? null),
                'updatedBy' => $entity['updatedBy'] ?? null,
                'etag'      => self::etag($document),
                'url'       => isset($entity['identifier'])
                    ? sprintf('https://schoenstatt.link/api/v3/associations/%s', (string) $entity['identifier'])
                    : null,
            ],
        ];
    }

    /**
     * The ETag for a representation's field document.
     *
     * Weak, and honestly so: it is computed from the fields v3 exposes, so two
     * representations with the same tag are the same *as far as this API is
     * concerned* — a change to a column v3 does not expose does not move it. That is
     * the right granularity for the lost-update problem it is here to solve.
     *
     * @param array<string, mixed> $document
     */
    public static function etag(array $document): string
    {
        return 'W/"' . md5(json_encode($document, JSON_THROW_ON_ERROR)) . '"';
    }

    /**
     * A stored value as JSON can carry it.
     *
     * Three shapes reach this from a hydrated row and none of them are JSON-native:
     * `DateTime` (foundationDate, updatedOn), `SionModel\Db\GeoPoint` (geoPoint) and
     * the `Laminas\Db\Sql\Expression` a GeoPoint becomes on the way *in*. Dates get
     * ISO-8601 because that is what an agent can send back and `ToDateTime` can
     * parse; everything else with a `__toString()` gets it, which is what turns a
     * GeoPoint into the `lat, long` string the form's `ToGeoPoint` filter accepts.
     * Round-tripping is the requirement, not prettiness.
     */
    private static function scalarize(mixed $value): mixed
    {
        if (null === $value || is_scalar($value)) {
            return $value;
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d\TH:i:sP');
        }
        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return null;
    }

    /**
     * The stored values of the fields a PATCH did not mention, so the whole record can
     * be validated as a unit.
     *
     * Form validation is whole-record: `name` and `kind` are required, so a PATCH
     * carrying only `openingHoursHuman` would fail on two fields the caller never
     * touched. Merging the patch onto the current row first is what makes a partial
     * update possible without a second, weaker set of rules — the resulting record is
     * validated exactly as a moderator's full form submission is.
     *
     * `associationId` is supplied from the row rather than from the caller: it is
     * required by the specification and it is not the caller's to choose.
     *
     * @param array<string, mixed> $entity
     * @param array<string, mixed> $patch
     * @param list<string> $fields
     * @return array<string, mixed>
     */
    public static function merge(array $entity, array $patch, array $fields): array
    {
        $merged = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $patch)) {
                $merged[$field] = $patch[$field];
                continue;
            }
            $merged[$field] = self::scalarize($entity[$field] ?? null);
        }

        $merged['associationId'] = $entity['associationId'] ?? null;

        return array_map(self::formValue(...), $merged);
    }

    /**
     * A value as the web form would have posted it.
     *
     * Booleans are the case, and they are not cosmetic. A `Checkbox` with
     * `use_hidden_element` posts the string `'0'` or `'1'`, never a PHP boolean —
     * and because the element contributes no `allowEmpty`, `Input::isValid()` injects
     * a `NotEmpty`, which rejects boolean `false`. So a shrine whose `isLifeCommunity`
     * is stored false, merged into a PATCH as `false`, failed validation with "Value is
     * required and can't be empty" on a field the agent never mentioned. Measured
     * against SL100319A; every association with a false flag would have been
     * unpatchable.
     *
     * Normalising here rather than in the specification is deliberate: the rules must
     * stay exactly the form's, so the fix belongs on the side that is *impersonating a
     * form submission*, which is this one.
     */
    private static function formValue(mixed $value): mixed
    {
        return is_bool($value) ? ($value ? '1' : '0') : $value;
    }

    /**
     * The fields a patch actually asks to change, ignoring keys whose value already
     * equals what is stored.
     *
     * Reported back to the caller so an agent can tell "I changed three fields" from
     * "I sent three fields and two were already correct" — which is the difference
     * between a useful edit log and 200 rows a day of noise in `sch_changes`.
     *
     * @param array<string, mixed> $entity
     * @param array<string, mixed> $patch
     * @return list<string>
     */
    public static function changedFields(array $entity, array $patch): array
    {
        $changed = [];
        foreach ($patch as $field => $value) {
            $current = self::scalarize($entity[$field] ?? null);
            //Loose, because a stored '0' and a submitted 0 are the same bit and a
            //stored null and a submitted '' are the same absence.
            if ($current != $value) {
                $changed[] = (string) $field;
            }
        }

        return $changed;
    }
}
