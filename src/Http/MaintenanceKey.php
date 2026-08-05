<?php

declare(strict_types=1);

namespace App\Http;

use App\Laminas\ServiceBridge;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use function hash_equals;
use function is_array;
use function is_string;

/**
 * The Symfony-side gate for the maintenance endpoints deploy hooks call without
 * a session — the counterpart of SionModel\Controller\MaintenanceKeyTrait, which
 * does the same job for the laminas controllers.
 *
 * Two channels and hash_equals, exactly as the trait: these are long-lived
 * shared secrets, and a length-independent comparison is one less thing to
 * reason about. The X-Api-Key header is the supported channel, because a query
 * string is recorded verbatim in the web server's access log and kept in the
 * shell history of whatever invoked it. `?key=` still works on purpose: the
 * deploy configuration that sends it lives in the gitignored phploy.ini on each
 * machine, so it cannot be updated in the same commit as this code. Dropping the
 * fallback is its own sequenced task (docs/BACKLOG.md).
 *
 * The rejection is the one behaviour that deliberately differs from the laminas
 * path. There, a failed check throws BjyAuthorize\Exception\UnAuthorizedException
 * and JUser\View\RedirectionStrategy answers 302 to /en/user/login — so a deploy
 * hook follows the redirect, is handed an HTML sign-in page, and can read it as
 * success. A machine endpoint has no use for a sign-in page: this answers 401
 * with a JSON body. Callers presenting a correct key see no difference at all.
 *
 * Where the keys come from is a property of this class rather than of each
 * controller, because two controllers reading `sion_model.api_keys` by hand is
 * two places to forget when that moves.
 */
final class MaintenanceKey
{
    public const HEADER = 'X-Api-Key';

    /** The laminas service holding the merged `sion_model` config block. */
    private const CONFIG_SERVICE = 'SionModel\Config';

    public function __construct(private readonly ServiceBridge $laminas)
    {
    }

    /**
     * The response to send *instead* of doing the work, or null when the caller
     * presented a configured key.
     *
     * A refusal rather than an exception because the answer is part of the
     * endpoint's contract: a deploy hook has to be able to tell "wrong key" from
     * "the flush failed", and both must be machine-readable.
     */
    public function refuse(Request $request): ?JsonResponse
    {
        if (self::accepts($this->configuredKeys(), $request)) {
            return null;
        }

        //says which channel to use, but never whether a key was presented at
        //all: a caller that has the key needs neither hint, and one that does not
        //should learn nothing from the difference
        return new JsonResponse(
            ['message' => 'Unauthorized: this endpoint requires a maintenance key in the ' . self::HEADER . ' header'],
            JsonResponse::HTTP_UNAUTHORIZED
        );
    }

    /**
     * The comparison itself, kept static and pure so it can be exercised without
     * a container or a laminas module tree.
     *
     * @param mixed $apiKeys the configured sion_model.api_keys, whatever it turns
     *        out to be — a missing or malformed value must deny, not crash
     */
    public static function accepts($apiKeys, Request $request): bool
    {
        $presented = self::presented($request);
        if (null === $presented || '' === $presented) {
            return false;
        }
        if (! is_array($apiKeys)) {
            return false;
        }

        foreach ($apiKeys as $candidate) {
            if (is_string($candidate) && hash_equals($candidate, $presented)) {
                return true;
            }
        }

        return false;
    }

    private function configuredKeys(): mixed
    {
        if (! $this->laminas->has(self::CONFIG_SERVICE)) {
            return [];
        }

        $config = $this->laminas->get(self::CONFIG_SERVICE);

        return is_array($config) ? $config['api_keys'] ?? [] : [];
    }

    private static function presented(Request $request): ?string
    {
        $header = $request->headers->get(self::HEADER);
        if (is_string($header) && '' !== $header) {
            return $header;
        }

        //via all() rather than get(): InputBag::get() throws on ?key[]=… , and an
        //array must not reach hash_equals either way
        $fromQuery = $request->query->all()['key'] ?? null;

        return is_string($fromQuery) ? $fromQuery : null;
    }
}
