<?php

declare(strict_types=1);

namespace App\Privacy;

/**
 * How `sch_publications.Authors` names a person: the value `p<PersonId>` in its
 * pipe-separated list (`PublicationsTable::getAuthorPersonValueOptions()` offers it, and
 * `filterDbArray()` splits on `|`). Anything else in the column is free text.
 */
final class AuthorLinks
{
    /** A MariaDB REGEXP matching exactly the value for `$personId`, not `p7080` for `p708`. */
    public static function pattern(int $personId): string
    {
        return '(^|\\|)p' . $personId . '(\\||$)';
    }
}
