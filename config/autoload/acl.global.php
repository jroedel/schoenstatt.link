<?php

return [
    'bjyauthorize' => [
        // resource providers provide a list of resources that will be tracked
        // in the ACL. like roles, they can be hierarchical
        'resource_providers' => [
            'BjyAuthorize\Provider\Resource\Config' => [
//                 'patres_details'    => [], //admin columns of fathers
            ],
        ],

        /* rules can be specified here with the format:
         * [roles (array], resource, [privilege (array|string], assertion]]
        * assertions will be loaded using the service manager and must implement
        * Zend\Acl\Assertion\AssertionInterface.
        * *if you use assertions, define them using the service manager!*
        */
        'rule_providers' => [
            'BjyAuthorize\Provider\Rule\Config' => [
                'allow' => [
                    //patres_details
//                     [['patres_poweruser', 'patres_moderator_general', 'patres_course_moderator'], 'patres_details'],
                ],
            ],
        ],

        'guards' => [
            /* If this guard is specified here (i.e. it is enabled], it will block
             * access to all routes unless they are specified here.
            */
            'BjyAuthorize\Guard\Route' => [
                ['route' => 'schoenstatt', 'roles' => ['guest', 'patres_user', 'patres_basic']],
                ['route' => 'home', 'roles' => ['guest', 'patres_user']],
                ['route' => 'contacts', 'roles' => ['patres_user']],
                ['route' => 'schoenstatt', 'roles' => ['patres_user']],
                ['route' => 'admin', 'roles' => ['patres_user']],
                ['route' => 'persons', 'roles' => ['patres_user', 'patres_basic']],
                ['route' => 'persons/person', 'roles' => ['patres_user', 'patres_basic']],
                ['route' => 'persons/search', 'roles' => ['patres_user', 'patres_basic']],
                ['route' => 'persons/create', 'roles' => ['patres_administrator', 'patres_moderator_general']],
                ['route' => 'persons/person/edit', 'roles' => ['patres_administrator', 'patres_moderator_general']],
                ['route' => 'persons/person/edit-private-info', 'roles' => ['patres_administrator', 'patres_moderator_general']],
                ['route' => 'persons/person/edit-contact-info', 'roles' => ['patres_contact_moderator', 'patres_administrator', 'patres_moderator_general']],
                ['route' => 'persons/person/moderate', 'roles' => ['patres_contact_moderator', 'patres_administrator', 'patres_moderator_general']],
                ['route' => 'persons/person/edit-personal-info', 'roles' => ['patres_administrator', 'patres_moderator_general']],
                ['route' => 'persons/person/suggest-contact-info', 'roles' => ['patres_user']],
                ['route' => 'associations', 'roles' => ['patres_user', 'patres_basic']],
                ['route' => 'associations/association', 'roles' => ['patres_user', 'patres_basic']],
                ['route' => 'associations/create', 'roles' => ['patres_administrator', 'patres_moderator_general']],
                ['route' => 'associations/association/edit', 'roles' => ['patres_administrator', 'patres_moderator_general']],
                ['route' => 'associations/association/moderate', 'roles' => ['patres_administrator', 'patres_moderator_general']],
                ['route' => 'jtranslate', 'roles' => ['patres_administrator', 'translator', 'patres_moderator_general']],
                ['route' => 'jtranslate/phrase', 'roles' => ['patres_administrator', 'translator', 'patres_moderator_general']],
            ],
        ],
    ],
];