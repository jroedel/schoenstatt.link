<?php

use Application\Service\DbConnectionFactory;
use SionModel\Db\Connection;

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
            /**
             * The Symfony-kernel canary toggle is deliberately *not* here. It lived in
             * the navbar until 2026-08-11, where it sat between "Movement" and
             * "Shrines" as a developer tool in a visitor-facing menu; it is now one of
             * the admin landing page's links instead (Schoenstatt\Controller\
             * AdminController::indexAction() and its copy App\Schoenstatt\AdminIndex).
             *
             * The cost of the move is that the toggle no longer returns you to the page
             * you were reading — the Referer is the admin page now, so that is where it
             * comes back to. Switching kernel to inspect a particular page therefore
             * means /en/admin, click, then navigate; the action itself is unchanged and
             * still offers whichever kernel you are not on.
             */
            [
                'label' => 'Shrines',
                'route' => 'shrines',
                'pages' => [
                    [
                        'label' => 'World',
                        'route' => 'shrines',
                    ],
//                     [
//                         'label' => 'Africa',
//                         'route' => 'shrines',
//                         'fragment' => 'Africa',
//                     ],
//                     [
//                         'label' => 'Asia',
//                         'route' => 'shrines',
//                         'fragment' => 'Asia',
//                     ],
//                     [
//                         'label' => 'Europe',
//                         'route' => 'shrines',
//                         'fragment' => 'Europe',
//                     ],
//                     [
//                         'label' => 'Americas',
//                         'route' => 'shrines',
//                         'fragment' => 'Americas',
//                     ],
//                     [
//                         'label' => 'Oceania',
//                         'route' => 'shrines',
//                         'fragment' => 'Oceania',
//                     ],
                    [
                        'label' => 'Submitting photos',
                        'route' => 'shrines/submitting-photos',
                    ],
                    [
                        'label' => 'Wayside shrines',
                        'route' => 'wayside-shrines',
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
                        'route' => 'publications',
                        'fragment' => 'dictionaries',
                    ],
                ],
            ],
            [
                'label' => 'Music',
                'route' => 'music',
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
            Connection::class => DbConnectionFactory::class,
        ],
        'aliases' => [
            //legacy service name still consumed by JUser factories and the
            //bjy-authorize identity provider factory
            'zfcuser_zend_db_adapter' => Connection::class,
        ],
    ],
];
