<?php

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

/**
 * This makes our life easier when dealing with paths. Everything is relative
 * to the application root now.
 */
chdir(dirname(__DIR__));

// Decline static file requests back to the PHP built-in webserver
if (php_sapi_name() === 'cli-server') {
    $path = realpath(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    if (__FILE__ !== $path && is_file($path)) {
        return false;
    }
    unset($path);
}

// Composer autoloading
include __DIR__ . '/../vendor/autoload.php';

// The class the front controller is about to instantiate. It was `Laminas\Mvc\Application`
// until that package left; a check naming a class nothing uses would pass a broken install.
if (! class_exists(Kernel::class)) {
    throw new RuntimeException(
        "Unable to load application.\n"
        . "- Type `composer install` if you are developing locally.\n"
        . "- Type `vagrant ssh -c 'composer install'` if you are using Vagrant.\n"
        . "- Type `docker-compose run zf composer install` if you are using Docker.\n"
    );
}

// Catch what the MVC error events cannot see: a PHP fatal (memory exhaustion, a
// hit max_execution_time, a TypeError escaping every catch) never reaches
// dispatch.error, so the visitor gets a blank HTTP 200 and nothing is logged.
// Installed here, before any configuration is loaded, so a failure while merging
// config or loading modules is still recorded; App\Kernel upgrades it to the fully
// configured pipeline — including notification — once the container exists.
SionModel\Error\FatalErrorHandler::registerEarly(dirname(__DIR__));

// Retrieve configuration.
//
// `config/development.config.php` was merged over this until 2026-09-22. It was
// laminas-development-mode's file: that package writes it from a `.dist`, it is not
// installed, there is no `.dist` in the tree, the path is gitignored, and no commit has
// ever carried one. So the branch could not fire, and a release is exactly
// `git ls-files`, which can never contain it.
$appConfig = require __DIR__ . '/../config/application.config.php';

// Report all errors except E_DEPRECATED
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
// Only ever SHOW them outside production; in production php.ini decides
// (rendering errors leaks paths and stack traces to visitors)
if ('production' !== (getenv('APP_ENV') ?: 'production')) {
    ini_set('display_errors', 1);
    ini_set('xdebug.var_display_max_depth', 1);
}
ini_set('memory_limit', '512M');

// Run the application!
//
// One front controller: App\Kernel (symfony/http-kernel). Nothing dispatches through
// laminas-mvc; LegacyBridge and the SYMFONY_KERNEL canary were deleted 2026-09-08 and
// there is no laminas front controller to roll back to (docs/laminas-exit.md).
$kernel   = new Kernel($appConfig);
$request  = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
