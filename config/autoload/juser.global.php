<?php
use Schoenstatt\Model\SchoenstattTable;

return [
    'juser' => [
        'person_provider' => SchoenstattTable::class,
    ],
    'zfcuser' => [
        'zend_db_adapter' => Zend\Db\Adapter\Adapter::class,

        'enable_default_entities' => false,

        'enable_registration' => false,

        'use_registration_form_captcha' => true,
        
        'use_login_form_captcha' => true,

        'form_captcha_options' => [
                'class'   => 'image',
                'options' => [
                        'font'      => './data/fonts/OpenSans-Regular.ttf',
                        'imgDir'   => 'public/img/captcha/',
                        'imgUrl'   => '/img/captcha/',
                        'wordLen'    => 5,
                        'expiration' => 300,
                        'timeout'    => 300,
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

        'supported' => ['en_US', 'es_ES', 'de_DE', 'pt_BR'],

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
        ],
    ],
    /**
     * GoalioRememberMe Configuration
     */
    'goaliorememberme' => [

        /**
         * RememberMe Model Entity Class
    *
    * Name of Entity class to use. Useful for using your own entity class
    * instead of the default one provided. Default is ZfcUser\Entity\User.
    */
        //'remember_me_entity_class' => 'GoalioRememberMe\Entity\RememberMe',

        /**
         * Remember me cookie expire time
    *
    * How long will the user be remembered for, in seconds?
    *
    * Default value: 2592000 seconds = 30 days
    * Accepted values: the number of seconds the user should be remembered
    */
        'cookie_expire' => 2592000,

        /**
         * Remember me cookie domain
    *
    * Default value: null (current domain]
        * Accepted values: a string containing the domain (example.com], subdomains (sub.example.com] or the all subdomains qualifier (.example.com]
            */
        //'cookie_domain' => null,

        /**
         * End of GoalioRememberMe configuration
        */
    ],
    'bjyauthorize' => [

        'guards' => [
            /* If this guard is specified here (i.e. it is enabled], it will block
             * access to all routes unless they are specified here.
            */
            'BjyAuthorize\Guard\Route' => [
                ['route' => 'zfcuser/login', 'roles' => ['guest']],
                ['route' => 'zfcuser/logout', 'roles' => ['user']],
                ['route' => 'change-password', 'roles' => ['user']],
//                 ['route' => 'change-email', 'roles' => ['user']],
                ['route' => 'register', 'roles' => ['guest']],
//                 ['route' => 'zfcuser/register', 'roles' => ['guest']],
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
];