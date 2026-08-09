<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Laminas\ServiceBridge;
use App\Schoenstatt\Association\AssociationFieldDomains;
use App\Schoenstatt\Association\AssociationInputFilterSpec;
use Laminas\Validator\InArray;
use Laminas\Validator\StringLength;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

use function array_values;
use function is_array;
use function is_int;
use function is_numeric;
use function is_string;
use function strrpos;
use function substr;

/**
 * `GET /api/v3/schema` — what an agent may write to an association, and what will be
 * accepted.
 *
 * This is the endpoint that makes v3 "more straightforward" in the way the brief
 * asked for. Without it an agent author reads PHP source, or guesses, or discovers
 * the rules one 422 at a time; the last of those is what actually happens, and it
 * means the rules are learned by making bad writes against production.
 *
 * ## It is generated, never written
 *
 * Every field, bound and enumeration below is read out of
 * {@see AssociationInputFilterSpec} — the same object the edit form and the PATCH
 * endpoint validate with. A hand-maintained schema document is a second source of
 * truth that starts correct and drifts, and the failure is silent in the worst
 * direction: an agent trusts a documented constraint that no longer holds, or stops
 * sending a field that is now required.
 *
 * The enumerations are the live ones — `kind` lists the association kinds this
 * database actually has, `country` the countries `CountriesInfo` knows, `parentId`
 * every association id including inactive ones. That last is why the response can be
 * large, and why it is worth having: an agent can validate a parent locally instead of
 * discovering by rejection.
 *
 * ## Open on purpose
 *
 * No bearer token. It describes the shape of the data and reveals no data — the kinds
 * and countries are already public through `/api/v1/associations/findByKind` and the
 * shrine index — and requiring a credential to *learn how to use a credential* is the
 * kind of friction that produces agents built against guesses.
 */
final class ApiSchemaController
{
    public function __construct(private readonly ServiceBridge $laminas)
    {
    }

    public function __invoke(): Response
    {
        $domains = AssociationFieldDomains::fromServices($this->laminas->get(...));
        $spec    = new AssociationInputFilterSpec($domains);

        $fields = [];
        foreach ($spec->toArray() as $name => $rules) {
            if ('associationId' === $name || 'security' === $name) {
                //Not a caller's to set: the identifier addresses the record, and the
                //CSRF token belongs to the browser form. See AssociationResource.
                continue;
            }
            $fields[(string) $name] = self::describe(is_array($rules) ? $rules : []);
        }

        return new JsonResponse([
            'version'     => 3,
            'entity'      => 'association',
            'description' => 'Fields an agent may read and write on an association. '
                . 'PATCH /api/v3/associations/{identifier} with a JSON object of any subset of these; '
                . 'the patch is merged onto the stored record and the result validated as a whole, '
                . 'by the same rules the moderator web form uses.',
            'concurrency' => [
                'etag'    => 'GET returns a weak ETag over the field document.',
                'ifMatch' => 'PATCH honours If-Match and answers 412 when the record has moved. '
                    . 'Omitting it is permitted and means a blind write.',
            ],
            'fields'      => $fields,
        ]);
    }

    /**
     * One field's contract, from its input-filter entry.
     *
     * @param array<string, mixed> $rules
     * @return array<string, mixed>
     */
    private static function describe(array $rules): array
    {
        $described = ['required' => (bool) ($rules['required'] ?? false)];

        $validators = is_array($rules['validators'] ?? null) ? $rules['validators'] : [];
        foreach ($validators as $validator) {
            if (! is_array($validator) || ! is_string($validator['name'] ?? null)) {
                continue;
            }

            $options = is_array($validator['options'] ?? null) ? $validator['options'] : [];

            if (StringLength::class === $validator['name'] && isset($options['max'])) {
                $described['maxLength'] = is_numeric($options['max']) ? (int) $options['max'] : null;
                continue;
            }
            if (InArray::class === $validator['name'] && is_array($options['haystack'] ?? null)) {
                $described['enum'] = array_values(array_filter(
                    $options['haystack'],
                    static fn (mixed $value): bool => is_string($value) || is_int($value)
                ));
                continue;
            }

            //Everything else is named rather than modelled. A short name is enough for
            //an agent to look up, and pretending to express `OpeningHoursSpecificationJson`
            //or `GpsPoint` as a JSON-schema fragment would be a description that is
            //wrong in the details rather than absent.
            $described['constraints'][] = self::shortName($validator['name']);
        }

        $filters = is_array($rules['filters'] ?? null) ? $rules['filters'] : [];
        foreach ($filters as $filter) {
            if (is_array($filter) && is_string($filter['name'] ?? null)) {
                $described['filters'][] = self::shortName($filter['name']);
            }
        }

        return $described;
    }

    /** The class name without its namespace: `StringTrim`, `GpsPoint`, `Twitter`. */
    private static function shortName(string $class): string
    {
        $position = strrpos($class, '\\');

        return false === $position ? $class : substr($class, $position + 1);
    }
}
