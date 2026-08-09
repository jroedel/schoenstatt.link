<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\BotIdentity;
use App\Http\AuthorizationHeader;
use App\Laminas\ServiceBridge;
use App\Schoenstatt\Association\AssociationResource;
use App\Schoenstatt\Association\AssociationValidator;
use JsonException;
use Schoenstatt\Filter\SchoenstattLinkIdentifier as IdentifierFilter;
use Schoenstatt\Model\SchoenstattTable;
use Schoenstatt\Validator\SchoenstattLinkIdentifier as IdentifierValidator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function array_slice;
use function count;
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
 * `/api/v3/associations` — the read/write API automated agents use to keep the shrine
 * database current.
 *
 * ## Why a v3 rather than writes on v2
 *
 * v1 and v2 are read-only, and not by omission: both extend
 * `Laminas\Mvc\Controller\AbstractRestfulController` and implement only `getList()`
 * and `get()`, so every write verb inherits the base class's 405. Adding writes there
 * would have meant three further problems, each on its own enough:
 *
 * - **They have no authentication.** `authenticateApiKey()` exists on both and every
 *   call site is commented out; the guards admit `null`, i.e. everyone.
 * - **They have no identity**, so `SionTable` would write `UpdatedBy = NULL` and
 *   `sch_changes` would record edits by nobody. Attribution is the whole point of
 *   letting agents write.
 * - **Their representation does not round-trip.** See
 *   App\Schoenstatt\Association\AssociationResource.
 *
 * v1 and v2 keep working, unchanged, for the map consumers that use them.
 *
 * ## Validation is the web form's, exactly
 *
 * A PATCH is merged onto the stored row and then validated by
 * {@see AssociationValidator}, which is the *edit form's own input filter* — not a
 * copy of its rules. An agent is therefore refused precisely what a moderator is
 * refused, with precisely the same messages, and
 * `test/Integration/AssociationValidationParityTest` fails if that ever stops being
 * true. The single documented difference is the CSRF token, which an agent has no
 * session to carry.
 *
 * Merging before validating is what makes a partial update possible at all: `name`
 * and `kind` are required, so validating a bare `{"openingHoursHuman": "..."}` would
 * fail on two fields the caller never mentioned.
 *
 * ## Concurrency
 *
 * `GET` returns an `ETag`. A `PATCH` carrying `If-Match` is refused with 412 when the
 * tag no longer matches; a `PATCH` without one proceeds. Advisory rather than
 * mandatory because a required `If-Match` would refuse a perfectly correct blind
 * update, and because nothing existing sends one — but an agent that reads before
 * writing gets real lost-update protection for the cost of echoing a header.
 */
final class AssociationsV3Controller
{
    /** A page of the collection, and the ceiling a caller can ask for. */
    private const DEFAULT_LIMIT = 100;
    private const MAX_LIMIT     = 500;

    public function __construct(
        private readonly ServiceBridge $laminas,
        private readonly BotIdentity $identity
    ) {
    }

    // ------------------------------------------------------------------ read

    public function index(Request $request): Response
    {
        $actingUser = $this->requireBot($request);
        if ($actingUser instanceof Response) {
            return $actingUser;
        }

        $table  = $this->table();
        $fields = $this->validator()->writableFields();

        //`get()` already collapses an array-valued parameter (`?kind[]=x`) to null, so
        //there is nothing here to reject — the filter simply does not apply.
        $kind = $request->query->get('kind');

        $rows = $table->getAssociations();
        if (is_string($kind) && '' !== $kind) {
            $rows = array_filter($rows, static fn (array $row): bool => ($row['kind'] ?? null) === $kind);
        }

        $limit  = self::boundedInt($request->query->get('limit'), self::DEFAULT_LIMIT, 1, self::MAX_LIMIT);
        $offset = self::boundedInt($request->query->get('offset'), 0, 0, PHP_INT_MAX);
        $total  = count($rows);
        $page   = array_slice(array_values($rows), $offset, $limit);

        return new JsonResponse([
            'total'  => $total,
            'offset' => $offset,
            'limit'  => $limit,
            'items'  => array_map(
                static fn (array $row): array => AssociationResource::represent($row, $fields),
                $page
            ),
        ]);
    }

    public function show(Request $request): Response
    {
        $actingUser = $this->requireBot($request);
        if ($actingUser instanceof Response) {
            return $actingUser;
        }

        $entity = $this->entity($request);
        if (null === $entity) {
            return self::problem(Response::HTTP_NOT_FOUND, 'No association has that identifier.');
        }

        $document = AssociationResource::represent($entity, $this->validator()->writableFields());

        return self::tagged(new JsonResponse($document), $document['meta']['etag']);
    }

    // ----------------------------------------------------------------- write

