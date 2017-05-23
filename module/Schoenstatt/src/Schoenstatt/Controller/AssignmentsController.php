<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonModule for the canonical source repository
 * @copyright Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Schoenstatt\Controller;

use Zend\Mvc\Controller\Plugin\FlashMessenger;
use Zend\View\Model\ViewModel;
use JTranslate\Controller\Plugin\NowMessenger;
use Schoenstatt\Model\SchoenstattTable;
use Schoenstatt\Form\SearchForm;
use Schoenstatt\Form\AssignmentForm;
use Zend\Json\Json;
use SionModel\Controller\SionController;

class AssignmentsController extends SionController
{
    public function __construct()
    {
        parent::__construct('assignment');
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
        $entities = null;
        if ($form->isValid()) {
            $data = $form->getData();
            if (!empty($data)) {
                /** @var SchoenstattTable $table */
                $table = $sm->get('Schoenstatt\Model\SchoenstattTable');
                $entities = $table->searchEntities($data);
            }
        }
        if (is_array($entities) && empty($entities)) {
            $this->nowMessenger()->addMessage("No results found.", NowMessenger::NAMESPACE_INFO);
        }
        return new ViewModel([
            'entities'       => $entities,
            'form'          => $form,
        ]);
    }

    public function showAction()
    {
        return $this->redirect()->toRoute('assignments');
        $id = (Int)$this->params()->fromRoute('assignment_id');
        //var_dump($id);
        if (!$id) {
            $this->flashMessenger()
            ->setNamespace(FlashMessenger::NAMESPACE_ERROR)
            ->addMessage('Assignment not found.');
            return $this->redirect()->toRoute('home');
        }
        $sm = $this->getServiceLocator();
        /** @var \Schoenstatt\Model\SchoenstattTable $table */
        $table = $sm->get('Schoenstatt\Model\SchoenstattTable');
        $person = $table->getAssignment($id);
        if (!$person) {
            $this->flashMessenger()
            ->setNamespace(FlashMessenger::NAMESPACE_ERROR)
            ->addMessage('Assignment not found.');
            return $this->redirect()->toRoute('home');
        }

//         $table->registerVisit(SchoenstattTable::ENTITY_PERSON, $person['personId']);
        return new ViewModel([
            'person'        => $person,
//             'suggestForm'   => $sm->get('Schoenstatt\Form\SuggestForm'),
        ]);
    }

    public function createAction()
    {
        $view = parent::createAction();

        /** @var SchoenstattTable $table **/
        $table = $this->getSionTable();
        $roleTitleValueOptions = $table->getJavascriptRoleTitleValueOptions();
        $rolesJson = Json::encode($roleTitleValueOptions);
        $view->setVariable('rolesJson', $rolesJson);

        if ($this->getRequest()->isGet()) {
            $queryRoleId = $this->params()->fromQuery('roleId');
            if (!is_null($queryRoleId)) {
                //verify the query param
                $queryAssociationId = null;
                $queryAssociationRoles = null;
                foreach ($roleTitleValueOptions as $associationId => $roles) {
                    if (key_exists($queryRoleId, $roles)) { //we found our role
                        $queryAssociationId = $associationId;
                        $queryAssociationRoles = $roles;
                        break;
                    }
                }
                //if it's valid, fill in the form
                if (!is_null($queryAssociationId)) {
                    $form = $view->getVariable('form');
                    if (!$form->get('associationId')->getValue()) {
                        $form->get('associationId')->setValue($queryAssociationId);
                        $form->get('roleId')->setValueOptions($queryAssociationRoles);
                        $form->get('roleId')->setValue($queryRoleId);
                        $view->setVariable('form', $form);
                    }
                }
            }
        }

        return $view;
    }

    /**
     * @todo Doesn't work yet!
     */
    public function editAction()
    {
        $id = (Int)$this->params()->fromRoute('assignment_id');
        if (!$id) {
            $this->flashMessenger()
            ->setNamespace(FlashMessenger::NAMESPACE_ERROR)
            ->addMessage('Assignment not found.');
            return $this->redirect()->toRoute('assignments');
        }
        $sm = $this->getServiceLocator();
        /** @var \Schoenstatt\Model\SchoenstattTable $table */
        $table = $sm->get('Schoenstatt\Model\SchoenstattTable');
        $entity = $table->getAssignment($id);
        if (!$entity) {
            $this->flashMessenger()
            ->setNamespace(FlashMessenger::NAMESPACE_ERROR)
            ->addMessage('Assignment not found.');
            return $this->redirect()->toRoute('assignments');
        }

        /** @var AssignmentForm $form */
        $form = $sm->get('Schoenstatt\Form\AssignmentForm');
        $form->get('associationId')->setAttribute('disabled', true);
        $form->get('personId')->setAttribute('disabled', true);
        $availableRoles = $table->getJavascriptRoleTitleValueOptions()[$entity['associationId']];
        $form->get('roleId')->setValueOptions($availableRoles);
        $request = $this->getRequest();
        if ($request->isPost ()) {
            $data = $request->getPost ()->toArray ();
            $form->setValidationGroup('assignmentId', 'roleId', 'startDate', 'endDate', 'security');
            $form->setData($data);
            if ($data['assignmentId'] != $id) { // make sure the user is trying to update the right event
                $this->flashMessenger()
                ->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                ->addMessage('Assignment not found.');
                return $this->redirect()->toRoute('assignments');
            }
            if ($form->isValid()) {
                $data = $form->getData();
                $result = $table->updateEntity('assignment', $id, $data);
                $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_SUCCESS )->addMessage ( 'Assignment successfully updated.' );
                $this->redirect()->toRoute ( 'assignments');///assignment', ['assignment_id' => $id] );
            } else {
                $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
            }
        } else {
            $form->setData($entity);
        }
        return array (
            'form' => $form,
            'entity' => $entity,
        );
    }
}
