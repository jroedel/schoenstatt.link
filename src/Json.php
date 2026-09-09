<?php

declare(strict_types=1);

namespace App;

use JsonException;

use function json_decode;
use function json_encode;

use const JSON_HEX_AMP;
use const JSON_HEX_APOS;
use const JSON_HEX_QUOT;
use const JSON_HEX_TAG;
use const JSON_THROW_ON_ERROR;

/**
 * What `Laminas\Json\Json` did for this application, in native PHP.
 *
 * Two behaviours of that class were load-bearing, and neither is what a bare
 * `json_encode()`/`json_decode()` does. Reproducing them here once, with the reasons,
 * beats spelling them out at each of the eight call sites — where the next person to
 * touch one would have no way of knowing the flags were deliberate.
 *
 * ## encode(): four escaping flags, and they are what makes the output safe
 *
 * `Json::encode()` always passed `JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP`,
 * which hex-escape `< > ' " &`. Both call sites put the result **into a rendered page**:
 * `templates/schoenstatt/assignment-create.html.twig` writes it inside a `<script>` as
 * `var roles = {{ form.getRolesJson()|raw }}`, and the dictionary page emits a JSON-LD
 * block the same way. Without the flags a role title containing `</script>` closes the
 * tag, and one containing a quote breaks the statement. Dropping them would look like a
 * tidy-up and would be an injection.
 *
 * (The same four flags are what `Symfony\Component\HttpFoundation\JsonResponse` sets, so
 * the API side already agrees with this — see the encoder note in
 * `test/Integration/CacheStatusEndpointTest`'s history.)
 *
 * ## decode(): it throws, and callers rely on that
 *
 * `Json::decode()` checked `json_last_error()` and threw a `RuntimeException`. Two
 * validators — `Schoenstatt\Validator\EventsJson` and `OpeningHoursSpecificationJson` —
 * are built around catching it: a `catch (\Exception)` is how they report "the input
 * could not be parsed as valid JSON". A silent `null` there would make every malformed
 * value **valid**, in a validator whose whole job is to reject it. `JSON_THROW_ON_ERROR`
 * throws `JsonException`, which extends `Exception`, so those catches keep working.
 */
final class Json
{
    /**
     * Encode for output that lands in a page. Returns `false` on failure, as
     * `Json::encode()` did — it never threw, and no call site checks, so throwing here
     * would be a new failure mode rather than a fix.
     *
     * @return string|false
     */
    public static function encode(mixed $value): string|false
    {
        return json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    }

    /**
     * Decode to an associative array, throwing on anything malformed.
     *
     * **The parameter is deliberately not typed `string`.** `Json::decode()` opened with
     * `(string) $encodedValue`, and callers depend on that: both JSON validators answer
     * *valid* for a null or empty value (`if (! isset($value) || '' === $value)`), so
     * `SchoenstattTable` reaches this with a null column and relies on the throw being
     * caught. A `string` type declaration turns that into a `TypeError`, which is an
     * `Error` and not an `Exception` — so the `catch (\Exception)` around the call misses
     * it and the shrine page dies as a fatal under HTTP 200. Measured: six smoke failures,
     * every one an empty 200.
     *
     * @throws JsonException on empty or malformed input, as `Json::decode()` threw
     *         `RuntimeException` — both extend `Exception`, which is what callers catch.
     */
    public static function decodeToArray(mixed $json): mixed
    {
        return json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);
    }
}
