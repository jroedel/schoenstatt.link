<?php 
namespace Books\Controller;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Schoenstatt\Validator\SchoenstattLinkIdentifier;
use Books\Model\LibraryTable;

class BooksApiController extends AbstractRestfulController
{
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
        $this->setIdentifierName('book_id');
        $this->libraryTable = $libraryTable;
        $this->config = $config;
    }

    public function getList()
    {
        $params = $this->params()->fromQuery();
        $table = $this->libraryTable;
        $objects = $table->searchBooks($params);
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
}
