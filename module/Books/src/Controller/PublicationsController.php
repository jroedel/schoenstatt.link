<?php
namespace Books\Controller;

use Zend\View\Model\ViewModel;
use JTranslate\Controller\Plugin\NowMessenger;
use Books\Model\PublicationsTable;
use SionModel\Controller\SionController;
use Books\Form\PublicationsSearchForm;
use SionModel\Db\Model\FilesTable;
use Books\Form\UploadForm;
use Zend\Form\Element\Select;
use Books\Service\DriveGateway;
use Books\Model\LibraryTable;
use SionModel\Db\Model\PredicatesTable;

class PublicationsController extends SionController
{
    const MAX_SEARCH_RESULTS = 1000;

    public function showAction()
    {
        $view = parent::showAction();
        $entityObject = $view->getVariable('entity');
        if (isset($entityObject['bookCoverFileId'])) {
            $bookCoverFileId = $entityObject['bookCoverFileId'];
            /** @var FilesTable $filesTable */
            $filesTable = $this->services[FilesTable::class];
            $entityObject['bookCoverFile'] = $filesTable->getFile($bookCoverFileId);
            $view->setVariable('entity', $entityObject);
        }
        /** @var DriveGateway $gateway */
        $gateway = $this->services[DriveGateway::class];
        $gateway->getCache()->flush();
        $publicationFiles = null;
        try {
            $publicationFiles = $this->isAllowed('publication_drive') ? $gateway->getPublicationFiles() : null;
        } catch (\Exception $e) {
        }
        if (isset($publicationFiles)) {
            if (isset($publicationFiles[$entityObject['publicationId']])) {
                $entityObject['files'] = $publicationFiles[$entityObject['publicationId']];
            }
            if (isset($entityObject['subEditions']) && is_array($entityObject['subEditions'])) {
                foreach ($entityObject['subEditions'] as $key => $value) {
                    if (isset($publicationFiles[$key])) {
                        $entityObject['files'] = array_merge($entityObject['files'], $publicationFiles[$key]);
                    }
                }
            }
            $view->setVariable('entity', $entityObject);
        }

        //get library results
        /** @var LibraryTable $libraryTable */
        $libraryTable = $this->services[LibraryTable::class];
        $libraries = $libraryTable->getUnlinkedLibraries();
        //check which libraries the user has access to
        foreach ($libraries as $libraryId => $library) {
            if (!$this->isAllowed($library['resourceId'], 'show')) {
                unset($libraries[$libraryId]);
            }
        }
        //gather the publicationIds to search for
        $publicationIds = [$entityObject['publicationId']];
        if (isset($entityObject['subEditions']) && is_array($entityObject['subEditions']) && !empty($entityObject['subEditions'])) {
            $publicationIds = array_merge($publicationIds, $entityObject['subEditions']);
        }
        //search the viewable libraries for pubId's related to this item (including subeditions)
        $libraryBooks = $libraryTable->searchBooks(['libraryId' => array_keys($libraries), 'publicationId' => $publicationIds]);
        foreach ($libraryBooks as $bookId => $book) {
            if (isset($libraries[$book['libraryId']])) {
                $libraryBooks[$bookId]['library'] = &$libraries[$book['libraryId']];
            }
        }
        $view->setVariable('libraryBooks', $libraryBooks);
        $view->setVariable('libraries', $libraries);

        //get comments
        /** @var PredicatesTable $predicates */
//         $predicates = $this->services[PredicatesTable::class];
//         $comments = $predicates->getCommentsForEntity([
//             'objectId' => $publicationIds,
//             'predicate'=> 'comment-publication'
//         ]);

//         $view->setVariable('comments', $comments);

        return $view;
    }

