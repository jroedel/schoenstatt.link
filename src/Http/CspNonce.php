<?php

declare(strict_types=1);

namespace App\Http;

use function base64_encode;
use function random_bytes;
use function rtrim;
use function substr;

/**
 * One nonce per request, shared by the listener that names it in the
 * Content-Security-Policy header and the template that puts it on the inline
 * <script> the policy is meant to allow.
 *
 * A value object rather than a string threaded through two constructors, because
 * the two collaborators are built at different moments — the listener when the
 * kernel wires its event dispatcher, the Twig extension only if a controller
 * renders something — and both must see the *same* value. Generated lazily so a
 * request that renders no HTML never spends the entropy.
 *
 * Twelve characters is what the laminas CSP listener used; there is nothing magic
 * about it beyond being well past guessable for a single response.
 */
final class CspNonce
{
    private string $value;

    public function value(): string
    {
        //`Laminas\Math\Rand::getString(12)` until 2026-09, and this is what that did with
        //no character list: base64 of ceil(12 * 0.75) = 9 random bytes, cut to 12. A nonce
        //is compared only against itself, so the base64 alphabet is fine where it appears —
        //quoted in the header, quoted in the attribute.
        return $this->value ??= substr(rtrim(base64_encode(random_bytes(9)), '='), 0, 12);
    }
}