    public function patch(Request $request): Response
    {
        $actingUser = $this->requireBot($request);
        if ($actingUser instanceof Response) {
            return $actingUser;
        }

        $entity = $this->entity($request);
        if (null === $entity) {
            return self::problem(Response::HTTP_NOT_FOUND, 'No association has that identifier.');
        }

        $patch = self::decodeBody($request);
        if (! is_array($patch)) {
            return self::problem(Response::HTTP_BAD_REQUEST, 'The request body must be a JSON object of fields.');
        }

        $validator = $this->validator();
        $fields    = $validator->writableFields();

        $unknown = array_diff(array_keys($patch), $fields);
        if ([] !== $unknown) {
            //Refused rather than ignored: an agent that misspells a field name and
            //gets a 200 will keep sending the misspelling forever, and the shrine it
            //believes it is maintaining silently never changes.
            return self::problem(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'The request names fields this API does not accept.',
                ['unknownFields' => array_values($unknown), 'writableFields' => $fields]
            );
        }

        $current = AssociationResource::represent($entity, $fields);
        $ifMatch = $request->headers->get('If-Match');
        if (
            is_string($ifMatch) && '' !== $ifMatch
            && self::normalizeEtag($ifMatch) !== self::normalizeEtag($current['meta']['etag'])
        ) {
            return self::problem(
                Response::HTTP_PRECONDITION_FAILED,
                'The association changed since you read it. Re-read it and re-apply your change.',
                ['etag' => $current['meta']['etag']]
            );
        }

        $filter = $validator->inputFilter();
        $filter->setData(AssociationResource::merge($entity, $patch, $fields));

        if (! $filter->isValid()) {
            //The messages are the form's, verbatim — see the class docblock.
            return self::problem(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'The association would not be valid after this change.',
                ['messages' => $filter->getMessages()]
            );
        }

        $changed = AssociationResource::changedFields($entity, $patch);
        if ([] === $changed) {
            //Nothing to write. Answered 200 with the current document rather than 204:
            //an agent polling for drift wants to see what it would have written.
            return self::tagged(
                new JsonResponse(['changed' => [], 'association' => $current]),
                $current['meta']['etag']
            );
        }

        $table = $this->table();
        //Attribution. Without it every agent edit lands in sch_changes as UpdatedBy
        //NULL and the log stops being able to answer "who did this".
        $table->setActingUserId($actingUser);

        /** @var array<string, mixed> $values */
        $values  = $filter->getValues();
        $updated = $table->updateEntity('association', (int) $entity['associationId'], $values);

        $fresh    = is_array($updated) && [] !== $updated ? $updated : $entity;
        $document = AssociationResource::represent($fresh, $fields);

        return self::tagged(
            new JsonResponse(['changed' => $changed, 'association' => $document]),
            $document['meta']['etag']
        );
    }

    // --------------------------------------------------------------- plumbing

    /** The acting user id, or the response that refuses the request. */
    private function requireBot(Request $request): int|Response
    {
        $userId = $this->identity->resolve(AuthorizationHeader::from($request));

        if (null === $userId) {
            $refusal = self::problem(
                Response::HTTP_UNAUTHORIZED,
                sprintf(
                    'This endpoint needs a bearer token belonging to an account that holds the `%s` role.',
                    BotIdentity::REQUIRED_ROLE
                )
            );
            $refusal->headers->set('WWW-Authenticate', 'Bearer realm="schoenstatt.link api v3"');

            return $refusal;
        }

        return $userId;
    }

    /** @return array<string, mixed>|null */
    private function entity(Request $request): ?array
    {
        $swId = $request->attributes->get('sw_id');
        if (! is_string($swId)) {
            return null;
        }

        $validator = new IdentifierValidator(IdentifierValidator::ENTITY_ASSOCIATION);
        if (! $validator->isValid($swId)) {
            return null;
        }

        $id     = (new IdentifierFilter(IdentifierValidator::ENTITY_ASSOCIATION))->filter($swId);
        $entity = is_numeric($id) ? $this->table()->getAssociation((int) $id) : null;

        return is_array($entity) && [] !== $entity ? $entity : null;
    }

    private function table(): SchoenstattTable
    {
        /** @var SchoenstattTable $table */
        $table = $this->laminas->get(SchoenstattTable::class);

        return $table;
    }

    private function validator(): AssociationValidator
    {
        return AssociationValidator::fromServices($this->laminas->get(...));
    }

    /** @return array<string, mixed>|null null when the body is absent or not a JSON object */
    private static function decodeBody(Request $request): ?array
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
     * A machine-readable refusal. One shape for every error this API produces, so an
     * agent needs one branch rather than one per status code.
     *
     * @param array<string, mixed> $extra
     */
    private static function problem(int $status, string $message, array $extra = []): JsonResponse
    {
        return new JsonResponse(['error' => ['status' => $status, 'message' => $message] + $extra], $status);
    }

    /**
     * Set the ETag verbatim.
     *
     * `Response::setEtag()` wraps its argument in `W/"…"` itself, and
     * AssociationResource::etag() already returns that form — the two together
     * produced `W/"W/"…""`, which no caller could echo back in an If-Match that this
     * controller would then recognise. One canonical spelling, emitted and compared.
     */
    private static function tagged(JsonResponse $response, string $etag): JsonResponse
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
    private static function normalizeEtag(string $etag): string
    {
        $etag = trim($etag);
        if (str_starts_with($etag, 'W/')) {
            $etag = substr($etag, 2);
        }
        $etag = trim($etag, '"');

        return str_ends_with($etag, '-gzip') ? substr($etag, 0, -5) : $etag;
    }

    private static function boundedInt(mixed $raw, int $default, int $minimum, int $maximum): int
    {
        if (! is_numeric($raw)) {
            return $default;
        }

        return max($minimum, min($maximum, (int) $raw));
    }
}
