<?php
return [
    'controllers' => [
        'invokables' => [
            'Schoenstatt\Controller\Schoenstatt' => 'Schoenstatt\Controller\SchoenstattController',
        ],
    ],
    'router' => [
        'routes' => [
            'contacts' => [
                'type'    => 'Literal',
                'options' => [
                    // Change this to something specific to your module
                    'route'    => '/contacts',
                    'defaults' => [
                        // Change this value to reflect the namespace in which
                        // the controllers for your module are found
                        '__NAMESPACE__' => 'Schoenstatt\Controller',
                        'controller'    => 'Schoenstatt',
                        'action'        => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    // This route is a sane default when developing a module;
                    // as you solidify the routes for your module, however,
                    // you may want to remove it and replace it with more
                    // specific routes.
                    'default' => [
                        'type'    => 'Segment',
                        'options' => [
                            'route'    => '/[:controller[/:action]]',
                            'constraints' => [
                                'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                            ],
                            'defaults' => [
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            'Schoenstatt' => __DIR__ . '/../view',
        ],
    ],
];
