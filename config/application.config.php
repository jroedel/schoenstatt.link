<?php

/**
 * What the application is built from: which modules are enabled, and how their
 * configuration is merged and cached.
 *
 * Read by {@see \App\Modules\ModuleConfig}, which took this over from
 * `laminas/laminas-modulemanager` on 2026-09-21.
 */

$env = getenv('APP_ENV') ?: 'production';
$inDevelopment = $env !== 'production';
return [
    // Retrieve list of modules used in this application.
    'modules' => require __DIR__ . '/modules.config.php',

    // How App\Modules\ModuleConfig assembles the merged configuration. The key keeps its
    // laminas-modulemanager name because every consumer still reads it under that name;
    // the listeners it used to configure left with the package on 2026-09-21.
    'module_listener_options' => [
        // Paths from which to glob configuration files after the modules' own configs are
        // collected. These override configuration provided by the modules themselves, and
        // the brace order is the precedence: global, *.global, local, *.local.
        'config_glob_paths' => [
            realpath(__DIR__) . '/autoload/{{,*.}global,{,*.}local}.php',
        ],

        // Whether the merged configuration is cached to disk and read back on subsequent
        // requests. Off in development, where a config edit must take effect; on in
        // production, where `bin/console cache:clear-config` and every deploy discard it
        // (tools/deploy.sh gives each release its own empty data/config).
        'config_cache_enabled' => ! $inDevelopment,

        // The key used to create the configuration cache file name.
        'config_cache_key' => 'sch_config',

        // Only names the file `cache:clear-config` deletes. laminas-modulemanager kept a
        // module class-map cache here; nothing writes one now, because composer's PSR-4
        // map resolves every Module class — the file it did write held an empty array.
        'module_map_cache_key' => 'sch_module_map',

        // The path in which to cache merged configuration.
        'cache_dir' => 'data/config/',
    ],
];
