<?php

use Schoenstatt\Model\SchoenstattTable;

return [
    'juser' => [
        'person_provider' => SchoenstattTable::class,

        /*
         * Accounts an administrator may mint an API token for, from
         * /users/:id/api-tokens. JUser ships this empty — naming a role here is
         * what switches the issue button on.
         *
         * `sch_api_bot` and nothing else, deliberately. Unrestricted, the button
         * would be a "mint a six-month bearer token for any account" tool, and the
         * account it would be most dangerous to mint for is an administrator's: the
         * token outlives the session that created it and no role or password change
         * revokes it (only /users/:id/api-tokens does). Confined to sch_api_bot, the
         * worst it can produce is a credential reaching exactly what that role
         * already reaches, which is /api/v3 and nothing else — see database/db6.6.sql.
         *
         * Never add a role that registration grants. sch_user would mean any
         * registered account could be handed a token that opens the association edit
         * form, which is the failure db6.6.sql exists to prevent.
         */
        'api_token_roles' => ['sch_api_bot'],
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
                /* These two deliberately tighten JUser's own defaults, which
                 * allow ['guest', 'user'] for both. BjyAuthorize keys its rules
                 * by resource and *assigns* rather than merges
                 * (AbstractGuard::__construct), so for a route named twice the
                 * last entry in the merged config wins outright and the earlier
                 * one is discarded silently. config/autoload/ merges after
                 * module config, so these win — but the mechanism is load
                 * order, not precedence, so do not restate a JUser default
                 * here unless you mean to change it. Entries for
                 * zfcuser/login and zfcuser/verify used to sit here repeating
                 * JUser's defaults verbatim; they were removed as pure
                 * duplication.
                 */
                ['route' => 'zfcuser/logout', 'roles' => ['user']],
//                 ['route' => 'change-email', 'roles' => ['user']],
                ['route' => 'zfcuser/register', 'roles' => ['guest']],
                ['route' => 'juser/verify-email', 'roles' => ['guest', 'user', null]],
                ['route' => 'juser/thanks', 'roles' => ['guest', 'user', null]],
                ['route' => 'juser', 'roles' => ['administrator']],
                ['route' => 'juser/user/edit', 'roles' => ['administrator']],
                ['route' => 'juser/user/delete', 'roles' => ['administrator']],
                ['route' => 'juser/user/show', 'roles' => ['administrator']],
                /*
                 * Issuing and revoking API credentials. Administrators only, and
                 * worth being explicit about why it is not merely "the same as the
                 * rest of the users screen": everything else there edits a record,
                 * while this hands out a credential that keeps working for six
                 * months after the session that created it has gone. There is no
                 * lesser role that should be able to do it.
                 */
                ['route' => 'juser/user/api-tokens', 'roles' => ['administrator']],
                ['route' => 'juser/user/api-token-revoke', 'roles' => ['administrator']],
                ['route' => 'juser/create', 'roles' => ['administrator']],
                ['route' => 'juser/create-role', 'roles' => ['administrator']],
            ],
        ],
    ],
    'service_manager' => [
        'aliases' => [
            'JUser\Logger' => \Psr\Log\LoggerInterface::class
        ],
    ],
];
