<?php

/**
 * The API response envelope, and the JWT signing parameters.
 *
 * Both halves are still load-bearing after /api/v1 and /api/v2 were retired on
 * 2026-08-14, which is not obvious from the name:
 *
 * - `responseFormat` is read by RestApi\Controller\RouteNotFoundController, the only
 *   thing left that builds this envelope — every retired v1/v2 URL is answered with it.
 * - `jwtAuth` is read by App\Api\BotIdentity and JUser\Service\ApiTokenService, i.e. by
 *   /api/v3. `cypherKey` lives in the untracked local.php and must be at least 32 bytes
 *   under php-jwt 7; see docs/DEPLOY.md.
 */

return [
    'ApiRequest' => [
        'responseFormat' => [
            'statusKey' => 'status',
            'statusOkText' => 'OK',
            'statusNokText' => 'NOK',
            'resultKey' => 'result',
            'messageKey' => 'message',
            'defaultMessageText' => 'Empty response!',
            'errorKey' => 'error',
            'defaultErrorText' => 'Unknown request!',
            'authenticationRequireText' => 'Authentication Required.',
            'pageNotFoundKey' => 'Request Not Found.',
            //Answered with 410 Gone, not 404, for the retired /api/v1 and /api/v2 URLs.
            //A 404 says "no such thing here" and invites a crawler to keep asking; 410
            //says the resource existed and is permanently gone, which is what gets an
            //indexed URL dropped rather than merely demoted. Scoped to v1 and v2 in
            //RouteNotFoundController — an unknown /api/v3 path is a typo, not a
            //withdrawal, and must stay a 404.
            'retiredVersionKey' => 'This API version has been retired. Use /api/v3.',
        ],
        'jwtAuth' => [
//             'cypherKey' => 'xxxxxxxxxxxxxxxxxxxxxxxxxx',
            'tokenAlgorithm' => 'HS256'
        ],
    ]
];
