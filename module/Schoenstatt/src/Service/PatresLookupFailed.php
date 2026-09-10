<?php

declare(strict_types=1);

namespace Schoenstatt\Service;

use RuntimeException;
use Throwable;

/**
 * Patres could not answer for a person: it returned a non-200, or a 200 with no record.
 *
 * ## Why this is its own class
 *
 * Because two very different things used to arrive as a bare `\Exception` from
 * {@see PatresGateway::getRemotePerson()}, and a caller could only tell them apart by
 * reading the message text:
 *
 *   - **the remote lookup failed** — patres is down, or has a record it cannot serve;
 *   - **the record came back and is not valid for this application**, which
 *     `getRemotePerson()` signals by returning `false` and filling
 *     `getLastRemotePersonMessages()`.
 *
 * The second was handled everywhere. The first was handled nowhere, so it left the
 * gateway, crossed the controller that had carefully written a message for the *other*
 * case, and became an error page. On 2026-09-10 that was a librarian at a lending desk
 * meeting a 500 twice in twenty-one seconds because patres answered HTTP 500 for one
 * father — its person *list* was fine, so the picker offered him and only the individual
 * lookup failed.
 *
 * A typed exception is what lets a caller catch exactly this and nothing else. Catching
 * `\Exception` around the same call would swallow every bug underneath it too, which is
 * how a lookup failure and a TypeError end up wearing the same apology.
 *
 * It extends `RuntimeException`, so anything that already catches `\Exception` — such as
 * `Schoenstatt\Service\FathersValueOptionsService`, which empties the person list when
 * patres is unreachable — keeps working unchanged.
 */
final class PatresLookupFailed extends RuntimeException
{
    private function __construct(
        string $message,
        public readonly string|int $personId,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function status(string|int $personId, int $status): self
    {
        return new self(
            'Request for information on father \'' . $personId . '\' failed. Status code: ' . $status,
            $personId
        );
    }

    /**
     * Patres could not be reached at all — connection refused, DNS, a timeout.
     *
     * Distinct from {@see status()} because it is the likelier failure and the one a
     * librarian can be told to wait out; the cause is kept as `previous` so the report
     * still names the socket error.
     */
    public static function unreachable(string|int $personId, Throwable $previous): self
    {
        return new self(
            'Patres could not be reached for father \'' . $personId . '\': ' . $previous->getMessage(),
            $personId,
            $previous
        );
    }

    public static function noRecord(string|int $personId): self
    {
        return new self(
            'Request for information on father \'' . $personId . '\' failed. No information returned.',
            $personId
        );
    }
}
