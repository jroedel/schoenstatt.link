<?php
use Application\Service\DbAdapterServiceFactory;
use Zend\Db\Adapter\Adapter;

/**
 * Global Configuration Override
 *
 * You can use this file for overriding configuration values from modules, etc.
 * You would place values in here that are agnostic to the environment and not
 * sensitive to security.
 *
 * @NOTE: In practice, this file will typically be INCLUDED in your source
 * control, so do not include passwords or other sensitive information in this
 * file.
 */

return [
    'schoenstatt' => [
        'gdpr_template' => 'application/index/gdpr',
        'log_path' => 'data/logs/application.log',
    ],
    'navigation' => [
        // navigation with name default
        'default' => [
            [
                'label' => 'Home',
                'route' => 'welcome',
                'pages' => [
                    [
                        'label' => 'Developers Center',
                        'route' => 'developers',
                    ], //afterwards we'll add each library
                    [
                        'label' => 'Security research acknowledgements',
                        'route' => 'acknowledgements',
                    ],
                ],
            ],
            [
                'label' => 'Movement',
                'route' => 'schoenstatt',
                'resource' => 'route/schoenstatt',
            ],
            [
                'label' => 'Shrines',
                'route' => 'shrines',
                'pages' => [
                    [
                        'label' => 'Africa',
                        'route' => 'shrines',
                        'fragment' => 'Africa',
                    ],
                    [
                        'label' => 'Asia',
                        'route' => 'shrines',
                        'fragment' => 'Asia',
                    ],
                    [
                        'label' => 'Europe',
                        'route' => 'shrines',
                        'fragment' => 'Europe',
                    ],
                    [
                        'label' => 'Americas',
                        'route' => 'shrines',
                        'fragment' => 'Americas',
                    ],
                    [
                        'label' => 'Oceania',
                        'route' => 'shrines',
                        'fragment' => 'Oceania',
                    ],
                ],
            ],
            [
                'label' => 'Literature',
                'route' => 'publications',
                'pages' => [
                    [
                        'label' => 'Libraries',
                        'route' => 'libraries',
                    ], //afterwards we'll add each library
                    [
                        'label' => 'Dictionaries',
                        'route' => 'dictionary',
                    ],
                ],
            ],
            [
                'label' => 'Blog',
                'route' => 'blog',
            ],
            [
                'label' => 'Admin',
                'route' => 'admin',
                'resource' => 'route/admin',
            ],
        ],
    ],
    'service_manager' => [
        'factories' => [
            Adapter::class => DbAdapterServiceFactory::class,
        ],
    ],
];
