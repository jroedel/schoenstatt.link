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
        'changes_model' => 'Schoenstatt\Model\SchoenstattTable',
        'changes_show_all' => true,
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

        'persistent_cache_config' => [
            'adapter' => [
                'name' => 'apcu',
                'options' => [
                    'ttl' => 60*60*24*5, //5 days
                ],
            ],
        ],
	],
];
