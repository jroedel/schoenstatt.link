<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\BotIdentity;
use App\Http\AuthorizationHeader;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function is_array;
use function is_numeric;
use function is_string;
use function json_decode;
use function max;
use function min;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function substr;
use function trim;

use const JSON_THROW_ON_ERROR;

/**
 * What every `/api/v3` endpoint does identically: decide who is calling, answer a
 * refusal, read a JSON body, bound a paging parameter, and speak ETags.
 *
 * All of this was `private static` on
 * {@see AssociationsV3Controller} while associations were the only resource, which was
 * right at the time. It is lifted here rather than copied because the two behaviours
 * most worth not duplicating are the two that are only obviously correct once:
 * normalizeEtag()'s `-gzip` handling, and the single uninformative 401. A second
 * controller with its own copy of the first would refuse every conditional write from
 * a gzip-capable client, and a second controller with its own copy of the second would
 * eventually distinguish "no such token" from "wrong role" in its wording.
 *
 * ## The role is a property of the subclass, not of the API
 *
 * requiredRole() is abstract on purpose. `BotIdentity` used to hold one constant, so
 * every endpoint asked the same question and the reach of a leaked token widened
 * silently each time v3 grew — see database/db6.8.sql. Making it abstract means a new
 * API controller cannot be written without answering "which role is this?", which is
 * the question that would otherwise be answered by inertia.
 */
abstract class AbstractApiController
{
    public function __construct(protected readonly BotIdentity $identity)
    {
    }

    /**
     * The role an account must hold to reach this controller's resource.
     *
     * @return non-empty-string
     */
    abstract protected function requiredRole(): string;

    /**
     * The acting user id, or the response that refuses the request.
     *
     * Missing, malformed, expired, revoked, unregistered, unknown user and wrong role
     * are one answer with one message. Distinguishing them tells an attacker which
     * half of a guess was right, and no legitimate agent needs to be told: its token
     * either works from the first request or was issued wrongly.
     */
    protected function requireAgent(Request $request): int|Response
    {
        $userId = $this->identity->resolve(AuthorizationHeader::from($request), $this->requiredRole());

        if (null === $userId) {
            $refusal = self::problem(
                Response::HTTP_UNAUTHORIZED,
                sprintf(
                    'This endpoint needs a bearer token belonging to an account that holds the `%s` role.',
                    $this->requiredRole()
                )
            );
            $refusal->headers->set('WWW-Authenticate', 'Bearer realm="schoenstatt.link api v3"');

            return $refusal;
        }

        return $userId;
    }

    /**
     * A machine-readable refusal. One shape for every error this API produces, so an
     * agent needs one branch rather than one per status code.
     *
     * @param array<string, mixed> $extra
     */
    protected static function problem(int $status, string $message, array $extra = []): JsonResponse
    {
        return new JsonResponse(['error' => ['status' => $status, 'message' => $message] + $extra], $status);
    }

    /**
     * @return array<string, mixed>|null null when the body is absent or not a JSON object
     */
    protected static function decodeBody(Request $request): ?array
    {
        $body = $request->getContent();
        if ('' === $body) {
            return null;
        }

        try {
            $decoded = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Set the ETag verbatim.
     *
     * `Response::setEtag()` wraps its argument in `W/"…"` itself, and the resource
     * classes already return that form — the two together produced `W/"W/"…""`, which
     * no caller could echo back in an If-Match that this API would then recognise. One
     * canonical spelling, emitted and compared.
     */
    protected static function tagged(JsonResponse $response, string $etag): JsonResponse
    {
        $response->headers->set('ETag', $etag);

        return $response;
    }

    /**
     * An entity tag reduced to the part both ends actually agree on.
     *
     * **`mod_deflate` rewrites the ETag.** When Apache compresses a response it
     * appends `-gzip` to whatever ETag the application set, so a client that sends
     * `Accept-Encoding: gzip` — which is every HTTP client an agent will be built on —
     * receives `W/"abc-gzip"` and echoes exactly that back in `If-Match`. Comparing
     * the raw strings meant *every* conditional write failed with 412, which would
     * have made the concurrency feature not merely useless but actively misleading:
     * an agent doing the correct read-modify-write dance would be refused, and an
     * agent doing the blind write would succeed.
     *
     * Caught by test/Smoke/ApiV3SmokeTest, whose curl calls request gzip; the manual
     * curl used while building this did not, and so did not see it.
     *
     * The weak prefix is dropped for the same reason — an intermediary may add or
     * remove it — leaving the hash, which is the only part this API generated.
     */
    protected static function normalizeEtag(string $etag): string
    {
        $etag = trim($etag);
        if (str_starts_with($etag, 'W/')) {
            $etag = substr($etag, 2);
        }
        $etag = trim($etag, '"');

        return str_ends_with($etag, '-gzip') ? substr($etag, 0, -5) : $etag;
    }

    /** Whether an `If-Match` header, if sent at all, still matches what we hold. */
    protected static function ifMatchSatisfied(Request $request, string $currentEtag): bool
    {
        $ifMatch = $request->headers->get('If-Match');
        if (! is_string($ifMatch) || '' === $ifMatch) {
            return true;
        }

        return self::normalizeEtag($ifMatch) === self::normalizeEtag($currentEtag);
    }

    protected static function boundedInt(mixed $raw, int $default, int $minimum, int $maximum): int
    {
        if (! is_numeric($raw)) {
            return $default;
        }

        return max($minimum, min($maximum, (int) $raw));
    }
}
