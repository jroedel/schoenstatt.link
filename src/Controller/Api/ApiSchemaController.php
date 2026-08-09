<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Api\BotIdentity;
use App\Laminas\ServiceBridge;
use JTranslate\Form\PhraseValidator;
use App\Schoenstatt\Association\AssociationFieldDomains;
use App\Schoenstatt\Association\AssociationInputFilterSpec;
use Laminas\Validator\InArray;
use Laminas\Validator\StringLength;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function array_values;
use function is_array;
use function is_int;
use function is_numeric;
use function is_string;
use function strrpos;
use function substr;

/**
 * `GET /api/v3/schema` and `/api/v3/schema/{entity}` — what an agent may write, and
 * what will be accepted.
 *
 * These are the endpoints that make v3 "more straightforward" in the way the brief
 * asked for. Without them an agent author reads PHP source, or guesses, or discovers
 * the rules one 422 at a time; the last of those is what actually happens, and it
 * means the rules are learned by making bad writes against production.
 *
 * ## It is generated, never written
 *
 * Every field, bound and enumeration below is read out of the same object the
 * corresponding web form and PATCH endpoint validate with —
 * {@see AssociationInputFilterSpec} for associations,
 * {@see PhraseValidator} for phrases. A hand-maintained schema document is a second
 * source of truth that starts correct and drifts, and the failure is silent in the
 * worst direction: an agent trusts a documented constraint that no longer holds, or
 * stops sending a field that is now required.
 *
 * The enumerations are the live ones — `kind` lists the association kinds this
 * database actually has, `country` the countries `CountriesInfo` knows, `parentId`
 * every association id including inactive ones, and the phrase schema's locales are
 * the ones `updatePhrase()` will actually iterate. That is why the association
 * response can be large, and why it is worth having: an agent can validate a parent
 * locally instead of discovering by rejection.
 *
 * ## Two levels, because there are two entities
 *
 * The index at `/api/v3/schema` names them and says which role each needs; the
 * contract lives one level down. It was a single document while associations were the
 * only resource, and the shape did not survive the second one — see index().
 *
 * ## Open on purpose
 *
 * No bearer token, on either level. They describe the shape of the data and reveal no
 * data — the kinds and countries are already public through
 * `/api/v1/associations/findByKind` and the shrine index, and the phrase schema names
 * four locale codes — and requiring a credential to *learn how to use a credential* is
 * the kind of friction that produces agents built against guesses.
 *
 * Note what this means for the phrase schema specifically: it lists the locales and
 * the length bound, and it does not list text domains or any phrase. The phrases
 * themselves are behind the token, because `trans_phrases` is shared with other
 * projects and its rows are whatever those projects render.
 */
final class ApiSchemaController
{
    public function __construct(private readonly ServiceBridge $laminas)
    {
    }

    /**
     * `GET /api/v3/schema` — the index, naming every entity v3 exposes.
     *
     * Added when phrases became the second entity. Before that the path answered the
     * association contract directly, which was right while there was one of them and
     * a dead end afterwards: an agent that had learned "the schema lives at
     * /api/v3/schema" would have had no way to discover that a second one existed.
     * Rather than bolt the phrase fields into the same document under a second key —
     * which would have made `entity: "association"` a lie — the path became a
     * directory and the contracts moved one level down.
     */
    public function index(): Response
    {
        return new JsonResponse([
            'version'     => 3,
            'description' => 'The resources /api/v3 exposes. Fetch an entity\'s schema before writing to it; '
                . 'each is generated from the same specification its endpoints validate with, so it '
                . 'cannot drift from what is actually enforced.',
            'entities'    => [
                'association' => [
                    'schema'       => '/api/v3/schema/association',
                    'collection'   => '/api/v3/associations',
                    'requiredRole' => BotIdentity::REQUIRED_ROLE,
                    'description'  => 'Shrines, wayside shrines and the other associations in the database.',
                ],
                'phrase'      => [
                    'schema'       => '/api/v3/schema/phrase',
                    'collection'   => '/api/v3/phrases',
                    'requiredRole' => BotIdentity::TRANSLATOR_ROLE,
                    'description'  => 'The translatable phrases the site renders, and their translations.',
                ],
            ],
        ]);
    }

    /** `GET /api/v3/schema/{entity}`. */
    public function entity(Request $request): Response
    {
        return match ($request->attributes->get('entity')) {
            'association' => $this->association(),
            'phrase'      => $this->phrase(),
            default       => new JsonResponse([
                'error' => [
                    'status'  => Response::HTTP_NOT_FOUND,
                    'message' => 'No such entity. GET /api/v3/schema lists them.',
                ],
            ], Response::HTTP_NOT_FOUND),
        };
    }

