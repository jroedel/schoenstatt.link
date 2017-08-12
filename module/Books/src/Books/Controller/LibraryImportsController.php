<?php
namespace Books\Controller;

use SionModel\Controller\SionController;
use Books\Form\ImportForm;
use Books\Model\LibraryTable;

class LibraryImportsController extends SionController
{
    /**
    * @var int $libraryId
    */
    protected $libraryId;

    /**
    * Get the libraryId value
    * @return int
    */
    public function getLibraryId()
    {
        if (is_null($this->libraryId)) {
            $this->libraryId = $this->params()->fromRoute('library_id');
        }
        return $this->libraryId;
    }

    /**
    *
    * @param int $libraryId
    * @return self
    */
    public function setLibraryId($libraryId)
    {
        $this->libraryId = $libraryId;
        return $this;
    }

    public function __construct()
    {
        return parent::__construct('library-import');
    }

    public function indexAction()
    {
        $view = parent::indexAction();
        $view->setVariable('libraryId', $this->getLibraryId());
        return $view;
    }

    public function createAction()
    {
        $view = parent::createAction();
        $libraryId = $this->getLibraryId();
        $view->setVariable('libraryId', $libraryId);
        /** @var ImportForm $form */
        $form = $view->getVariable('form');
        $form->get('submit')->setValue('Simulate import');
        $view->setVariable('form', $form);
        $view->setVariable('fieldsMap', $this->getColegioMayorLibraryFieldsMap());
        return $view;
    }

    public function getPostDataForCreateAction()
    {
        $data = $this->getRequest()->getPost()->toArray();
        $data['libraryId'] = $this->getLibraryId();
        return $data;
    }

    public function createEntityPostFormValidation($data, $form)
    {
        $data['columnMapping'] = $this->getColegioMayorLibraryFieldsMap();
        return parent::createEntityPostFormValidation($data, $form);
    }

    private function getColegioMayorLibraryFieldsMap()
    {
        return [
            'author'            => 'Autor',
            'title'             => 'Titulo',
            'callNumber'        => 'Lomo',
            'category'          => 'Categoría',
            'pages'             => 'Páginas',
            'language'          => 'Idioma',
            'withinLibraryId'   => 'ID',
            'copyrightYear'     => 'Año',
            'publisher'         => 'Editorial',
            'publishingPlace'   => 'Ciudad',
            'isbn'              => 'ISBN',
            'publicationId'     => 'PubID',
            'keywords'          => 'Categorías',
            'edition'           => 'Edition',
        ];
    }

    public function editAction()
    {
        $importId = $this->getEntityIdParam('edit');
        $object = $this->getEntityObject($importId);
        $this->setLibraryId($object['libraryId']);
        $view = parent::editAction();
        /** @var ImportForm $form */
        $form = $view->getVariable('form');
        //first thing is to figure out if it has already been imported
        if (!is_null($object['booksCreated']) || !is_null($object['booksUpdated']) || !is_null($object['booksInactivated']))
        {
            $form->get('filePath')->setAttribute('disabled', true);
            $form->get('worksheet')->setAttribute('disabled', true);
            $form->get('isCompleteImport')->setAttribute('disabled', true);
            $form->get('submit')->setValue('Save');
            return $view->setVariable('isImported', true);
        } else {
            $form->get('submit')->setValue('Save and simulate');
        }

        //get the object again, just in case it was recently updated in the editAction
        $object = $this->getEntityObject($importId);
        $request = $this->getRequest();
        //@todo change this line to $object['columnMapping']
        $fieldsMap = $this->getColegioMayorLibraryFieldsMap();
        $errorMessage = null;
        if (file_exists($object['filePath']) &&
            !is_null($object['worksheet'])
        ) {
            $shouldSimulate = !$request->isPost() || is_null($request->getPost('import'));
            $objects = $this->importSpreadsheetFile($object['filePath'], $object['worksheet'], $fieldsMap, $shouldSimulate, $object['isCompleteImport']);
        } else {
            $errorMessage = 'File not found.';
        }
        //do stats on the objects
        $stats = [
            'create'    => 0,
            'update'    => 0,
            'inactivate'=> 0,
            'error'     => 0,
        ];
        foreach ($objects as $object) {
            ++$stats[$object['action']];
        }

        if (!$shouldSimulate) { //update the stats
            $params = [
                'booksUpdated' => $stats['update'],
                'booksCreated' => $stats['create'],
                'booksInactivated' => $stats['inactivate'],
            ];
            /** @var LibraryTable $table */
            $table = $this->getSionTable();
            $table->updateEntity('library-import', $importId, $params);
        }

        return $view->setVariables([
            'isImported'    => !$shouldSimulate,
            'transactions'  => $objects,
            'fields'        => $fieldsMap,
            'simulate'      => $shouldSimulate,
            'statistics'    => $stats,
            'errorMessage'  => $errorMessage,
        ], false);
    }

