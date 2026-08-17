<?php
namespace Books\Controller;

use Laminas\View\Model\ViewModel;
use JTranslate\Controller\Plugin\NowMessenger;
use SionModel\Controller\SionController;
use Books\Form\CopyToMainCorpusForm;
use Books\Form\PublicationsSearchForm;
use SionModel\Db\Model\FilesTable;
use Books\Form\UploadForm;
use Books\Service\DriveGateway;
use Books\Model\LibraryTable;
use Books\Model\DictionaryTable;
use Schoenstatt\Filter\ToSchoenstattLinkIdentifier;
use Schoenstatt\Validator\SchoenstattLinkIdentifier;
use Laminas\Mvc\Plugin\FlashMessenger\FlashMessenger;

class PublicationsController extends SionController
{
    const MAX_SEARCH_RESULTS = 1000;

    // Seven actions lived here until 2026-08-17: migrateDataSourceStructure and the six
    // /admin/literature-maintenance sweeps. They were the 2020 migration that turned
    // imported ("data-sourced") publication rows into first-class ones, and measuring
    // showed it had finished — the whole remaining output of the five write sweeps was
    // three database references and four publications' cover files. Both were repaired:
    // database/db8.2.sql for the references, and a one-off script for the covers, applied
    // by hand on 2026-08-18 and deleted afterwards (docs/BACKLOG.md has the record). The
    // per-row successor, copyToMainCorpusAction() below, is what merges the 2,333 rows
    // still awaiting one.

    /**
     * Copy a data-sourced publication into the main corpus. **GET confirms, POST copies.**
     *
     * It used to copy on the GET — `copyPublicationToMainCorpus()` is an INSERT, and this
     * action ran it with no method check, no CSRF token and no confirmation step. The
     * route's `pub_moderator` guard kept that away from the public, but nine effective
     * roles hold it and browsers prefetch links a signed-in moderator has only hovered
     * over, so an accidental duplicate publication needed no mistake anybody could see.
     * It is the same shape as the `publications/import` retired on 2026-08-14, except
     * that this one is a feature still in use.
     *
     * Structure follows `SionController::deleteAction()` rather than inventing one: build
     * the form, act only on a valid POST, otherwise render the confirmation. The `action`
     * attribute is the current request URI, so the POST returns here whichever locale
     * prefix the visitor arrived under.
     */
    public function copyToMainCorpusAction()
    {
        $view = parent::showAction();
        if ($view instanceof \Laminas\Stdlib\ResponseInterface) {
            return $view;
        }
        $entityObject = $view->getVariable('entity');

        //`empty()` rather than `! $entityObject['dataSource']`, which warned on a row
        //without the key at all. Same outcome, no diagnostic — and production narrows
        //error_reporting to hide notices, so the warning was invisible rather than absent.
        if (empty($entityObject['dataSource'])) {
            $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
                ->addMessage('Only data-sourced publications can be copied.');
            return $this->redirect()->toRoute(
                'publication',
                ['sw_id' => $entityObject['identifier'], 'slug' => $entityObject['slug']]
            );
        }

        $request = $this->getRequest();
        $form = new CopyToMainCorpusForm();

        if ($request->isPost()) {
            $form->setData($request->getPost());
            if ($form->isValid()) {
                $newId = $this->getSionTable()->copyPublicationToMainCorpus($entityObject['publicationId']);
                $swFilter = new ToSchoenstattLinkIdentifier('publication');
                $newSwId = $swFilter->filter($newId);
                $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_SUCCESS)
                    ->addMessage('Publication copied into main literature corpus.');
                return $this->redirect()->toRoute('publication', ['sw_id' => $newSwId]);
            }
            //An expired or forged token re-renders the confirmation rather than copying.
            //deleteAction() answers 401 here; 400 is the honest code — the visitor is
            //authorized, their token is not valid — and nothing branches on it.
            $this->nowMessenger()->setNamespace(NowMessenger::NAMESPACE_ERROR)
                ->addMessage('Your confirmation expired. Please try again.');
            $this->getResponse()->setStatusCode(400);
        }

        $form->setAttribute('action', $request->getRequestUri());

        $confirm = new ViewModel([
            'form'         => $form,
            'entityObject' => $entityObject,
            'cancelUrl'    => $this->url()->fromRoute('publication', [
                'sw_id' => $entityObject['identifier'],
                'slug'  => $entityObject['slug'] ?? null,
            ]),
        ]);
        $confirm->setTemplate('books/publications/copy-to-main-corpus');

