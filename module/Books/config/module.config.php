<?php
return [
    'books' => [
        'books_db_adapter' => 'Zend\Db\Adapter\Adapter',
        'book_format_type_value_options' => [
            'AudiobookFormat'   => 'AudiobookFormat',
            'EBook'             => 'EBook',
            'Hardcover'         => 'Hardcover',
            'Paperback'         => 'Paperback',
        ]
    ],
    'controllers' => [
        'factories' => [
            'Books\Controller\Library' => 'Books\Service\LibraryControllerFactory',
        ],
        'invokables' => [
            'Books\Controller\Publications' => 'Books\Controller\PublicationsController',
        ],
    ],
    'service_manager' => [
        'factories' => [
            'Books\Model\PublicationsTable' => 'Books\Service\PublicationsTableFactory',
        ],
    ],

    'router' => [
        'routes' => [
            'publications' => [
                'type'    => 'Literal',
                'options' => [
                    'route'    => '/publications',
                    'defaults' => [
                        'controller' => 'Books\Controller\Publications',
                        'action'     => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'create' => [
                        'type'    => 'Literal',
                        'options' => [
                            'route'    => '/create',
                            'defaults' => [
                                'action'     => 'create',
                            ],
                        ],
                    ],
                    'import' => [
                        'type'    => 'Literal',
                        'options' => [
                            'route'    => '/import',
                            'defaults' => [
                                'action'     => 'import',
                            ],
                        ],
                    ],
                    'publication' => [
                        'type'    => 'Segment',
                        'options' => [
                            'route'    => '/:publication_id',
                            'constraints' => [
                                'publication_id' => '[0-9]{1,5}',
                            ],
                            'defaults' => [
                                'action'     => 'show',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'edit' => [
                                'type'    => 'Literal',
                                'options' => [
                                    'route'    => '/edit',
                                    'defaults' => [
                                        'action'     => 'edit',
                                    ],
                                ],
                            ],
                            'suggest' => [
                                'type'    => 'Literal',
                                'options' => [
                                    'route'    => '/suggest',
                                    'defaults' => [
                                        'action'     => 'suggest',
                                    ],
                                ],
                            ],
                            'moderate' => [
                                'type'    => 'Segment',
                                'options' => [
                                    'route'    => '/moderate/:suggestion_id',
                                    'constraints' => [
                                        'suggestion_id' => '[0-9]{1,5}',
                                    ],
                                    'defaults' => [
                                        'action'     => 'moderate',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'sion_model' => [
        'entities' => [
            'publication' => [
                'name'									=> 'publication',
                'table_name' 							=> 'sch_publications',
                'table_key' 							=> 'PublicationId',
                'entity_key_field'               		=> 'publicationId',
                'sion_model_class'               		=> 'Books\Model\PublicationsTable',
                'get_object_function' 					=> 'getPublication',
                'get_objects_function'               	=> 'getPublications',
                'required_columns_for_creation' 		=> [
                    'title'
                ],
                'name_field'               				=> 'title',
                'name_field_is_translateable'           => false,
                'country_field'               			=> 'country',
                'text_columns'               			=> [],
//                 'many_to_one_update_columns'     		=> [
//                     'email'	=> 'contactInfo',
//                     'cell'	=> 'contactInfo',
//                 ],
                'report_changes'               			=> true,
                'index_route'               			=> 'publications',
//                 'index_template'               			=> 'project/events/index',
//                 'show_action_template'               	=> 'project/events/show',
                'show_route' 							=> 'publications/publication',
                'show_route_key' 						=> 'publication_id',
                'show_route_key_field' 					=> 'publicationId',
                'edit_action_form'               		=> 'Books\Form\EditPublicationForm',
//                 'edit_action_template'               	=> 'project/events/edit',
                'edit_route'               				=> 'publications/publication/edit',
                'edit_route_key'               			=> 'publication_id',
                'edit_route_key_field'           		=> 'publicationId',
                'create_action_form'              		=> 'Books\Form\CreatePublicationForm',
//                 'create_action_valid_data_handler'		=> 'createEvent',
                'create_action_redirect_route'         	=> 'publications/publication',
                'create_action_redirect_route_key'    	=> 'publication_id',
                'create_action_redirect_route_key_field'=> 'publicationId',
//                 'create_action_template'           		=> 'project/events/create',
//                 'touch_default_field'               	=> 'publicationId',
//                 'touch_field_route_key'           		=> 'publication_id',
//                 'touch_json_route'               		=> 'publications/publication/touch',
//                 'touch_json_route_key'            		=> 'publication_id',
//                 'database_bound_data_preprocessor' 		=> 'preprocessEvent',
//                 'database_bound_data_postprocessor' 	=> 'postprocessEvent',
//                 'moderate_route' 						=> 'events/event/moderate',
//                 'moderate_route_entity_key' 			=> 'event_id',
                'has_dedicated_suggest_form' 			=> false,
//                 'suggest_form'               			=> 'Project\Form\SuggestEventForm',
                'enable_delete_action' 					=> false,
//                 'delete_action_acl_resource' 			=> 'event_:id',
//                 'delete_action_acl_permission' 			=> 'delete_event',
//                 'delete_action_redirect_route' 			=> 'events',
                'update_columns' => [
                    'publicationId'             => 'PublicationId',
                    'title'                     => 'Title',
                    'resourceId'                => 'ResourceId',
                    'authorPerson1'             => 'AuthorPerson1',
                    'authorPerson2'             => 'AuthorPerson2',
                    'authorPerson3'             => 'AuthorPerson3',
                    'authors'                   => 'Authors',
                    'bookEdition'               => 'BookEdition',
                    'inLanuage'                 => 'InLanguage',
                    'description'               => 'Description',
                    'isbn'                      => 'Isbn',
                    'illustrator'               => 'Illustrator',
                    'translator'                => 'Translator',
                    'numberOfPages'             => 'NumberOfPages',
                    'copyrightYear'             => 'CopyrightYear',
                    'publisher'                 => 'Publisher',
                    'publishingPlace'           => 'PublishingPlace',
                    'datePublished'             => 'DatePublished',
                    'publishingStatus'          => 'PublishingStatus',
                    'bookFormatType'            => 'BookFormatType',
                    'genre'                     => 'Genre',
                    'url1'                      => 'Url1',
                    'url1Label'                 => 'Url1Label',
                    'url2'                      => 'Url2',
                    'url2Label'                 => 'Url2Label',
                    'url3'                      => 'Url3',
                    'url3Label'                 => 'Url3Label',
                    'mainPublicationId'         => 'MainPublicationId', //mainEntity
                    'volumeNumber'              => 'VolumeNumber',
                    'containedIn'               => 'ContainedIn',
                    'containedInIsbn'           => 'ContainedInIsbn',
                    'keywords'                  => 'PublicTags',

                    'isAccessableForFree'       => 'IsAccessableForFree',
                    'isInternalForPatres'       => 'IsInternalForPatres',
                    'isScientificWork'          => 'IsScientificWork',
                    'isAwaitingMerge'           => 'IsAwaitingMerge',
                    'hasBeenMerged'             => 'HasBeenMerged',
                    'publicNotes'               => 'PublicNotes',
                    'publicNotesUpdatedOn'      => 'PublicNotesUpdatedOn',
                    'publicNotesUpdatedBy'      => 'PublicNotesUpdatedBy',
                    'adminTags'                 => 'AdminTags',
                    'adminNotes'                => 'AdminNotes', //store source info here
                    'adminNotesUpdatedOn'       => 'AdminNotesUpdatedOn',
                    'adminNotesUpdatedBy'       => 'AdminNotesUpdatedBy',
                    'updatedOn'                 => 'UpdatedOn',
                    'updatedBy'                 => 'UpdatedBy',
                    'createdOn'                 => 'CreatedOn',
                    'createdBy'                 => 'CreatedBy',

                    'dataSource'                => 'DataSource',
                    'dataSourceId'              => 'DataSourceId',
                    'dataSourceUpdatedOn'       => 'DataSourceUpdatedOn',
                    'jkQuality'                 => 'JkQuality',
                    'jkQualityNotes'            => 'JkQualityNotes',
                    'jkPeriod'                  => 'JkPeriod',
                    'jkEventId'                 => 'JkEventId',
                ],
            ],
        ],
    ],

    'bjyauthorize' => [
        // resource providers provide a list of resources that will be tracked
        // in the ACL. like roles, they can be hierarchical
        'resource_providers' => [
           'BjyAuthorize\Provider\Resource\Config' => [
               'book',
               'book_teo',
               'book_sch',
           ],
           //'Event\Model\EventTable' => 'Event\Model\EventTable'
         ],

        /* rules can be specified here with the format:
         * array(roles (array), resource, array(privilege (array|string), assertion))
        * assertions will be loaded using the service manager and must implement
        * Zend\Acl\Assertion\AssertionInterface.
        * *if you use assertions, define them using the service manager!*
        */
        'rule_providers' => [
            'BjyAuthorize\Provider\Rule\Config' => [
                'allow' => [
//                 allow guests and users (and admins, through inheritance]
//                 the "wear" privilege on the resource "pants"
                    //read permissions
                    [['lib_user'], 'book', 'read'],
                    [['lib_teo_viewer'], 'book_teo', 'read'],
                    [['lib_sch_viewer'], 'book_sch', 'read'],
                    //write permissions
//                     [['lib_moderator'], 'book', 'write'],
//                     [['lib_teo_moderator'], 'book_teo', 'write'],
//                     [['lib_sch_moderator'], 'book_sch', 'write'],
                    //suggest permissions
                    [['lib_user'], 'book', 'suggest'],
                    [['lib_user'], 'book_teo', 'suggest'],
                    [['lib_user'], 'book_sch', 'suggest'],
                    //approve permissions
//                     [['lib_moderator'], 'book', 'approve'],
//                     [['lib_teo_moderator'], 'book_teo', 'approve'],
//                     [['lib_sch_moderator'], 'book_sch', 'approve'],
                    //delete permissions
                    [['pub_general_moderator'], 'book', 'delete'],
                    [['pub_general_moderator'], 'book_teo', 'delete'],
                    [['pub_general_moderator'], 'book_sch', 'delete'],
                ],
            ],
        ],
        'guards' => [
            'BjyAuthorize\Guard\Route' => [
//                 ['route' => 'library', 'roles' => ['lib_user', 'lib_administrator']],
//                 ['route' => 'library/book', 'roles' => ['lib_teo_viewer', 'lib_sch_viewer', 'lib_administrator']],
//                 ['route' => 'library/import', 'roles' => ['lib_administrator']],
                ['route' => 'publications', 'roles' => ['pub_user']],
                ['route' => 'publications/search', 'roles' => ['pub_user']],
                ['route' => 'publications/import', 'roles' => ['pub_administrator']],
                ['route' => 'publications/publication', 'roles' => ['pub_user']],
                ['route' => 'publications/publication/edit', 'roles' => ['pub_moderator']],
                ['route' => 'publications/publication/create', 'roles' => ['pub_moderator']],
                ['route' => 'publications/publication/delete', 'roles' => ['pub_general_moderator']],
            ],
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            'library' => __DIR__ . '/../view',
        ],
    ],
];
