<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/Schoenstatt for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Schoenstatt\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Schoenstatt\Model\SchoenstattTable;
use Schoenstatt\Form\SearchForm;
use Zend\View\Model\ViewModel;

class SchoenstattController extends AbstractActionController
{
    public function indexAction()
    {
        if (!$this->zfcUserAuthentication()->hasIdentity()) {
            return $this->redirect()->toRoute('zfcuser/login');
        }

        $sm = $this->getServiceLocator();
        /** @var SchoenstattTable $table */
        $table = $sm->get('Schoenstatt\Model\SchoenstattTable');
        //@todo get this from config or something
        $generalPresidium = $table->getAssociation(71);
        $nationalLeaders = $table->getNationalMovementsLeaders();
        $form = new SearchForm();

        return new ViewModel([
            'form' => $form,
            'generalPresidium' => $generalPresidium,
            'nationalLeaders' => $nationalLeaders,
        ]);
    }
}
