<?php

declare(strict_types=1);

namespace App\Schoenstatt\Association;

use InvalidArgumentException;
use Schoenstatt\Model\SchoenstattTable;
use Schoenstatt\Validator\TimeZone;

use function array_is_list;
use function array_keys;
use function array_map;
use function array_values;
use function is_array;
use function is_int;
use function get_debug_type;
use function is_string;
use function sprintf;

/**
 * The four association fields whose valid values come from somewhere other than the
 * field itself: which kinds exist, which countries, which associations may be a
 * parent, which time zones.
 *
 * These are the domains an `InArray` needs, and they are a separate object from
 * {@see AssociationInputFilterSpec} because they are the only part of the
 * specification that cannot be written down. Everything else in that class is a
 * literal; these four are a database query and two config reads, so they are passed
 * in and the spec stays a pure function of them.
 *
 * ## Why the domains come from the sources and not from the form elements
 *
 * The obvious implementation is `array_keys($form->get('kind')->getValueOptions())`,
 * and it is wrong for two of the four:
 *
 * - **parentId.** `SchoenstattTable::getAssociationValueOptions()` defaults to
 *   *active* associations, because that is what should appear in a dropdown. The set
 *   of ids that may legally be stored is wider — an association whose parent was
 *   later deactivated still has that parent — so a haystack built from the dropdown
 *   would reject a value the row already holds. `includeInactive` is passed `true`
 *   here for exactly that reason.
 * - **timeZoneId.** `Schoenstatt\Controller\AssociationsController::editAction()`
 *   narrows the element's options to the zones of the association's country *for
 *   display*. It happens to run after validation today, so reading the element would
 *   work by accident; deriving from `TimeZone::getTimeZoneValueOptions()` instead
 *   means it keeps working if that order ever changes.
 *
 * The general rule the two cases share: a dropdown's contents are a *presentation*
 * decision and the validator's haystack is a *correctness* one, and tying the second
 * to the first makes every future narrowing of a dropdown into a silent validation
 * change.
 *
 * ## Emptiness is an error, not an absent check
 *
 * A domain that came back empty means a service failed, not that everything is
 * permitted. An empty haystack would reject every submission, which is loud and
 * therefore safe; silently dropping the `InArray` would be quiet and therefore not.
 * Neither is what anyone wants at request time, so the constructor refuses, naming
 * the domain that is empty.
 */
final class AssociationFieldDomains
{
    /**
     * @param list<string> $kinds      association kind slugs, e.g. `sch-shrine`
     * @param list<string> $countries  ISO 3166 codes as `CountriesInfo` spells them,
     *                                 which includes the subdivision `GB-SCT`
     * @param list<int>    $parentIds  every association id, active or not
     * @param list<string> $timeZones  IANA identifiers, e.g. `America/Chicago`
     */
    public function __construct(
        public readonly array $kinds,
        public readonly array $countries,
        public readonly array $parentIds,
        public readonly array $timeZones,
    ) {
        foreach (
            [
                'kinds'     => $kinds,
                'countries' => $countries,
                'parentIds' => $parentIds,
                'timeZones' => $timeZones,
            ] as $name => $domain
        ) {
            if ([] === $domain) {
                throw new InvalidArgumentException(sprintf(
                    'The %s domain is empty. An empty haystack rejects every submission, so this is '
                    . 'reported here rather than at the first refused edit — the service it comes from '
                    . 'has failed.',
                    $name
                ));
            }
        }
    }

    /**
     * Build the domains from the application's own services.
     *
     * Takes a resolver rather than a container so that both callers can use it: the
     * laminas `AssociationFormFactory` passes the ServiceManager's `get`, and a
     * Symfony-served route passes `App\Laminas\ServiceBridge`'s. Neither container
     * type is named here, which is the point — this file is on the side of the
     * migration that outlives laminas-mvc.
     *
     * @param callable(string): mixed $service
     */
    public static function fromServices(callable $service): self
    {
        /** @var array<string, mixed> $config */
        $config = $service('config');

        $kinds = $config['schoenstatt']['association_kinds'] ?? [];

        $table = $service(SchoenstattTable::class);
        if (! $table instanceof SchoenstattTable) {
            throw new InvalidArgumentException(sprintf(
                'Expected the service %s to be a %s, got %s.',
                SchoenstattTable::class,
                SchoenstattTable::class,
                get_debug_type($table)
            ));
        }

        return new self(
            self::stringKeys($kinds),
            self::stringKeys($service('CountryValueOptions')),
            //true: every association id, not only the ones the dropdown offers.
            self::intKeys($table->getAssociationValueOptions(true)),
            self::stringKeys(TimeZone::getTimeZoneValueOptions()),
        );
    }

    /**
     * The keys of a laminas `value_options` array, as strings.
     *
     * Value-option arrays are `value => label`, so the keys are the domain. A list
     * is accepted too and taken as the domain itself, which is how a plain array of
     * slugs can be passed without wrapping it.
     *
     * @return list<string>
     */
    private static function stringKeys(mixed $options): array
    {
        if (! is_array($options)) {
            return [];
        }

        $source = array_is_list($options) ? array_values($options) : array_keys($options);

        $domain = [];
        foreach ($source as $value) {
            if (is_string($value)) {
                $domain[] = $value;
                continue;
            }
            if (is_int($value)) {
                //array_keys() narrows a numeric-string key to int; put it back.
                $domain[] = (string) $value;
            }
        }

        return $domain;
    }

    /** @return list<int> */
    private static function intKeys(mixed $options): array
    {
        if (! is_array($options)) {
            return [];
        }

        return array_values(array_map(
            static fn (int|string $key): int => (int) $key,
            array_keys($options)
        ));
    }
}
