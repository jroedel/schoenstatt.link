<?php
namespace Books\Model;

use SionModel\Db\Model\SionTable;
use JUser\Model\UserTable;
use Zend\Db\Adapter\AdapterInterface;
use SionModel\Filter\ToAscii;
use Carbon\Carbon;

class LibraryTable extends SionTable
{
    const CHECKOUT_STATUS_CHECKED_OUT = 'checked-out';
    const CHECKOUT_STATUS_RETURNED = 'returned';
    const CHECKOUT_STATUS_OVERDUE = 'overdue';

    /** @var UserTable $userTable */
    protected $userTable;

    protected $config;

    /**
     * @var int $libraryId
     */
    protected $libraryId;


    public function __construct(AdapterInterface $dbAdapter, $serviceLocator, $actingUserId, $libraryConfig)
    {
        parent::__construct($dbAdapter, $serviceLocator, $actingUserId);
        $this->config = $libraryConfig;
    }

    /**
     * Get a list of available books from a given library to pass to the CheckoutForm
     * @param array $query
     * @return string[]
     */
    public function getLibraryBookValueOptions(array $query)
    {
        $books = $this->searchBooks($query);
        $return = [];
        foreach ($books as $bookId => $book) {
            $return[(string)$bookId] = (string)$bookId.'-'.$book['author'].' '.$book['title'];
        }
        return $return;
    }

    /**
     * Search for books. Returns a list of books. The query parameters are:
     * search(string), libraryId(int|array), maxResults(int)
     * @param mixed[] $query
     * @return mixed[]
     */
    public function searchBooks($query)
    {
        $filter = new ToAscii();
        if (isset($query['search']) && !is_null($query['search'])) {
            $query['search'] = $filter->filter($query['search']);
        }

        $entities = $this->getBooks();
        $results = [];
        $count = 0;
        foreach ($entities as $bookId => $book) {
            //isAvailable
            if (isset($query['isAvailable']) && is_bool($query['isAvailable']) &&
                $query['isAvailable'] != $book['isAvailable']
            ) {
                continue;
            }

            //isCheckedOut
            if (isset($query['isCheckedOut']) && is_bool($query['isCheckedOut']) &&
                    $query['isCheckedOut'] != $book['isCheckedOut']
                    ) {
                        continue;
                    }

            //library
            if (isset($query['libraryId']) && !is_null($query['libraryId']) && is_numeric($query['libraryId']) &&
                $query['libraryId'] != $book['libraryId']
            ) {
                continue;
            }
            if (isset($query['libraryId']) && !is_null($query['libraryId']) && is_array($query['libraryId']) &&
                !in_array($book['libraryId'], $query['libraryId'])
            ) {
                continue;
            }

            //category
            if (isset($query['category']) && !is_null($query['category']) && is_string($query['category']) &&
                $query['category'] != $book['category']
            ) {
                continue;
            }
            if (isset($query['category']) && !is_null($query['category']) && is_array($query['category']) &&
                !in_array($book['category'], $query['category'])
            ) {
                continue;
            }

            if (isset($query['search']) && !is_null($query['search']) &&
               false === stripos($filter->filter($book['author']), $query['search']) &&
               false === stripos($filter->filter($book['title']), $query['search']) &&
               false === stripos($filter->filter($book['callNumber']), $query['search']) &&
               false === stripos($filter->filter($book['category']), $query['search']) &&
                !(!isset($query['libraryId']) && //@todo test this
                    false === stripos($filter->filter($book['library']['name']), $query['search']))
            ) {
                continue;
            }
            $count++;
            if (isset($query['maxResults']) && is_numeric($query['maxResults']) &&
                $count > $query['maxResults']
            ) {
                break;
            }
            $results[$bookId] = $book;
        }
        return $results;
    }

