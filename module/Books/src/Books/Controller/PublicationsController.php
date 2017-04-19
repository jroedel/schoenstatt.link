<?php
namespace Books\Controller;

use Zend\Mvc\Controller\Plugin\FlashMessenger;
use Zend\View\Model\ViewModel;
use Books\Form\PublicationForm;
use SionModel\Mailing\Mailer;
use JTranslate\Controller\Plugin\NowMessenger;
use Books\Model\PublicationsTable;
use SionModel\Controller\SionController;
use Books\Form\PublicationsSearchForm;

class PublicationsController extends SionController
{
    const MAX_SEARCH_RESULTS = 300;

    public function __construct()
    {
        parent::__construct('publication');
    }

    public function indexAction()
    {
        $view = parent::indexAction();
        $form = $this->getServiceLocator()->get('Books\Form\PublicationsSearchForm');
        $view->setVariable('form', $form);
        return $view;
    }

    public function searchAction()
    {
        $params = $this->params()->fromQuery();
        $form = $this->getServiceLocator()->get('Books\Form\PublicationsSearchForm');
        $form->setData($params);
        $entities = null;

        if ($form->isValid()) {
            $notAllowed = [];
//             if (!$this->isAllowed('book_teo', 'read')) {
//                 $notAllowed[] = '\'DigitaSión Teo\'';
//                 $notAllowed[] = '\'DigitaSión Fil\'';
//             }
//             if (!$this->isAllowed('book_sch', 'read')) {
//                 $notAllowed[] = '\'DigitaSión Sch\'';
//             }
            $data = $form->getData();

            if (!empty($data)) {
                $sm = $this->getServiceLocator();
                /** @var PublicationsTable $table */
                $table = $sm->get('Books\Model\PublicationsTable');
//                 $data['notAllowed'] = $notAllowed;
                $data['maxResults'] = self::MAX_SEARCH_RESULTS;
                $entities = $table->searchPublications($data);
                if (is_array($entities) && count($entities) == self::MAX_SEARCH_RESULTS) {
                    $this->nowMessenger()->addMessage("More than the max number of publications match your search. Only the first 300 results shown.", NowMessenger::NAMESPACE_INFO);
                }
            }
        }

        if (is_array($entities) && empty($entities)) {
            $this->nowMessenger()->addMessage("No results found.", NowMessenger::NAMESPACE_INFO);
        }

        return new ViewModel([
            'entities'  => $entities,
            'form'      => $form,
        ]);
    }

    /**
     * Import records from the old tables into the new publications table.
     * First, the new records will be simulated and presented to the user
     * If the user accepts, and POSTs the order to import, they will be
     * imported.
     *
     */
    public function importAction()
    {
        /** @var PublicationsTable $table */
        $table = $this->getSionTable();
        $entities = $table->importPublications();//false);
        $view = new ViewModel([
            'entities' => $entities,
        ]);
        $view->setTemplate('books/publications/index');
        return $view;
    }

    public function suggestAction()
    {
        $id = ( int ) $this->params ()->fromRoute ( 'publication_id' );
        $sm = $this->getServiceLocator ();
        /** @var PublicationsTable $table **/
        $table = $sm->get ( 'Books\Model\PublicationsTable' );

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
        /** @var PublicationsTable $table **/
        $table = $sm->get ( 'Books\Model\PublicationsTable' );

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
