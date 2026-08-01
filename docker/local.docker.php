<?php

/**
 * Docker-only configuration. Mounted over config/autoload/local.php inside
 * the app container (see docker-compose.yml) — the host's local.php is not
 * touched. No real credentials belong in this file.
 */

$smtpOptions = [
    // Mailpit captures all outgoing mail: UI at http://localhost:8025
    'username' => 'webmaster@schoenstatt.link',
    'password' => '',
    'ssl' => null,
    'port' => 1025,
    'server' => 'mailpit',
];

return [
    'db' => [
        'driver' => 'pdo_mysql',
        'hostname' => 'db',
        'database' => 'ourlink_db1',
        'username' => 'schoenstatt',
        'password' => 'schoenstatt',
        'driver_options' => [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES UTF8;'],
    ],
    'view_manager' => [
        'display_not_found_reason' => true,
        'display_exceptions' => true,
    ],
    'sion_model' => [
        'max_items_to_cache' => 1,
        'api_keys' => ['local-dev-api-key'],
    ],
    'books' => [
        'files_api_key' => 'local-dev-dummy',
        'files_api_url' => 'http://localhost/dev-null',
    ],
    'schoenstatt' => [
        'gdpr_ip_address_exceptions' => [],
        'api_keys' => ['local-dev-api-key'],
        'patres_api_key' => 'local-dev-dummy',
        'patres_api_person_list_uri' => 'https://schoenstatt-fathers.link/api/persons',
        'patres_api_get_person_uri' => 'https://schoenstatt-fathers.link/api/persons/%s',
    ],
    'service_manager' => [
        'factories' => [
            \Zend\Db\Adapter\Adapter::class => \Application\Service\DbAdapterServiceFactory::class,
        ],
    ],
    'recaptcha' => [
        'name' => 'recaptcha',
        'privKey' => 'local-dev-dummy',
        'pubKey' => 'local-dev-dummy',
    ],
    'acmailer_options' => [
        'default' => [
            'smtp_options' => [
                'connection_config' => $smtpOptions,
            ],
        ],
    ],
    'smtp_options' => $smtpOptions,
    'session_config' => [
        // Local dev runs over plain http; a secure-only cookie would break login
        'cookie_secure' => false,
        'cookie_http_only' => true,
    ],
    'ApiRequest' => [
        'jwtAuth' => [
            'cypherKey' => 'local-dev-jwt-cypher-key-not-secret',
        ],
    ],
];
