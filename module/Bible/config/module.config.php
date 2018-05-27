<?php
return [
    'controllers' => [
        'invokables' => [
            'Bible\Controller\Bible' => 'Bible\Controller\BibleController',
        ],
    ],
    'service_manager' => [
        'factories' => [
            'Bible\Config'                  => 'Bible\Service\ConfigServiceFactory',
            'Bible\Model\BibleTable'        => 'Bible\Service\BibleTableFactory',
        ],
    ],
    'view_helpers' => [
        'factories' => [
            'formatBibleVerse'          => 'Bible\Service\FormatBibleVerseFactory',
        ],
    ],
    // The following section is new and should be added to your file
    'router' => [
        'routes' => [
            'bible' => [
                'type'    => 'Literal',
                'options' => [
                    'route'    => '/bible',
                    'defaults' => [
                        'controller' => 'Bible\Controller\Bible',
                        'action'     => 'index',
                        'translation' => 'bnt',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'translation' => [
                        'type'    => 'Segment',
                        'options' => [
                            'route'    => '/:translation',
                            'constraints' => [
                                'translation' => '[0-9a-zA-Z]{3,3}',
                            ],
                            'defaults' => [
                                'controller' => 'Bible\Controller\Bible',
                                'action'     => 'index',
                            ],
                        ],
                    ],
                    'text' => [
                        'type'    => 'Segment',
                        'may_terminate' => true,
                        'options' => [
                            'route'    => '/:translation/:book[/:chapter]',
                            'constraints' => [
                                'translation' => '[a-zA-Z]{3,3}',
                                'book'        => '[0-9a-zA-Z]{3,3}',
                                'chapter'     => '[0-9]{1,3}'
                            ],
                            'defaults' => [
                                'controller' => 'Bible\Controller\Bible',
                                'action'     => 'bible',
                            ],
                        ],
                    ],
                    'bnt' => [
                        'type'    => 'Segment',
                        'may_terminate' => true,
                        'options' => [
                            'route'    => '/bnt/:book/:chapter',
                            'constraints' => [
                                'book'        => '[0-9a-zA-Z]{3,3}',
                                'chapter'     => '[0-9]{1,3}'
                            ],
                            'defaults' => [
                                'controller' => 'Bible\Controller\Bible',
                                'action'     => 'bnt',
                                'translation' => 'bnt',
                            ],
                        ],
                    ],
                    'bibleimport' => [
                        'type'    => 'Segment',
                        'may_terminate' => true,
                        'options' => [
                            'route'    => '/import/:translation',
                            'constraints' => [
                                'translation' => '[a-zA-Z]{3,3}',
                            ],
                            'defaults' => [
                                'controller' => 'Bible\Controller\Bible',
                                'action'     => 'import',
                            ],
                        ],
                    ],
                ],
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
