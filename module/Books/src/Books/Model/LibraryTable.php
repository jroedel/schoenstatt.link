<?php
namespace Books\Model;

use SionModel\Db\Model\SionTable;
use JUser\Model\UserTable;
use Zend\Db\Adapter\AdapterInterface;
use SionModel\Filter\ToAscii;
use Carbon\Carbon;
use Zend\Db\Sql\Select;

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

    const DEFAULT_CHECKOUT_TIME_PERIOD_IN_DAYS = 14;

    const MAIN_SHOW_DISPLAY_VALUE_OPTIONS = [
        self::MAIN_SHOW_DISPLAY_SHOW_CATEGORIES => 'Show categories',
        self::MAIN_SHOW_DISPLAY_SHOW_COLLECTIONS => 'Show collections',
        self::MAIN_SHOW_DISPLAY_SHOW_COLLECTIONS_CATEGORIES => 'Show collections and categories',
//         self::MAIN_SHOW_DISPLAY_SHOW_LANGUAGES => 'Show languages',
    ];
    const MAIN_SHOW_DISPLAY_DEFAULT = self::MAIN_SHOW_DISPLAY_SHOW_CATEGORIES;
    const MAIN_SHOW_DISPLAY_SHOW_CATEGORIES = 'show-categories';
    const MAIN_SHOW_DISPLAY_SHOW_COLLECTIONS = 'show-collections';
    const MAIN_SHOW_DISPLAY_SHOW_COLLECTIONS_CATEGORIES = 'show-collections-categories';
