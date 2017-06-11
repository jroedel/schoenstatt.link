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
            return $this->redirect()->toRoute('welcome');
        }

        $sm = $this->getServiceLocator();
        /** @var SchoenstattTable $table */
        $table = $sm->get('Schoenstatt\Model\SchoenstattTable');


        $config = $sm->get('Schoenstatt\Config');
        if (!isset($config['general_presidium_id']) || !is_numeric($config['general_presidium_id'])) {
            throw new \Exception('Please set the "general_presidium_id" configuration.');
        }
        $generalPresidiumId = $config['general_presidium_id'];
        $generalPresidium = $table->getAssociation($generalPresidiumId);
        $nationalLeaders = $table->getNationalMovementsLeaders();
        $form = new SearchForm();

        return new ViewModel([
            'form'              => $form,
            'generalPresidiumId'=> $generalPresidiumId,
            'generalPresidium'  => $generalPresidium,
            'nationalLeaders'   => $nationalLeaders,
        ]);
    }
}
