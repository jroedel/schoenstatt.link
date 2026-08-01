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
    'zfcuser' => [
        'zend_db_adapter' => Laminas\Db\Adapter\Adapter::class,

        'auth_adapters' => [
//             50 => \Application\Authentication\Adapter\JsonPost::class,
        ],

        'enable_default_entities' => false,

        'enable_registration' => true,

        'login_after_registration' => true,

        'use_registration_form_captcha' => true,

        'use_login_form_captcha' => false,

        'form_captcha_options' => [
                'class'   => 'image',
                'options' => [
                        'font'      => './data/fonts/OpenSans-Regular.ttf',
                        'imgDir'   => 'public/img/captcha/',
                        'imgUrl'   => '/img/captcha/',
                        'wordLen'    => 5,
                        'useNumbers' => false,
                        'expiration' => 300,
                        'timeout'    => 300,
                        'lineNoiseLevel' => 3,
                        'dotNoiseLevel' => 50,
                ],
        ],
        'enable_display_name' => true,

        'enable_username' => true,

        'login_redirect_route' => 'welcome',

        'logout_redirect_route' => 'zfcuser/login',

        'use_redirect_parameter_if_present' => true,

        'enable_user_state' => true,
        //the user state will stay at 0 until the user has been validated
        'default_user_state' => 0,

        'allowed_login_states' => [1],
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
                ['route' => 'zfcuser/login', 'roles' => ['guest', null]],
                ['route' => 'zfcuser/logout', 'roles' => ['user', null]],
                ['route' => 'change-password', 'roles' => ['user']],
//                 ['route' => 'change-email', 'roles' => ['user']],
                ['route' => 'zfcuser/register', 'roles' => ['guest']],
                ['route' => 'juser/verify-email', 'roles' => ['guest', 'user', null]],
                ['route' => 'juser/thanks', 'roles' => ['guest', 'user', null]],
                ['route' => 'juser', 'roles' => ['administrator']],
                ['route' => 'juser/user/edit', 'roles' => ['administrator']],
                ['route' => 'juser/user/delete', 'roles' => ['administrator']],
                ['route' => 'juser/user/change-password', 'roles' => ['administrator']],
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
