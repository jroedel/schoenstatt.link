<?php
namespace Books\Controller;

use Zend\View\Model\JsonModel;
use Books\Model\LibraryTable;
use Books\Form\BookForm;
use RestApi\Controller\ApiController;
use Zend\View\Model\ModelInterface;
use Books\Model\PublicationsTable;
use Zend\InputFilter\InputFilter;

class BooksApiController extends ApiController
{
    const RECORD_ACTION_UPDATE = 'record-update';
    const RECORD_ACTION_CREATE = 'record-create';
    
    const PUBLICATION_TO_BOOK_FIELD_MAP = [
        'title' => 'title',
        'bookEdition' => 'bookEdition',
        'authorsText' => 'authorsText',
        'datePublishedText' => 'publishedYear', //special case, only take first 4 chars
        'publisher' => 'publisher',
        'publishingPlace' => 'publishingPlace',
        'numberOfPages' => 'numberOfPages',
        'inLanguage' => 'inLanguage',
        'isbn' => 'isbn',
        'keywords' => 'keywords',
    ];
    /**
     * @var LibraryTable $libraryTable
     */
    protected $libraryTable;

    /**
     * @var array $config
     */
    protected $config;

    /**
     * @var BookForm $bookForm
     */
    protected $bookForm;
    
    /**
     * @var PublicationsTable $publicationsTable
     */
    protected $publicationsTable;
    
    public function __construct(
        LibraryTable $libraryTable, 
        BookForm $bookForm, 
        PublicationsTable $publicationsTable, 
        array $config
    ) {
        $this->setIdentifierName('book_id');
        $this->libraryTable = $libraryTable;
        $this->bookForm = $bookForm;
        $this->publicationsTable = $publicationsTable;
        $this->config = $config;
    }

    public function getList()
    {
        $params = $this->params()->fromQuery();
        $table = $this->libraryTable;
        $objects = array_values($table->searchBooks($params));
        LibrariesApiController::jsonSerializeDateTimeObjects($objects);
        return new JsonModel([
            'items'         => $objects,
        ], ['prettyPrint' => true]);
    }
    
    public function get($id)
    {
        $table = $this->libraryTable;
        $object = $table->getBook($id);
        return new JsonModel($object, ['prettyPrint' => true]);
    }
    
