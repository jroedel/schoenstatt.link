<?php
namespace Bible;

use Zend\Router\Http\Segment;
use Zend\Router\Http\Literal;
use BjyAuthorize\Guard\Route;

return [
//     'controllers' => [
//         'invokables' => [
//             'Bible\Controller\Bible' => 'Bible\Controller\BibleController',
//         ],
//     ],
    'service_manager' => [
        'factories' => [
            Model\BibleTable::class        => Service\BibleTableFactory::class,
            Model\DhTable::class        => Service\DhTableFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'dh' => [
                'type'    => Literal::class,
                'options' => [
                    'route'    => '/dh',
                    'defaults' => [
                        'controller' => Controller\DhController::class,
                        'action'     => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'number' => [
                        'type'    => Segment::class,
                        'options' => [
                            'route'    => '/:dh_number',
                            'constraints' => [
                                'dh_number' => '[0-9]{1,4}',
                            ],
                            'defaults' => [
                                'action'     => 'show',
                            ],
                        ],
                    ],
                    'page' => [
                        'type'    => Segment::class,
                        'options' => [
                            'route'    => '/pg/:page_number',
                            'constraints' => [
                                'page_number' => '[0-9]{1,4}',
                            ],
                            'defaults' => [
                                'action'     => 'show',
                            ],
                        ],
                    ],
                    'import' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/import',
                            'defaults' => [
                                'action'     => 'import',
                            ],
                        ],
                    ],
                ],
            ],
            'bible' => [
                'type'    => Literal::class,
                'options' => [
                    'route'    => '/bible',
                    'defaults' => [
                        'controller' => Controller\BibleController::class,
                        'action'     => 'index',
                        'translation' => 'bnt',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'translation' => [
                        'type'    => Segment::class,
                        'options' => [
                            'route'    => '/:translation',
                            'constraints' => [
                                'translation' => '[0-9a-zA-Z]{3,3}',
                            ],
                            'defaults' => [
                                'action'     => 'index',
                            ],
                        ],
                    ],
                    'text' => [
                        'type'    => Segment::class,
                        'may_terminate' => true,
                        'options' => [
                            'route'    => '/:translation/:book[/:chapter]',
                            'constraints' => [
                                'translation' => '[a-zA-Z]{3,3}',
                                'book'        => '[0-9a-zA-Z]{3,3}',
                                'chapter'     => '[0-9]{1,3}'
                            ],
                            'defaults' => [
                                'action'     => 'bible',
                            ],
                        ],
                    ],
                    'bnt' => [
                        'type'    => Segment::class,
                        'may_terminate' => true,
                        'options' => [
                            'route'    => '/bnt/:book/:chapter',
                            'constraints' => [
                                'book'        => '[0-9a-zA-Z]{3,3}',
                                'chapter'     => '[0-9]{1,3}'
                            ],
                            'defaults' => [
                                'action'     => 'bnt',
                                'translation' => 'bnt',
                            ],
                        ],
                    ],
                    'bibleimport' => [
                        'type'    => Segment::class,
                        'may_terminate' => true,
                        'options' => [
                            'route'    => '/import/:translation',
                            'constraints' => [
                                'translation' => '[a-zA-Z]{3,3}',
                            ],
                            'defaults' => [
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
            'bible-verse' => [
                'name'                                      => 'bible-verse',
                'table_name'                                => 'bib_verses',
                'table_key'                                 => 'id',
                'entity_key_field'                          => 'verseId',
                'sion_model_class'                          => Model\BibleTable::class,
                'sion_controllers'                          => [Controller\BibleController::class],//BorrowersController::class],
                'controller_services'                       => [
                    
                ],
//                 'get_object_function'                       => 'getVerses',
//                 'get_objects_function'                      => 'getUnlinkedTexts',
                //                 'format_view_helper'                        => 'formatEvent',
                'required_columns_for_creation'             => [
//                     'title',
//                     'kind',
//                     'inLanguage',
                ],
                'name_field'                                => 'texts',
                'name_field_is_translateable'               => false,
                //                 'country_field'                             => 'country',
                'text_columns'                              => [],
//                 'many_to_one_update_columns'                => [
//                     'email'    => 'contactInfo',
//                     'cell'    => 'contactInfo',
//                 ],
                'report_changes'                            => false,
//                 'index_route'                               => 'events',
//                 'index_template'                            => 'project/events/index',
                'default_route_key'                         => 'verse_id',
//                 'show_a  ction_template'                      => 'project/events/show',
//                 'show_route'                                => 'texts/text',
//                 'show_route_key'                            => 'text_id',
//                 'show_route_key_field'                      => 'textId',
//                 'edit_action_form'                          => Form\BlogForm::class,
//                 'edit_action_template'                      => 'project/events/edit',
//                 'edit_route'                                => 'blog/blog-post/edit',
//                 'edit_route_key'                            => 'text_id',
//                 'edit_route_key_field'                      => 'textId',
//                 'create_action_form'                        => Form\BlogForm::class,
//                 'create_action_valid_data_handler'          => 'blog/blog-post/edit',
//                 'create_action_redirect_route'              => 'blog/blog-post',
//                 'create_action_redirect_route_key'          => 'text_id',
//                 'create_action_redirect_route_key_field'    => 'textId',
//                 'create_action_template'                    => 'project/events/create',
//                 'touch_default_field'                       => 'eventId',
//                 'touch_route_key'                           => 'event_id',
//                 'touch_field_route_key'                     => 'event_id',
//                 'touch_json_route'                          => 'events/event/touch',
//                 'touch_json_route_key'                      => 'event_id',
//                 'database_bound_data_preprocessor'          => 'preprocessText',
//                 'database_bound_data_postprocessor'         => 'postprocessEvent',
//                 'moderate_route'                            => 'events/event/moderate',
//                 'moderate_route_entity_key'                 => 'event_id',
//                 'suggest_form'                              => 'Project\Form\SuggestEventForm',
//                 'enable_delete_action'                      => true,
//                 'delete_action_acl_resource'                => 'event_:id',
//                 'delete_action_acl_permission'              => 'delete',
//                 'delete_action_redirect_route'              => 'blog',
                'update_columns'                            => [
                    'verseId' => 'id',
                    'translation' => 'translation_id',
                    'book' => 'book_id',
                    'chapter' => 'chapter',
                    'verse' => 'verse',
                    'text' => 'text',
                ],
            ],
            'dh-page' => [
                'name'                                      => 'dh-page',
                'table_name'                                => 'bib_dh_page',
                'table_key'                                 => 'PageNumber',
                'entity_key_field'                          => 'pageNumber',
                'sion_model_class'                          => Model\DhTable::class,
                'sion_controllers'                          => [Controller\DhController::class],//BorrowersController::class],
                'controller_services'                       => [
                    
                ],
//                 'get_object_function'                       => 'getVerses',
//                 'get_objects_function'                      => 'getUnlinkedTexts',
                'row_processor_function'                    => 'processDhRow',
//                 'format_view_helper'                        => 'formatEvent',
                'required_columns_for_creation'             => [
                    'pageNumber',
                    'fileName',
                ],
                'name_field'                                => 'pageNumber',
                'name_field_is_translateable'               => false,
                'report_changes'                            => false,
//                 'index_route'                               => 'dh',
//                 'index_template'                            => 'project/events/index',
                'default_route_key'                         => 'page_number',
//                 'show_action_template'                      => 'project/events/show',
                'show_route'                                => 'dh',
                'show_route_key'                            => 'page_number',
                'show_route_key_field'                      => 'pageNumber',
//                 'edit_action_form'                          => Form\BlogForm::class,
//                 'edit_action_template'                      => 'project/events/edit',
//                 'edit_route'                                => 'blog/blog-post/edit',
//                 'edit_route_key'                            => 'text_id',
//                 'edit_route_key_field'                      => 'textId',
//                 'create_action_form'                        => Form\BlogForm::class,
//                 'create_action_valid_data_handler'          => 'blog/blog-post/edit',
//                 'create_action_redirect_route'              => 'blog/blog-post',
//                 'create_action_redirect_route_key'          => 'text_id',
//                 'create_action_redirect_route_key_field'    => 'textId',
//                 'create_action_template'                    => 'project/events/create',
//                 'touch_default_field'                       => 'eventId',
//                 'touch_route_key'                           => 'event_id',
//                 'touch_field_route_key'                     => 'event_id',
//                 'touch_json_route'                          => 'events/event/touch',
//                 'touch_json_route_key'                      => 'event_id',
//                 'database_bound_data_preprocessor'          => 'preprocessText',
//                 'database_bound_data_postprocessor'         => 'postprocessEvent',
//                 'moderate_route'                            => 'events/event/moderate',
//                 'moderate_route_entity_key'                 => 'event_id',
//                 'suggest_form'                              => 'Project\Form\SuggestEventForm',
//                 'enable_delete_action'                      => true,
//                 'delete_action_acl_resource'                => 'event_:id',
//                 'delete_action_acl_permission'              => 'delete',
//                 'delete_action_redirect_route'              => 'blog',
                'update_columns'                            => [
                    'pageNumber' => 'PageNumber',
                    'fileName' => 'FileName',
                    'headerDhNumber' => 'ReferenceNumber',
                    'startDhNumber' => 'StartDhNumber',
                    'endDhNumber' => 'EndDhNumber',
                    'headerText' => 'HeaderText',
                    'widthInPixels' => 'WidthInPixels',
                    'heightInPixels' => 'HeightInPixels',
                    'fullTextFileName' => 'FullTextFileName',
                    'fullText' => 'FullTextOCR',
                ],
            ],
        ],
    ],
    'bjyauthorize' => [
        'guards' => [
            Route::class => [
                ['route' => 'bible', 'roles' => ['bib_user']],
                ['route' => 'bible/translation', 'roles' => ['bib_user']],
                ['route' => 'bible/text', 'roles' => ['bib_user']],
                ['route' => 'bible/bnt', 'roles' => ['bib_user']],
                ['route' => 'bible/bibleimport', 'roles' => ['bib_administrator']],
                ['route' => 'dh', 'roles' => ['bib_administrator']],
                ['route' => 'dh/import', 'roles' => ['bib_administrator']],
                ['route' => 'dh/page', 'roles' => ['bib_user']],
                ['route' => 'dh/number', 'roles' => ['bib_user']],
            ],
        ],
    ],

    'view_manager' => [
        'template_map' => include __DIR__ . '/template_map.config.php',
        'template_path_stack' => [
            'bible' => __DIR__ . '/../view',
        ],
    ],
];