//     const MAIN_SHOW_DISPLAY_SHOW_LANGUAGES = 'show-languages';

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
        if (isset($options['labelOption']) &&
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

    public function getCollectionValueOptions($libraryId = null)
    {
        if (!isset($libraryId) && null === ($libraryId = $this->getLibraryId())) {
            throw new \InvalidArgumentException('This function can only be called for a specific library');
        }
        $collections = $this->getUnlinkedCollections();
        $valueOptions = [];
        foreach ($collections as $collectionId => $collection) {
            $valueOptions[$collectionId] = $collection['name'];
        }
        return $valueOptions;
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
        if (isset($query['search']) && null !== $query['search']) {
            $query['search'] = $filter->filter($query['search']);
        }

        $entities = $this->getBooks();
        $results = [];
        $count = 0;
        foreach ($entities as $bookId => $book) {
            //isActive, by default we don't include inactive books,
            //if query['isActive'] is null, we include everything, otherwise whatever it says it should be
            if (!isset($query['isActive']) && !$book['isActive']
            ) {
                continue;
            } else if (isset($query['isActive']) &&
                $query['isActive'] !== $book['isActive']
            ) {
                continue;
            }

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
            if (isset($query['libraryId']) && is_numeric($query['libraryId']) &&
                $query['libraryId'] != $book['libraryId']
            ) {
                continue;
            }
            if (isset($query['libraryId']) && is_array($query['libraryId']) &&
                !in_array($book['libraryId'], $query['libraryId'])
            ) {
                continue;
            }


            //collection
            if (isset($query['collectionId']) && is_numeric($query['collectionId']) &&
                $query['collectionId'] != $book['collectionId']
            ) {
                continue;
            }
            if (isset($query['collectionId']) && is_array($query['collectionId']) &&
                !in_array($book['collectionId'], $query['collectionId'])
            ) {
                continue;
            }

            //category
            if (isset($query['category']) && is_string($query['category']) &&
                $query['category'] != $book['category']
            ) {
                continue;
            }
            if (isset($query['category']) && is_array($query['category']) &&
                !in_array($book['category'], $query['category'])
            ) {
                continue;
            }

            if (isset($query['search']) &&
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
        $cacheKey = !isset($libraryId) ? 'books' : 'books-'.$libraryId;
        if (null !== $cache = $this->fetchCachedEntityObjects($cacheKey)) {
            return $cache;
        }
        $entities = $this->getUnlinkedBooks();
        $libraries = $this->getUnlinkedLibraries();
        foreach ($entities as $entityId => $entity) {
            if (isset($libraries[$entity['libraryId']])) {
                $entities[$entityId]['library'] = $libraries[$entity['libraryId']];
            } else {
                unset($entities[$entityId]); //all books should be in a library
            }
        }

        $checkouts = $this->getUnlinkedCheckouts();
        foreach ($checkouts as $checkoutId => $checkout) {
            if ($checkout['status'] !== self::CHECKOUT_STATUS_RETURNED &&
                    isset($entities[$checkout['bookId']])
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
     * Get a standardized select object to retrieve records from the database
     * @return \Zend\Db\Sql\Select
     */
    protected function getBookSelectPrototype()
    {
        static $select;
        if (!isset($select)) {
            $select = new Select('lib_books');
//         $select->columns(['TheMonth' => new Expression('MONTH(`modified_on`)'), 'TheYear' => new Expression('YEAR(`modified_on`)'), 'Count' => new Expression('Count(*)')]);
            $select->columns(['book_id', 'library_id', 'collection_id', 'author', 'title', 'edition', 'call_number',
'category', 'pages', 'lang', 'original_id', 'publication_id', 'updated_at', 'created_by', 'created_at', 'updated_by',
'inactivation_reason', 'is_active', 'isbn', 'copyright_year', 'publisher', 'publisher_place', 'public_tags', 'admin_tags',
'public_notes', 'public_notes_updated_at', 'public_notes_updated_by', 'admin_notes', 'admin_notes_updated_at',
'admin_notes_updated_by']);
//         $select->group(['TheMonth', 'TheYear']);
//         $select->where($predicate->in('ChangedEntity', $tableEntities));
            $select->order(['library_id', 'call_number', 'category', 'lang', 'author', 'title']);
        }

        return clone $select;
    }

    public function getSimpleBook($id)
    {
        static $gateway;
        if (!isset($gateway)) {
            $gateway = $this->getTableGateway('lib_books');
        }
        $select = $this->getBookSelectPrototype();
        $select->where(['book_id' => $id]);
        /** @var ResultSet $result */
        $result = $gateway->selectWith($select);
        $results = $result->toArray();

        if (!isset($results[0])) {
            return null;
        }
        return $this->processBookRow($results[0]);
    }

    /**
     * @return mixed|NULL|boolean[][]|NULL[][]|unknown[][]|string[][]|\SionModel\Db\Model\NULL[][]|number[][]|DateTime[][]
     */
    protected function getUnlinkedBooks()
    {
        $libraryId = $this->getLibraryId();
        $cacheKey = !isset($libraryId) ? 'unlinked-books' : 'unlinked-books-'.$libraryId;
        if (null !== $cache = $this->fetchCachedEntityObjects($cacheKey)) {
            return $cache;
        }

        $gateway = $this->getTableGateway('lib_books');
        if (isset($libraryId)) {
            $select = $this->getBookSelectPrototype();
            $select->where(['library_id' => $libraryId]);
            $results = $gateway->selectWith($select);
        } else {
            $select = $this->getBookSelectPrototype();
            $results = $gateway->selectWith($select);
        }
        $entities = [];
        foreach ($results as $row) {
            $processedRow = $this->processBookRow($row);
            $entities[$processedRow['bookId']] = $processedRow;
        }
        $this->cacheEntityObjects($cacheKey, $entities, ['book']);
        return $entities;
    }

    protected function processBookRow($row)
    {
        $id = $this->filterDbId($row['book_id']);
        $author = $this->filterDbString($row['author']);
        $title = $this->filterDbString($row['title']);
        $name = $author . ($author ? ' - ' : '') . $title;
        $isActive = $this->filterDbBool($row['is_active']);
        $processedRow = [
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
        return $processedRow;
    }

    /**
     * Return a lookup associated array keyed by the library's id, mapped to the bookId
     * @return number[]
     */
    public function getLibraryBookLookup($libraryId = null, $includeInactive = false)
    {
        if (!isset($libraryId)) {
            $libraryId = $this->getLibraryId();
        }
        if (!isset($libraryId)) {
            throw new \InvalidArgumentException('There must by a libraryId set to get the active library book lookup list.');
        }
        $entities = $this->getUnlinkedBooks();
        $bookLookup = [];
        foreach ($entities as $bookId => $book) {
            if ($book['libraryId'] == $libraryId && ($book['isActive'] || $includeInactive) &&
                isset($book['withinLibraryId'])
            ) {
                $bookLookup[$book['withinLibraryId']] = $bookId;
            }
        }
        return $bookLookup;
    }

    public function getLibraryBookLookupWithActive($libraryId = null)
    {
        if (!isset($libraryId)) {
            $libraryId = $this->getLibraryId();
        }
        if (!isset($libraryId)) {
            throw new \InvalidArgumentException('There must by a libraryId set to get the active library book lookup list.');
        }
        $select = new Select('lib_books');
        //         $select->columns(['TheMonth' => new Expression('MONTH(`modified_on`)'), 'TheYear' => new Expression('YEAR(`modified_on`)'), 'Count' => new Expression('Count(*)')]);
        $select->columns(['book_id',  'original_id',  'is_active']);
        $select->where(['library_id' => $libraryId]);
        $select->order(['original_id', 'book_id']);
        $gateway = $this->getTableGateway('lib_books');
        $results = $gateway->selectWith($select);

        $lookup = [];
        foreach ($results as $row) {
            $lookup[$row['original_id']] = [
                'bookId' => $row['book_id'],
                'isActive' => $this->filterDbBool($row['is_active']),
            ];
        }
        return $lookup;
    }

    /**
     * Checks in the bookId's passed to the function. If requested, a book that wasn't checked
     * out will be first checked out and then back in.
     * @param array $bookIds
     * @return boolean
     */
    public function checkinBooks(array $bookIds, $createCheckoutsForBooksWithNoCheckouts = true)
    {
        $checkouts = $this->getUnlinkedCheckouts();
        $libraries = $this->getUnlinkedLibraries();

        $tz = new \DateTimeZone('UTC');
        $today = new \DateTime(null, $tz);

        $books = $this->getUnlinkedBooks();
        $booksToCheckin = [];
        foreach ($bookIds as $bookId) {
            if (isset($books[$bookId])) {
                $booksToCheckin[$bookId] = false;
            }
        }

        //Check in all the outstanding checkouts
        foreach ($checkouts as $checkoutId => $checkout) {
            if ($checkout['status'] != self::CHECKOUT_STATUS_RETURNED &&
                isset($booksToCheckin[$checkout['bookId']])
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
                        'personId'              => $libraries[$books[$bookId]['libraryId']]['defaultCheckoutPersonId'],
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
        $this->removeDependentCacheItems('checkout'); // force cache refresh

        return true;
    }

    /**
     * Checks in the withinLibraryId's passed to the function. If requested, a book that wasn't checked
     * out will be first checked out and then back in.
     * @param array $bookIds
     * @return boolean
     */
    public function checkinWithinLibraryBooks($libraryId, array $withinLibraryIds, $createCheckoutsForBooksWithNoCheckouts = true)
    {
        $checkouts = $this->getUnlinkedCheckouts();
        $library = $this->getLibrary($libraryId);

        $tz = new \DateTimeZone('UTC');
        $today = new \DateTime(null, $tz);

        $bookLookup = $this->getLibraryBookLookup($libraryId);
        $bookIds = [];
        foreach ($withinLibraryIds as $withinLibraryId) {
            if (isset($bookLookup[$withinLibraryId])) {
                $bookIds[] = $bookLookup[$withinLibraryId];
            }
        }

        return $this->checkinBooks($bookIds, $createCheckoutsForBooksWithNoCheckouts);
    }

    /**
     * Checks out books listed in $data. Data must contain 'personId' and 'withinLibraryIds' keys.
     * If any of the books have open checkout records, they will be checked in first.
     * If any of the bookIds don't exist within the library, an exception will be thrown before doing any checkouts.
     * @param int $libraryId
     * @param array $data
     * @throws \InvalidArgumentException
     * @return boolean|number[]
     */
    public function checkoutWithinLibraryBooks($libraryId, array $data)
    {
        if (!is_array($data) || !isset($data['personId']) ||
            !isset($data['withinLibraryIds'])
        ) {
            throw new \InvalidArgumentException('data must be an associative array containing at least \'personId\' and \'withinLibraryIds\' keys.');
        }
        $withinLibraryIdLookup = $this->getLibraryBookLookup($libraryId);
        $books = $this->getUnlinkedBooks();

        //confirm all bookIds are valid
        $bookIds = [];
        $withinLibraryIdErrors = [];
        foreach ($data['withinLibraryIds'] as $withinLibraryId)
        {
            if (!isset($withinLibraryIdLookup[$withinLibraryId]) ||
                false === $books[$withinLibraryIdLookup[$withinLibraryId]]['isActive'] //make sure book is active
            ) {
                $withinLibraryIdErrors[] = $withinLibraryId;
            } else {
                $bookIds[] = $withinLibraryIdLookup[$withinLibraryId];
            }
        }

        if (!empty($withinLibraryIdErrors)) {
            throw new \InvalidArgumentException(sprintf("The following book(s) don't exist or are inactivated: (%s) Please try again.",
                implode(', ', $withinLibraryIdErrors)));
        }

        //first checkin books if any were formerly checked out
        $this->checkinBooks($bookIds, false);

        //create a prototype in order to allow for any possible other fields to be inserted into the checkouts table
        //many other fields are filled in within preprocessCheckout
        $paramsPrototype = $data;
        unset($paramsPrototype['withinLibraryIds']);
        if (isset($paramsPrototype['checkedInOn']) && !$paramsPrototype['checkedInOn'] instanceof \DateTime) {
            unset($paramsPrototype['checkedInOn']);
        }

        //check out books
        $badValues = [];
        foreach ($bookIds as $bookId) {
            $currentBook = $paramsPrototype;
            $currentBook['bookId'] = $bookId;
            if (!$newId = $this->createEntity('checkout', $currentBook, false))
            {
                $badValues[] = $bookId;
            }
        }
        $this->removeDependentCacheItems('checkout'); //force cache refresh
        return empty($badValues) ? true : $badValues;
    }

    /**
     * @todo fill in monthlyCheckoutStatistics
     * @return mixed[]
     */
    public function getLibraries()
    {
        if (null !== ($cache = $this->fetchCachedEntityObjects('libraries'))) {
            return $cache;
        }

        $entities = $this->getUnlinkedLibraries();
        $books = $this->getUnlinkedBooks();
        foreach ($books as $bookId => $book) {
            if ($book['isActive'] && isset($book['libraryId']) && //don't do anything here with inactive books
                isset($entities[$book['libraryId']])
            ) {
                $entities[$book['libraryId']]['books'][$bookId] = $book; //@todo I don't think we need this and it makes the cache much bigger

                $libraryId = $book['libraryId'];
                //fill in statistics
                $collectionId = 0;
                if (isset($book['collectionId'])) {
                    $collectionId = $book['collectionId'];
                }
                $category = '';
                if (isset($book['category'])) {
                    $category = $book['category'];
                }
                //statistics by category without respect for collections
                if (!isset($entities[$book['libraryId']]['categoryStatistics'][$category])) {
                    $entities[$libraryId]['categoryStatistics'][$category] = 1;
                } else {
                    $entities[$libraryId]['categoryStatistics'][$category]++;
                }

                //statistics on 2 levels: collection, category
                if (!isset($entities[$book['libraryId']]['collectionCategoryStatistics'][$collectionId])) {
                    $entities[$libraryId]['collectionCategoryStatistics'][$collectionId] = [];
                }
                if (!isset($entities[$book['libraryId']]['collectionCategoryStatistics'][$collectionId][$category])) {
                    $entities[$libraryId]['collectionCategoryStatistics'][$collectionId][$category] = 1;
                } else {
                    $entities[$libraryId]['collectionCategoryStatistics'][$collectionId][$category]++;
                }
            }
        }

        //sort stats
        foreach ($entities as $entityId => $entity) {
            ksort($entities[$entityId]['categoryStatistics']);
            ksort($entities[$entityId]['collectionCategoryStatistics']);
            foreach ($entities[$entityId]['collectionCategoryStatistics'] as $collectionId => $categories) {
                ksort($entities[$entityId]['collectionCategoryStatistics'][$collectionId]);
            }
        }

        //check if the books are checked out
        $checkouts = $this->getUnlinkedCheckouts();
        foreach ($checkouts as $checkoutId => $checkout) {
            if (null === $checkout['checkedInOn'] && isset($books[$checkout['bookId']]) &&
                isset($entities[$books[$checkout['bookId']]['libraryId']]['books'][$checkout['bookId']])
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
        if (null !== ($cache = $this->fetchCachedEntityObjects('unlinked-libraries'))) {
            return $cache;
        }
        $sql = "SELECT `LibraryId`, `LibraryName`, `Description`, `CallNumberHelpText`,
`CallNumberExplanation`, `FiliationId`, `ContactPerson`, `ContactEmail`, `MainShowDisplay`,
`UseCollections`, `AllowCollectionlessBooks`, `MainCollectionId`, `RequireCallNumbers`,
`CallNumberRegex`, `EnforceCallNumberRegex`, `LabelLine1`, `LabelLine2`, `LabelLine3`,
`BarcodeText`, `CreateCheckoutsIfCheckingInANonCheckedOutBook`, `DefaultCheckoutPersonId`,
`DefaultCheckoutTimePeriodInDays`, `EnableCheckouts`, `IsPublicallyListed`, `CheckoutPersonListKind`,
`IsActive`, `AdminNotes`, `AdminNotesUpdatedOn`, `AdminNotesUpdatedBy`,
`UpdatedOn`, `UpdatedBy`, `CreatedOn`, `CreatedBy`,
(SELECT COUNT(*) FROM `lib_books` b WHERE (`is_active` = TRUE AND b.`library_id` = l.LibraryId)) AS BookCount
FROM `lib_libraries` l
WHERE 1
ORDER BY `LibraryName`";

        $results = $this->fetchSome(null, $sql, null);

        $entities = [];
        foreach ($results as $row) {
            $id = $this->filterDbId($row['LibraryId']);
            $entity = [
                'libraryId'             => $id,
                'name'                  => $this->filterDbString($row['LibraryName']),
                'description'           => $this->filterDbString($row['Description']),
                'callNumberHelpText'    => $this->filterDbString($row['CallNumberHelpText']),
                'callNumberExplanation' => $this->filterDbString($row['CallNumberExplanation']),
                'filiationId'           => $this->filterDbId($row['FiliationId']),
                'contactPersonId'       => $this->filterDbId($row['ContactPerson']),
                'contactEmail'          => $this->filterEmailString($row['ContactEmail']),
                'mainShowDisplay'       => $this->filterDbString($row['MainShowDisplay']),
                'useCollections'        => $this->filterDbBool($row['UseCollections']),
                'allowCollectionlessBooks'=> $this->filterDbBool($row['AllowCollectionlessBooks']),
                'mainCollectionId'      => $this->filterDbId($row['MainCollectionId']),
                'requireCallNumbers'    => $this->filterDbBool($row['RequireCallNumbers']),
                'callNumberRegex'       => $this->filterDbString($row['CallNumberRegex']),
                'enforceCallNumberRegex'=> $this->filterDbBool($row['EnforceCallNumberRegex']),
                'labelLine1'            => $this->filterDbString($row['LabelLine1']),
                'labelLine2'            => $this->filterDbString($row['LabelLine2']),
                'labelLine3'            => $this->filterDbString($row['LabelLine3']),
                'barcodeText'           => $this->filterDbString($row['BarcodeText']),
                'createCheckoutsIfCheckingInANonCheckedOutBook' => $this->filterDbBool($row['CreateCheckoutsIfCheckingInANonCheckedOutBook']),
                'defaultCheckoutPersonId' => $this->filterDbId($row['DefaultCheckoutPersonId']),
                'defaultCheckoutTimePeriodInDays' => $this->filterDbInt($row['DefaultCheckoutTimePeriodInDays']),
                'enableCheckouts'       => $this->filterDbBool($row['EnableCheckouts']),
                'isPublicallyListed'    => $this->filterDbBool($row['IsPublicallyListed']),
                'checkoutPersonListKind'=> $this->filterDbString($row['CheckoutPersonListKind']),
                'isActive'              => $this->filterDbBool($row['IsActive']),
                'adminNotes'            => $this->filterDbString($row['AdminNotes']),
                'adminNotesUpdatedOn'   => $this->filterDbDate($row['AdminNotesUpdatedOn']),
                'adminNotesUpdatedBy'   => $this->filterDbId($row['AdminNotesUpdatedBy']),
                'updatedOn'             => $this->filterDbDate($row['UpdatedOn']),
                'updatedBy'             => $this->filterDbId($row['UpdatedBy']),
                'createdOn'             => $this->filterDbDate($row['CreatedOn']),
                'createdBy'             => $this->filterDbId($row['CreatedBy']),

                'bookCount'             => $this->filterDbInt($row['BookCount']),
                'books'                 => [], //to be filled in, in getLibraries()
                'collections'           => [],
                'categoryStatistics'    => [],
                'collectionCategoryStatistics' => [],
                'monthlyCheckoutStatistics' => [],
                'options'               => null,
            ];
            $entity['options'] = new LibraryOptions($entity);
            $entities[$id] = $entity;
        }
        $collections = $this->getUnlinkedCollections();
        foreach ($collections as $collectionId => $collection) {
            if (isset($entities[$collection['libraryId']])) {
                $collection['library'] = $entities[$collection['libraryId']];
                $entities[$collection['libraryId']]['options']->collections[$collectionId] =
                    new CollectionOptions($collection);
            }
        }

        $this->cacheEntityObjects('unlinked-libraries', $entities, ['library', 'collection']);
        return $entities;
    }

    /**
     * @return mixed[]
     */
    public function getSimpleLibrary($id)
    {
        $libraries = $this->getUnlinkedLibraries();
        if (!isset($libraries[$id]) || !($library = $libraries[$id])) {
            return null;
        }
        return $library;
    }

    /**
     * Get a Library instance for a given libraryId
     * @param number $libraryId
     * @throws \InvalidArgumentException
     * @return \Books\Model\Library
     */
    public function getLibraryOptions($libraryId = null)
    {
        if (!isset($libraryId)) {
            $libraryId = $this->getLibraryId();
        }
        if (!isset($libraryId) || !is_numeric($libraryId)) {
            throw new \InvalidArgumentException('getLibraryOptions requires an active libraryId');
        }
        $entities = $this->getLibrariesOptions();
        if (!isset($entities[(int)$libraryId])) {
            throw new \InvalidArgumentException('getLibraryOptions requires a valid libraryId');
        }
        return $entities[(int)$libraryId];
    }

    /**
     * Retrieve list of LibraryOptions objects for all libraries
     * @return \Books\Model\LibraryOptions[]
     */
    public function getLibrariesOptions()
    {
        if (null !== ($cache = $this->fetchCachedEntityObjects('libraries-options'))) {
            return $cache;
        }
        $libraries = $this->getUnlinkedLibraries();
        $entities = [];
        foreach ($libraries as $libraryId => $library) {
            $entities[$libraryId] = new LibraryOptions($libraries[$libraryId]);
        }
        $this->cacheEntityObjects('libraries-options', $entities, ['library']);
        return $entities;
    }

    protected function getUnlinkedCollections()
    {
        if (null !== ($cache = $this->fetchCachedEntityObjects('unlinked-collections'))) {
            return $cache;
        }
        $sql = "SELECT `CollectionId`, `LibraryId`, `CollectionName`,
`Description`, `CallNumberRegex`, `CallNumberHelpText`, `CallNumberExplanation`,
`MainShowDisplay`, `LabelLine1`, `LabelLine2`, `LabelLine3`,
`DefaultCheckoutTimePeriodInDays`, `EnforceCallNumberRegex`, `RequireCallNumbers`,
`IsActive`, `AdminNotes`, `AdminNotesUpdatedOn`, `AdminNotesUpdatedBy`, `UpdatedOn`,
`UpdatedBy`, `CreatedOn`, `CreatedBy`,
(SELECT COUNT(*) FROM `lib_books` b WHERE (`is_active` = TRUE AND b.`collection_id` = c.CollectionId)) AS BookCount
FROM `lib_collections` c
ORDER BY `LibraryId`, `IsActive` DESC, `CollectionName`";

        $results = $this->fetchSome(null, $sql, null);

        $entities = [];
        foreach ($results as $row) {
            $id = $this->filterDbId($row['CollectionId']);
            $entities[$id] = [
                'collectionId'          => $id,
                'libraryId'             => $this->filterDbId($row['LibraryId']),
                'name'                  => $this->filterDbString($row['CollectionName']),
                'description'           => $this->filterDbString($row['Description']),
                'callNumberRegex'       => $this->filterDbString($row['CallNumberRegex']),
                'callNumberHelpText'    => $this->filterDbString($row['CallNumberHelpText']),
                'callNumberExplanation' => $this->filterDbString($row['CallNumberExplanation']),
                'mainShowDisplay'       => $this->filterDbString($row['MainShowDisplay']),
                'requireCallNumbers'    => $this->filterDbBool($row['RequireCallNumbers']),
                'enforceCallNumberRegex'=> $this->filterDbBool($row['EnforceCallNumberRegex']),
                'labelLine1'            => $this->filterDbString($row['LabelLine1']),
                'labelLine2'            => $this->filterDbString($row['LabelLine2']),
                'labelLine3'            => $this->filterDbString($row['LabelLine3']),
                'defaultCheckoutTimePeriodInDays' => $this->filterDbInt($row['DefaultCheckoutTimePeriodInDays']),
                'isActive'              => $this->filterDbBool($row['IsActive']),
                'adminNotes'            => $this->filterDbString($row['AdminNotes']),
                'adminNotesUpdatedOn'   => $this->filterDbDate($row['AdminNotesUpdatedOn']),
                'adminNotesUpdatedBy'   => $this->filterDbId($row['AdminNotesUpdatedBy']),
                'updatedOn'             => $this->filterDbDate($row['UpdatedOn']),
                'updatedBy'             => $this->filterDbId($row['UpdatedBy']),
                'createdOn'             => $this->filterDbDate($row['CreatedOn']),
                'createdBy'             => $this->filterDbId($row['CreatedBy']),
            ];
        }
        $this->cacheEntityObjects('unlinked-collections', $entities, ['collection']);
        return $entities;
    }

    /**
     * @return mixed[]
     */
    public function getSimpleCollection($id)
    {
        $collections = $this->getUnlinkedCollections();
        if (!isset($collections[$id]) || !($collection = $collections[$id])) {
            return null;
        }
        return $collection;
    }

    public function getLibraryBooksStatuses($libraryId = null)
    {
        if (!isset($libraryId)) {
            $libraryId = $this->getLibraryId();
        }
        if (!isset($libraryId) || !is_numeric($libraryId)) {
            throw new \InvalidArgumentException('getLibraryBooksStatuses requires an active libraryId');
        }
        $library = $this->getLibrary($libraryId);
        $entities = [];
        foreach ($library['books'] as $entityId => $entity) {
            $currentCheckout = $entity['currentCheckout'];
            $entities[$entity['withinLibraryId']] = [
                'title'     => $entity['title'],
                'isActive'  => $entity['isActive'],
                'checkedOutBy' => isset($currentCheckout) ? $currentCheckout['personId'] : null,
            ];
        }
        return $entities;
    }

    /**
     * Inactivate a list of books given in $data['withinLibraryIds']
     * Other keys that can be set are 'inactivationReason' or any other book field
     * All books are first checked in if they were checked out
     * Function does not inactivate any books if one of the ids is not valid; instead
     * an array of bad ids is returned
     * @param number $libraryId
     * @param array $data
     * @throws \InvalidArgumentException
     * @return boolean|number[]
     */
    public function inactivateWithinLibraryBooks($libraryId, array $data)
    {
        if (!is_array($data) || !isset($data['withinLibraryIds'])
        ) {
            throw new \InvalidArgumentException('data must be an associative array containing at least the \'withinLibraryIds\' key.');
        }
        $withinLibraryIdLookup = $this->getLibraryBookLookup($libraryId);

        //confirm all bookIds are valid
        $bookIds = [];
        $withinLibraryIdErrors = [];
        foreach ($data['withinLibraryIds'] as $withinLibraryId)
        {
            if (!isset($withinLibraryIdLookup[$withinLibraryId])) {
                $withinLibraryIdErrors[] = $withinLibraryId;
            } else {
                $bookIds[] = $withinLibraryIdLookup[$withinLibraryId];
            }
        }

        if (!empty($withinLibraryIdErrors)) {
            throw new \InvalidArgumentException(sprintf("There was a problem inactivating one or more books: (%s) Please try again.",
                implode(', ', $withinLibraryIdErrors)));
        }

        //first checkin books if any were formerly checked out
        $this->checkinBooks($bookIds, false);

        //create a prototype in order to allow for any possible other fields to be inserted into the checkouts table
        //many other fields are filled in within preprocessCheckout
        $paramsPrototype = $data;
        if (isset($paramsPrototype['bookId'])) {
            unset($paramsPrototype['bookId']);
        }
        unset($paramsPrototype['withinLibraryIds']);
        $paramsPrototype['isActive'] = false;

        //check out books
        $badValues = [];
        foreach ($bookIds as $bookId) {
            $currentBook = $paramsPrototype;
            if (!$newId = $this->updateEntity('book', $bookId, $currentBook, [], false))
            {
                $badValues[] = $bookId;
            }
        }
        $this->removeDependentCacheItems('book'); //refresh the cache
        return empty($badValues) ? true : $badValues;
    }

    /**
     * @return mixed[]
     */
    public function getLibraryImports()
    {
        if (null !== ($libraryId = $this->getLibraryId())) {
            $cacheKey = 'library-imports-'.$libraryId;
        } else {
            $cacheKey = 'library-imports';
        }
        if (null !== ($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }

        if (!isset($libraryId)) {
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
            if (isset($data['columnMapping'])) {
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
     * @param string $subset
     */
    public function getCheckoutsForLibrary($libraryId, $subset = 'all')
    {
        $checkouts = $this->getCheckouts();
        $entities = [];
        $tz = new \DateTimeZone('UTC');
        $now = new \DateTime(null, $tz);
        foreach ($checkouts as $checkoutId => $checkout) {
            if ($checkout['book']['libraryId'] == $libraryId &&
                ($subset == 'all' || ($subset == 'current' &&
                !isset($checkout['checkedInOn'])) ||
                ($subset == 'overdue' && !isset($checkout['checkedInOn'])
                    && is_object($checkout['dueOn']) && $now > $checkout['dueOn'])
            )) {
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
        if (null !== ($cache = $this->fetchCachedEntityObjects('checkouts'))) {
            return $cache;
        }

        $entities = $this->getUnlinkedCheckouts();
        $books = $this->getUnlinkedBooks();
        $libraries = $this->getUnlinkedLibraries();

        foreach ($entities as $entityId => $entity) {
            if (isset($books[$entity['bookId']]) &&
                isset($libraries[$books[$entity['bookId']]['libraryId']])
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
        if (null !== ($cache = $this->fetchCachedEntityObjects('unlinked-checkouts'))) {
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
            if (isset($checkedIn)) {
                $status = self::CHECKOUT_STATUS_RETURNED;
            } elseif (isset($dueOn) && $today >= $dueOn) {
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
        static $now;
        /**
         * @var LibraryOptions[] $librariesOptions
         */
        static $librariesOptions;
        static $books;
        if ($action == self::ENTITY_ACTION_CREATE) {
            if (!isset($data['bookId'])) {
                throw new \InvalidArgumentException('bookId is required to create a checkout.');
            }
            if (!isset($data['checkedOutOn'])) {
                if (!isset($now)) {
                    $now = new \DateTime(null, new \DateTimeZone('UTC'));
                }
                $data['checkedOutOn'] = $now;
            }

            if (!isset($data['checkedOutBy'])) {
                $data['checkedOutBy'] = $this->getActingUserId();
            }

            if (!isset($data['checkedOutIp'])) {
                $data['checkedOutIp'] = $_SERVER['REMOTE_ADDR'];
            }

            //@todo find a safe way to store user agent
//             if (!isset($data['checkedOutUserAgent'])) {
//                 $data['checkedOutUserAgent'] = $_SERVER['REMOTE_ADDR'];
//             }

            //calculate the dueDate
            if (!isset($data['dueOn'])) {
                if (!isset($librariesOptions)) {
                    $librariesOptions = $this->getLibrariesOptions();
                }
                if (!isset($books)) {
                    $books = $this->getUnlinkedBooks();
                }
                if (!isset($books[$data['bookId']])) {
                    throw new \InvalidArgumentException('Invalid book attempting to be checked out.');
                }
                $daysToLend = $librariesOptions[$books[$data['bookId']]['libraryId']]->defaultCheckoutTimePeriodInDays;
                if (!is_numeric($daysToLend)) { //shouldn't happen
                    $daysToLend = self::DEFAULT_CHECKOUT_TIME_PERIOD_IN_DAYS;
                }
                if ($data['checkedOutOn'] instanceof \DateTime) {
                    $dueDate = Carbon::instance($data['checkedOutOn']);
                } else {
                    $tz = new \DateTimeZone('UTC');
                    $dueDate = new Carbon(null, $tz);
                }
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
