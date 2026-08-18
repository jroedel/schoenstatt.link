<?php

declare(strict_types=1);

namespace App\Books\Import;

/**
 * What applying a plan actually did.
 *
 * Separate from ImportPlan because a plan is a prediction and this is a record. The
 * counts can differ: a collection named by two rows is created once, and a row whose
 * book vanished between the simulation and the run is counted here and not there.
 */
final class ImportResult
{
    /** @param array<string, int> $newCollections collection name => new collection id */
    public function __construct(
        public readonly int $created = 0,
        public readonly int $updated = 0,
        public readonly int $inactivated = 0,
        public readonly array $newCollections = [],
        public readonly int $skipped = 0
    ) {
    }
}
