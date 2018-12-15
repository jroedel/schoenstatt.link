<?php 
namespace Books\Controller;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Schoenstatt\Validator\SchoenstattLinkIdentifier;
use Books\Model\LibraryTable;
use Zend\View\Model\ViewModel;
use Zend\Validator\Regex;

class LibrariesApiController extends AbstractRestfulController
{
    const LIBRARY_API_FIELDS = [
        'libraryId', 'name', 'description', 'requireCallNumbers', 'callNumberHelpText', 
        'callNumberExplanation', 'callNumberPlaceholder', 'callNumberRegex', 'filiationId', 
        'contactPersonId', 'contactEmail', 'mainShowDisplay', 'useCollections', 
        'allowCollectionlessBooks', 'mainCollectionId', 'labelLine1', 'labelLine2', 
        'labelLine3', 'barcodeText', 'enableCheckouts', 'defaultCheckoutTimePeriodInDays', 
        'checkoutPersonListKind', 'defaultCheckoutPersonId', 'isActive', 'adminNotes', 
        'nextWithinLibraryId', 'bookCount', 'collections', 'contactPerson'
    ];
    
    /**
     * @var LibraryTable $libraryTable
     */
    protected $libraryTable;

    /**
     * @var array $config
     */
    protected $config;

    public function __construct(LibraryTable $libraryTable, array $config)
    {
        $this->setIdentifierName('library_id');
        $this->libraryTable = $libraryTable;
        $this->config = $config;
    }

    public function getList()
    {
        $params = $this->params()->fromQuery();
        $table = $this->libraryTable;
        $objects = $table->getUnlinkedLibraries();
        
        return new JsonModel([
            'items'         => $json,
            'md5'           => $md5,
            'objectMd5s'    => $md5s,
        ], ['prettyPrint' => false]);
    }
    
    public function get($id)
    {
        $table = $this->libraryTable;
        $object = $table->getSimpleLibrary($id);
        self::prepLibraryObject($object);
        return new JsonModel([$object], ['prettyPrint' => true]);
    }
    
    public function pendingLabelsAction()
    {
        $libraryId = $this->params()->fromRoute('library_id');
        //just query the database with searchBooks and report the books back
        $table = $this->libraryTable;
        $objects = $table->searchBooks(['libraryId' => $libraryId], ['onlyPendingBooks' => true]);
        $this->jsonSerializeDateTimeObjects($objects);
        return new JsonModel([
            'items' => $objects,
        ], ['prettyPrint' => true]);
    }
    
    public function finishPendingLabelsAction()
    {
        //get bookIds from query params
        $libraryId = $this->params()->fromRoute('library_id');
        $bookIdParam = $this->params()->fromQuery('bookIds');
        $validator = new Regex('/\d{1,8}(?:\|\d{1,8})*/');
        if (!$validator->isValid($bookIdParam)) {
            return $this->sendFailedMessage('Invalid bookId query parameter passed. It should be a pipe-separated list.');
        }
        $bookIds = explode('|', $bookIdParam);
        
        //verify that they each have pending label changes
        $table = $this->libraryTable;
        $objects = $table->searchBooks([
            'libraryId' => $libraryId,
            'bookId' => $bookIds,
        ], ['onlyPendingBooks' => true]);
        
        //make the database change (not manually, better to do it through updateEntity)
        $results = [];
        foreach ($objects as $object) {
            $results[$object['bookId']] = $table->updateEntity('book', $object['bookId'], [
                'callNumber' => $object['newCallNumber'],
                'newCallNumber' => null,
            ]);
        }
        
        //report back how it went with each book
        $return = [];
        foreach ($bookIds as $bookId) {
            if (isset($results[$bookId])) {
                $return[$bookId] = $results[$bookId];
            } else {
                $return[$bookId] = false;
            }
        }
        
        return new JsonModel(['results' => $return]);
    }
    
    /**
     * This function formats a library array from the LibraryTable for standard API output
     * @param array $data
     */
    protected static function prepBookObject(array &$data)
    {
        //@todo write this
        foreach ($data as $key => $value) {
            if (!in_array($key, self::LIBRARY_API_FIELDS, true)) {
                unset($data[$key]);
            }
        }
        self::jsonSerializeDateTimeObjects($data);
    }
    
    /**
     * This function formats a library array from the LibraryTable for standard API output
     * @param array $data
     */
    protected static function prepLibraryObject(array &$data)
    {
        foreach ($data as $key => $value) {
            if (!in_array($key, self::LIBRARY_API_FIELDS, true)) {
                unset($data[$key]);
            }
        }
        self::jsonSerializeDateTimeObjects($data);
    }
    
    /**
     * Recursively look for Datetime objects and serialize them for use in JSON
     * @param array $data
     */
    public static function jsonSerializeDateTimeObjects(array &$data)
    {
        foreach ($data as $key => $value) {
            if ($value instanceof \DateTime) {
                $data[$key] = $value->format('Y-m-d\TH:i:s\Z');
            } elseif (is_array($value)) {
                self::jsonSerializeDateTimeObjects($data[$key]);
            }
        }
    }
    
    protected function sendFailedMessage($message, $statusCode = 401)
    {
        $response = $this->getResponse();
        $response->setStatusCode($statusCode);
        $response->sendHeaders();
        $response->setContent($message);
        return $response;
    }
}
