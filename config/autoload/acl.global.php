<?php

return [
    'bjyauthorize' => [
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
                ['route' => 'schoenstatt', 'roles' => ['sch_user', 'sch_basic']],
                ['route' => 'admin', 'roles' => ['sch_moderator', 'translator']],
                ['route' => 'sion-model/data-problems', 'roles' => ['sch_general_moderator']],
                ['route' => 'sion-model/view-changes', 'roles' => ['sch_general_moderator']],
                ['route' => 'sion-model/clear-persistent-cache', 'roles' => ['sch_administrator']],
                ['route' => 'sion-model/phpinfo', 'roles' => ['sch_administrator']],
                ['route' => 'sion-model/auto-fix-data-problems', 'roles' => ['lib_administrator']],
                
                ['route' => 'jtranslate', 'roles' => ['translator', 'sch_general_moderator']],
                ['route' => 'jtranslate/phrase', 'roles' => ['translator', 'sch_general_moderator']],
                
                
                ['route' => 'comments/create', 'roles' => ['user']],
            ],
        ],
    ],
];
