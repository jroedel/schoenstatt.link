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
    //`Laminas\Session` left on 2026-09-21 with `App\Session\HttpSession` and
    //`SionModel\Session\*`. It provided a `SessionManager`, a session `Config` and a
    //`Container` abstract factory; the first two are now one factory in Application's
    //config and the third had no consumers.
    //`Laminas\Form`, `Laminas\Validator` and `Laminas\Filter` all left on 2026-09-11 with
    //the form model and the rule library. Each registered a plugin manager — the
    //`FormElementManager` that built elements, the two that turned a specification's rule
    //names into objects — and `SionModel\Form\Element\Registry`,
    //`SionModel\Validator\Registry` and `SionModel\Filter\Registry` are those lists now:
    //flat maps, reached directly, with nothing to configure and no module to load.
    'JUser',
    'SionModel',
    'JTranslate',
    'Books',
    'Schoenstatt',
    'Application',
];

return $modules;
