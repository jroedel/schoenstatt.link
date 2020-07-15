<?php
return [
    'jtranslate' => [
        'phrases_table_name' => 'trans_phrases',
        'translations_table_name' => 'trans_translations',
        'project_name' => 'Schoenstatt', //change this value for each project
        'locales_to_translate' => [
            'it_IT',
        ],

        // cache options have to be compatible with Zend\Cache\StorageFactory::factory
        'cache_options' => [
            'adapter' => [
                'name'    => 'apcu',
                'ttl'       => 60 * 60, //1 hour
                // With a namespace we can indicate the same type of items
                // -> So we can simple use the db id as cache key
                'options' => [
                    'namespace' => 'jtranslate'
                ],
            ],
        ],
    ],
];
