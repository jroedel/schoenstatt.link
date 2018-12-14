<?php 
namespace Books\Controller;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Schoenstatt\Validator\SchoenstattLinkIdentifier;
use Books\Model\LibraryTable;

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
}