        return $confirm;
    }

    public function oneFiftyPreguntasAction()
    {
        $view = new ViewModel();
        $view->setTemplate('books/publications/one-fifty-preguntas');
        return $view;
    }

    public function sendToNewUrlAction()
    {
        $id = $this->params()->fromRoute('publication_id');
        if (isset($id)) {
            $object = $this->getEntityObject($id);
            if (isset($object)) {
                /**
                 * @var \Laminas\Http\Response $response
                 */
                $response = $this->redirect()->toRoute(
                    'publication',
                    ['sw_id' => $object['identifier'], 'slug' => $object['slug']]
                );
                $response->setStatusCode(301);
                return $response;
            }
        }
        $entity = $this->getEntity();
        $entitySpec = $this->getEntitySpecification();
        $this->flashMessenger()->setNamespace(FlashMessenger::NAMESPACE_ERROR)
        ->addMessage(ucwords($entity) . ' not found.');
        $redirectRoute = $entitySpec->indexRoute ? $entitySpec->indexRoute : $this->getDefaultRedirectRoute();
        return $this->redirect()->toRoute($redirectRoute);
    }

    public function showAction()
    {
        $view = parent::showAction();
        if ($view instanceof \Laminas\Stdlib\ResponseInterface) {
            return $view;
        }
        $entityObject = $view->getVariable('entity');

        //redirect iff this pub has been merged and the client is not logged in
        if (isset($entityObject['mergedIntoPublicationId']) && ! $this->isAllowed('publication_user', 'show')) {
            $queryResults = $this->getSionTable()->queryObjects('publication', ['publicationId' => $entityObject['mergedIntoPublicationId']]);
            if (is_array($queryResults) && count($queryResults) === 1) {
                $mergedInto = current($queryResults);
                if (isset($mergedInto) && isset($mergedInto['identifier']) && isset($mergedInto['slug'])) {
                    $response = $this->redirect()->toRoute(
                        'publication',
                        ['sw_id' => $mergedInto['identifier'], 'slug' => $mergedInto['slug']]
                    );
                    $response->setStatusCode(301);
                    return $response;
                }
            }
        }

        if (isset($entityObject['bookCoverFileId'])) {
            $bookCoverFileId = $entityObject['bookCoverFileId'];
            /** @var FilesTable $filesTable */
            $filesTable = $this->services[FilesTable::class];
            $entityObject['bookCoverFile'] = $filesTable->getFile($bookCoverFileId);
            $view->setVariable('entity', $entityObject);
        }
        /** @var DriveGateway $gateway */
        $gateway = $this->services[DriveGateway::class];
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
                $subEditionIds = array_keys($entityObject['subEditions']);
                foreach ($subEditionIds as $key) {
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
        $libraries = $libraryTable->getObjects('library');
        //check which libraries the user has access to
        foreach ($libraries as $libraryId => $library) {
            if (! $this->isAllowed($library['resourceId'], 'show')) {
                unset($libraries[$libraryId]);
            }
        }
        //gather the publicationIds to search for
        $publicationIds = [$entityObject['publicationId']];
        if (isset($entityObject['subEditions']) && is_array($entityObject['subEditions']) && ! empty($entityObject['subEditions'])) {
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
        return $view;
    }

    public function literatureHomeAction()
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
        $cLanguage = \Locale::getPrimaryLanguage(\Locale::getDefault());
        $languages = $table->getLanguageNames($cLanguage);

        $dictionaries = $this->getDictionaries();
        $libraries = $this->getLibraries();

        $view = new ViewModel([
            'form'      => $form,
            'entity'    => $entity,
            'entitySpec' => $entitySpec,
            'languages' => $languages,
            'objects'   => $objects,
            'dictionaries' => $dictionaries,
            'libraries' => $libraries,
        ]);
        return $view;
    }

    protected function getDictionaries()
    {
        /** @var DictionaryTable $dictionaryTable */
        $dictionaryTable = $this->services[DictionaryTable::class];
        $dictionaries = $dictionaryTable->getAvailableDictionaryLanguages();

        return $dictionaries;
    }

    protected function getLibraries()
    {
        /** @var LibraryTable $table */
        $table = $this->services[LibraryTable::class];
        $objects = $table->getObjects('library');
        return $objects;
    }

    public function indexAction()
    {
        /** @var \Books\Model\PublicationsTable $table */
        $table      = $this->getSionTable();
        $entity     = $this->getEntity();
        $entitySpec = $this->getEntitySpecification();
        $language   = $this->params()->fromRoute('inLanguage');
        $objects    = $table->searchPublications(
            ['inLanguage' => [$language]],
            ['noSubEditions' => true]
        );
        $objects    = $this->groupPublicationsByCategory($objects);

        $form = $this->services[PublicationsSearchForm::class];
        /** @var DriveGateway $gateway */
        $gateway = $this->services[DriveGateway::class];
        $publicationFiles = null;
        try {
            $publicationFiles = $this->isAllowed('publication_drive') ? $gateway->getPublicationFiles() : null;
        } catch (\Exception $e) {
        }

        return new ViewModel([
            'form'      => $form,
            'language'  => $language,
            'entity'    => $entity,
            'entitySpec' => $entitySpec,
            'objects'   => $objects,
            'files'     => $publicationFiles,
        ]);
    }

    protected function groupPublicationsByCategory($objects)
    {
        //keyed by the category name, if we did the Id's it might re-sort our array
        //WARNING, this won't work if child categories aren't sorted properly directly after their parents
        $categories = [];
        $noCategoryObjects = [];
        foreach ($objects as $pubId => $object) {
            if (isset($object['categoryName'])) {
                if (! isset($categories[$object['categoryName']])) { //add this group array key
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
        if ($view instanceof \Laminas\Stdlib\ResponseInterface) {
            return $view;
        }
        $entityId = $view->getVariable('entityId');
        $this->injectPublicationValueOptions($view, $entityId);
        return $view;
    }

    public function createAction()
    {
        $view = parent::createAction();
        if ($view instanceof \Laminas\Stdlib\ResponseInterface) {
            return $view;
        }
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

            if (! empty($data)) {
                /** @var \Books\Model\PublicationsTable $table */
                $table = $this->getSionTable();
                $options = [];
                $options['maxResults'] = self::MAX_SEARCH_RESULTS;
                if (! isset($data['showEditionsSeparately']) || $data['showEditionsSeparately'] != '1') {
                    $options['noSubEditions'] = true;
                }
                if (isset($data['includeDataSources']) && $data['includeDataSources'] === '1') {
                    $options['includeDataSources'] = true;
                }
                $entities = $table->searchPublications($data, $options);
                if (is_array($entities) && count($entities) == self::MAX_SEARCH_RESULTS) {
                    $this->nowMessenger()->addMessage(
                        "More than the max number of publications match your search. Only the first 300 results shown.",
                        NowMessenger::NAMESPACE_INFO
                    );
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
        $id         = (int)$this->getEntityIdParam('show');
        $object     = $this->getEntityObject($id);

        //set the new mainPublicationId to the old publicationId
        if (! isset($object['mainPublicationId'])) { //else, leave it as it was
            $object['mainPublicationId'] = $object['publicationId'];
        }

        //unset edition-specific fields, and create new publication
        unset($object['publicationId']);
        unset($object['bookEdition']);
        unset($object['numberOfPages']);
        unset($object['datePublishedText']);
        unset($object['publishingStatus']);
        unset($object['isbn']);
        unset($object['hasNoISBN']);
        unset($object['hasNoExplictEditionNumber']);
        unset($object['editionNotes']);
        unset($object['isAwaitingMerge']);
        unset($object['isRevisedWithBookInHand']);
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

        $swFilter = new ToSchoenstattLinkIdentifier('publication');
        $identifier = $swFilter->filter($newId);
        return $this->redirect()->toRoute('publication-edit', ['sw_id' => $identifier]);
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
                if (! $newId = $filesTable->createEntity('file', $data)) {
                    //update the publication record
                    $publicationData = [
                        'bookCoverFileId' => $newId
                    ];
                    $publicationTable = $this->getSionTable();
                    $publicationTable->updateEntity('publication', $id, $publicationData);

                    $swFilter = new ToSchoenstattLinkIdentifier('publication');
                    $identifier = $swFilter->filter($id);
                    return $this->redirect()->toRoute('publication', ['sw_id' => $identifier]);
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
     * Makes sure this function returns the publicationId if passed a site-wide id
     *
     * {@inheritDoc}
     * @see \SionModel\Controller\SionController::getEntityIdParam()
     */
    protected function getEntityIdParam($action = 'show', $default = null)
    {
        static $swValidator;
        static $swFilter;
        $id = $this->params()->fromRoute('sw_id');
        if (isset($id)) {
            if (! isset($swValidator)) {
                $swValidator = new SchoenstattLinkIdentifier();
            }
            if (! $swValidator->isValid($id)) {
                throw new \Exception('Invalid site-wide id');
            }
            if (! isset($swFilter)) {
                $swFilter = new \Schoenstatt\Filter\SchoenstattLinkIdentifier();
            }
            $id = $swFilter->filter($id);
        } else {
            throw new \Exception('Invalid publication id');
        }
        return $id;
    }
}