    /**
     * Receive a list of book objects. The withinLibraryIds that already
     * exists will be updated (PATCH), while non-existant ones will be created (POST).
     * We use the PATCH verb referencing the fact that we will not be replacing the 
     * whole list of books with a new complete list, but instead modifying the list.
     * 
     * The libraryId query parameter is required.
     * 
     * {@inheritDoc}
     * @see \Zend\Mvc\Controller\AbstractRestfulController::patchList()
     */
    public function patchList($data)
    {
        //authenticate user
        
        //verify the library query parameter
        $libraryId = $this->getLibraryId();
        if ($libraryId instanceof ModelInterface) { //there was a problem
            return $libraryId;
        }
        
        $table = $this->getLibraryTable();
        $table->setLibraryId($libraryId);
        $simulateParam = $this->params()->fromQuery('simulate');
        $isSimulation = $simulateParam === 'true' || $simulateParam === '1';
        
        //run through the data, make sure each record has a withinLibraryId, and make an array of these
        if (!$this->requestIsJson()) {
            $this->httpStatusCode = 400; //bad request
            $this->apiResponse['message'] = 'Please specify a JSON request body by setting the HTTP header `Content-Type: application/json`';
            return $this->createResponse();
        }
        if (!isset($data) || !is_array($data)) {
            $this->httpStatusCode = 400; //bad request
            $this->apiResponse['message'] = 'Please send a JSON request body with an array of book objects. '
                .'See https://schoenstatt.link/api/v1';
            return $this->createResponse();
        }
        $collectionNames = $table->getCollectionNames($libraryId);
        $withinLibraryIds = [];
        $duplicateWithinLibraryIds = [];
        $nonExtantWithinLibraryIds = [];
        $publicationIds = [];
        $receivedBooks = [];
        //@todo verify that libraries that require collections have them (maybe somehow in the InputFilter)
        foreach ($data as $book) {
            if (!is_array($book) || !isset($book['withinLibraryId']) || !is_numeric($book['withinLibraryId'])) {
                $this->httpStatusCode = 400; //bad request
                $this->apiResponse['message'] = 'Each book object must have a numeric `withinLibraryId` property.';
                return $this->createResponse();
            }
            if (in_array($book['withinLibraryId'], $withinLibraryIds)) {
                $duplicateWithinLibraryIds[] = (int)$book['withinLibraryId'];
            }
            $withinLibraryId = (int)$book['withinLibraryId'];
            $withinLibraryIds[] = $withinLibraryId;
            
            $collectionId = null;
            if (!isset($book['collectionid']) 
                && isset($book['collectionName'])
            ) {
                $collectionId = array_search($book['collectionName'], $collectionNames);
                if (false === $collectionId) {
                    //error: if the user submits a collectionName it MUST exist
                    $nonExtantWithinLibraryIds[] = $withinLibraryId;
                } else {
                    $book['collectionId'] = $collectionId;
                }
            }
            
            if (isset($book['publicationId'])) {
                $publicationIds[] = $book['publicationId'];
            }
            
            $receivedBooks[$withinLibraryId] = $book;
        }
        if (!empty($duplicateWithinLibraryIds)) {
            $this->httpStatusCode = 409; //conflict: https://www.restapitutorial.com/httpstatuscodes.html#conflict
            $this->apiResponse['message'] = 'Repeated `withinLibraryId`s found. '
                .'These can be found in the `withinLibraryId` key (array).';
            $this->apiResponse['withinLibraryId'] = $duplicateWithinLibraryIds;
            return $this->createResponse();
        }
        if (!empty($nonExtantWithinLibraryIds)) {
            $this->httpStatusCode = 400; //bad request
            $this->apiResponse['message'] = 'Some records submitted refer to `collectionName`s that don\'t exist. '
                .'These can be found in the `withinLibraryId` key (array).';
            $this->apiResponse['withinLibraryId'] = $nonExtantWithinLibraryIds;
            return $this->createResponse();
        }
        
        //fetch records from the db of these withinLibraryId, this will tell us to update or create
        $books = $table->queryObjects('book', ['withinLibraryId' => $withinLibraryIds, 'libraryId' => $libraryId]);
//         $books = $table->searchBooks(['withinLibraryId' => $withinLibraryIds, 'libraryId' => $libraryId]);
        
        
        $publications = [];
        if (!empty($publicationIds)) {
            $publicationsTable = $this->publicationsTable;
            $publications = $publicationsTable->queryObjects('publication', ['publicationId' => $publicationIds]);
        }
        
        //loop through data again:
        $withinLibraryIdLookup = $this->getLookupBookIdsByWithinLibraryId($books);
        $results = [];
        $toCreate = [];
        $errorRecords = [];
        $inputFilter = $this->getInputFilter();
        $inputFilter->get('isActive')->getFilterChain()->getFilters()->toArray()[0]->setOptions(['null_defaults_to' => true]);
        foreach ($withinLibraryIds as $withinLibraryId) {
            //if the user specified a publicationId, copy over that info
            if (isset($receivedBooks[$withinLibraryId]['publicationId'])) {
                $publicationId = (int)$receivedBooks[$withinLibraryId]['publicationId'];
                if (isset($publications[$publicationId])) {
                    $this->fillInPublicationInformation(
                        $receivedBooks[$withinLibraryId],
                        $publications[$publicationId],
                        $inputFilter
                        );
                }
            }
            
            //update
            if (isset($withinLibraryIdLookup[$withinLibraryId])) {
                $bookId = $withinLibraryIdLookup[$withinLibraryId];
                
                //merge incoming data with the data in the db
                if (isset($books[$bookId])) {
                    //if update, add data fields to db fields and run them through inputFilter
                    $merged = array_merge($books[$bookId], $receivedBooks[$withinLibraryId]);
                } else {
                    throw new \Exception('We have a withinLibraryId lookup record, but we dont have the record: '
                        .$withinLibraryId);
                }
                $merged['libraryId'] = $libraryId;
                $inputFilter->setData($merged);
                if ($inputFilter->isValid()) {
                    //if valid, queue a db update
                    $goodData = $inputFilter->getValues();
                    $goodData['action'] = self::RECORD_ACTION_UPDATE;
                    $results[$bookId] = $goodData;
                } else {
                    $errorRecords[$withinLibraryId] = $this->formatInputFilterErrors($inputFilter->getMessages());
                }
            } else {
                //if create, run through input filter, queue db insert
                $book = $receivedBooks[$withinLibraryId];
                $book['libraryId'] = $libraryId;
                $inputFilter->setData($book);
                if ($inputFilter->isValid()) {
                    //if valid, queue a db update
                    $goodData = $inputFilter->getValues();
                    $goodData['action'] = self::RECORD_ACTION_CREATE;
                    $toCreate[] = $goodData;
                } else {
                    $errorRecords[$withinLibraryId] = $this->formatInputFilterErrors($inputFilter->getMessages());
                }
            }
        }
       
        if (!empty($errorRecords)) { //report errors
            $this->httpStatusCode = 400; //bad request
            $this->apiResponse['message'] = 'Data validation errors';
            $this->apiResponse['results'] = $errorRecords;
            return $this->createResponse();
        }
        
        if (!$isSimulation) {
            //if all looks good execute all the db queries
            
            //first the updates
            foreach ($results as $bookId => $book) {
                try {
                    $resultingBook = $table->updateEntity('book', $bookId, $book, [], false); 
                    $results[$bookId]['result'] = isset($resultingBook);
                } catch (\Exception $e) { //@todo think about this
                    $message = $e->getMessage();
                    $results[$bookId]['result'] = $message;
                    $this->httpStatusCode = 500; //server error
                    $this->apiResponse['result'] = 'NOK';
                }
            }
            
            //then the creations
            foreach ($toCreate as $book) {
                try {
                    $bookId = $table->createEntity('book', $book, false);
                    $book['result'] = "1";
                    $results[$bookId] = $book;
                } catch (\Exception $e) { //@todo think about this
                    $message = $e->getMessage();
                    $book['result'] = $message;
                    $results[$bookId] = $book;
                }
            }
        }
        
        //report back to the caller on what's happened with each one
        $this->apiResponse['results'] = $results;
        return $this->createResponse();
        /*
         * {"result": "OK", "results": [
         *   {"withinLibaryId": 232, "action": "update", "success": false, "messages": {
         *     "publicationId": "Publication Id doesn't exist", "title": "Title field is required"
         *     }
         *   }
         * ]}
         */
    }
    
