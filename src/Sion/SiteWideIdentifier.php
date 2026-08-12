<?php

declare(strict_types=1);

namespace App\Sion;

use Schoenstatt\Filter\SchoenstattLinkIdentifier as IdentifierFilter;
use Schoenstatt\Validator\SchoenstattLinkIdentifier as IdentifierValidator;

use function is_int;
use function is_numeric;
use function trim;

/**
 * The `SL200417L` ↔ `417` translation every entity show route needs.
 *
 * Each of the four laminas controllers ported in this batch overrides
 * `SionController::getEntityIdParam()` with the same eleven lines — validate the
 * site-wide identifier for its own entity type, filter it down to the numeric id,
 * **throw** if it is not one. `AssociationEditController` had already written a
 * private copy when it was the only ported route that needed one; this is that copy
 * promoted, before it became five.
 *
 * The one deliberate difference from the laminas original is the throw. A bad
 * identifier there becomes an uncaught `\Exception` and a 500; here it becomes null
 * and the caller answers 404, which is what a Symfony route should do with a path it
 * cannot parse. In practice neither fires: the route constraint below is the same
 * regex, so a request that reaches a controller has already matched it. This is the
 * second of two checks, not the only one.
 */
final class SiteWideIdentifier
{
    /**
     * The regex a route declares to claim identifiers of one entity type, with
     * `SchoenstattLinkIdentifier`'s anchors stripped — Symfony anchors its own.
     *
     * Shared with config/symfony/routes.php so that `/SL200001L/edit` (a publication)
     * keeps falling through to `legacy` rather than being swallowed by the association
     * route and 404'd, which is exactly the mistake the constraint exists to prevent.
     */
    public static function pattern(string $entity): string
    {
        return trim(IdentifierValidator::ENTITY_REGEXS[$entity], '/^$');
    }

    /**
     * The numeric id behind a site-wide identifier of the given entity type, or null
     * when the identifier is not one of that type.
     */
    public static function toId(string $entity, string $swId): ?int
    {
        $validator = new IdentifierValidator($entity);
        if (! $validator->isValid($swId)) {
            return null;
        }

        $filtered = (new IdentifierFilter($entity))->filter($swId);

        if (is_int($filtered)) {
            return $filtered;
        }

        return is_numeric($filtered) ? (int) $filtered : null;
    }
}
