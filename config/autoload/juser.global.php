<?php

use Schoenstatt\Model\SchoenstattTable;

return [
    'juser' => [
        'person_provider' => SchoenstattTable::class,
        // cache options have to be compatible with Laminas\Cache\StorageFactory::factory
        'cache_options' => [
            'adapter' => [
                'name'    => 'apcu',
                'ttl'       => 60 * 60 * 24, //1 day
                // With a namespace we can indicate the same type of items
                // -> So we can simple use the db id as cache key
                'options' => [
                    'namespace' => 'juser'
                ],
            ],
        ],
    ],
    'slm_locale' => [
        'default' => 'en_US',

        'supported' => ['en_US', 'es_ES', 'de_DE', 'pt_BR', 'it_IT'],

        'strategies' => [
            [
                'name' => \SlmLocale\Strategy\UriPathStrategy::class,
                'options' => [
                    'redirect_when_found' => true,
                    'aliases' => [
                        'en' => 'en_US',
                        'es' => 'es_ES',
                        'pt' => 'pt_BR',
                        'de' => 'de_DE',
                        'it' => 'it_IT',
                    ],
                ]
            ],
            'cookie',
            'acceptlanguage'
        ],

        'aliases' => [
            'en' => 'en_US',
            'es' => 'es_ES',
            'pt' => 'pt_BR',
            'de' => 'de_DE',
            'it' => 'it_IT',
        ],
    ],
    'bjyauthorize' => [

        'guards' => [
            /* If this guard is specified here (i.e. it is enabled], it will block
             * access to all routes unless they are specified here.
            */
            'BjyAuthorize\Guard\Route' => [
                ['route' => 'zfcuser/login', 'roles' => ['guest', 'user']],
                ['route' => 'zfcuser/logout', 'roles' => ['user']],
//                 ['route' => 'change-email', 'roles' => ['user']],
                ['route' => 'zfcuser/register', 'roles' => ['guest']],
                ['route' => 'zfcuser/verify', 'roles' => ['guest', 'user']],
                ['route' => 'juser/verify-email', 'roles' => ['guest', 'user', null]],
                ['route' => 'juser/thanks', 'roles' => ['guest', 'user', null]],
                ['route' => 'juser', 'roles' => ['administrator']],
                ['route' => 'juser/user/edit', 'roles' => ['administrator']],
                ['route' => 'juser/user/delete', 'roles' => ['administrator']],
                ['route' => 'juser/user/show', 'roles' => ['administrator']],
                ['route' => 'juser/create', 'roles' => ['administrator']],
                ['route' => 'juser/create-role', 'roles' => ['administrator']],
            ],
        ],
    ],
    'service_manager' => [
        'aliases' => [
            'JUser\Logger' => \Laminas\Log\LoggerInterface::class
        ],
    ],
];
