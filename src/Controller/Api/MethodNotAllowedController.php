<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function implode;
use function is_array;
use function is_string;

/**
 * The 405 the v3 API owes a caller who used the wrong verb.
 *
 * Symfony's UrlMatcher raises MethodNotAllowedException and Symfony turns that into a
 * 405 with an `Allow` header — but only when no *other* route matches, and one always
 * does here: `legacy`, the catch-all that hands unported paths to laminas. So a
 * `PUT /api/v3/associations/SL100319A` did not get a 405, it got laminas' 302 to the
 * sign-in page. An agent debugging that sees an authentication problem where it has a
 * spelling problem.
 *
 * These routes are therefore declared explicitly, below their method-constrained
 * siblings, with the permitted verbs passed in as a route default. Two routes rather
 * than one dispatching controller because the `Allow` header differs per path, and a
 * 405 without an accurate `Allow` is barely better than the 302 it replaces.
 */
final class MethodNotAllowedController
{
    /** Route default naming the verbs the path does accept. */
    public const ALLOWED = '_allowed_methods';

    public function __invoke(Request $request): Response
    {
        $allowed = $request->attributes->get(self::ALLOWED);
        $allowed = is_array($allowed) ? $allowed : ['GET'];
        $list    = implode(', ', array_filter($allowed, is_string(...)));

        $response = new JsonResponse([
            'error' => [
                'status'  => Response::HTTP_METHOD_NOT_ALLOWED,
                'message' => 'That method is not supported on this endpoint.',
                'allow'   => $allowed,
            ],
        ], Response::HTTP_METHOD_NOT_ALLOWED);
        $response->headers->set('Allow', $list);

        return $response;
    }
}