    public function languageIndexAction()
    {
        /** @var PublicationsTable $table */
        $table      = $this->getSionTable();
        $entity     = $this->getEntity();
        $entitySpec = $this->getEntitySpecification();
        $objects    = $table->getPublicationLanguageCounts(
            $this->isAllowed('publication_user', 'show'),
            $this->isAllowed('publication_institute', 'show'),
            $this->isAllowed('publication_patres', 'show')
        );
        $form = $this->services[PublicationsSearchForm::class];
        $languages = $this->services['Books\LanguagesValueOptions'];
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
        $languages  = $this->services['Books\LanguagesValueOptions'];
        $objects    = $table->searchPublications(['inLanguage' => $language], ['noSubEditions' => true]);
        $objects    = $this->groupPublicationsByCategory($objects);

        $form = $this->services[PublicationsSearchForm::class];
        /** @var DriveGateway $gateway */
        $gateway = $this->services[DriveGateway::class];
        $publicationFiles = null;
        try {
            $publicationFiles = $this->isAllowed('publication_drive') ? $gateway->getPublicationFiles() : null;
        } catch (\Exception $e) {
        }

        $view = new ViewModel([
            'form'      => $form,
            'language'  => $language,
            'languages' => $languages,
            'entity'    => $entity,
            'entitySpec'=> $entitySpec,
            'objects'   => $objects,
            'files'     => $publicationFiles,
        ]);
        return $view;
    }

    protected function groupPublicationsByCategory($objects)
    {
        //keyed by the category name, if we did the Id's it might re-sort our array
        //WARNING, this won't work if child categories aren't sorted properly directly after their parents
        $categories = [];
        $noCategoryObjects = [];
        foreach ($objects as $pubId => $object) {
            if (isset($object['categoryName'])) {
                if (!isset($categories[$object['categoryName']])) { //add this group array key
                    $bookmark = preg_replace("/\s+/", "-", strtolower(trim($object['categoryName'])));
                    $bookmark = preg_replace("/[^a-z-]+/", "", $bookmark);
                    $bookmark = trim($bookmark, '- ');
                    $categories[$object['categoryName']] = [
                        'id' => $object['categoryId'],
                        'name' => $object['categoryName'],
                        'sort' => $object['categorySort'],
                        'parentId' => $object['categoryParentId'],
                        'bookmark' => $bookmark,
                        'objects' => [],
                    ];
                }
                $categories[$object['categoryName']]['objects'][$pubId] = $object;
            } else {
                $noCategoryObjects[$pubId] = $object;
            }
        }
        $categories['Uncategorized'] = [
            'id' => null,
            'name' => 'Uncategorized',
            'sort' => 1000,
            'parentId' => null,
            'bookmark' => 'uncategorized',
            'objects' => $noCategoryObjects,
        ];
        return $categories;
    }

    /**
     * Remove the publication's self from the mainPublicationId select
     * {@inheritDoc}
     * @see \SionModel\Controller\SionController::editAction()
     */
    public function editAction()
    {
        $view = parent::editAction();
        $entityId = $view->getVariable('entityId');
        $this->injectPublicationValueOptions($view, $entityId);
        return $view;
    }

    public function createAction()
    {
        $view = parent::createAction();
        $entityId = $view->getVariable('entityId');
        $this->injectPublicationValueOptions($view, $entityId);
        return $view;
    }

    protected function injectPublicationValueOptions(ViewModel &$view, $publicationId)
    {
        $form = $view->getVariable('form');
        /** @var Select $mainPublicationId */
        $mainPublicationId = $form->get('mainPublicationId');
        $valueOptions = $mainPublicationId->getValueOptions();
        if (isset($publicationId) && isset($valueOptions[$publicationId])) { //unset the publication's own id
            unset($valueOptions[$publicationId]);
        }
        $valueOptions = $this->transformValueOptionsObject($valueOptions);

        //we pass the valueOptions directly to selectize to reduce file size, but keep them set for validation
        $view->setVariable('publicationValueOptions', $valueOptions);

        $translatorsAll = $form->get('translatorsAll');

        $authorPersons = $translatorsAll->getValueOptions();
        /** @var PublicationsTable $table */
        $table = $this->getSionTable();
        $authorAssociations = $table->getAuthorAssociationValueOptions();

        $authorPersons = $this->transformValueOptionsObject($authorPersons);
        $authorAssociations = $this->transformValueOptionsObject($authorAssociations);
        $view->setVariable('authorPersons', $authorPersons);
        $view->setVariable('authorAssociations', $authorAssociations);
    }

    protected function transformValueOptionsObject($associativeOptions)
    {
        $valueOptions = [];
        foreach ($associativeOptions as $key => $value) {
            $valueOptions[] = [
                'i' => $key,
                'n' => $value,
            ];
        }
        return $valueOptions;
    }

