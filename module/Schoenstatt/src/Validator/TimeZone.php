<?php
namespace Schoenstatt\Validator;

use App\Time\TimeZoneOptions;

/**
 * The time zones an association may be set to.
 *
 * Despite the namespace this is not a validator and has not been one in practice: its
 * `isValid()` compared the submitted value against the option *labels* rather than the
 * identifiers, so it would have rejected every real time zone, and the `@todo` beside it
 * said as much ("don't use in production"). Nothing ever reached it — no input filter
 * specification names it, and `SionModel\Validator\Registry` does not either — because the
 * class is used only for the static list below. The dead method went with
 * `scottconnerly/timezone` on 2026-09-21 rather than being fixed, since fixing it would
 * have added a check no form had.
 *
 * What does enforce the domain is `App\Schoenstatt\Association\AssociationFieldDomains`,
 * which takes the *keys* of this list — the IANA identifiers — and hands them to the
 * engine as a `ChoiceDomain`. The labels are presentation only.
 */
class TimeZone
{
    /**
     * @param string|null $country an ISO 3166 code to narrow the list to, or null for all
     * @return array<string, string> identifier => label
     */
    public static function getTimeZoneValueOptions(?string $country = null)
    {
        $all = TimeZoneOptions::all();
        if (! isset($country) || 'GB-SCT' === $country) {
            return $all;
        }

        $countryList = \DateTimeZone::listIdentifiers(\DateTimeZone::PER_COUNTRY, $country);
        if (! is_array($countryList) || empty($countryList)) {
            return $all;
        }

        $result = [];
        foreach ($all as $identifier => $label) {
            if (in_array($identifier, $countryList)) {
                $result[$identifier] = $label;
            }
        }
        return $result;
    }
}
