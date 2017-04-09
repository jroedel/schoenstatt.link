<?php

return [
    'bjyauthorize' => [
        // resource providers provide a list of resources that will be tracked
        // in the ACL. like roles, they can be hierarchical
        'resource_providers' => [
            'BjyAuthorize\Provider\Resource\Config' => [
                'personal_information' => [], //admin columns of fathers
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
                ['route' => 'schoenstatt', 'roles' => ['guest', 'sch_user', 'sch_basic']],
                ['route' => 'home', 'roles' => ['guest', 'sch_user', 'sch_basic']],
                ['route' => 'sion-model/data-problems', 'roles' => ['sch_general_moderator']],
                ['route' => 'sion-model/view-changes', 'roles' => ['sch_general_moderator']],
                ['route' => 'jtranslate', 'roles' => ['translator', 'sch_general_moderator']],
                ['route' => 'jtranslate/phrase', 'roles' => ['translator', 'sch_general_moderator']],
            ],
        ],
    ],
];