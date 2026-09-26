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
        //PHP 8.5 deprecates PDO::MYSQL_ATTR_INIT_COMMAND in favour of
        //Pdo\Mysql::ATTR_INIT_COMMAND, which does not exist before 8.4 — and
        //this capsule can still be switched down to 7.4 for bisecting. Both
        //spellings are the same integer, so the check is only about not emitting
        //a deprecation on the newer rungs.
        //
        //utf8mb4, not UTF8: in MariaDB `utf8` means `utf8mb3`, so 'SET NAMES UTF8'
        //opens a 3-byte connection. Against the utf8mb4 tables that database/db7.0
        //creates, the server would silently convert anything outside the BMP down
        //to `?` on the way out — two different emoji sent over such a connection
        //come back identical and compare equal. See database/db7.0.sql.
        'driver_options' => [
            (class_exists('Pdo\Mysql') ? \Pdo\Mysql::ATTR_INIT_COMMAND : \PDO::MYSQL_ATTR_INIT_COMMAND)
                => 'SET NAMES utf8mb4;',
        ],
    ],
    'view_manager' => [
        'display_not_found_reason' => true,
        'display_exceptions' => true,
    ],
    'sion_model' => [
        'api_keys' => ['local-dev-api-key'],
    ],
    'books' => [
        'files_api_key' => 'local-dev-dummy',
        'files_api_url' => 'http://localhost/dev-null',
    ],
    'schoenstatt' => [
        'api_keys' => ['local-dev-api-key'],
        'patres_api_key' => 'local-dev-dummy',
        'patres_api_person_list_uri' => 'https://schoenstatt-fathers.link/api/persons',
        'patres_api_get_person_uri' => 'https://schoenstatt-fathers.link/api/persons/%s',
    ],
    'recaptcha' => [
        'name' => 'recaptcha',
        'privKey' => 'local-dev-dummy',
        'pubKey' => 'local-dev-dummy',
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
