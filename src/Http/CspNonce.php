<?php

declare(strict_types=1);

namespace App\Http;

use Laminas\Math\Rand;

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
 * Twelve characters is what SionModel\Mvc\CspListener uses; there is nothing
 * magic about it beyond being well past guessable for a single response.
 */
final class CspNonce
{
    private string $value;

    public function value(): string
    {
        return $this->value ??= Rand::getString(12);
    }
}
