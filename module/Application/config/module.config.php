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
use SionModel\Service\ProblemService;
use JTranslate\Model\TranslationsTable;
use Zend\ServiceManager\Proxy\LazyServiceFactory;

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
        'lazy_services' => [
            // Mapping services to their class names is required
            // since the ServiceManager is not a declarative DIC.
            'class_map' => [
                ProblemService::class => ProblemService::class,
                TranslationsTable::class => TranslationsTable::class,
            ],
        ],
        'delegators' => [
            ProblemService::class => [
                LazyServiceFactory::class,
            ],
            TranslationsTable::class => [
                LazyServiceFactory::class,
            ],
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
    'view_helpers' => [
        'aliases' => [
            'formElement' => 'TwbBundle\Form\View\Helper\TwbBundleFormElement',
        ],
    ],
];
