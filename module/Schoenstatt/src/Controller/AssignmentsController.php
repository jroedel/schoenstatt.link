<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonModule for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Schoenstatt\Controller;

use Zend\View\Model\ViewModel;
use JTranslate\Controller\Plugin\NowMessenger;
use Schoenstatt\Model\SchoenstattTable;
use Schoenstatt\Form\SearchForm;
use SionModel\Controller\SionController;
use Schoenstatt\Form\AdvancedSearchForm;

class AssignmentsController extends SionController
{
    /**
     * @return \Zend\View\Model\ViewModel
     */
    public function searchAction()
    {
        //make sure we get clean parameters
        $params = $this->params()->fromQuery();
        /** @var SearchForm $form */
        $form = new SearchForm();
        $form->setData($params);
        $entities = null;
        if ($form->isValid()) {
            $data = $form->getData();
//             if (!empty($data)) {
                /** @var SchoenstattTable $table */
                $table = $this->getSionTable();
                $entities = $table->searchEntities($data, ['bypassRequiredParams' => true]);
//             }
        }
        if (is_array($entities) && empty($entities)) {
            $this->nowMessenger()->addMessage("No results found.", NowMessenger::NAMESPACE_INFO);
        }
        return new ViewModel([
            'entities'       => $entities,
            'form'          => $form,
        ]);
    }

    /**
     * @return \Zend\View\Model\ViewModel
     */
    public function advancedSearchAction()
    {
        //make sure we get clean parameters
        $params = $this->params()->fromQuery();
        /** @var AdvancedSearchForm $form */
        $form = $this->services[AdvancedSearchForm::class];
        $form->setData($params);
        $entities = null;
        //         $showPhotos = false;
        if ($form->isValid()) {
            $data = $form->getData();
//             $showPhotos = $data['showPhotos'];
//             unset($data['showPhotos']);
            if (!empty($data)) {
                /** @var SchoenstattTable $table */
                $table = $this->getSionTable();
                $entities = $table->searchEntities($data);
            }
        }
        return new ViewModel([
            'objects'      => $entities,
//             'showPhotos'    => $showPhotos,
            'form'          => $form,
        ]);
    }

    public function createAction()
    {
        $view = parent::createAction();
        if ($view instanceof \Zend\Stdlib\ResponseInterface) {
            return $view;
        }
        $form = null;

        if ($this->getRequest()->isGet()) {
            $queryRoleId = $this->params()->fromQuery('roleId');
            $queryPersonId = $this->params()->fromQuery('personId');
            if (isset($queryRoleId) || isset($queryPersonId)) {
                /** @var \Schoenstatt\Form\AssignmentForm $form */
                $form = $view->getVariable('form');
            }
            if (isset($queryRoleId)) {
                //verify the query param
                $queryAssociationId = null;
                $queryAssociationRoles = null;
                $roleTitleValueOptions = $form->getRoleTitleValueOptions();
                foreach ($roleTitleValueOptions as $associationId => $roles) {
                    if (key_exists($queryRoleId, $roles)) { //we found our role
                        $queryAssociationId = $associationId;
                        $queryAssociationRoles = $roles;
                        break;
                    }
                }
                //if it's valid, fill in the form
                if (isset($queryAssociationId)) {
                    if (!$form->get('associationId')->getValue()) {
                        $form->get('associationId')->setValue($queryAssociationId);
                        $form->get('roleId')->setValueOptions($queryAssociationRoles);
                        $form->get('roleId')->setValue($queryRoleId);
                    }
                }
            }
            if (isset($queryPersonId)) {
                $personElement = $form->get('personId');
                if (key_exists($queryPersonId, $personElement->getValueOptions())) {
                    $personElement->setValue($queryPersonId);
                }
            }
        }

        return $view;
    }
}