    private function association(): Response
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
            'version'      => 3,
            'entity'       => 'association',
            'requiredRole' => BotIdentity::REQUIRED_ROLE,
            'description'  => 'Fields an agent may read and write on an association. '
                . 'PATCH /api/v3/associations/{identifier} with a JSON object of any subset of these; '
                . 'the patch is merged onto the stored record and the result validated as a whole, '
                . 'by the same rules the moderator web form uses.',
            'concurrency'  => [
                'etag'    => 'GET returns a weak ETag over the field document.',
                'ifMatch' => 'PATCH honours If-Match and answers 412 when the record has moved. '
                    . 'Omitting it is permitted and means a blind write.',
            ],
            'fields'       => $fields,
        ]);
    }

    /**
     * The phrase contract.
     *
     * Shaped differently from the association one because the resource is: an
     * association has thirty-odd heterogeneous fields, each with its own rules, and a
     * phrase has one rule repeated across four locales. Describing the locales as
     * `fields` would have been a template applied to the wrong thing — what an agent
     * actually needs to know here is which locales are writable, that the source
     * phrase is not, and how to ask for the subset of 6,874 phrases it should work on.
     *
     * Built from PhraseValidator, i.e. from the translator form's own input filter, so
     * the bound below is the bound enforced.
     */
    private function phrase(): Response
    {
        /** @var PhraseValidator $validator */
        $validator = $this->laminas->get(PhraseValidator::class);
        $locales   = $validator->writableLocales();
        $filter    = $validator->inputFilter();

        $maxLength = null;
        if ([] !== $locales && $filter->has($locales[0])) {
            foreach ($filter->get($locales[0])->getValidatorChain()->getValidators() as $entry) {
                $candidate = $entry['instance'] ?? null;
                if ($candidate instanceof StringLength) {
                    $maxLength = $candidate->getMax();
                }
            }
        }

        return new JsonResponse([
            'version'      => 3,
            'entity'       => 'phrase',
            'requiredRole' => BotIdentity::TRANSLATOR_ROLE,
            'description'  => 'The phrases this site renders, and their translations. '
                . 'PATCH /api/v3/phrases/{phraseId} with a JSON object of locale code to translation, '
                . 'or PATCH /api/v3/phrases with a `phrases` object to write many at once. '
                . 'Translations are validated by the same rules the translator web form uses.',
            'writable'     => [
                'locales'   => $locales,
                'maxLength' => is_numeric($maxLength) ? (int) $maxLength : null,
                'notes'     => [
                    'The source `phrase` is read-only: it is the key the site looks itself up by, '
                        . 'not editable content, so changing it would orphan the row rather than '
                        . 'change what any page renders.',
                    'An empty string means "leave this locale alone", not "blank it" — the web form '
                        . 'behaves the same way, and there is no way to remove a translation through '
                        . 'either surface.',
                    'A locale not listed here is refused rather than ignored. The list is read from '
                        . 'the merged configuration at request time, so it is what this site actually '
                        . 'writes rather than what any one config file says.',
                ],
            ],
            'filters'      => [
                'textDomain'     => 'exact match on the phrase\'s text domain',
                'originRoute'    => 'exact match on the route the phrase was first seen on',
                'search'         => 'substring of the source phrase',
                'untranslatedIn' => 'a locale code; phrases with no usable translation in it',
                'translatedIn'   => 'a locale code; phrases that do have one',
                'limit'          => 'page size, default 100, maximum 500',
                'offset'         => 'page offset',
            ],
            'concurrency'  => [
                'etag'    => 'GET returns a weak ETag over the translations, and only the translations.',
                'ifMatch' => 'PATCH /api/v3/phrases/{phraseId} honours If-Match and answers 412 when the '
                    . 'translations have moved. The batch endpoint does not: a conditional request is '
                    . 'defined over one resource.',
            ],
            'sideEffects'  => [
                'catalogs' => 'A write recompiles the .lang.php catalogs the site renders from. '
                    . 'Until that happens the page still shows the old text, so a response carrying '
                    . 'a `warning` key means the row was saved and the site is stale.',
                'batching' => 'The batch endpoint recompiles once at the end rather than once per '
                    . 'phrase. Use it for more than a handful of writes.',
            ],
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