    protected function getLookupBookIdsByWithinLibraryId($books)
    {
        $lookup = [];
        foreach ($books as $objectId => $object) {
            if (isset($object['withinLibraryId']) && !isset($lookup[$object['withinLibraryId']])) {
                $lookup[$object['withinLibraryId']] = $objectId;
            }
        }
        return $lookup;
    }
    
    /**
     * 
     * @param mixed[] $book
     * @param mixed[] $publication
     * @param InputFilter $inputFilter
     */
    protected function fillInPublicationInformation(&$book, $publication, $inputFilter)
    {
        foreach (self::PUBLICATION_TO_BOOK_FIELD_MAP as $publicationField => $bookField) {
            if (isset($publication[$publicationField])) {
                $value = $publication[$publicationField];
                if ('datePublishedText' === $publicationField) {
                    $value = substr($value, 0, 4);
                }
                if ($inputFilter->has($bookField) && !$inputFilter->get($bookField)->setValue($value)->isValid()) {
                    //don't add the data if it's going to fail our input filter
                    //@todo this would be interesting to log
                    var_dump($publication['publicationId']);
                    var_dump($publicationField);
                    continue;
                }
                $book[$bookField] = $value;
            }
        }
        
        //no return, by ref
    }
    
    public function getLibraryId()
    {
        $libraryId = $this->params()->fromRoute('library_id');
        if (!isset($libraryId)) {
            $this->httpStatusCode = 201;
            $this->apiResponse['message'] = 'Invalid library id specified.';
            return $this->createResponse();
        }
        $table = $this->getLibraryTable();
        if (!$table->existsEntity('library', $libraryId)) {
            $this->httpStatusCode = 201;
            $this->apiResponse['message'] = 'Library doesn\'t exist.';
            return $this->createResponse();
        }
        return $libraryId;
    }
    
    public function requestIsJson()
    {
        $request = $this->getRequest();
        return $this->requestHasContentType($request, self::CONTENT_TYPE_JSON);
    }
    
