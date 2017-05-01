<?php
return [
    'sion_model' => [
		/**
		 * Database table name of where to store change records
		 */
		'changes_table' => 'sch_changes',
		/**
		 * Database table name of where to store visit records
		 */
        'visits_table' => 'sch_visits',
		/**
		 * This is the service name of a SionTable instance to call the getChanges() method
		 */
		'visits_model' => 'Schoenstatt\Model\SchoenstattTable',

//        'post_place_line_format' => ':zip :cityState',
//        'post_place_line_format_by_country' => [
//            'US' => ':cityState :zip',
//            'CL' => ':cityState :zip',
//        ],

	    'default_redirect_route' => 'home',

		/**
		* If this key is set, it will be used to prime the SionForm with persons for suggestions.
		* The class must implement the SionModel\Person\PersonProviderInterface
		*/
		'person_provider' => 'Schoenstatt\Model\SchoenstattTable',

        'route_permission_checking_enabled' => true,

        'cache_config' => [
            'adapter' => [
                'name' => 'filesystem',
                'options' => [
                    'dirLevel' => 2,
                    'cacheDir' => 'cache/data',
                    'dirPermission' => 0755,
                    'filePermission' => 0666,
                    'namespaceSeparator' => '-db-'
                ],
            ],
            'plugins' => ['serializer'],//   - See more at: https://arjunphp.com/zend-framework-2-cache-example/#sthash.1P0kgSma.dpuf
        ],
	],
];
