<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\BotIdentity;
use App\Laminas\ServiceBridge;
use App\Provenance\ApiEnvelope;
use App\Provenance\FieldGroups;
use App\Provenance\ProvenanceStore;
use App\Provenance\Recorder;
use App\Provenance\WriteGate;
use App\Schoenstatt\Association\AssociationResource;
use App\Schoenstatt\Association\AssociationValidator;
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
final class AssociationsV3Controller extends AbstractApiController
{
    /** A page of the collection, and the ceiling a caller can ask for. */
    private const DEFAULT_LIMIT = 100;
    private const MAX_LIMIT     = 500;

    public function __construct(
        private readonly ServiceBridge $laminas,
        BotIdentity $identity
    ) {
        parent::__construct($identity);
    }

    protected function requiredRole(): string
    {
        return BotIdentity::REQUIRED_ROLE;
    }

    // ------------------------------------------------------------------ read

    public function index(Request $request): Response
    {
        $actingUser = $this->requireAgent($request);
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
        $actingUser = $this->requireAgent($request);
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
        $actingUser = $this->requireAgent($request);
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

        //The provenance envelope comes out before the field check, exactly as
        //PhrasesV3Controller lifts `_note` out: all four keys were a 422 until now, so no
        //caller can be sending them meaning something else.
        $now      = ProvenanceStore::now();
        $envelope = ApiEnvelope::parse($patch, $now);
        if (! $envelope instanceof ApiEnvelope) {
            return self::problem(Response::HTTP_UNPROCESSABLE_ENTITY, $envelope);
        }
        foreach (ApiEnvelope::keys() as $reserved) {
            unset($patch[$reserved]);
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
        if (! self::ifMatchSatisfied($request, $current['meta']['etag'])) {
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

        $associationId = (int) $entity['associationId'];
        $groups        = $this->fieldGroups();
        $recorder      = $this->recorder();
        $changed       = AssociationResource::changedFields($entity, $patch);

        if ([] === $changed) {
            //Nothing to write — but something to *record*. This is a confirmation: the
            //caller checked these fields and every one already held the right value, which
            //until now left no trace anywhere and was the central gap in the whole API. The
            //response still carries `changed: []`, so an existing agent polling for drift
            //sees exactly what it saw before.
            $assessment = $recorder->assessConfirmation(array_keys($patch), $groups);
            $recorder->commit(
                $assessment,
                'association',
                $associationId,
                $envelope->source,
                $envelope->assertedOn,
                $now,
                $envelope->sourceUrl,
                $envelope->sourceNote,
                $actingUser,
            );

            return self::tagged(
                new JsonResponse([
                    'changed'   => [],
                    'confirmed' => array_keys($assessment->outcomes),
                    'association' => $current,
                ]),
                $current['meta']['etag']
            );
        }

        //What may this source actually change? A group a better-sourced, fresher claim is
        //protecting is withheld rather than refused: the finding is kept on file and the
        //rest of the write proceeds. See App\Provenance\WriteGate.
        $assessment = $recorder->assess(
            'association',
            $associationId,
            $changed,
            $groups,
            $envelope->source,
            $now
        );

        /** @var array<string, mixed> $values */
        $values = $filter->getValues();

        if ($assessment->withheldAnything()) {
            //Narrow the write to the fields that survived. The withheld ones keep their
            //stored value, so the merged-and-validated record stays valid by construction:
            //what we drop is a change, never a requirement.
            foreach ($assessment->withheldFields as $withheld) {
                unset($values[$withheld]);
            }
        }

        $applied = $assessment->writableFields;

        if ([] !== $applied) {
            $table = $this->table();
            //Attribution. Without it every agent edit lands in sch_changes as UpdatedBy
            //NULL and the log stops being able to answer "who did this".
            $table->setActingUserId($actingUser);
            $updated = $table->updateEntity('association', $associationId, $values);
            $entity  = is_array($updated) && [] !== $updated ? $updated : $entity;
        }

        $recorder->commit(
            $assessment,
            'association',
            $associationId,
            $envelope->source,
            $envelope->assertedOn,
            $now,
            $envelope->sourceUrl,
            $envelope->sourceNote,
            $actingUser,
        );

        $document = AssociationResource::represent($entity, $fields);

        $body = ['changed' => $applied, 'association' => $document];
        if ($assessment->withheldAnything()) {
            //Named, not silent. An agent that is not told its change was withheld will send
            //it again forever and believe the shrine it maintains is drifting.
            $body['withheld'] = [
                'fields' => $assessment->withheldFields,
                'groups' => $assessment->competingGroups(),
                'reason' => 'A better-sourced and more recent claim covers these. Your finding '
                    . 'was recorded as a competing assertion; the stored value did not change.',
            ];
        }

        return self::tagged(new JsonResponse($body), $document['meta']['etag']);
    }

    /** The provenance write path, assembled here because nothing else in this class needs it. */
    private function recorder(): Recorder
    {
        return new Recorder(new ProvenanceStore($this->laminas), new WriteGate());
    }

    /** Field groups derived from the association's own entity configuration. */
    private function fieldGroups(): FieldGroups
    {
        return FieldGroups::forEntity($this->laminas->config(), 'association');
    }

    // --------------------------------------------------------------- plumbing

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
}
