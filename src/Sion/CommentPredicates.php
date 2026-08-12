<?php

declare(strict_types=1);

namespace App\Sion;

use App\Laminas\ServiceBridge;
use SionModel\Db\Model\PredicatesTable;

use function is_array;
use function is_string;

/**
 * Which entities can be commented on — the question `SionController::showAction()`
 * asks before it renders a comment list and a form, and the one
 * App\Controller\CommentCreateController asks before it writes.
 *
 * The answer is **data, not configuration**: it is the `predicates` table, filtered to
 * rows whose subject is `comment`. On this database that is five rows —
 *
 *     comment-comments-composition → composition
 *     comment-comments-event       → event
 *     comment-comments-file        → file
 *     comment-comments-text        → text
 *     comment-reviews-publication  → publication
 *
 * — and the fifth is why this class exists rather than a constant. docs/strangler.md
 * recorded composition and text as the entities blocked on the comment form, because
 * those are the two whose `.phtml` most obviously renders one; `publication` was
 * missed, and a hardcoded list written from that document would have shipped a
 * publication page that silently stopped accepting reviews. A moderator adding a
 * predicate row would have the same effect on any list written here.
 *
 * Memoized per request because both callers ask, and on a show page the answer is
 * needed before the entity is even loaded. `getCommentPredicates()` is a single
 * indexed read of a five-row table, so the memoization is about not building the
 * table service twice rather than about the query.
 */
final class CommentPredicates
{
    /** @var array<string, string>|null entity kind => predicate kind */
    private ?array $map = null;

    public function __construct(private readonly ServiceBridge $laminas)
    {
    }

    /**
     * The predicate kind naming comments on `$entity`, or null when that entity takes
     * no comments — which is the same test `showAction()` makes with
     * `isset($commentableEntities[$entity])`.
     */
    public function forEntity(string $entity): ?string
    {
        $map = $this->map();

        return isset($map[$entity]) ? $map[$entity] : null;
    }

    /** @return array<string, string> */
    private function map(): array
    {
        if (null !== $this->map) {
            return $this->map;
        }

        /** @var PredicatesTable $table */
        $table = $this->laminas->get(PredicatesTable::class);
        $map   = $table->getCommentPredicates();

        $clean = [];
        if (is_array($map)) {
            foreach ($map as $entity => $predicate) {
                if (is_string($entity) && is_string($predicate)) {
                    $clean[$entity] = $predicate;
                }
            }
        }

        return $this->map = $clean;
    }
}
