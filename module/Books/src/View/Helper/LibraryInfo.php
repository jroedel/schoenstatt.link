<?php
namespace Books\View\Helper;

use Zend\View\Helper\AbstractHelper;
use Spatie\SchemaOrg\Schema;
use OpenURL\ContextObject;
use Books\Model\LibraryTable;
use Zend\Router\RouteMatch;

class LibraryInfo extends AbstractHelper
{
    /**
     * @var LibraryTable $libraryTable
     */
    protected $libraryTable;
    
    /**
     * @var RouteMatch $routeMatch
     */
    protected $routeMatch;

    public function __invoke()
    {
        $libraryId = $this->getLibraryId();
        if (!isset($libraryId)) {
            return null;
        }
        $table = $this->getLibraryTable();
        if (!$table instanceof LibraryTable) {
            return null;
        }
        $info = $table->getSimpleLibrary($libraryId);
        return $info;
    }
    
    public function getLibraryId()
    {
        $match = $this->getRouteMatch();
        if (!isset($match)) {
            return null;
        }
        $libraryId = $match->getParam('library_id'); //this should return null if not found
        if (!isset($libraryId)) {
            $bookId = $match->getParam('book_id');
            if (isset($bookId)) {
                $bookData = $this->getLibraryTable()->getSimpleBook($bookId);
                $libraryId = $bookData['libraryId'];
            } else {
                $libraryImportId = $match->getParam('import_id');
                $importData = $this->getLibraryTable()->getLibraryImport($libraryImportId);
                $libraryId = $importData['libraryId'];
            }
        }
        return $libraryId;
    }
    
    /**
     * Get the routeMatch value
     * @return RouteMatch
     */
    public function getRouteMatch()
    {
        if (!isset($this->routeMatch)) {
            throw new \Exception('Something went wrong, no routeMatch available');
        }
        return $this->routeMatch;
    }
    
    /**
     * Set the routeMatch value
     * @param RouteMatch $routeMatch
     * @return self
     */
    public function setRouteMatch(?RouteMatch $routeMatch)
    {
        $this->routeMatch = $routeMatch;
        return $this;
    }

    /**
     * Get the libraryTable value
     * @return LibraryTable
     */
    public function getLibraryTable()
    {
        return $this->libraryTable;
    }
    
    /**
     * Set the libraryTable value
     * @param LibraryTable $libraryTable
     * @return self
     */
    public function setLibraryTable(LibraryTable $libraryTable)
    {
        $this->libraryTable = $libraryTable;
        return $this;
    }
}
