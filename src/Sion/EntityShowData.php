<?php

declare(strict_types=1);

namespace App\Sion;

use SionModel\Form\CommentForm;

/**
 * What App\Sion\EntityShow::load() hands a show controller: the five view variables
 * `SionController::showAction()` sets that every entity show template reads.
 *
 * A value object rather than an array because all four controllers pass it straight
 * into Twig, and `strict_variables` turns a key nobody set into an exception at render
 * time rather than a blank on the page. Named properties make that a static fact.
 *
 * `entityId` is deliberately absent: it is the caller's own route parameter, and the
 * one thing here that was never in doubt.
 */
final class EntityShowData
{
    /**
     * @param array<string, mixed> $entity the hydrated row
     * @param array<mixed> $changes what SionTable::getEntityChanges() returned
     * @param list<array<string, mixed>> $comments published comments, oldest first, or
     *        empty for an entity that takes comments but has none *and* for one that
     *        takes none at all — `commentForm` is what tells those apart
     * @param CommentForm|null $commentForm null when this entity has no comment
     *        predicate, which is how a template knows not to render the panel
     * @param array<string, mixed> $visits always carrying at least `total` and
     *        `pastMonth`, which is what SionTable::getVisitCounts() documents and what
     *        App\Sion\EntityShow defaults to when an entity has never been visited. Not
     *        pinned to a stricter shape here: the counts come back from an aggregate
     *        query as strings on some drivers and ints on others, and the templates
     *        print them either way.
     */
    public function __construct(
        public readonly array $entity,
        public readonly array $changes,
        public readonly array $comments,
        public readonly ?CommentForm $commentForm,
        public readonly array $visits
    ) {
    }
}
