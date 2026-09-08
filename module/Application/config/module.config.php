<?php

/**
 * Zend Framework (http://framework.zend.com/]
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c] 2005-2015 Zend Technologies USA Inc. (http://www.zend.com]
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application;

use App\Acl\AclProvider;
use App\Acl\IsAllowed;
use App\Laminas\ContainerServices;
use App\Console\Command\BuildSitemapCommand;
use App\Console\Command\BuildSitemapCommandFactory;
use Laminas\Router\Http\Literal;
use Laminas\Navigation\Service\DefaultNavigationFactory;
use Laminas\I18n\Translator\TranslatorServiceFactory;
use Laminas\Cache\Service\StorageCacheAbstractServiceFactory;
use Laminas\Cache\Storage\StorageInterface;
use Application\Session\SessionBootstrap;
use Application\View\GdprStrategy;
use Laminas\Session\ManagerInterface as SessionManagerInterface;
use Psr\Container\ContainerInterface;
use Laminas\Router\Http\Segment;
use Schoenstatt\Validator\SchoenstattLinkIdentifier;
use SionModel\Cache\EntityChangeListeners;
use Psr\Log\LoggerInterface;

return [
    'router' => [
        'routes' => [
            'welcome' => [
                'type' => Literal::class,
                'options' => [
                    'route'    => '/',
                    'defaults' => [
                        'controller' => Controller\IndexController::class,
                        'action'     => 'index',
                    ],
                ],
            ],
            'sitemap' => [
                'type' => Literal::class,
                'options' => [
                    'route'    => '/sitemap.xml',
                    'defaults' => [
                        'controller' => Controller\IndexController::class,
                        'action'     => 'sitemap',
                    ],
                ],
            ],
            'developers' => [
                'type' => Literal::class,
                'options' => [
                    'route'    => '/developers',
                    'defaults' => [
                        'controller' => Controller\IndexController::class,
                        'action'     => 'developers',
                    ],
                ],
            ],
            'privacy' => [
                'type' => Literal::class,
                'options' => [
                    'route'    => '/privacy',
                    'defaults' => [
                        'controller' => Controller\IndexController::class,
                        'action'     => 'privacy',
                    ],
                ],
            ],
            'acknowledgements' => [
                'type' => Literal::class,
                'options' => [
                    'route'    => '/acknowledgements',
                    'defaults' => [
                        'controller' => Controller\IndexController::class,
                        'action'     => 'acknowledgements',
                    ],
                ],
            ],
            'redirect-pre-april-2020-sl-id' => [
                'type' => Segment::class,
                'options' => [
                    'route'    => '/:sw_id[/:slug]',
                    'constraints' => [
                        'sw_id' => trim(
                            SchoenstattLinkIdentifier::GENERAL_OLD_REGEX,
                            '/^$'
                        ),
                        'slug' => '[a-z0-9-]{1,200}',
                    ],
                    'defaults' => [
                        'controller' => Controller\IndexController::class,
                        'action'     => 'redirectPreApril2020SlId',
                    ],
                ],
            ],
        ],
    ],
    'service_manager' => [
        'abstract_factories' => [
            StorageCacheAbstractServiceFactory::class,
        ],
        'factories' => [
            'translator' => TranslatorServiceFactory::class,
            'navigation' => DefaultNavigationFactory::class,
            //default persistent storage, configured in cache.local.php
            StorageInterface::class => Service\CacheFactory::class,
            GdprStrategy::class => \Application\Service\GdprStrategyServiceFactory::class,
            /*
             * Starts the session, and is attached from `config/application.config.php`'s
             * `listeners` key rather than from a module `onBootstrap()` — it has to outrank
             * every module hook, because SionModel's asks BjyAuthorize for the identity and
             * that bakes the ACL's roles for the rest of the request. See the class.
             *
             * A closure rather than a factory class: one constructor argument, and the
             * indirection would only hide which service that is.
             */
            SessionBootstrap::class => static fn (ContainerInterface $c): SessionBootstrap
                => new SessionBootstrap($c->get(SessionManagerInterface::class)),
            //The sitemap builder. An App\ class registered from a laminas module config
            //because bin/console resolves commands out of this container — see
            //App\Console\Command\BuildSitemapCommandFactory for what it does and does not
            //build.
            BuildSitemapCommand::class => BuildSitemapCommandFactory::class,
            /*
             * Told whenever any SionTable invalidates an entity, so the cached
             * BjyAuthorize ACL can go with it. Registered here rather than from the
             * Symfony kernel because it has to exist under *both* front controllers and
             * before any table is built: SionTableWiring asks the container for it as it
             * wires each table, and this config is what both containers load.
             *
             * An ordinary shared service, unlike SionModel's CacheFlushQueue — it holds
             * no per-request state. Resolving it builds the ACL cache storage, which is
             * an APCu handle and nothing more.
             */
            EntityChangeListeners::class => static function (ContainerInterface $c): EntityChangeListeners {
                // Empty since the ACL cutover: the new engine (App\Acl\Authorizer) assembles
                // per request from plain arrays (~0.45ms) rather than caching an Acl object
                // across requests, so a role/library/text change is picked up on the next
                // request with nothing to invalidate. BjyAuthorize's cache, which this used to
                // clear, is no longer written — nothing resolves its Authorize service.
                return new EntityChangeListeners();
            },
        ],
        'aliases' => [
            //this helps clarify throughout the app which kind of Logger we should expect.
            LoggerInterface::class => 'SionModel\Logger',
        ],
    ],
    /*
     * `bin/console sitemap:build` writes public/sitemap*.xml, which Apache then serves
     * directly. It exits without building anything when nothing has changed since the last
     * build, so it is cheap to run often; docs/sitemap.md has the cron entry.
     */
    'console' => [
        'commands' => [
            'sitemap:build' => BuildSitemapCommand::class,
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
        'factories' => [
            Controller\IndexController::class => Service\IndexControllerFactory::class,
        ],
        'abstract_factories' => [
            \Application\Controller\LazyControllerFactory::class,
        ],
    ],
    'view_manager' => [
        'doctype'                  => 'HTML5',
        'not_found_template'       => 'error/404',
        'exception_template'       => 'error/index',
        'template_map' => include __DIR__ . '/template_map.config.php',
        'template_path_stack' => [
            __NAMESPACE__ => __DIR__ . '/../view',
        ],
        'strategies' => [
            'ViewJsonStrategy',
        ],
    ],
    'bjyauthorize' => [
        'guards' => [
            // legacy identifier string (BjyAuthorize removed); App\Acl\AclAssembler keys guards on it
            'BjyAuthorize\Guard\Route' => [
                ['route' => 'redirect-pre-april-2020-sl-id', 'roles' => ['guest', 'user']],
                ['route' => 'welcome', 'roles' => ['guest', 'user']],
                ['route' => 'developers', 'roles' => ['guest', 'user']],
                ['route' => 'sitemap', 'roles' => ['guest', 'user']],
                ['route' => 'acknowledgements', 'roles' => ['guest', 'user']],
                ['route' => 'privacy', 'roles' => ['guest', 'user']],
                //Administrators only. The canary itself is not a privilege — both front
                //controllers enforce the same ACL — but a menu item that changes how the
                //site renders has no business being offered to visitors.
            ],
        ],
    ],
    'view_helpers' => [
        'aliases' => [
            'formElement' => 'TwbBundle\Form\View\Helper\TwbBundleFormElement',
            'requestUri'  => View\Helper\RequestUri::class,
        ],
        'factories' => [
            View\Helper\RequestUri::class  => Service\RequestUriFactory::class,
            /*
             * The `isAllowed` view helper, since the ACL cutover pointed at App\Acl\Authorizer
             * rather than BjyAuthorize\View\Helper\IsAllowed (the only prior registrant, which
             * disappears with the package). This one registration covers every laminas-side
             * caller at once: the ported controllers that fetch it from the ViewHelperManager,
             * and the module view helpers (Books\FormatField, Schoenstatt\FormatAssociation)
             * that call `$this->view->isAllowed()`. The container a view-helper factory receives
             * is the app ServiceManager itself, so ContainerServices adapts it without building
             * a second one.
             */
            'isAllowed'                    => static fn (ContainerInterface $c): IsAllowed
                => new IsAllowed(new AclProvider(new ContainerServices($c))),
        ],
    ],
];
