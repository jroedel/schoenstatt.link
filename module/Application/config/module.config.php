<?php

/**
 * Zend Framework (http://framework.zend.com/]
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c] 2005-2015 Zend Technologies USA Inc. (http://www.zend.com]
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application;

use JUser\Host\SessionInterface as JUserSessionInterface;
use App\Console\Command\BuildSitemapCommand;
use App\Session\HttpSession;
use App\Console\Command\BuildSitemapCommandFactory;
use App\Console\Command\PrivacyRetentionCommand;
use App\Console\Command\PrivacyRetentionCommandFactory;
use JTranslate\I18n\Translator\Translator as AppTranslator;
use SionModel\Cache\Storage as CacheStorage;
use Schoenstatt\Validator\SchoenstattLinkIdentifier;
use SionModel\Cache\EntityChangeListeners;
use Psr\Log\LoggerInterface;

return [
    'service_manager' => [
        'factories' => [
            //The request's session, from the merged `session_config` block. Registered
            //here since 2026-09-21, when laminas-session left and JUser's module config
            //stopped registering a SessionManager and a session Config.
            HttpSession::class => Service\HttpSessionFactory::class,
            //the session JUser reads and writes, as its own host contract; one
            //registration is what makes the Symfony kernel and the laminas container share
            //a single adapter
            JUserSessionInterface::class => Service\JUserSessionFactory::class,
            //default persistent storage, configured in cache.local.php
            CacheStorage::class => Service\CacheFactory::class,
            //JTranslate's phrase cache, from the `jtranslate.cache_options` block
            'JTranslate\Cache' => Service\JTranslateCacheFactory::class,
            //The sitemap builder. An App\ class registered from a laminas module config
            //because bin/console resolves commands out of this container — see
            //App\Console\Command\BuildSitemapCommandFactory for what it does and does not
            //build.
            BuildSitemapCommand::class => BuildSitemapCommandFactory::class,
            //The privacy policy's retention rule for contact data; App\Privacy\ContactRetention.
            PrivacyRetentionCommand::class => PrivacyRetentionCommandFactory::class,
        ],
        'invokables' => [
            /*
             * Told whenever any SionTable invalidates an entity, so the cached
             * BjyAuthorize ACL can go with it. Registered here rather than from the
             * Symfony kernel because it has to exist under *both* front controllers and
             * before any table is built: SionTableWiring asks the container for it as it
             * wires each table, and this config is what both containers load.
             *
             * An ordinary shared service, unlike SionModel's CacheFlushQueue — it holds
             * no per-request state, and since the ACL cutover it starts empty: the new
             * engine (App\Acl\Authorizer) assembles per request from plain arrays
             * (~0.45ms) rather than caching an Acl object across requests, so a
             * role/library/text change is picked up on the next request with nothing to
             * invalidate. BjyAuthorize's cache, which this used to clear, is no longer
             * written — nothing resolves its Authorize service.
             *
             * An invokable rather than the closure it was until 2026-09-21, which is what
             * the class had always been: it takes no arguments and reads no config. A
             * closure in a module config can only be cached by an exporter that can write
             * one back out. {@see \SchoenstattTest\Integration\MergedConfigIsPlainDataTest}
             */
            EntityChangeListeners::class => EntityChangeListeners::class,
        ],
        'aliases' => [
            //this helps clarify throughout the app which kind of Logger we should expect.
            LoggerInterface::class => 'SionModel\Logger',
            /*
             * The historical short name for the translator, which three factories still
             * resolve. An alias rather than a factory since 2026-09: there is one
             * translator and every other name for it — `MvcTranslator`,
             * `jtranslate_translator` — is an alias of the same class, so a second
             * factory here would build a second
             * translator with no catalogs and no missing-phrase listener.
             */
            'translator' => AppTranslator::class,
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
            'privacy:retention' => PrivacyRetentionCommand::class,
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
];
