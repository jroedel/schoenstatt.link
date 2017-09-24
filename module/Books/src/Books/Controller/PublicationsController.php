<?php
namespace Books\Controller;

use Zend\View\Model\ViewModel;
use JTranslate\Controller\Plugin\NowMessenger;
use Books\Model\PublicationsTable;
use SionModel\Controller\SionController;
use Books\Form\PublicationsSearchForm;
use SionModel\Db\Model\FilesTable;
use Books\Form\UploadForm;

class PublicationsController extends SionController
{
    const MAX_SEARCH_RESULTS = 1000;

    public function __construct()
    {
        parent::__construct('publication');
    }

    public function showAction()
    {
        $view = parent::showAction();
        $entityObject = $view->getVariable('entity');
        if (!is_null($entityObject['bookCoverFileId'])) {
            $bookCoverFileId = $entityObject['bookCoverFileId'];
            /** @var FilesTable $filesTable */
            $filesTable = $this->getServiceLocator()->get('SionModel\FilesTable');
            $entityObject['bookCoverFile'] = $filesTable->getFile($bookCoverFileId);
            $view->setVariable('entity', $entityObject);
        }
        return $view;
    }

    public function languageIndexAction()
    {
        /** @var PublicationsTable $table */
        $table      = $this->getSionTable();
        $entity     = $this->getEntity();
        $entitySpec = $this->getEntitySpecification();
        $objects    = $table->getPublicationLanguageCounts($this->isAllowed('publication_user', 'show'),
            $this->isAllowed('publication_institute', 'show'), $this->isAllowed('publication_patres', 'show'));
        $form = $this->getServiceLocator()->get('Books\Form\PublicationsSearchForm');
        $languages = $this->getServiceLocator()->get('Books\LanguagesValueOptions');
        $view = new ViewModel([
            'form'      => $form,
            'entity'    => $entity,
            'entitySpec'=> $entitySpec,
            'languages' => $languages,
            'objects'   => $objects,
        ]);
        return $view;
    }

    public function indexAction()
    {
        /** @var PublicationsTable $table */
        $table      = $this->getSionTable();
        $entity     = $this->getEntity();
        $entitySpec = $this->getEntitySpecification();
        $language   = $this->params()->fromRoute('inLanguage');
        $languages  = $this->getServiceLocator()->get('Books\LanguagesValueOptions');
        $objects    = $table->searchPublications(['inLanguage' => $language]);
        $form = $this->getServiceLocator()->get('Books\Form\PublicationsSearchForm');
        $view = new ViewModel([
            'form'      => $form,
            'language'  => $language,
            'languages' => $languages,
            'entity'    => $entity,
            'entitySpec'=> $entitySpec,
            'objects'   => $objects,
        ]);
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

    public function uploadCoverAction()
    {
        $id = $this->getEntityIdParam();
        $form = new UploadForm();
        $request = $this->getRequest();
        if ($request->isPost()) {
            $data = $request->getPost()->toArray();
            $form->setData($data);
            if ($form->isValid()) {
                $data = $form->getData();
                /** @var FilesTable $filesTable */
                $filesTable = $this->getServiceLocator()->get('SionModel\FilesTable');
                if (!$newId = $filesTable->createEntity('file', $data)) {
                    //update the publication record
                    $publicationData = [
                        'bookCoverFileId' => $newId
                    ];
                    $publicationTable = $this->getSionTable();
                    $publicationTable->updateEntity('publication', $id, $publicationData);
                    $this->redirect()->toRoute('publications/publication', ['publication_id' => $id]);
                } else {
                    throw new \Exception('Error uploading file.');
                }
            }
        }
        return new ViewModel([
            'form' => $form,
        ]);
    }

    public function primeAuthorsAction()
    {
        /** @var PublicationsTable $table */
        $table = $this->getSionTable();
        $entities = $table->getAuthors();//false);
        $view = new ViewModel([
            'authors' => $entities,
        ]);
        $view->setTemplate('books/publications/prime-authors');
        return $view;
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
}
