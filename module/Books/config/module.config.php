<?php
return [
    'books' => [
        'books_db_adapter' => 'Zend\Db\Adapter\Adapter',
        'book_format_type_value_options' => [
            'AudiobookFormat'   => 'AudiobookFormat',
            'EBook'             => 'EBook',
            'Hardcover'         => 'Hardcover',
            'Paperback'         => 'Paperback',
        ],
        'language_value_options' => [
            'en' => 'English',
            'es' => 'Spanish',
            'de' => 'German',
            'fr' => 'French',
            'pl' => 'Polish',
            'cz' => 'Czhec',
            'fr' => 'French',
            'la' => 'Latin',
            'gr' => 'Greek',
        ],
        'url_label_value_options' => [
            'download'  => 'Download',
            'amazon'    => 'Purchase',
            'borrow'    => 'Borrow',
            'wikipedia' => 'Wikipedia',
        ],
    ],
    'controllers' => [
        'factories' => [
            'Books\Controller\Library'      => 'Books\Service\LibraryControllerFactory',
        ],
        'invokables' => [
            'Books\Controller\Publications' => 'Books\Controller\PublicationsController',
            'Books\Controller\Library'      => 'Books\Controller\LibraryController',
            'Books\Controller\Libraries'    => 'Books\Controller\LibrariesController',
            'Books\Controller\Books'        => 'Books\Controller\BooksController',
            'Books\Controller\Checkouts'    => 'Books\Controller\CheckoutsController',
            'Books\Controller\Borrowers'    => 'Books\Controller\BorrowersController',
        ],
    ],
    'service_manager' => [
        'factories' => [
            'Books\Config'                  => 'Books\Service\ConfigServiceFactory',
            'Books\Model\PublicationsTable' => 'Books\Service\PublicationsTableFactory',
            'Books\Model\LibraryTable'      => 'Books\Service\LibraryTableServiceFactory',
            'Books\Form\CreateCheckoutForm' => 'Books\Service\CheckoutFormFactory',
            'Books\Form\CheckinForm'        => 'Books\Service\CheckinFormFactory',
            'Books\Form\PublicationForm'    => 'Books\Service\PublicationFormFactory',
            'Books\Form\PublicationsSearchForm' => 'Books\Service\PublicationsSearchFormFactory',
        ],
    ],
    'view_helpers' => [
        'factories' => [
        ],
        'invokables' => [
            'formatPublication'             => 'Books\View\Helper\FormatPublication',
            'formatField'                   => 'Books\View\Helper\FormatField',
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
                    'search' => [
                        'type'    => 'Literal',
                        'options' => [
                            'route'    => '/search',
                            'defaults' => [
                                'action'     => 'search',
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
            'books' => [
                'type' => 'Literal',
                'options' => [
                    'route'    => '/books',
                    'defaults' => [
                        'controller' => 'Library\Controller\Books',
                    ],
                ],
                'may_terminate' => false,
                'child_routes' => [
                    'book' => [
                        'type'    => 'Segment',
                        'options' => [
                            'route'    => '/:book_id',
                            'constraints' => [
                                'book_id' => '[0-9]{1,5}',
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
                        ],
                    ],
                    'create' => [
                        'type'    => 'Literal',
                        'options' => [
                            'route'    => '/create',
                            'defaults' => [
                                'action'     => 'create',
                            ],
                        ],
                    ],
                ],
            ],
            'libraries' => [
                'type' => 'Literal',
                'options' => [
                    'route'    => '/libraries',
                    'defaults' => [
                        'controller' => 'Library\Controller\Libraries',
                        'action'     => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'library' => [
                        'type'    => 'Segment',
                        'options' => [
                            'route'    => '/:library_id',
                            'constraints' => [
                                'library_id' => '[0-9]{1,4}', //@todo accept library names
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
                            'delete' => [
                                'type'    => 'Literal',
                                'options' => [
                                    'route'    => '/delete',
                                    'defaults' => [
                                        'action'     => 'delete',
                                    ],
                                ],
                            ],
                            'checkout' => [
                                'type'    => 'Literal',
                                'options' => [
                                    'route'    => '/checkout',
                                    'defaults' => [
                                        'action'     => 'create',
                                        'controller' => 'Library\Controller\Checkouts',
                                    ],
                                ],
                            ],
                            'checkin' => [
                                'type'    => 'Literal',
                                'options' => [
                                    'route'    => '/checkin',
                                    'defaults' => [
                                        'action'     => 'checkin',
                                        'controller' => 'Library\Controller\Checkouts',
                                    ],
                                ],
                            ],
                            'admin' => [
                                'type'    => 'Literal',
                                'options' => [
                                    'route'    => '/admin',
                                    'defaults' => [
                                        'action'     => 'admin',
                                    ],
                                ],
                            ],
                            'import' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route'    => '/import',
                                    'defaults' => [
                                        'action'     => 'import',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'create' => [
                        'type'    => 'Segment',
                        'options' => [
                            'route'    => '/create',
                            'defaults' => [
                                'action'     => 'create',
                            ],
                        ],
                    ],
                    'borrowers' => [
                        'type' => 'Literal',
                        'options' => [
                            'route'    => '/borrowers',
                            'defaults' => [
                                'controller'=> 'Library\Controller\Borrowers',
                                'action'    => 'index',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'borrower' => [
                                'type'    => 'Segment',
                                'options' => [
                                    'route'    => '/:person_id',
                                    'constraints' => [
                                        'person_id' => '[0-9]{1,5}',
                                    ],
                                    'defaults' => [
                                        'action'     => 'show',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'checkouts' => [
                        'type' => 'Literal',
                        'options' => [
                            'route'    => '/checkouts',
                            'defaults' => [
                                'controller'=> 'Library\Controller\Checkouts',
                                'action'    => 'index',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'checkout' => [
                                'type'    => 'Segment',
                                'options' => [
                                    'route'    => '/:checkout_id',
                                    'constraints' => [
                                        'book_id' => '[0-9]{1,5}',
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
                                ],
                            ],
                            'library' => [
                                'type'    => 'Segment',
                                'options' => [
                                    'route'    => '/library/:library_id',
                                    'constraints' => [
                                        'library_id' => '[0-9]{1,5}',
                                    ],
                                    'defaults' => [
                                        'action'     => 'library',
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
            'library' => [
                'sion_model_class'                  => 'Library\Model\LibraryTable',
                'table_name'                        => 'lib_libraries',
                'table_key'                         => 'LibraryId',
                'entity_key_field'                  => 'libraryId',
                'get_object_function'               => 'getLibrary',
                'name_field'                        => 'name',
                'name_field_is_translateable'       => true,
                // 'country_field' => 'country',
                'has_dedicated_suggest_form'        => false,
                'report_changes'                    => true,
                // 'database_bound_data_preprocessor' => 'preprocessTerritory', //this will separate the nationality array
                'required_columns_for_creation'     => [ //required for creation
                    'name',
                ],
                // 'moderate_route' => 'territorys/territory/moderate',
                // 'moderate_route_entity_key' => 'territory_id',
                'show_route'                        => 'libraries/library',
                'show_route_key'                    => 'library_id',
                'show_route_key_field'              => 'libraryId',
                'edit_route'                        => 'libraries/library/edit',
                'edit_route_key'                    => 'library_id',
                'edit_route_key_field'              => 'libraryId',
                'get_object_function'               => 'getLibrary',
                'get_objects_function'              => 'getLibraries',
                'text_columns'                      => [
                ],
                'many_to_one_update_columns' => [
                ],
                'update_columns' => [
                    'libraryId'             => 'LibraryId',
                    'name'                  => 'LibraryName',
                    'description'           => 'Description',
                    'callNumberHelpText'    => 'CallNumberHelpText',
                    'callNumberExplanation' => 'CallNumberExplanation',
                    'filiationId'           => 'FiliationId',
                    'contactPerson'         => 'ContactPerson',
                    'contactEmail'          => 'ContactEmail',
                    'updatedOn'             => 'UpdatedOn',
                    'updatedBy'             => 'UpdatedBy',
                    'createdOn'             => 'CreatedOn',
                    'createdBy'             => 'CreatedBy',
                ],
            ],
            'book' => [
                'sion_model_class'                  => 'Library\Model\LibraryTable',
                'table_name'                        => 'lib_books',
                'table_key'                         => 'book_id',
                'entity_key_field'                  => 'bookId',
                'report_changes'                    => true,
                'has_dedicated_suggest_form'        => false,
                'show_route'                        => 'books/book',
                'show_route_key'                    => 'book_id',
                'show_route_key_field'              => 'bookId',
                'edit_route'                        => 'books/book/edit',
                'edit_route_key'                    => 'book_id',
                'edit_route_key_field'              => 'bookId',
                'get_object_function'               => 'getBook',
                'get_objects_function'              => 'getBooks',
                //                 'index_route'                       => 'roles',
            //                 'index_template'                    => 'patres/roles/index',
            //                 'database_bound_data_preprocessor'  => 'rolePreprocessor', //this will fill in entityId and associationKind
                'enable_delete_action'              => false,
                'create_action_form'                => 'Patres\Form\CreateBookForm',
                'create_action_redirect_route'      => 'books/book',
                'create_action_redirect_route_key'  => 'bookId',
                'create_action_template'            => 'library/books/create',
                'required_columns_for_creation'     => [
                    'title',
                    'libraryId',
                ],
                'name_field'                        => 'name',
                'name_field_is_translateable'       => false,
                //                 'country_field' => 'houseCountry',
                //                 'moderate_route' => 'courses/course/moderate',
                //                 'moderate_route_entity_key' => 'course_id',
                'update_columns' => [
                    'bookId'        => 'book_id',
                    'author'        => 'author',
                    'title'         => 'title',
                    'edition'       => 'edition',
                    'callNumber'    => 'call_number',
                    'category'      => 'category',
                    'pages'         => 'pages',
                    'language'      => 'lang',
                    'originalId'    => 'original_id',
                    'libraryId'     => 'library_id',
                    'publicationId' => 'publication_id',
                    'updatedOn'     => 'updated_at',
                    'updatedBy'     => 'updated_by',
                    'createdOn'     => 'created_at',
                    'createdBy'     => 'created_by',
                ],
            ],
            /**
             * For more information on entity config:
             * @see \SionModel\Entity\Entity
             */
            'checkout' => [
                'name'									=> 'checkout',
                'table_name' 							=> 'lib_checkouts',
                'table_key' 							=> 'CheckoutId',
                'entity_key_field'               		=> 'checkoutId',
                'sion_model_class'               		=> 'Library\Model\LibraryTable',
                'get_object_function' 					=> 'getCheckout',
                'get_objects_function'               	=> 'getCheckouts',
                'required_columns_for_creation' 		=> [
                    'personId',
                    'bookId',
                    'checkedOutOn',
                    'checkedOutBy',
                ],
                'name_field'               				=> 'dueOn',
                'name_field_is_translateable'           => false,
                //                 'country_field'               			=> 'country',
                //                 'text_columns'               			=> [],
                //                 'many_to_one_update_columns'     		=> [
                    //                     'email'	=> 'contactInfo',
                    //                     'cell'	=> 'contactInfo',
                    //                 ],
                'report_changes'               			=> false,
                //                 'index_route'               			=> 'events',
                //                 'index_template'               			=> 'project/events/index',
                //                 'show_action_template'               	=> 'library/checkouts/show',
                'show_route' 							=> 'checkouts/checkout',
                'show_route_key' 						=> 'checkout_id',
                'show_route_key_field' 					=> 'checkoutId',
                //                 'edit_action_form'               		=> 'Library\Form\EditCheckoutForm',
                //                 'edit_action_template'               	=> 'project/events/edit',
                //                 'edit_route'               				=> 'events/event/edit',
                //                 'edit_route_key'               			=> 'event_id',
                //                 'edit_route_key_field'           		=> 'eventId',
                'create_action_form'              		=> 'Library\Form\CreateCheckoutForm',
                'create_action_valid_data_handler'		=> 'createCheckouts',
                'create_action_redirect_route'         	=> 'borrowers/borrower',
                'create_action_redirect_route_key'    	=> 'person_id',
                'create_action_redirect_route_key_field'=> 'personId',
                'create_action_template'           		=> 'library/checkouts/multiple-checkouts',
                //                 'touch_default_field'               	=> 'eventId',
                //                 'touch_field_route_key'           		=> 'event_id',
                //                 'touch_json_route'               		=> 'events/event/touch',
                //                 'touch_json_route_key'            		=> 'event_id',
                'database_bound_data_preprocessor' 		=> 'preprocessCheckout',
                //                 'database_bound_data_postprocessor' 	=> 'postprocessEvent',
                //                 'moderate_route' 						=> 'events/event/moderate',
                //                 'moderate_route_entity_key' 			=> 'event_id',
                'has_dedicated_suggest_form' 			=> false,
                //                 'suggest_form'               			=> 'Project\Form\SuggestEventForm',
                'enable_delete_action' 					=> true,
                'delete_action_acl_resource' 			=> 'checkout_:id',
                'delete_action_acl_permission' 			=> 'delete_checkout',
                'delete_action_redirect_route' 			=> 'checkouts',
                'update_columns' 						=> [
                    'checkoutId'            => 'CheckoutId',
                    'personId'              => 'PersonId',
                    'bookId'                => 'BookId',
                    'checkedOutOn'          => 'CheckedOutOn',
                    'checkedOutBy'          => 'CheckedOutBy',
                    'checkedOutIp'          => 'CheckedOutIp',
                    'checkedOutUserAgent'   => 'CheckedOutUserAgent',
                    'dueOn'                 => 'DueOn',
                    'timesRenewed'          => 'TimesRenewed',
                    'lastRenewedOn'         => 'LastRenewedOn',
                    'checkedInOn'           => 'CheckedInOn',
                    'checkedInBy'           => 'CheckedInBy',
                    'checkedInIp'           => 'CheckedInIp',
                    'checkedInUserAgent'    => 'CheckedInUserAgent',
                    'AdminNotes'            => 'AdminNotes',
                    'AdminNotesUpdatedOn'   => 'AdminNotesUpdatedOn',
                    'AdminNotesUpdatedBy'   => 'AdminNotesUpdatedBy',
                    'updatedOn'             => 'UpdatedOn',
                    'updatedBy'             => 'UpdatedBy',
                ],
            ],
            'publication' => [
                'name'									=> 'publication',
                'table_name' 							=> 'sch_publications',
                'table_key' 							=> 'PublicationId',
                'entity_key_field'               		=> 'publicationId',
                'sion_model_class'               		=> 'Books\Model\PublicationsTable',
                'get_object_function' 					=> 'getPublication',
                'get_objects_function'               	=> 'getPublications',
                'format_view_helper'                    => 'formatPublication',
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
                'edit_action_form'               		=> 'Books\Form\PublicationForm',
//                 'edit_action_template'               	=> 'project/events/edit',
                'edit_route'               				=> 'publications/publication/edit',
                'edit_route_key'               			=> 'publication_id',
                'edit_route_key_field'           		=> 'publicationId',
                'create_action_form'              		=> 'Books\Form\PublicationForm',
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
                ['route' => 'publications', 'roles' => ['pub_user']],
                ['route' => 'publications/search', 'roles' => ['pub_user']],
                ['route' => 'publications/import', 'roles' => ['pub_administrator']],
                ['route' => 'publications/create', 'roles' => ['pub_moderator']],
                ['route' => 'publications/publication', 'roles' => ['pub_user']],
                ['route' => 'publications/publication/edit', 'roles' => ['pub_moderator']],
                ['route' => 'publications/publication/delete', 'roles' => ['pub_general_moderator']],
                ['route' => 'home', 'roles' => ['lib_user']],
                ['route' => 'libraries', 'roles' => ['lib_user']],
                ['route' => 'libraries/library', 'roles' => ['lib_user']],
                ['route' => 'libraries/library/checkout', 'roles' => ['lib_user']],
                ['route' => 'libraries/library/checkin', 'roles' => ['lib_administrator']],
                ['route' => 'libraries/library/admin', 'roles' => ['lib_administrator']],
                ['route' => 'libraries/import', 'roles' => ['lib_administrator']],
                ['route' => 'libraries/checkouts', 'roles' => ['lib_administrator']],
                ['route' => 'libraries/checkouts/library', 'roles' => ['lib_administrator']],
                ['route' => 'libraries/borrowers', 'roles' => ['lib_administrator']],
                ['route' => 'libraries/borrowers/borrower', 'roles' => ['lib_administrator']],
                ['route' => 'books/book', 'roles' => ['lib_teo_viewer', 'lib_sch_viewer', 'lib_administrator']],
            ],
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            'library' => __DIR__ . '/../view',
        ],
    ],
];