    public function searchAction()
    {
        $params = $this->params()->fromQuery();
        $form = $this->services[PublicationsSearchForm::class];
        $form->setData($params);
        $entities = null;

        if ($form->isValid()) {
            $data = $form->getData();

            if (!empty($data)) {
                /** @var PublicationsTable $table */
                $table = $this->getSionTable();
                $options = [];
                $options['maxResults'] = self::MAX_SEARCH_RESULTS;
                if (!isset($data['showEditionsSeparately']) || $data['showEditionsSeparately'] != '1') {
                    $options['noSubEditions'] = true;
                }
                //@todo modify searchPublications call to include options with noSubEditions
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

    public function exportAction()
    {
        $view = parent::indexAction();
        return $view;
    }

    public function createNewEditionAction()
    {
        /** @var PublicationsTable $table */
        $table      = $this->getSionTable();
        $entity     = $this->getEntity();
        $entitySpec = $this->getEntitySpecification();
        $id         = (Int)$this->getEntityIdParam('show');
        $object     = $this->getEntityObject($id);

        //set the new mainPublicationId to the old publicationId
        if (!isset($object['mainPublicationId'])) { //else, leave it as it was
            $object['mainPublicationId'] = $object['publicationId'];
        }

        //unset edition-specific fields, and create new publication
        unset($object['publicationId']);
        unset($object['bookEdition']);
        unset($object['numberOfPages']);
        unset($object['copyrightYear']);
        unset($object['datePublished']);
        unset($object['publishingStatus']);
        unset($object['isbn']);
        unset($object['hasNoISBN']);
        unset($object['hasNoExplictEditionNumber']);
        unset($object['editionNotes']);
        unset($object['isAwaitingMerge']);
        unset($object['isRevisedWithBookInHand']);
        unset($object['publishDataAsJsonLd']);
        unset($object['isFormallyPublished']);
        unset($object['url1']);
        unset($object['url1Label']);
        unset($object['url2']);
        unset($object['url2Label']);
        unset($object['url3']);
        unset($object['url3Label']);
        unset($object['dataSource']);
        unset($object['dataSourceId']);
        unset($object['dataSourceUpdatedOn']);
        unset($object['createdOn']);
        unset($object['createdBy']);
        unset($object['updatedOn']);
        unset($object['updatedBy']);

        $newId = $table->createEntity('publication', $object);

        $this->redirect()->toRoute('publications/publication/edit', ['publication_id' => $newId]);
    }

    public function uploadCoverAction()
    {
        $id = $this->getEntityIdParam();
        $form = new UploadForm();
        $request = $this->getRequest();
        if ($request->isPost()) {
            $data = array_merge_recursive(
                $this->getRequest()->getPost()->toArray(),
                $this->getRequest()->getFiles()->toArray()
            );
            $form->setData($data);
            if ($form->isValid()) {
                $data = $form->getData();
//                 var_dump($data);
                /** @var FilesTable $filesTable */
                $filesTable = $this->services[FilesTable::class];
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

    public function trimTitlesAction()
    {
        $simulate = '0' !== $this->params()->fromQuery('simulate', '1');
        /** @var PublicationsTable $table */
        $table = $this->getSionTable();
        $publications = $table->getUnlinkedPublications();
        $changes = [];
        foreach ($publications as $publicationId => $object) {
            $trimmed = trim($object['title'], '. ');
            $howMany = strlen($object['title'])-strlen($trimmed);
            if ($howMany !== 0 && $howMany < 3) {
                $changes[$object['title']] = $trimmed;
                if (!$simulate) {
                    $table->updateEntity('publication', $publicationId, ['title' => $trimmed], [], false);
                }
            }
        }
        return new ViewModel([
            'changes' => $changes,
            'simulate' => $simulate,
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

    public function adminTasksAction()
    {
        /** @var PublicationsTable $table */
        $table = $this->getSionTable();
//         $updates = $table->fillNoAccentsColumns();
        $updates = [];
        $updates2 = $table->fillDatePublished();
        $updates3 = $table->clearCopyrightYear();

        //after performing these updates, delete the DatePublished field in favor of the  DatePublishedText field

        $view = new ViewModel([
            'updates' => $updates,
        ]);
        return $view;
    }
}
