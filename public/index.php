<?php

use App\Kernel;
use Laminas\Mvc\Application;
use Laminas\Stdlib\ArrayUtils;
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

if (! class_exists(Application::class)) {
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
// config or loading modules is still recorded; SionModel\Module::onBootstrap()
// upgrades it to the fully configured pipeline — including notification — once
// the service container exists.
SionModel\Error\FatalErrorHandler::registerEarly(dirname(__DIR__));

// Retrieve configuration
$appConfig = require __DIR__ . '/../config/application.config.php';
if (file_exists(__DIR__ . '/../config/development.config.php')) {
    $appConfig = ArrayUtils::merge($appConfig, require __DIR__ . '/../config/development.config.php');
}

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
// Two front controllers live here while the Symfony strangler is young, chosen
// by the SYMFONY_KERNEL environment variable (Apache SetEnv; see
// docs/strangler.md). Unset or "0" is the laminas-mvc entry point that has
// always been here, unchanged. "1" puts the Symfony kernel in front, with a
// catch-all route delegating every unported path back to that same laminas
// application — so the observable behaviour is meant to be identical, and the
// smoke suite is what says whether it is.
//
// The variable, rather than a config key, because this branch has to be taken
// before any configuration is loaded; and an environment variable makes
// reverting production an .htaccess edit rather than a deploy, which matters
// while phploy still has its mid-deploy broken window.
if ('1' === (string) (getenv('SYMFONY_KERNEL') ?: '0')) {
    $kernel   = new Kernel($appConfig);
    $request  = Request::createFromGlobals();
    $response = $kernel->handle($request);
    $response->send();
    $kernel->terminate($request, $response);
} else {
    Application::init($appConfig)->run();
}
