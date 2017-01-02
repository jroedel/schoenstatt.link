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
use Schoenstatt\Form\AssociationForm;

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
        $entities = $table->getAssignments(true);
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
//         var_dump($person['urls']);
//         var_dump($person);
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

        /** @var AssociationForm $form */
        $form = $sm->get('Schoenstatt\Form\AssociationForm');
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
        );
    }

    public function editAction()
    {
        $id = (Int)$this->params()->fromRoute('person_id');
        $entity = $this->params()->fromRoute('entity');
        $routeName = $this->getEvent()->getRouteMatch()->getMatchedRouteName();
        if (!$id) {
            $this->flashMessenger()
            ->setNamespace(FlashMessenger::NAMESPACE_ERROR)
            ->addMessage('Person not found.');
            return $this->redirect()->toRoute('home');
        }
        $sm = $this->getServiceLocator();
        /** @var \Schoenstatt\Model\SchoenstattTable $table */
        $table = $sm->get('Schoenstatt\Model\SchoenstattTable');
        $person = $table->getPerson($id);
        if (!$person) {
            $this->flashMessenger()
            ->setNamespace(FlashMessenger::NAMESPACE_ERROR)
            ->addMessage('Person not found.');
            return $this->redirect()->toRoute('home');
        }

        /** @var PersonForm $form */
        $form = $sm->get('Schoenstatt\Form\PersonForm');
        $request = $this->getRequest();
        if ($request->isPost ()) {
            $data = $request->getPost ()->toArray ();
            $form->setData($data);
            if ($data ['personId'] != $id) { // make sure the user is trying to update the right event
                $this->flashMessenger()
                    ->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                    ->addMessage('Person not found.');
                return $this->redirect()->toRoute('home');
            }
            if ($form->isValid()) {
                $data = $form->getData();
                $result = $table->updateEntity('person', $id, $data);
                $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_SUCCESS )->addMessage ( 'Person successfully updated.' );
                $this->redirect()->toRoute ( 'persons/person', array('person_id' => $id) );
            } else {
                $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
            }
        } else {
            $form->setData($person);
        }
        return array (
            'action' => $routeName,
            'form' => $form,
            'person' => $person,
            'personId' => $person['personId'],
            'personName' => $person['fullName'],
            'entity' => $entity,
        );
    }

    public function suggestContactInfoAction()
    {
        $id = ( int ) $this->params ()->fromRoute ( 'person_id' );
        $sm = $this->getServiceLocator ();
        /** @var SchoenstattTable $table **/
        $table = $sm->get ( 'Schoenstatt\Model\SchoenstattTable' );

        if (! $id) {
            $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )->addMessage ( 'Course not found.' );
            return $this->redirect ()->toRoute ( 'home');
        }
        $person = $table->getPerson( $id );
        if (! $person) {
            $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )->addMessage ( 'Person not found.' );
            return $this->redirect ()->toRoute ( 'home');
        }
        /** @var PersonForm $form **/
        $form = $sm->get('Schoenstatt\Form\PersonForm');
        $form->prepareForSuggestion($sm);
        $request = $this->getRequest();
        if ($request->isPost ()) {
            $data = $request->getPost ()->toArray ();
            $form->setValidationGroup($this->entityFieldMap['suggestContactInfo']['validationFields']);
            $form->setData($data);
            if ($data ['personId'] != $id) { // make sure the user is trying to update the right event
                $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
                return [
                    'person' => $person,
                    'form' => $form,
                ];
            }
            if ($form->isValid()) {
                $data = $form->getData();
                try {
                    $result = $table->suggestEntity('person', $id, $data);
                    $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_SUCCESS )->addMessage ( 'Thanks for the suggestions! You will receive an email upon response.' );
                    //send an email to admin
                    $suggestion = $table->getLastSuggestion();
                    /** @var Mailer $mailer **/
                    $mailer = $sm->get('Schoenstatt\Mailing\Mailer');
                    $mailer->sendNewSuggestionNotice($suggestion);
                    $this->redirect()->toRoute ( 'persons/person', ['person_id' => $id] );
                } catch (\Exception $e) {
                    //@todo this message should be logged
                    $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.');
                }
            } else {
                $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
            }
        } else {
            $form->setData($person);
        }
        return [
            'person' => $person,
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
        $person = $table->getSuggestionData( $suggestionId, $oldData); //$oldData is a byRef return
        $id = $person['personId'];
        if (! $person) {
            $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )->addMessage ( 'Person not found.' );
            return $this->redirect ()->toRoute ( 'home' );
        }

        /** @var \Schoenstatt\Form\EditCourseForm $form **/
        $form = $sm->get('Schoenstatt\Form\PersonForm');
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
                $form->setValidationGroup($this->entityFieldMap['moderateContactInfo']['validationFields']);
                $form->setData($data);
                if ($form->isValid()) {
                    $data = $form->getData();
                    try {
                        $result = $table->updateEntity('person', $id, $data);
                        $table->updateSuggestion($data);

                        //send an email to user
                        /** @var Mailer $mailer **/
                        $mailer = $sm->get('Schoenstatt\Mailing\Mailer');
                        $suggestion = $table->getSuggestion($data['suggestionId']);
                        $mailer->sendReviewedSuggestionNotice($suggestion);

                        $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_SUCCESS )->addMessage ( 'Person successfully updated.' );
                        $this->redirect()->toRoute ( 'admin/moderate');
                    } catch (\Exception $e) {
                        //@todo this message should be logged
//                         var_dump($e);
//                         var_dump($form);
                        $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
                    }
                } else {
//                         var_dump($form);
                    $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
                }
            }
        } else {
            $form->setData($person);
        }

        $view = new ViewModel([
            'action' => 'persons/person/moderate',
            'entity' => 'contactInfo',
            'person' => $person,
            'personId' => $person['personId'],
            'personName' => $person['fullName'],
            'form' => $form,
        ]);
        $view->setTemplate('schoenstatt/persons/edit');
        return $view;
    }
}
