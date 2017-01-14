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
use Zend\Mvc\Controller\AbstractActionController;
use Schoenstatt\Form\PersonForm;
use Patres\Mailing\Mailer;
use JTranslate\Controller\Plugin\NowMessenger;
use Schoenstatt\Model\SchoenstattTable;
use Schoenstatt\Form\SearchForm;
use Schoenstatt\Form\AssignmentForm;
use Zend\Json\Json;

class AssignmentsController extends AbstractActionController
{
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
        /** @var SchoenstattTable $table */
        $table = $sm->get('Schoenstatt\Model\SchoenstattTable');
        $entities = $table->getAssignmentPersonAssociations(true);
        if ($form->isValid()) {
            $data = $form->getData();
            if (!empty($data)) {
                /** @var SchoenstattTable $table */
//                 $table = $sm->get('Schoenstatt\Model\SchoenstattTable');
//                 $persons = $table->searchPersons($data);
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
        $sm = $this->getServiceLocator ();
        /** @var SchoenstattTable $table **/
        $table = $sm->get ( 'Schoenstatt\Model\SchoenstattTable' );
        $rolesJson = Json::encode($table->getJavascriptRoleTitleValueOptions());
        $form = $sm->get('Schoenstatt\Form\AssignmentForm');
        $request = $this->getRequest();
        if ($request->isPost ()) {
            $data = $request->getPost ()->toArray ();
            $form->setData($data);
            if ($form->isValid()) {
                $data = $form->getData();
                if (!($newId = $table->createEntity('assignment', $data))) {
                    $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
                } else {
                    $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_SUCCESS )->addMessage ( 'Assignment successfully created.' );
                    $this->redirect ()->toRoute ( 'assignments/assignment', array('assignment_id' => $newId) );
                }
            } else {
//                 print_r(array_keys($form->getInputFilter()->getInvalidInput()));
                $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
            }
        }
        return array (
            'form' => $form,
            'rolesJson' => $rolesJson,
        );
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
                $this->redirect()->toRoute ( 'assignments');///assignment', array('assignment_id' => $id) );
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

    public function suggestAction()
    {
        $id = ( int ) $this->params ()->fromRoute ( 'assignment_id' );
        $sm = $this->getServiceLocator ();
        /** @var SchoenstattTable $table **/
        $table = $sm->get ( 'Schoenstatt\Model\SchoenstattTable' );

        if (! $id) {
            $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )->addMessage ( 'Assignment not found.' );
            return $this->redirect ()->toRoute ( 'assignments');
        }
        $assignment = $table->getAssignment( $id );
        if (! $assignment) {
            $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )->addMessage ( 'Assignment not found.' );
            return $this->redirect ()->toRoute ( 'assignments');
        }
        /** @var AssignmentForm $form **/
        $form = $sm->get('Schoenstatt\Form\AssignmentForm');
        $form->prepareForSuggestion($sm);
        $request = $this->getRequest();
        if ($request->isPost ()) {
            $data = $request->getPost ()->toArray ();
            $form->setData($data);
            if ($data ['assignmentId'] != $id) { // make sure the user is trying to update the right event
                $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
                return [
                    'assignment' => $assignment,
                    'form' => $form,
                ];
            }
            if ($form->isValid()) {
                $data = $form->getData();
                $result = $table->suggestEntity('assignment', $id, $data);
                $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_SUCCESS )->addMessage ( 'Thanks for the suggestions! You will receive an email upon response.' );
                //send an email to admin
                $suggestion = $table->getLastSuggestion();
                /** @var Mailer $mailer **/
                $mailer = $sm->get('Schoenstatt\Mailing\Mailer');
                $mailer->sendNewSuggestionNotice($suggestion);
                $this->redirect()->toRoute ( 'assignments/assignment', ['assignment_id' => $id] );
            } else {
                $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
            }
        } else {
            $form->setData($assignment);
        }
        return [
            'entity' => $assignment,
            'form' => $form,
        ];
    }

    public function moderateAction()
    {
        $suggestionId = ( int ) $this->params ()->fromRoute ( 'suggestion_id' );

        $sm = $this->getServiceLocator();
        /** @var SchoenstattTable $table **/
        $table = $sm->get ( 'Schoenstatt\Model\SchoenstattTable' );

        $oldData = null;
        $assignment = $table->getSuggestionData( $suggestionId, $oldData); //$oldData is a byRef return
        $id = $assignment['assignmentId'];
        if (! $assignment) {
            $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )->addMessage ( 'Assignment not found.' );
            return $this->redirect ()->toRoute ( 'assignments' );
        }

        /** @var \Schoenstatt\Form\AssignmentForm $form **/
        $form = $sm->get('Schoenstatt\Form\AssignmentForm');
        $form->prepareForModeration($oldData);
        $request = $this->getRequest();
        if ($request->isPost ()) {
            $data = $request->getPost ()->toArray ();
            $data['suggestionId'] = $suggestionId;
            if (isset($data['deny'])) { //don't worry about validating the form. We're just throwing it out anyways
                $updateData = array(
                    'suggestionId' => $suggestionId,
                    'deny' => true,
                    'suggestionResponse' => $data['suggestionResponse'] != '' ? $data['suggestionResponse'] : null //@todo validate this
                );
                $table->updateSuggestion($updateData);

                $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_SUCCESS )->addMessage ( 'Suggestion denied.' );
                $this->redirect()->toRoute ( 'admin/moderate');
            } else {
                $form->setData($data);
                if ($form->isValid()) {
                    $data = $form->getData();
                    $result = $table->updateEntity('assignment', $id, $data);
                    $table->updateSuggestion($data);

                    //send an email to user
                    /** @var Mailer $mailer **/
                    $mailer = $sm->get('SionModel\Mailing\Mailer');
                    $suggestion = $table->getSuggestion($data['suggestionId']);
                    $mailer->sendReviewedSuggestionNotice($suggestion);

                    $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_SUCCESS )->addMessage ( 'Assignment successfully updated.' );
                    $this->redirect()->toRoute ( 'admin/moderate');
                } else {
                    $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
                }
            }
        } else {
            $form->setData($assignment);
        }

        return new ViewModel([
            'entity' => $assignment,
            'form' => $form,
        ]);
    }
}
