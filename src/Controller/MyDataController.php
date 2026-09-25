<?php

declare(strict_types=1);

namespace App\Controller;

use App\Privacy\PersonalData;
use Closure;
use JUser\Host\IdentityInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

use function gmdate;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * GET /user/my-data — the signed-in account downloads everything held about it, and about
 * the person it is linked to. The privacy policy's right of access, without writing in.
 *
 * Only ever the current identity: the route takes no id, so there is nothing to tamper with.
 * The guard (`route/my-data`, role `user`) sends an anonymous visitor to the sign-in page;
 * the null check below is for an account deactivated between the guard and here.
 */
final class MyDataController
{
    /** @param Closure(): PersonalData $data built only once a request is authorized */
    public function __construct(
        private readonly IdentityInterface $identity,
        private readonly Closure $data
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $user = $this->identity->current();
        $export = null === $user ? null : ($this->data)()->forAccount((int) $user->getId());
        if (null === $export) {
            return new JsonResponse(['error' => 'not signed in'], 403);
        }

        $response = new JsonResponse($export);
        $response->setEncodingOptions(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                'schoenstatt-link-my-data-' . gmdate('Y-m-d') . '.json'
            )
        );
        //personal data: nothing between here and the browser may keep a copy
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