    /**
     * Retrieve an input filter to validate api-submitted dictionary entries
     * @return \Zend\InputFilter\InputFilterInterface
     */
    public function getInputFilter()
    {
        if (!isset($this->inputFilter)) {
            $form = $this->bookForm;
            $this->inputFilter = $form->getInputFilter();
            $fields = $this->inputFilter->getInputs();
            if (isset($fields['security'])) {
                unset($fields['security']);
            }
            if (isset($fields['submit'])) {
                unset($fields['submit']);
            }
            if (isset($fields['nextWithinLibraryId'])) {
                unset($fields['nextWithinLibraryId']);
            }
            
            $this->inputFilter->get('isActive')->setFallbackValue(1);
            $fieldsToValidate = array_keys($fields);
            $this->inputFilter->setValidationGroup($fieldsToValidate);
        }
        return $this->inputFilter;
    }
    
    protected function formatInputFilterErrors($messages)
    {
        $errors = [];
        foreach ($messages as $field => $error) {
            foreach ($error as $errorKey => $message) {
                $errors[] = [
                    "field" => $field,
                    "errorKey" => $errorKey,
                    "message" => $message,
                ];
            }
        }
        return $errors;
    }
    
    /**
     * Massage ORM-returned objects for handing over the API
     * @param mixed $objects
     * @return mixed
     */
    protected function prepBooks($objects)
    {
        $results = [];
        foreach ($objects as $object) {
            $results[] = $this->prepBook($object);
        }
        return $results;
    }
    
    protected function prepBook($object)
    {
        /*
         * 
            'bookId'                => $id,
            'collectionId'          => $collectionId,
            'authorsText'           => $authorsText,
            'title'                 => $title,
            'bookEdition'           => $row['edition'],
            'callNumber'            => $row['call_number'],
            'newCallNumber'         => $row['new_call_number'],
            'category'              => $row['category'],
            'numberOfPages'         => $this->filterDbInt($row['pages']),
            'inLanguage'            => $this->filterDbArray($row['lang']),
            'withinLibraryId'       => $this->filterDbId($row['original_id']),
            'libraryId'             => $libraryId,
            'publicationId'         => $this->filterDbId($row['publication_id']),
            'sortText'              => $row['sort_text'],
            'isActive'              => $isActive,
            'inactivationReason'    => $row['inactivation_reason'],
            'updatedOn'             => $this->filterDbDate($row['updated_at']),
            'updatedBy'             => $this->filterDbId($row['updated_by']),
            'createdOn'             => $this->filterDbDate($row['created_at']),
            'createdBy'             => $this->filterDbId($row['created_by']),
            'publishedYear'         => $this->filterDbInt($row['copyright_year']),
            'publisher'             => $row['publisher'],
            'publishingPlace'       => $row['publisher_place'],
            'isbn'                  => $row['isbn'],
            'keywords'              => $this->filterDbArray($row['public_tags']),
            'publicNotes'           => $row['public_notes'],
            'publicNotesUpdatedOn'  => $this->filterDbDate($row['public_notes_updated_at']),
            'publicNotesUpdatedBy'  => $this->filterDbId($row['public_notes_updated_by']),
            'adminTags'             => $this->filterDbArray($row['admin_tags']),
            'adminNotes'            => $row['admin_notes'], //store source info here
            'adminNotesUpdatedOn'   => $this->filterDbDate($row['admin_notes_updated_at']),
            'adminNotesUpdatedBy'   => $this->filterDbId($row['admin_notes_updated_by']),

            'resourceId'            => 'library_'.$libraryId,
            'authors'               => $authors,
            'authorsPrettyText'     => $authorsPrettyText,
            'name'                  => $name,
            'isAvailable'           => $isActive && !isset($row['current_checkout_id']),
            'isCheckedOut'          => isset($row['current_checkout_id']),
            'currentCheckoutId'     => $this->filterDbId($row['current_checkout_id']),
            'currentCheckout'       => null,
            'library'               => null,
            'collectionName'        => $collectionName,
         */
        unset($object['publicNotesUpdatedOn']);
        unset($object['publicNotesUpdatedBy']);
        unset($object['adminNotesUpdatedOn']);
        unset($object['adminNotesUpdatedBy']);
        unset($object['createdOn']);
        unset($object['createdBy']);
        unset($object['updatedOn']);
        unset($object['updatedBy']);
        return $object;
    }
    
    /**
     * @return \Books\Model\LibraryTable
     */
    public function getLibraryTable()
    {
        //tell the DictionaryTable who the acting user is according to their token
        if (is_object($this->tokenPayload) && isset($this->tokenPayload->sub)) {
            $userId = $this->tokenPayload->sub;
            $this->libraryTable->setActingUserId($userId);
        }
        return $this->libraryTable;
    }
}
