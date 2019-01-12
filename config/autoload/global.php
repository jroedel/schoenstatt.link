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
    ],
    'navigation' => [
        // navigation with name default
        'default' => [
            [
                'label' => 'Movement',
                'route' => 'schoenstatt',
                'resource' => 'route/schoenstatt',
            ],
            [
                'label' => 'Shrines',
                'route' => 'shrines',
                'resource' => 'route/shrines',
            ],
            [
                'label' => 'Literature',
                'route' => 'publications',
                'resource' => 'route/publications',
                'pages' => [
                    [
                        'label' => 'Libraries',
                        'route' => 'libraries',
                        'resource' => 'route/libraries',
                    ], //afterwards we'll add each library
                    [
                        'label' => 'Dictionaries',
                        'route' => 'dictionary',
                        'resource' => 'route/dictionary',
                    ], 
                    [
                        'label' => 'Libraries',
                        'route' => 'libraries',
                        'resource' => 'route/libraries',
                    ],
                ],
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
