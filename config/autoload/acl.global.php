<?php

return [
    'bjyauthorize' => [
        /*
         * Cache the assembled ACL in APCu. Taken 2026-08-22; the line above this one
         * used to say it was "a deliberate future decision", and this is that decision.
         *
         * Assembling it costs ~6 ms on every request, anonymous ones included — measured
         * in the web SAPI, which is the only place worth measuring it: a CLI run has its
         * own APCu segment, so its dynamic providers miss on everything and report a
         * number five times too large. Reading it back is 0.37 ms.
         *
         * `cache_enabled` was false rather than absent because BjyAuthorize defaults it to
         * *true* with a `memory` adapter, which caches nothing beyond the request. Turning
         * the flag on without also naming a real adapter would have looked like a change
         * and done nothing at all, which is why the two live next to each other here.
         *
         * The identity is never part of what is stored: `Authorize::load()` writes the ACL
         * to the cache and only then calls `addRole($identity, $parentRoles)`, so one
         * cached document is correct for every visitor.
         *
         * Invalidation is App\Acl\AclCacheInvalidator, driven from
         * SionCacheTrait::removeDependentCacheItems() — read its docblock before changing
         * anything here, in particular before trusting the TTL to do the job. The TTL is a
         * backstop; a role creation that outran it would be a 500, not a stale page.
         */
        'cache_enabled' => true,
        'cache_options' => [
            'adapter' => ['name' => 'apcu'],
            /*
             * NOTE the nesting: BjyAuthorize\Service\CacheFactory reads
             * `cache_options.options`, *not* `cache_options.adapter.options`. Putting them
             * under the adapter — which is how every other cache block in this application
             * is shaped, including sionmodel.global.php's — is silently accepted and
             * silently ignored, leaving an ACL cached under the default namespace with no
             * TTL at all.
             */
            'options' => [
                //five minutes: long enough that the saving is the normal case, short
                //enough that a missed invalidation is an incident nobody notices
                'ttl'       => 300,
                //its own namespace so /sm/cache-status names it in largestEntries; the
                //deploy's APCu flush clears the whole segment either way
                'namespace' => 'bjyauthorize',
            ],
            //no serializer plugin: APCu stores PHP values natively, and the package's
            //default list carries one only because its default adapter is `memory`
            'plugins' => [],
        ],
        'cache_key' => 'acl',

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
                ['route' => 'sion-model/cache-status', 'roles' => ['user', 'guest', null]],
                ['route' => 'sion-model/phpinfo', 'roles' => ['sch_administrator']],

                ['route' => 'jtranslate', 'roles' => ['translator', 'sch_general_moderator']],
                ['route' => 'jtranslate/phrase/edit', 'roles' => ['translator', 'sch_general_moderator']],
                ['route' => 'jtranslate/phrase/delete', 'roles' => ['translator', 'sch_general_moderator']],

                ['route' => 'comments/create', 'roles' => ['user']],
            ],
        ],
    ],
];
