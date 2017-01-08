<?php
namespace Books;

return [
    'books' => [
        'books_db_adapter' => 'Zend\Db\Adapter\Adapter',
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
            'Books\Model\BooksTable' => 'Books\Service\BooksTableFactory',
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
                                        'entity'    => 'publication'
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
            'library' => [
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => [
                    'route'    => '/library',
                    'defaults' => [
                        'controller' => 'Books\Controller\Library',
                        'action'     => 'index',
                        'book_id'    => '',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'update' => [
                        'type' => 'Zend\Mvc\Router\Http\Literal',
                        'options' => [
                            'route'    => '/update',
                            'defaults' => [
                                'controller' => 'Books\Controller\Library',
                                'action'     => 'update',
                                'book_id'    => '',
                            ],
                        ],
                    ],
                    'book' => [
                        'type'    => 'Zend\Mvc\Router\Http\Segment',
                        'options' => [
                            'route'    => '/book/:book_id',
                            'constraints' => [
                                'book_id' => '[0-9]{1,5}',
                            ],
                            'defaults' => [
                                'controller' => 'Books\Controller\Library',
                                'action'     => 'document',
                            ],
                        ],
                    ],
                    'import' => [
                        'type' => 'Zend\Mvc\Router\Http\Literal',
                        'options' => [
                            'route'    => '/import',
                            'defaults' => [
                                'controller' => 'Books\Controller\Library',
                                'action'     => 'import',
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
                'table_name' => 'sch_publications',
                'table_key' => 'PublicationId',
                'entity_key_field' => 'publicationId',
                'update_reference_data_function' => 'getPublication',
                'name_field' => 'title',
                'has_dedicated_suggest_form' => false,
                'report_changes' => true,
                // 'scope' => 'Publication',
                // 'database_bound_data_preprocessor' => 'preprocessPublication', //this will separate the nationality array
                'required_columns_for_creation' => [ //required for creation
                    'title',
                ],
                // 'moderate_route' => 'publications/publication/moderate',
                // 'moderate_route_entity_key' => 'publication_id',
                'show_route' => 'publications/publication',
                'show_route_key' => 'publication_id',
                'show_route_key_field' => 'publicationId',
                'text_columns' => [
                    'description',
                    'publicNotes',
                    'adminNotes'
                ],
                'date_columns' => [
                    'datePublished',
                    'dataSourceUpdatedOn',
                    'adminNotesUpdatedOn',
                    'publicNotesUpdatedOn',
                    'updatedOn',
                    'createdOn',
                ],
                'many_to_one_update_columns' => [
                ],
                'update_columns' => [
                    'publicationId' 			=> 'PublicationId',
                    'title' 					=> 'Title',
                    'resourceId'				=> 'ResourceId',
                    'authorPerson1' 			=> 'AuthorPerson1',
                    'authorPerson2' 			=> 'AuthorPerson2',
                    'authorPerson3' 			=> 'AuthorPerson3',
                    'authors' 					=> 'Authors',
                    'bookEdition' 				=> 'BookEdition',
                    'inLanguage' 				=> 'InLanguage',
                    'description' 				=> 'Description',
                    'isbn' 						=> 'Isbn',
                    'translator' 				=> 'Translator',
                    'illustrator' 				=> 'Illustrator',
                    'numberOfPages' 			=> 'NumberOfPages',
                    'copyrightYear' 			=> 'CopyrightYear',
                    'publisher' 				=> 'Publisher',
                    'publishingPlace' 			=> 'PublishingPlace',
                    'datePublished' 			=> 'DatePublished',
                    'publishingStatus' 			=> 'PublishingStatus',
                    'bookFormatType'			=> 'BookFormatType',
                    'mainPublicationId' 		=> 'MainPublicationId',
                    'volumeNumber' 				=> 'VolumeNumber',
                    'cntainedIn' 				=> 'ContainedIn',
                    'containedInIsbn' 			=> 'ContainedInIsbn',
                    'genre' 					=> 'Genre',
                    'keywords' 					=> 'Keywords',
                    'adminTags'					=> 'AdminTags',
                    'isAccessableForFree'       => 'IsAccessableForFree',
                    'isInternalForPatres'       => 'IsInternalForPatres',
                    'isScientificWork'          => 'IsScientificWork',
                    'isAwaitingMerge'           => 'IsAwaitingMerge',
                    'hasBeenMerged'             => 'HasBeenMerged',
                    'jkQuality' 				=> 'JkQuality',
                    'jkQualityNotes' 			=> 'JkQualityNotes',
                    'jkPeriodId' 				=> 'JkPeriod',
                    'jkEventId' 				=> 'JkEventId',
                    'url1'                      => 'Url1',
                    'url1Label'                 => 'Url1Label',
                    'url2'                      => 'Url2',
                    'url2Label'                 => 'Url2Label',
                    'url3'                      => 'Url3',
                    'url3Label'                 => 'Url3Label',
                    'dataSource'                => 'DataSource',
                    'dataSourceId'              => 'DataSourceId',
                    'dataSourceUpdatedOn'       => 'DataSourceUpdatedOn',
                    'publicNotes'               => 'PublicNotes',
                    'publicNotesUpdatedOn'      => 'PublicNotesUpdatedOn',
                    'publicNotesUpdatedBy'      => 'PublicNotesUpdatedBy',
                    'adminNotes'                => 'AdminNotes',
                    'adminNotesUpdatedOn'       => 'AdminNotesUpdatedOn',
                    'adminNotesUpdatedBy'       => 'AdminNotesUpdatedBy',
                    'updatedOn'                 => 'UpdatedOn',
                    'updatedBy'                 => 'UpdatedBy',
                    'createdOn'                 => 'CreatedOn',
                    'createdBy'                 => 'CreatedBy',
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
                ['route' => 'library', 'roles' => ['lib_user', 'lib_administrator']],
                ['route' => 'library/book', 'roles' => ['lib_teo_viewer', 'lib_sch_viewer', 'lib_administrator']],
                ['route' => 'library/import', 'roles' => ['lib_administrator']],
                ['route' => 'publications', 'roles' => ['pub_user']],
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