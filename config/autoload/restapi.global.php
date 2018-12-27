<?php

/**
 * Global Configuration 
 *
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
            'pageNotFoundKey' => 'Request Not Found.'
        ],
        'jwtAuth' => [
//             'cypherKey' => 'xxxxxxxxxxxxxxxxxxxxxxxxxx',
            'tokenAlgorithm' => 'HS256'
        ],
    ]
];