    public function redirectAfterEdit($id)
    {
        //don't redirect after edit
    }

    protected function getBookFields()
    {
        return [
            'publicationId',
            'author',
            'title',
            'edition',
            'callNumber',
            'category',
            'pages',
            'language',
            'withinLibraryId',
            'publicationId',
            'isActive',
            'inactivationReason',
            'updatedOn',
            'updatedBy',
            'createdOn',
            'createdBy',
            'copyrightYear',
            'publisher',
            'publishingPlace',
            'isbn',
            'keywords',
            'publicNotes',
            'publicNotesUpdatedOn',
            'publicNotesUpdatedBy',
            'adminTags',
            'adminNotes',
            'adminNotesUpdatedOn',
            'adminNotesUpdatedBy',
        ];
    }
    /**
     * Read an excel file and import the records into the database
     * @param string $fileName
     * @param string $sheetName
     * @param string[] $fieldsMap
     * @param bool $simulate
     * @param bool $deleteMissingRowsFromDatabase
     * @throws \Exception
     * @return array[]|string[][]|boolean[][]|unknown[][]|number[][]|\Books\Model\number[][]
     */
    public function importSpreadsheetFile($fileName, $sheetName, $fieldsMap, $simulate = true, $deleteMissingRowsFromDatabase = false)
    {
        $bookFields = $this->getBookFields();
        $objPHPExcel = \PHPExcel_IOFactory::load($fileName);
        $sheet = $objPHPExcel->getSheetByName($sheetName);
        $highRow = $sheet->getHighestDataRow();
        $highColumn = $sheet->getHighestDataColumn();

        if ($highColumn == 'A' || $highRow == 1) {
            throw new \Exception('No data contained in the spreadsheet.');
        }

        //first, get the first row which should contain the column headers
        // and make sure we have all the required headers
        $rowHeaders = $sheet->rangeToArray('A1:'.$highColumn.'1')[0];

        $fieldIndices = [];
        foreach ($rowHeaders as $key => $value) {
            if (in_array($value, $fieldsMap)) {
                foreach ($fieldsMap as $bookField => $columnName) {
                    if ($columnName == $value && in_array($bookField, $bookFields)) {
                        $fieldIndices[$bookField] = $key;
                    }
                }
            }
        }

        //check if we got all the required fields mapped
        $missingRequiredFields = [];
        foreach ($requiredFields as $value) {
            if (!key_exists($value, $fieldIndices)) {
                $missingRequiredFields[] = $value;
            }
        }
        if (!empty($missingRequiredFields)) {
            throw new \Exception('Missing required fields for the excel file: '.implode(', ', $missingRequiredFields));
        }

        $rows = $sheet->rangeToArray('A2:'.$highColumn.$highRow);
        /** @var \Books\Model\LibraryTable $table */
        $table = $this->getSionTable();
        $libraryId = $this->getLibraryId();
        if (is_null($libraryId)) {
            throw new \Exception('This function should only be called in the context of a particular library.');
        }
        $table->setLibraryId($libraryId);
        $bookLookup = $table->getLibraryBookLookup();
        $transactions = [];
        $publications = null;
        $bookIdsBeingUpdated = [];
        foreach ($rows as $rowNumber => $rowColumns) {
            $withinLibraryId = (int)$rowColumns[$fieldIndices['withinLibraryId']];
            $params = [
                'libraryId' => $libraryId
            ];
            //add the fields to the param list
            foreach ($fieldIndices as $bookField => $columnIndex) {
                $params[$bookField] = $rowColumns[$columnIndex];
            }
            //make sure we get an int not a float
            $params['withinLibraryId'] = $withinLibraryId;

            if (key_exists('publicationId', $fieldIndices) && is_numeric($params['publicationId'])) {
                //lazy load the publications list
                if (is_null($publications)) {
                    /** @var \Books\Model\PublicationsTable $publicationsTable */
                    $publicationsTable = $this->getServiceLocator()->get('Books\Model\PublicationsTable');
                    $publications = $publicationsTable->getUnlinkedPublications();
                }
                $publicationId = (int)$params['publicationId'];
                if (key_exists($publicationId, $publications)) {
                    //fill in info from the publication to the books table
                    $params['title'] = $publications[$publicationId]['title'];
                    if (!is_null($publications[$publicationId]['authors'])) {
                        $params['author'] = $publications[$publicationId]['authors'];
                    }
                    if (!is_null($publications[$publicationId]['copyrightYear'])) {
                        $params['copyrightYear'] = $publications[$publicationId]['copyrightYear'];
                    }
                    if (!is_null($publications[$publicationId]['publisher'])) {
                        $params['publisher'] = $publications[$publicationId]['publisher'];
                    }
                    if (!is_null($publications[$publicationId]['publishingPlace'])) {
                        $params['publishingPlace'] = $publications[$publicationId]['publishingPlace'];
                    }
                    if (!is_null($publications[$publicationId]['numberOfPages'])) {
                        $params['pages'] = $publications[$publicationId]['numberOfPages'];
                    }
                    if (!is_null($publications[$publicationId]['inLanguage'])) {
                        $params['language'] = $publications[$publicationId]['inLanguage'];
                    }
                    if (!is_null($publications[$publicationId]['isbn'])) {
                        $params['isbn'] = $publications[$publicationId]['isbn'];
                    }
                }
            }

            //determine the action to take on the row
            if (!key_exists('title', $params) || is_null($params['title'])
                || is_null($withinLibraryId) || !is_numeric($withinLibraryId)
            ) {
                $params['action'] = 'error';
                if (is_numeric($withinLibraryId)) {
                    //make sure we don't delete this book, because there was an import error
                    $bookIdsBeingUpdated[] = $bookLookup[$withinLibraryId];
                }
            } else if (key_exists($withinLibraryId, $bookLookup)) {
                $params['action'] = 'update';
                $params['bookId'] = $bookLookup[$withinLibraryId];
                $bookIdsBeingUpdated[] = $bookLookup[$withinLibraryId];
            } else {
                $params['action'] = 'create';
            }
            $transactions[] = $params;
        }

        //delete missing rows from the database if asked to do so
        if ($deleteMissingRowsFromDatabase) {
            $books = $table->getBooks();
            foreach ($bookLookup as $withinLibraryId => $bookId) {
                if (!in_array($bookId, $bookIdsBeingUpdated)) {
                    $params = [
                        'action'            => 'inactivate',
                        'bookId'            => $bookId,
                        'isActive'          => false,
                        'inactivationReason'=> 'Mass book import',
                    ];
                    $transactions[] = $params;
                }
            }
        }

        if (!$simulate) {
            $this->persistImportTransactions($transactions);
        }

        return $transactions;
    }

    /**
     * Persist import changes to the database
     * @param array $transactions
     */
    protected function persistImportTransactions(array &$transactions)
    {
        /** @var \Books\Model\LibraryTable $table */
        $table = $this->getSionTable();
        foreach ($transactions as $key => $transaction) {
            switch ($transaction['action']) {
                case 'update':
                    $transactions[$key]['result'] = $table->updateEntity('book', $transaction['bookId'], $transaction, [], false);
                    break;
                case 'create':
                    $transactions[$key]['result'] = $table->createEntity('book', $transaction, false);
                    break;
                case 'inactivate':
                    $transactions[$key]['result'] = $table->updateEntity('book', $transaction['bookId'], $transaction, [], false);
                    break;
            }
        }
        $table->removeDependentCacheItems('book'); //force cache refresh
    }
}
