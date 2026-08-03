<?php

return [
    'bjyauthorize' => [
        // BjyAuthorize 2.x defaults to caching the assembled ACL (into a
        // per-request memory store, so it caches nothing anyway). Disabled
        // explicitly to keep the 1.7 behavior: rebuild the ACL each request.
        // Enabling apcu-backed ACL caching is a deliberate future decision.
        'cache_enabled' => false,

        // resource providers provide a list of resources that will be tracked
        // in the ACL. like roles, they can be hierarchical
        'resource_providers' => [
            'BjyAuthorize\Provider\Resource\Config' => [
                'personal_information' => [], //admin columns of fathers
                'publication_drive' => [],
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
                    //who can see files attached to publications
                    [['pub_drive'], 'publication_drive'],
                ],
            ],
        ],

        'guards' => [
            /* If this guard is specified here (i.e. it is enabled], it will block
             * access to all routes unless they are specified here.
            */
            'BjyAuthorize\Guard\Route' => [
                ['route' => 'admin', 'roles' => ['sch_moderator', 'translator']],
                ['route' => 'sion-model/data-problems', 'roles' => ['sch_general_moderator']],
                ['route' => 'sion-model/view-changes', 'roles' => ['sch_general_moderator', 'view_changes']],
                ['route' => 'sion-model/clear-persistent-cache', 'roles' => ['user', 'guest', null]],
                ['route' => 'sion-model/phpinfo', 'roles' => ['sch_administrator']],
                ['route' => 'sion-model/auto-fix-data-problems', 'roles' => ['lib_administrator']],

                ['route' => 'jtranslate', 'roles' => ['translator', 'sch_general_moderator']],
                ['route' => 'jtranslate/phrase/edit', 'roles' => ['translator', 'sch_general_moderator']],
                ['route' => 'jtranslate/phrase/delete', 'roles' => ['translator', 'sch_general_moderator']],

                ['route' => 'comments/create', 'roles' => ['user']],
            ],
        ],
    ],
];
