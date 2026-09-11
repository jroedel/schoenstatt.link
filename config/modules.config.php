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
    //`Laminas\Form` left on 2026-09-11 with the form model: its module registered the
    //`FormElementManager` that built elements, and `SionModel\Form\Element\Registry` is
    //that list now — reached by the form's own factory, with nothing to configure.
    'Laminas\Validator',
    //provides the session factories JUser\Module::onBootstrap starts
    'Laminas\Session',
    'JUser',
    'SionModel',
    'JTranslate',
    'Books',
    'Schoenstatt',
    'Application',
];

return $modules;