    /**
     * @return mixed[]
     */
    public function getBooks()
    {
        $libraryId = $this->getLibraryId();
        $cacheKey = is_null($libraryId) ? 'books' : 'books-'.$libraryId;
        if (!is_null($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }
        $entities = $this->getUnlinkedBooks();
        $libraries = $this->getUnlinkedLibraries();
        foreach ($entities as $entityId => $entity) {
            if (key_exists($entity['libraryId'], $libraries)) {
                $entities[$entityId]['library'] = $libraries[$entity['libraryId']];
            } else {
                unset($entities[$entityId]); //all books should be in a library
            }
        }

        $checkouts = $this->getUnlinkedCheckouts();
        foreach ($checkouts as $checkoutId => $checkout) {
            if ($checkout['status'] !== self::CHECKOUT_STATUS_RETURNED &&
                key_exists($checkout['bookId'], $entities)
            ) {
                $entities[$checkout['bookId']]['isAvailable'] = false;
                $entities[$checkout['bookId']]['isCheckedOut'] = true;
                $entities[$checkout['bookId']]['currentCheckout'] = $checkout;
            }
        }
        $this->cacheEntityObjects($cacheKey, $entities, ['book', 'checkout', 'library']);
        return $entities;
    }

    protected function getUnlinkedBooks()
    {
        $libraryId = $this->getLibraryId();
        $cacheKey = is_null($libraryId) ? 'unlinked-books' : 'unlinked-books-'.$libraryId;
        if (!is_null($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }

        if (!is_null($libraryId)) {
            $sql = "SELECT book_id, library_id, author, title, edition, call_number,
category, pages, lang, original_id, publication_id, updated_at, created_by, created_at, updated_by
FROM lib_books
ORDER BY library_id, call_number, category, lang, author, title";
            $results = $this->fetchSome(null, $sql, ['library_id' => $libraryId]);
        } else {
            $sql = "SELECT book_id, library_id, author, title, edition, call_number,
category, pages, lang, original_id, publication_id, updated_at, created_by, created_at, updated_by
FROM lib_books
ORDER BY library_id, call_number, category, lang, author, title";
            $results = $this->fetchSome(null, $sql, null);
        }
        $entities = [];
        foreach ($results as $row) {
            $id = $this->filterDbId($row['book_id']);
            $author = $this->filterDbString($row['author']);
            $title = $this->filterDbString($row['title']);
            $name = $author . ($author ? ' - ' : '') . $title;
            $entities[$id] = [
                'bookId'        => $id,
                'author'        => $author,
                'title'         => $title,
                'edition'       => $this->filterDbString($row['edition']),
                'callNumber'    => $this->filterDbString($row['call_number']),
                'category'      => $this->filterDbString($row['category']),
                'pages'         => $this->filterDbInt($row['pages']),
                'language'      => $this->filterDbString($row['lang']),
                'originalId'    => $this->filterDbId($row['original_id']),
                'libraryId'     => $this->filterDbId($row['library_id']),
                'publicationId' => $this->filterDbId($row['publication_id']),
                'updatedOn'     => $this->filterDbDate($row['updated_at']),
                'updatedBy'     => $this->filterDbId($row['updated_by']),
                'createdOn'     => $this->filterDbDate($row['created_at']),
                'createdBy'     => $this->filterDbId($row['created_by']),

                'name'          => $name,
                'isAvailable'   => true, //available unless proved otherwise
                'isCheckedOut'  => false, //until proved otherwise
                'currentCheckout'=> null,
                'library'       => null,
            ];
        }
        $this->cacheEntityObjects($cacheKey, $entities, ['book']);
        return $entities;
    }

    public function checkinBooks($bookIds)
    {
        $checkouts = $this->getUnlinkedCheckouts();

        $tz = new \DateTimeZone('UTC');
        $today = new \DateTime(null, $tz);

        foreach ($checkouts as $checkoutId => $checkout) {
            if ($checkout['status'] != self::CHECKOUT_STATUS_RETURNED &&
                in_array($checkout['bookId'], $bookIds)
            ) {
                $data = [
                    'checkedInOn'           => $today,
                    'checkedInBy'           => $this->getActingUserId(),
                    'checkedInIp'           => $_SERVER['REMOTE_ADDR'], //@todo there should be a better way to do this
//                     'checkedInUserAgent'    => $this->filterDbString($row['CheckedInUserAgent']),
                ];
                (string)$this->updateEntity('checkout', $checkoutId, $data);
            }
        }
        return true;
    }

    /**
     * @return mixed[]
     */
    public function getLibraries()
    {
        if (!is_null($cache = $this->fetchCachedEntityObjects('libraries'))) {
            return $cache;
        }

        $entities = $this->getUnlinkedLibraries();
        $books = $this->getUnlinkedBooks();
        foreach ($books as $bookId => $book) {
            if (!is_null($book['libraryId']) && key_exists($book['libraryId'], $entities)) {
                $entities[$book['libraryId']]['books'][$bookId] = $book;

                //fill in category statistics
                if (!is_null($book['category'])) {
                    if (!key_exists($book['category'], $entities[$book['libraryId']]['categoryStatistics'])) {
                        $entities[$book['libraryId']]['categoryStatistics'][$book['category']] = 1;
                    } else {
                        $entities[$book['libraryId']]['categoryStatistics'][$book['category']]++;
                    }
                }
            }
        }

        $this->cacheEntityObjects('libraries', $entities, ['library', 'book']);
        return $entities;
    }

    public function getUnlinkedLibraries()
    {
        if (!is_null($cache = $this->fetchCachedEntityObjects('unlinked-libraries'))) {
            return $cache;
        }
        $sql = "SELECT `LibraryId`, `LibraryName`, `Description`, `CallNumberHelpText`,
`CallNumberExplanation`, `FiliationId`, `ContactPerson`, `ContactEmail`,
`UpdatedOn`, `UpdatedBy`, `CreatedOn`, `CreatedBy`
FROM `lib_libraries`
WHERE 1
ORDER BY `FiliationId`, `LibraryName`";

        $results = $this->fetchSome(null, $sql, null);

        $entities = [];
        foreach ($results as $row) {
            $id = $this->filterDbId($row['LibraryId']);
            $entities[$id] = [
                'libraryId'             => $id,
                'name'                  => $this->filterDbString($row['LibraryName']),
                'description'           => $this->filterDbString($row['Description']),
                'callNumberHelpText'    => $this->filterDbString($row['CallNumberHelpText']),
                'callNumberExplanation' => $this->filterDbString($row['CallNumberExplanation']),
                'filiationId'           => $this->filterDbId($row['FiliationId']),
                'contactPerson'         => $this->filterDbId($row['ContactPerson']),
                'contactEmail'          => $this->filterEmailString($row['ContactEmail']),
                'updatedOn'             => $this->filterDbDate($row['UpdatedOn']),
                'updatedBy'             => $this->filterDbId($row['UpdatedBy']),
                'createdOn'             => $this->filterDbDate($row['CreatedOn']),
                'createdBy'             => $this->filterDbId($row['CreatedBy']),

                'books'                 => [], //to be filled in in getLibraries()
                'categoryStatistics'    => [],
            ];
        }
        $this->cacheEntityObjects('unlinked-libraries', $entities, ['library']);
        return $entities;
    }

    /**
     * Return an array of checkouts performed by a certain person
     * @param int $personId
     * @return mixed[]
     */
    public function getCheckoutsForPerson($personId)
    {
        $checkouts = $this->getCheckouts();
        $entities = [];
        foreach ($checkouts as $checkoutId => $checkout) {
            if ($checkout['personId'] == $personId) {
                $entities[$checkoutId] = $checkout;
            }
        }
        return $entities;
    }

    /**
     * Get an array of checkouts for a given libraryId
     * @param int $libraryId
     */
    public function getCheckoutsForLibrary($libraryId)
    {
        $checkouts = $this->getCheckouts();
        $entities = [];
        foreach ($checkouts as $checkoutId => $checkout) {
            if ($checkout['book']['libraryId'] == $libraryId) {
                $entities[$checkoutId] = $checkout;
            }
        }
        return $entities;
    }

    /**
     * @return mixed[]
     */
    public function getCheckouts()
    {
        if (!is_null($cache = $this->fetchCachedEntityObjects('checkouts'))) {
            return $cache;
        }

        $entities = $this->getUnlinkedCheckouts();
        $books = $this->getUnlinkedBooks();
        $libraries = $this->getUnlinkedLibraries();

        foreach ($entities as $entityId => $entity) {
            if (key_exists($entity['bookId'], $books) &&
                key_exists($books[$entity['bookId']]['libraryId'], $libraries)
            ) {
                $books[$entity['bookId']]['library'] = $libraries[$books[$entity['bookId']]['libraryId']];
                $entities[$entityId]['book'] = $books[$entity['bookId']];
            } else {
                unset($entities[$entityId]); //get rid of bad records
            }
        }

        $this->cacheEntityObjects('checkouts', $entities, ['checkout', 'book', 'library']);
        return $entities;
    }

    protected function getUnlinkedCheckouts()
    {
        if (!is_null($cache = $this->fetchCachedEntityObjects('unlinked-checkouts'))) {
            return $cache;
        }

        $sql = "SELECT CheckoutId, PersonId, BookId, CheckedOutOn, CheckedOutBy,
CheckedOutIp, CheckedOutUserAgent, DueOn, TimesRenewed, LastRenewedOn, CheckedInOn,
CheckedInBy, CheckedInIp, CheckedInUserAgent, AdminNotes, AdminNotesUpdatedOn,
AdminNotesUpdatedBy, UpdatedOn, UpdatedBy
FROM lib_checkouts
ORDER BY DueOn;";

        $results = $this->fetchSome(null, $sql, null);

        $tz = new \DateTimeZone('UTC');
        $today = new \DateTime(null, $tz);
        $entities = [];
        foreach ($results as $row) {
            $id = $this->filterDbId($row['CheckoutId']);
            $dueOn = $this->filterDbDate($row['DueOn']);
            $checkedIn = $this->filterDbDate($row['CheckedInOn']);
            $status = null;
            if (!is_null($checkedIn)) {
                $status = self::CHECKOUT_STATUS_RETURNED;
            } elseif (!is_null($dueOn) && $today >= $dueOn) {
                $status = self::CHECKOUT_STATUS_OVERDUE;
            } else {
                $status = self::CHECKOUT_STATUS_CHECKED_OUT;
            }

            $entities[$id] = [
                'checkoutId'            => $id,
                'personId'              => $this->filterDbId($row['PersonId']),
                'bookId'                => $this->filterDbId($row['BookId']),
                'checkedOutOn'          => $this->filterDbDate($row['CheckedOutOn']),
                'checkedOutBy'          => $this->filterDbId($row['CheckedOutBy']),
                'checkedOutIp'          => $this->filterDbString($row['CheckedOutIp']),
                'checkedOutUserAgent'   => $this->filterDbString($row['CheckedOutUserAgent']),
                'dueOn'                 => $dueOn,
                'timesRenewed'          => $this->filterDbInt($row['TimesRenewed']),
                'lastRenewedOn'         => $this->filterDbDate($row['LastRenewedOn']),
                'checkedInOn'           => $checkedIn,
                'checkedInBy'           => $this->filterDbId($row['CheckedInBy']),
                'checkedInIp'           => $this->filterDbString($row['CheckedInIp']),
                'checkedInUserAgent'    => $this->filterDbString($row['CheckedInUserAgent']),
                'adminNotes'            => $this->filterDbString($row['AdminNotes']),
                'adminNotesUpdatedOn'   => $this->filterDbDate($row['AdminNotesUpdatedOn']),
                'adminNotesUpdatedBy'   => $this->filterDbId($row['AdminNotesUpdatedBy']),
                'updatedOn'             => $this->filterDbDate($row['UpdatedOn']),
                'updatedBy'             => $this->filterDbId($row['UpdatedBy']),

                'status'                => $status,
                'book'                  => null,
            ];
        }

        $this->cacheEntityObjects('unlinked-checkouts', $entities, ['checkout']);
        return $entities;
    }

    /**
     * Process checkout data before putting into the database. Sets checkedOutOn and checkedOutBy.
     * @param mixed[] $data
     * @param mixed[] $entityData
     * @return mixed[]
     */
    protected function preprocessCheckout($data, $entityData, $action)
    {
        if ($action == self::ENTITY_ACTION_CREATE) {
            if (!isset($data['checkedOutOn'])) {
                $date = new \DateTime(null, new \DateTimeZone('UTC'));
                $data['checkedOutOn'] = $date;
            }

            if (!isset($data['checkedOutBy'])) {
                $data['checkedOutBy'] = $this->getActingUserId();
            }

            //calculate the dueDate
            if (!isset($data['dueOn'])) {
//                 $library = $this->getLibrary($data['libraryId']);
                $daysToLend = 14;
                $tz = new \DateTimeZone('UTC');
                $dueDate = new Carbon(null, $tz);
                $dueDate->addDays($daysToLend)
                    ->endOfDay();
                $data['dueOn'] = $dueDate;
            }
        }
        return $data;
    }

    /**
     *
     * @param int $id
     * @return mixed[]
     */
    public function getBook($id)
    {
        $entities = $this->getBooks();

        if (!isset($entities[$id]) || !($entity = $entities[$id])) {
            return null;
        }

        return $entity;
    }

    /**
     *
     * @param int $id
     * @return mixed[]
     */
    public function getLibrary($id)
    {
        $entities = $this->getLibraries();
        if (!key_exists($id, $entities) || !($entity = $entities[$id])) {
            return null;
        }

        return $entity;
    }

    /**
     *
     * @param int $id
     * @return mixed[]
     */
    public function getCheckout($id)
    {
        $entities = $this->getCheckouts();

        if (!isset($entities[$id]) || !($entity = $entities[$id])) {
            return null;
        }

        return $entity;
    }

    /**
     * Get the current libraryId value
     * @return int
     */
    public function getLibraryId()
    {
        return $this->libraryId;
    }

    /**
     * Set the current library
     * @param int $libraryId
     * @return self
     */
    public function setLibraryId($libraryId)
    {
        $this->libraryId = $libraryId;
        return $this;
    }
}
