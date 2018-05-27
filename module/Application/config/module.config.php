<?php
/**
 * Zend Framework (http://framework.zend.com/]
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c] 2005-2015 Zend Technologies USA Inc. (http://www.zend.com]
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application;

use Application\Controller\IndexController;
use Zend\Router\Http\Literal;
use Zend\Navigation\Service\DefaultNavigationFactory;
use Zend\I18n\Translator\TranslatorServiceFactory;
use Zend\Log\LoggerAbstractServiceFactory;
use Zend\Cache\Service\StorageCacheAbstractServiceFactory;
use Zend\ServiceManager\Factory\InvokableFactory;
use BjyAuthorize\Guard\Route;
use SionModel\Controller\LazyControllerFactory;

return [
    'router' => [
        'routes' => [
            'welcome' => [
                'type' => Literal::class,
                'options' => [
                    'route'    => '/',
                    'defaults' => [
                        'controller' => IndexController::class,
                        'action'     => 'index',
                    ],
                ],
            ],
            'sitemap' => [
                'type' => Literal::class,
                'options' => [
                    'route'    => '/sitemap.xml',
                    'defaults' => [
                        'controller' => IndexController::class,
                        'action'     => 'sitemap',
                    ],
                ],
            ],
            'developers' => [
                'type' => Literal::class,
                'options' => [
                    'route'    => '/developers',
                    'defaults' => [
                        'controller' => IndexController::class,
                        'action'     => 'developers',
                    ],
                ],
            ],
            'acknowledgements' => [
                'type' => Literal::class,
                'options' => [
                    'route'    => '/acknowledgements',
                    'defaults' => [
                        'controller' => IndexController::class,
                        'action'     => 'acknowledgements',
                    ],
                ],
            ],
            // The following is a route to simplify getting started creating
            // new controllers and actions without needing to create a new
            // module. Simply drop new controllers in, and you can access them
            // using the path /application/:controller/:action
//             'application' => [
//                 'type'    => Literal::class,
//                 'options' => [
//                     'route'    => '/application',
//                     'defaults' => [
//                         '__NAMESPACE__' => 'Application\Controller',
//                         'controller'    => 'Index',
//                         'action'        => 'index',
//                     ],
//                 ],
//                 'may_terminate' => true,
//                 'child_routes' => [
//                     'default' => [
//                         'type'    => 'Segment',
//                         'options' => [
//                             'route'    => '/[:controller[/:action]]',
//                             'constraints' => [
//                                 'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
//                                 'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
//                             ],
//                             'defaults' => [
//                             ],
//                         ],
//                     ],
//                 ],
//             ],
        ],
    ],
    'service_manager' => [
        'abstract_factories' => [
            StorageCacheAbstractServiceFactory::class,
            LoggerAbstractServiceFactory::class,
        ],
        'factories' => [
            'translator' => TranslatorServiceFactory::class,
            'navigation' => DefaultNavigationFactory::class
        ],
    ],
//     'translator' => [
//         'locale' => 'en_US',
//         'translation_file_patterns' => [
//             [
//                 'type'     => 'gettext',
//                 'base_dir' => __DIR__ . '/../language',
//                 'pattern'  => '%s.mo',
//             ],
//         ],
//     ],
    'controllers' => [
//         'invokables' => [
//             IndexController::class => IndexController::class
//         ],
//         'factories' => [
//             IndexController::class => IndexController::class,
//         ],
        'abstract_factories' => [
            \Application\Controller\LazyControllerFactory::class,
        ],
    ],
    'view_manager' => [
        'display_not_found_reason' => true,
        'display_exceptions'       => true,
        'doctype'                  => 'HTML5',
        'not_found_template'       => 'error/404',
        'exception_template'       => 'error/index',
        'template_map' => include __DIR__ . '/template_map.config.php',
        'template_path_stack' => [
            __DIR__ . '/../view',
        ],
    ],
    'asset_manager' => array(
        'resolver_configs' => array(
            'collections' => array(
                'js/basic.js' => array(
                    'js/jquery.min.js',
                    'js/bootstrap.min.js',
                    'js/basic-include.js',
                ),
                'css/basic.css' => array(
                    'css/bootstrap.min.css',
                    'css/flag-icon.min.css',
                    'css/font-awesome.min.css',
                ),
            ),
            'paths' => array(
//                 'photos' => __DIR__ . '/../../../../data/foto',
                __NAMESPACE__ => __DIR__ . '/../public',
            ),
//             'map' => array(
//                 'specific-path.css' => __DIR__ . '/some/particular/file.css',
//             ),
        ),
        'caching' => array(
            'css/basic.css' => array(
                'cache'     => 'AssetManager\\Cache\\FilePathCache',
                'options' => array(
                    'dir' => 'public', // path/to/cache
                ),
            ),
            'js/basic.js' => array(
                'cache'     => 'AssetManager\\Cache\\FilePathCache',
                'options' => array(
                    'dir' => 'public', // path/to/cache
                ),
            ),
        ),
//         'filters' => array(
//             'js/d.js' => array(
//                 array(
//                     // Note: You will need to require the classes used for the filters yourself.
//                     'filter' => 'JSMin',
//                 ),
//             ),
//         ),
        'view_helper' => array(
            // Note: You will need to require the factory used for the cache yourself.
//             'cache'        => 'Application\Cache\Redis',
        ),
    ),
    'bjyauthorize' => [
        'guards' => [
            Route::class => [
                ['route' => 'welcome', 'roles' => ['guest', 'user']],
                ['route' => 'developers', 'roles' => ['guest', 'user']],
                ['route' => 'sitemap', 'roles' => ['guest', 'user']],
                ['route' => 'acknowledgements', 'roles' => ['guest', 'user']],
            ],
        ],
    ],
];
