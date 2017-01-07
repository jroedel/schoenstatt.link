<?php
namespace Books\Controller;

use Zend\Mvc\Controller\Plugin\FlashMessenger;
use Zend\View\Model\ViewModel;
use Zend\Mvc\Controller\AbstractActionController;
use Books\Form\PublicationForm;
use SionModel\Mailing\Mailer;
use JTranslate\Controller\Plugin\NowMessenger;
use Books\Model\BooksTable;

class PublicationsController extends AbstractActionController
{
    public function indexAction()
    {
        $sm = $this->getServiceLocator();
        /** @var \Books\Model\BooksTable $table */
        $table = $sm->get('Books\Model\BooksTable');

        $entities = $table->importPublications();
        return new ViewModel([
            'entities' => $entities,
        ]);
    }

    public function showAction()
    {
        $id = (Int)$this->params()->fromRoute('publication_id');
        if (!$id) {
            $this->flashMessenger()
            ->setNamespace(FlashMessenger::NAMESPACE_ERROR)
            ->addMessage('Publication not found.');
            return $this->redirect()->toRoute('home');
        }
        $sm = $this->getServiceLocator();
        /** @var \Books\Model\BooksTable $table */
        $table = $sm->get('Books\Model\BooksTable');
        $publication = $table->getPublication($id);
        if (!$publication) {
            $this->flashMessenger()
            ->setNamespace(FlashMessenger::NAMESPACE_ERROR)
            ->addMessage('Publication not found.');
            return $this->redirect()->toRoute('home');
        }

//         $table->registerVisit(publication, $publication['publicationId']);
        return new ViewModel([
            'publication'        => $publication,
//             'suggestForm'   => $sm->get('Books\Form\SuggestForm'),
        ]);
    }

    public function createAction()
    {
        $sm = $this->getServiceLocator ();
        /** @var BooksTable $table **/
        $table = $sm->get ( 'Books\Model\BooksTable' );

        /** @var PublicationForm $form */
        $form = $sm->get('Books\Form\PublicationForm');
        $request = $this->getRequest();
        if ($request->isPost ()) {
            $data = $request->getPost ()->toArray ();
            $form->setData($data);
            if ($form->isValid()) {
                $data = $form->getData();
                if (!($newId = $table->createEntity('publication', $data))) {
                    $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
                } else {
                    $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_SUCCESS )->addMessage ( 'Publication successfully created.' );
                    $this->redirect ()->toRoute ( 'publications/publication', array('publication_id' => $newId) );
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
        $id = (Int)$this->params()->fromRoute('publication_id');
        if (!$id) {
            $this->flashMessenger()
            ->setNamespace(FlashMessenger::NAMESPACE_ERROR)
            ->addMessage('Publication not found.');
            return $this->redirect()->toRoute('home');
        }
        $sm = $this->getServiceLocator();
        /** @var \Books\Model\BooksTable $table */
        $table = $sm->get('Books\Model\BooksTable');
        $publication = $table->getPublication($id);
        if (!$publication) {
            $this->flashMessenger()
            ->setNamespace(FlashMessenger::NAMESPACE_ERROR)
            ->addMessage('Publication not found.');
            return $this->redirect()->toRoute('home');
        }

        /** @var PublicationForm $form */
        $form = $sm->get('Books\Form\PublicationForm');
        $request = $this->getRequest();
        if ($request->isPost ()) {
            $data = $request->getPost ()->toArray ();
            $form->setData($data);
            if ($data ['publicationId'] != $id) { // make sure the user is trying to update the right event
                $this->flashMessenger()
                    ->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                    ->addMessage('Publication not found.');
                return $this->redirect()->toRoute('home');
            }
            if ($form->isValid()) {
                $data = $form->getData();
                $result = $table->updateEntity('publication', $id, $data);
                $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_SUCCESS )->addMessage ( 'Publication successfully updated.' );
                $this->redirect()->toRoute ( 'publications/publication', array('publication_id' => $id) );
            } else {
                $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
            }
        } else {
            $form->setData($publication);
        }
        return array (
            'form' => $form,
            'entity' => $entity,
        );
    }

    public function suggestAction()
    {
        $id = ( int ) $this->params ()->fromRoute ( 'publication_id' );
        $sm = $this->getServiceLocator ();
        /** @var BooksTable $table **/
        $table = $sm->get ( 'Books\Model\BooksTable' );

        if (! $id) {
            $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )->addMessage ( 'Publication not found.' );
            return $this->redirect ()->toRoute ( 'home');
        }
        $publication = $table->getPublication( $id );
        if (! $publication) {
            $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )->addMessage ( 'Publication not found.' );
            return $this->redirect ()->toRoute ( 'home');
        }
        /** @var PublicationForm $form **/
        $form = $sm->get('Books\Form\PublicationForm');
        $form->prepareForSuggestion($sm);
        $request = $this->getRequest();
        if ($request->isPost ()) {
            $data = $request->getPost ()->toArray ();
            $form->setData($data);
            if ($data ['publicationId'] != $id) { // make sure the user is trying to update the right event
                $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
                return [
                    'publication' => $publication,
                    'form' => $form,
                ];
            }
            if ($form->isValid()) {
                $data = $form->getData();
                $result = $table->suggestEntity('publication', $id, $data);
                $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_SUCCESS )->addMessage ( 'Thanks for the suggestions! You will receive an email upon response.' );
                //send an email to admin
                $suggestion = $table->getLastSuggestion();
                /** @var Mailer $mailer **/
                $mailer = $sm->get('Books\Mailing\Mailer');
                $mailer->sendNewSuggestionNotice($suggestion);
                $this->redirect()->toRoute ( 'publications/publication', ['publication_id' => $id] );
            } else {
                $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
            }
        } else {
            $form->setData($publication);
        }
        return [
            'entity' => $publication,
            'form' => $form,
        ];
    }

    public function moderateAction()
    {
        $suggestionId = ( int ) $this->params ()->fromRoute ( 'suggestion_id' );

        $sm = $this->getServiceLocator();
        /** @var BooksTable $table **/
        $table = $sm->get ( 'Books\Model\BooksTable' );

        $oldData = null;
        $publication = $table->getSuggestionData( $suggestionId, $oldData); //$oldData is a byRef return
        $id = $publication['publicationId'];
        if (! $publication) {
            $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_ERROR )->addMessage ( 'Publication not found.' );
            return $this->redirect ()->toRoute ( 'home' );
        }

        /** @var \Books\Form\PublicationForm $form **/
        $form = $sm->get('Books\Form\PublicationForm');
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
                    $result = $table->updateEntity('publication', $id, $data);
                    $table->updateSuggestion($data);

                    //send an email to user
                    /** @var Mailer $mailer **/
                    $mailer = $sm->get('SionModel\Mailing\Mailer');
                    $suggestion = $table->getSuggestion($data['suggestionId']);
                    $mailer->sendReviewedSuggestionNotice($suggestion);

                    $this->flashMessenger ()->setNamespace ( FlashMessenger::NAMESPACE_SUCCESS )->addMessage ( 'Publication successfully updated.' );
                    $this->redirect()->toRoute ( 'admin/moderate');
                } else {
                    $this->nowMessenger ()->setNamespace ( NowMessenger::NAMESPACE_ERROR )->addMessage ( 'Error in form submission, please review.' );
                }
            }
        } else {
            $form->setData($publication);
        }

        return new ViewModel([
            'entity' => $publication,
            'form' => $form,
        ]);
    }
}
