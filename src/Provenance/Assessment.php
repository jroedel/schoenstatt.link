<?php

declare(strict_types=1);

namespace App\Provenance;

use function array_values;

/**
 * What a proposed write is allowed to do, group by group.
 *
 * The answer {@see Recorder::assess()} gives a write path *before* it writes. It is a plan,
 * not a record: nothing has been stored when this is built, which matters because a caller
 * has to be able to narrow its write to `writableFields` and then commit the outcomes that
 * actually resulted.
 *
 * The two field lists are the operative part. A protected group does not fail the request —
 * its fields are moved from `writableFields` to `withheldFields` and the rest of the write
 * proceeds. A PATCH correcting both the phone number and the Mass times, where only the
 * phone group is protected, applies the Mass times and keeps the phone claim on file.
 * Refusing the whole request would lose the half nobody disputed.
 */
final class Assessment
{
    /**
     * @param array<string, Outcome> $outcomes       group name => what happened to it
     * @param list<string>           $writableFields fields the caller may go ahead and write
     * @param list<string>           $withheldFields fields a better source is protecting
     */
    public function __construct(
        public readonly array $outcomes,
        public readonly array $writableFields,
        public readonly array $withheldFields,
    ) {
    }

    /** Whether anything at all may be written. */
    public function hasWritableFields(): bool
    {
        return [] !== $this->writableFields;
    }

    /** Whether a better-sourced claim held anything back. */
    public function withheldAnything(): bool
    {
        return [] !== $this->withheldFields;
    }

    /** @return list<string> the groups a better source is protecting */
    public function competingGroups(): array
    {
        $groups = [];
        foreach ($this->outcomes as $group => $outcome) {
            if (Outcome::Competing === $outcome) {
                $groups[] = $group;
            }
        }

        return array_values($groups);
    }
}
