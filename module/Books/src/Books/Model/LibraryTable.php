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

    const BOOK_VALUE_OPTIONS_LABEL_ID_AUTHOR_TITLE = 'id-author-title';
    const BOOK_VALUE_OPTIONS_LABEL_ID = 'id';

    const BOOK_VALUE_OPTIONS_LABEL_OPTIONS = [
        self::BOOK_VALUE_OPTIONS_LABEL_ID_AUTHOR_TITLE,
        self::BOOK_VALUE_OPTIONS_LABEL_ID
    ];
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

    public function getLibraryValueOptions()
    {
        $libraries = $this->getUnlinkedLibraries();
        $valueOptions = [];
        foreach ($libraries as $libraryId => $library) {
            $valueOptions[$libraryId] = $library['name'];
        }
        return $valueOptions;
    }

    /**
     * Get a list of available books from a given library to pass to the CheckoutForm
     * @param array $query
     * @return string[]
     */
    public function getLibraryBookValueOptions(array $query, $options = [])
    {
        $labelOption = self::BOOK_VALUE_OPTIONS_LABEL_ID_AUTHOR_TITLE;
        if (key_exists('labelOption', $options) &&
            in_array($options['labelOption'], self::BOOK_VALUE_OPTIONS_LABEL_OPTIONS)
        ) {
            $labelOption = $options['labelOption'];
        }

        $books = $this->searchBooks($query);
        $return = [];
        foreach ($books as $bookId => $book) {
            $withinLibraryId = (string)$book['withinLibraryId'];
            switch ($labelOption) {
                case self::BOOK_VALUE_OPTIONS_LABEL_ID:
                    $return[$bookId] = $withinLibraryId;
                    break;
                case self::BOOK_VALUE_OPTIONS_LABEL_ID_AUTHOR_TITLE:
                default:
                    $return[$bookId] = $withinLibraryId.'-'.$book['author'].' '.$book['title'];
                    break;
            }
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

    /**
     * @todo add an optional $libraryId param for when we're not in a library route
     * @return mixed|NULL|boolean[][]|NULL[][]|unknown[][]|string[][]|\SionModel\Db\Model\NULL[][]|number[][]|DateTime[][]
     */
    protected function getUnlinkedBooks()
    {
        $libraryId = $this->getLibraryId();
        $cacheKey = is_null($libraryId) ? 'unlinked-books' : 'unlinked-books-'.$libraryId;
        if (!is_null($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }

        if (!is_null($libraryId)) {
            $sql = "SELECT book_id, library_id, collection_id, author, title, edition, call_number,
category, pages, lang, original_id, publication_id, updated_at, created_by, created_at, updated_by,
inactivation_reason, is_active,
isbn, copyright_year, publisher, publisher_place, public_tags, admin_tags,
public_notes, public_notes_updated_at, public_notes_updated_by, admin_notes, admin_notes_updated_at,
admin_notes_updated_by
FROM lib_books
WHERE (library_id = ?)
ORDER BY library_id, call_number, category, lang, author, title";
            $results = $this->fetchSome(null, $sql, [$libraryId]);
        } else {
            $sql = "SELECT book_id, library_id, collection_id, author, title, edition, call_number,
category, pages, lang, original_id, publication_id, updated_at, created_by, created_at, updated_by,
inactivation_reason, is_active, isbn, copyright_year, publisher, publisher_place, public_tags,
admin_tags, public_notes, public_notes_updated_at, public_notes_updated_by, admin_notes,
admin_notes_updated_at, admin_notes_updated_by
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
            $isActive = $this->filterDbBool($row['is_active']);
            $entities[$id] = [
                'bookId'                => $id,
                'collectionId'          => $this->filterDbId($row['collection_id']),
                'author'                => $author,
                'title'                 => $title,
                'edition'               => $this->filterDbString($row['edition']),
                'callNumber'            => $this->filterDbString($row['call_number']),
                'category'              => $this->filterDbString($row['category']),
                'pages'                 => $this->filterDbInt($row['pages']),
                'language'              => $this->filterDbString($row['lang']),
                'withinLibraryId'       => $this->filterDbId($row['original_id']),
                'libraryId'             => $this->filterDbId($row['library_id']),
                'publicationId'         => $this->filterDbId($row['publication_id']),
                'isActive'              => $isActive,
                'inactivationReason'    => $this->filterDbString($row['inactivation_reason']),
                'updatedOn'             => $this->filterDbDate($row['updated_at']),
                'updatedBy'             => $this->filterDbId($row['updated_by']),
                'createdOn'             => $this->filterDbDate($row['created_at']),
                'createdBy'             => $this->filterDbId($row['created_by']),

                'copyrightYear'         => $this->filterDbInt($row['copyright_year']),
                'publisher'             => $this->filterDbString($row['publisher']),
                'publishingPlace'       => $this->filterDbString($row['publisher_place']),
                'isbn'                  => $this->filterDbString($row['isbn']),
                'keywords'              => $this->filterDbArray($row['public_tags']),
                'publicNotes'           => $this->filterDbString($row['public_notes']),
                'publicNotesUpdatedOn'  => $this->filterDbDate($row['public_notes_updated_at']),
                'publicNotesUpdatedBy'  => $this->filterDbId($row['public_notes_updated_by']),
                'adminTags'             => $this->filterDbArray($row['admin_tags']),
                'adminNotes'            => $this->filterDbString($row['admin_notes']), //store source info here
                'adminNotesUpdatedOn'   => $this->filterDbDate($row['admin_notes_updated_at']),
                'adminNotesUpdatedBy'   => $this->filterDbId($row['admin_notes_updated_by']),

                'name'                  => $name,
                'isAvailable'           => $isActive, //available unless proved otherwise
                'isCheckedOut'          => false, //until proved otherwise
                'currentCheckout'       => null,
                'library'               => null,
            ];
        }
        $this->cacheEntityObjects($cacheKey, $entities, ['book']);
        return $entities;
    }


    /**
     * Return a lookup associated array keyed by the library's id, mapped to the bookId
     * @return number[]
     */
    public function getLibraryBookLookup($libraryId = null)
    {
        if (is_null($libraryId)) {
            $libraryId = $this->getLibraryId();
        }
        if (is_null($libraryId)) {
            throw new \InvalidArgumentException('There must by a libraryId set to get the active library book lookup list.');
        }
        $entities = $this->getUnlinkedBooks();
        $bookLookup = [];
        foreach ($entities as $bookId => $book) {
            if ($book['libraryId'] == $libraryId && $book['isActive'] &&
                !is_null($book['withinLibraryId'])
            ) {
                $bookLookup[$book['withinLibraryId']] = $bookId;
            }
        }
        return $bookLookup;
    }

    /**
     * Returns the bookId's passed to the function. If requested, a book that wasn't checked
     * out will be first checked out and then back in.
     * @param array $bookIds
     * @return boolean
     */
    public function checkinBooks($libraryId, array $withinLibraryIds, $createCheckoutsForBooksWithNoCheckouts = true)
    {
        $checkouts = $this->getUnlinkedCheckouts();
        $library = $this->getLibrary($libraryId);

        $tz = new \DateTimeZone('UTC');
        $today = new \DateTime(null, $tz);

        $bookLookup = $this->getLibraryBookLookup($libraryId);
        $booksToCheckin = [];
        foreach ($withinLibraryIds as $withinLibraryId) {
            if (key_exists($withinLibraryId, $bookLookup)) {
                $booksToCheckin[$bookLookup[$withinLibraryId]] = false;
            }
        }

        //Check in all the outstanding checkouts
        foreach ($checkouts as $checkoutId => $checkout) {
            if ($checkout['status'] != self::CHECKOUT_STATUS_RETURNED &&
                key_exists($checkout['bookId'], $booksToCheckin)
            ) {
                $data = [
                    'checkedInOn'           => $today,
                    'checkedInBy'           => $this->getActingUserId(),
                    'checkedInIp'           => $_SERVER['REMOTE_ADDR'], //@todo there should be a better way to do this
//                     'checkedInUserAgent'    => $this->filterDbString($row['CheckedInUserAgent']),
                ];
                //don't refresh the cache
                (string)$this->updateEntity('checkout', $checkoutId, $data, [], false);
                $booksToCheckin[$checkout['bookId']] = true;
            }
        }

        //If any books are left, create new checkout records for them
        if ($createCheckoutsForBooksWithNoCheckouts) {
            foreach ($booksToCheckin as $bookId => $alreadyCheckedIn) {
                if (!$alreadyCheckedIn) {
                    $data = [
                        'personId'              => $library['defaultCheckoutPerson'],
                        'bookId'                => $bookId,
                        'checkedOutOn'          => $today,
                        'checkedOutBy'          => $this->getActingUserId(),
                        'checkedOutIp'          => $_SERVER['REMOTE_ADDR'],
//                         'checkedOutUserAgent'   => $this->filterDbString($row['CheckedOutUserAgent']),
                        'dueOn'                 => $today,

                        'checkedInOn'           => $today,
                        'checkedInBy'           => $this->getActingUserId(),
                        'checkedInIp'           => $_SERVER['REMOTE_ADDR'], //@todo there should be a better way to do this
//                     'checkedInUserAgent'    => $this->filterDbString($row['CheckedInUserAgent']),
                    ];
                    (string)$this->createEntity('checkout', $data, false);
                    $booksToCheckin[$checkout['bookId']] = true;
                }
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

        foreach ($entities as $entityId => $entity) {
            ksort($entities[$entityId]['categoryStatistics']);
        }

        //check if the books are checked out
        $checkouts = $this->getUnlinkedCheckouts();
        foreach ($checkouts as $checkoutId => $checkout) {
            if (is_null($checkout['checkedInOn']) && key_exists($checkout['bookId'], $books) &&
                key_exists($checkout['bookId'], $entities[$books[$checkout['bookId']]['libraryId']]['books'])
            ) { //book is checked out
                $bookEntry = &$entities[$books[$checkout['bookId']]['libraryId']]['books'][$checkout['bookId']];
                $bookEntry['isAvailable'] = false;
                $bookEntry['checkedOut'] = true;
                $bookEntry['currentCheckout'] = $checkout;
            }
        }

        $this->cacheEntityObjects('libraries', $entities, ['library', 'book', 'checkout']);
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

                'defaultCheckoutPerson' => 604, //@todo make a new column for this
                'defaultCollection'     => null, //@todo make a new column for this

                'books'                 => [], //to be filled in, in getLibraries()
                'categoryStatistics'    => [],
            ];
        }
        $this->cacheEntityObjects('unlinked-libraries', $entities, ['library']);
        return $entities;
    }

    public function getLibraryBooksStatuses($libraryId = null)
    {
        if (is_null($libraryId)) {
            $libraryId = $this->getLibraryId();
        }
        if (is_null($libraryId) || !is_numeric($libraryId)) {
            throw new \InvalidArgumentException('getLibraryBooksStatuses requires an active libraryId');
        }
        $library = $this->getLibrary($libraryId);
        $entities = [];
        foreach ($library['books'] as $entityId => $entity) {
            $currentCheckout = $entity['currentCheckout'];
            $entities[$entity['withinLibraryId']] = [
                'title'     => $entity['title'],
                'author'    => $entity['author'],
                'isCheckedOut' => !is_null($currentCheckout),
                'checkedOutBy' => !is_null($currentCheckout) ? $currentCheckout['personId'] : null, //@todo give the person's name
                'checkedOutOn' => !is_null($currentCheckout) ? $currentCheckout['checkedOutOn'] : null, //@todo convert to Json date format
            ];
        }
        return $entities;
    }

    /**
     * @return mixed[]
     */
    public function getLibraryImports()
    {
        if (!is_null($libraryId = $this->getLibraryId())) {
            $cacheKey = 'library-imports-'.$libraryId;
        } else {
            $cacheKey = 'library-imports';
        }
        if (!is_null($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }

        if (is_null($libraryId)) {
            $sql = "SELECT ImportId, ImportName, LibraryId, Description, Status,
ColumnMapping, Worksheet, FilePath, IsCompleteImport, BooksUpdated, BooksCreated, BooksDeleted,
UpdatedOn, UpdatedBy, CreatedOn, CreatedBy
FROM lib_imports
ORDER BY CreatedOn DESC";
            $results = $this->fetchSome(null, $sql, null);
        } else {
            $sql = "SELECT ImportId, ImportName, LibraryId, Description, Status,
ColumnMapping, Worksheet, FilePath, IsCompleteImport, BooksUpdated, BooksCreated, BooksDeleted,
UpdatedOn, UpdatedBy, CreatedOn, CreatedBy
FROM lib_imports
WHERE (LibraryId = ?)
ORDER BY CreatedOn DESC";
            $results = $this->fetchSome(null, $sql, [$libraryId]);
        }

        $entities = [];
        foreach ($results as $row) {
            $id = $this->filterDbId($row['ImportId']);
            $columnMappingSerialized = $this->filterDbString($row['ColumnMapping']);
            $filePath = $this->filterDbString($row['FilePath']);
            $fileAvailable = file_exists($filePath);
            $entities[$id] = [
                'importId'                  => $id,
                'name'                      => $this->filterDbString($row['ImportName']),
                'libraryId'                 => $this->filterDbId($row['LibraryId']),
                'status'                    => $this->filterDbString($row['Status']),
                'description'               => $this->filterDbString($row['Description']),
                'columnMappingSerialized'   => $columnMappingSerialized,
                'worksheet'                 => $this->filterDbString($row['Worksheet']),
                'filePath'                  => $filePath,
                'isCompleteImport'          => $this->filterDbBool($row['IsCompleteImport']),
                'booksUpdated'              => $this->filterDbInt($row['BooksUpdated']),
                'booksCreated'              => $this->filterDbInt($row['BooksCreated']),
                'booksInactivated'              => $this->filterDbInt($row['BooksDeleted']),
                'createdOn'                 => $this->filterDbDate($row['CreatedOn']),
                'createdBy'                 => $this->filterDbId($row['CreatedBy']),
                'updatedOn'                 => $this->filterDbDate($row['UpdatedOn']),
                'updatedBy'                 => $this->filterDbId($row['UpdatedBy']),

                'columnMapping'             => unserialize($columnMappingSerialized),
                'fileAvailable'             => $fileAvailable,
            ];
        }

        $this->cacheEntityObjects($cacheKey, $entities, ['library-import']);
        return $entities;
    }

    /**
     *
     * @param int $id
     * @return mixed[]
     */
    public function getLibraryImport($id)
    {
        $entities = $this->getLibraryImports();

        if (!isset($entities[$id]) || !($entity = $entities[$id])) {
            return null;
        }

        return $entity;
    }

    protected function preprocessLibraryImport($data, $entityData, $action)
    {
        if (isset($data['columnMapping'])) {
            if (!is_null($data['columnMapping'])) {
                $data['columnMappingSerialized'] = serialize($data['columnMapping']);
            } else {
                $data['columnMappingSerialized'] = null;
            }
        }
        return $data;
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
ORDER BY CheckedInOn, CheckedOutOn DESC;";

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
