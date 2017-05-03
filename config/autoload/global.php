<?php
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
    'navigation' => [
        // navigation with name default
        'default' => [
            [
                'label' => 'Associations',
                'route' => 'associations',
                'resource' => 'route/associations',
            ],
            [
                'label' => 'All contact info',
                'route' => 'assignments',
                'resource' => 'route/assignments',
            ],
            [
                'label' => 'Publications',
                'route' => 'publications',
                'resource' => 'route/publications',
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
            'Zend\Db\Adapter\Adapter' => 'Application\Service\DbAdapterServiceFactory',
        ],
    ],
];
