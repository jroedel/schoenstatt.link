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
    'Laminas\Mvc\Plugin\Identity',
    'Laminas\Mvc\Plugin\FlashMessenger',
    'Laminas\Mvc\Plugin\Prg',
    'Laminas\Mvc\I18n',
    'Laminas\Validator',
    //provides the session factories JUser\Module::onBootstrap starts
    'Laminas\Session',
    //cache + its adapters must precede BjyAuthorize, whose Module declares a
    //dependency on Laminas\Cache
    'Laminas\Cache',
    'Laminas\Cache\Storage\Adapter\Apcu',
    'Laminas\Cache\Storage\Adapter\Filesystem',
    //BjyAuthorize's default cache_options ask for the memory adapter
    'Laminas\Cache\Storage\Adapter\Memory',
    'Laminas\Serializer',
    'BjyAuthorize',
    'SlmLocale',
    'RestApi',
    'JUser',
    'SionModel',
    'JTranslate',
    'Books',
    'Schoenstatt',
    'Application',
    'TwbBundle',
];

return $modules;
