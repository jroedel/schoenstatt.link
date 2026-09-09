<?php
/**
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

/**
 * List of enabled modules for this application.
 *
 * This should be an array of module namespaces used in the application.
 */
$modules = [
    'Laminas\Router',
    'Laminas\I18n',
    'Laminas\Form',
    'Laminas\Navigation',
    'Laminas\Validator',
    //provides the session factories JUser\Module::onBootstrap starts
    'Laminas\Session',
    //Laminas\Cache + its APCu/Filesystem adapters back the application's own caches
    //(config/autoload's StorageInterface, the SionModel persistent cache).
    'Laminas\Cache',
    'Laminas\Cache\Storage\Adapter\Apcu',
    'Laminas\Cache\Storage\Adapter\Filesystem',
    'Laminas\Serializer',
    'JUser',
    'SionModel',
    'JTranslate',
    'Books',
    'Schoenstatt',
    'Application',
];

return $modules;
