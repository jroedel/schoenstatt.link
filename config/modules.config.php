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
$toolsOrNot = [];
$env = getenv('APP_ENV') ?: 'production';
$inDevelopment = $env != 'production';
if ($inDevelopment) {
    $toolsOrNot[] = 'ZendDeveloperTools';
}
$modules = [
    'BeaucalInvalidSession',
    'Zend\Router',
    'Zend\I18n',
    'Zend\Form',
    'Zend\Navigation',
    'Zend\Mvc\Plugin\Identity',
    'Zend\Mvc\Plugin\FlashMessenger',
    'Zend\Mvc\I18n',
    'Zend\Validator',
    'MaglMarkdown',
    'ZfSnapGeoip',
    'ZfcUser',
    'BjyAuthorize',
    'SlmLocale',
    'JUser',
    'SionModel',
    'JTranslate',
    'Neilime\MobileDetect',
//    'ZfcDatagrid',
//    'AcMailer', Should we upgrade to v7 or switch to a web service?
//     'Bible',
    'Books',
    'Schoenstatt',
    'Application',
    'TwbBundle',
];
$modules = array_merge($toolsOrNot, $modules);
return $modules;
