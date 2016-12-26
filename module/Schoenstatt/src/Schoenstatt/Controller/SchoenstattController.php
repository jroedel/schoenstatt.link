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
use JTranslate\Controller\Plugin\NowMessenger;

class SchoenstattController extends AbstractActionController
{
    public function indexAction()
    {
        if (!$this->zfcUserAuthentication()->hasIdentity()) {
            return $this->redirect()->toRoute('home');
        }

        $sm = $this->getServiceLocator();
        /** @var SchoenstattTable $table */
        $table = $sm->get('Schoenstatt\Model\SchoenstattTable');
        $form = new SearchForm();
        return new ViewModel([
            'form' => $form,
        ]);
    }

    /**
     * @return \Zend\View\Model\ViewModel
     */
    public function searchAction()
    {
        $sm = $this->getServiceLocator();
        //make sure we get clean parameters
        $params = $this->params()->fromQuery();
        /** @var SearchForm $form */
        $form = new SearchForm();
        $form->setData($params);
        $fathers = null;
        $showPhotos = false;
        if ($form->isValid()) {
            $data = $form->getData();
            $showPhotos = $data['showPhotos'];
            $data['exMembers'] = false;
            unset($data['showPhotos']);
            if (!empty($data)) {
                /** @var SchoenstattTable $table */
                $table = $sm->get('Schoenstatt\Model\SchoenstattTable');
                $fathers = $table->searchPersons($data);
            }
        }
        if (is_array($fathers) && empty($fathers)) {
            $this->nowMessenger()->addMessage("No results found.", NowMessenger::NAMESPACE_INFO);
        }
        return new ViewModel(array(
            'fathers'       => $fathers,
            'showPhotos'    => $showPhotos,
            'form'          => $form,
        ));
    }
}
