<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Laminas\ServiceBridge;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function is_array;
use function is_string;
use function preg_match;

/**
 * The JSON refusal for any `/api/...` path no real route matched — Symfony-served since
 * the API refusal was ported off `LegacyBridge` (Phase B prep of the laminas-mvc removal,
 * 2026-09-08). It reproduces `RestApi\Controller\RouteNotFoundController`.
 *
 * ## Why this had to move
 *
 * It was the last thing besides the admin-only `kernel-switch` that the bridge answered:
 * every unmatched `/api/...` request fell through to `legacy` and into the laminas
 * `RestApi` module. `LegacyBridge` cannot be deleted while anything real still routes
 * through it, so the refusal comes here first. The canary is untouched — under
 * `SYMFONY_KERNEL=0` the laminas route still answers, byte for byte the same.
 *
 * ## 410 for the retired versions, 404 for everything else
 *
 * `/api/v1` and `/api/v2` were withdrawn 2026-08-14. Those URLs get **410 Gone** plus
 * `Link: </api/v3/schema>; rel="successor-version"` (RFC 5829) — the resource existed and
 * is permanently removed, which Google treats as "drop from the index" where a 404 is a
 * softer "try later", and two were published as an indexed schema.org Dataset. The Link
 * names the **schema document**, not `/api/v3` (which is not a route): a successor link
 * pointing at a 404 is worse than none. Every other unmatched `/api/` path keeps its
 * **404** and byte-identical body — an unknown `/api/v3/phrasez` is a typo in a live API,
 * not a withdrawal, and saying "permanently gone" to a caller that misspelled an endpoint
 * is a lie it acts on. So the 410 is matched narrowly against the two retired prefixes.
 *
 * ## One deliberate difference from the laminas route: no locale hop
 *
 * The laminas route inherits SlmLocale, so an unprefixed `/api/v1/x` 302s to
 * `/en/api/v1/x` and only then 410s. These Symfony routes answer directly, prefixed or
 * not — the same choice the `/api/v3` endpoints make ("an agent has no Accept-Language
 * preference worth honouring"). The retired-path regex still accepts an optional locale
 * segment, because crawlers indexed the prefixed forms and those must still 410.
 */
final class ApiRouteNotFoundController
{
    /**
     * Paths whose resources are permanently gone rather than merely absent — reproduced
     * verbatim from the laminas controller. The optional locale segment catches the
     * prefixed forms SlmLocale used to produce; `[/.]` catches `/api/v1.yaml` (the old
     * OpenAPI document) alongside `/api/v1/anything`; `v[12]` cannot match `v3`.
     */
    private const RETIRED_PATH = '#^(/(en|de|es|pt|it))?/api/v[12]([/.]|$)#';

    public function __construct(private readonly ServiceBridge $laminas)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $format = $this->responseFormat();

        if (1 === preg_match(self::RETIRED_PATH, $request->getPathInfo())) {
            $response = $this->envelope(
                $format,
                $this->text(
                    $format,
                    'retiredVersionKey',
                    'This API version has been retired. Use /api/v3; see /api/v3/schema.'
                ),
                Response::HTTP_GONE
            );
            $response->headers->set('Link', '</api/v3/schema>; rel="successor-version"');

            return $response;
        }

        return $this->envelope(
            $format,
            $this->text($format, 'pageNotFoundKey', 'Request Not Found.'),
            Response::HTTP_NOT_FOUND
        );
    }

    /** @param array<string, mixed> $format */
    private function envelope(array $format, string $error, int $status): JsonResponse
    {
        return new JsonResponse([
            $this->key($format, 'statusKey', 'status')  => $this->text($format, 'statusNokText', 'NOK'),
            $this->key($format, 'resultKey', 'result')  => [
                $this->key($format, 'errorKey', 'error') => $error,
            ],
        ], $status);
    }

    /**
     * The ApiRequest.responseFormat block, the same one the laminas factory reads.
     *
     * @return array<string, mixed>
     */
    private function responseFormat(): array
    {
        $config = $this->laminas->config();
        $api    = is_array($config['ApiRequest'] ?? null) ? $config['ApiRequest'] : [];

        return is_array($api['responseFormat'] ?? null) ? $api['responseFormat'] : [];
    }

    /**
     * A configured key name, defaulted. Kept as a helper rather than inlined so that the
     * envelope reads as the shape it is: two configurable key *names* and their values.
     *
     * @param array<string, mixed> $format
     */
    private function key(array $format, string $name, string $default): string
    {
        return is_string($format[$name] ?? null) ? $format[$name] : $default;
    }

    /** @param array<string, mixed> $format */
    private function text(array $format, string $name, string $default): string
    {
        return $this->key($format, $name, $default);
    }
}
