<?php
namespace Books\Model;

use SionModel\Db\Model\SionTable;
use JUser\Model\UserTable;
use Zend\Db\Adapter\AdapterInterface;
use Carbon\Carbon;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Predicate\Expression;
use Zend\Db\Sql\Where;
use Zend\Db\Sql\Predicate\Predicate;
use Zend\Db\Sql\Predicate\Like;
use Zend\Db\Sql\Predicate\Operator;
use Zend\Db\Sql\Predicate\PredicateSet;
use Zend\Db\Sql\Predicate\In;
use BjyAuthorize\Provider\Resource\ProviderInterface as ResourceProviderInterface;
use BjyAuthorize\Provider\Rule\ProviderInterface as RuleProviderInterface;
use Zend\Permissions\Acl\Resource\GenericResource;
use SionModel\Problem\EntityProblem;
use SionModel\Problem\ProblemProviderInterface;
use Zend\Db\Sql\Predicate\IsNull;
use Zend\Db\Sql\Predicate\IsNotNull;

class LibraryTable extends SionTable implements
    ResourceProviderInterface,
    RuleProviderInterface,
    ProblemProviderInterface
{
    const IMPORT_STATUS_PENDING = 'pending';
    const IMPORT_STATUS_COMPLETED = 'completed';

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

    const BOOK_QUERY_FIELDS = [
        'title', 'author',
    ];

    const LIBRARY_GENERAL_ROLE_OPTIONS = [
        'guest'         => 'Public',
        'lib_user'      => 'Authenticated users',
        'lib_academic'  => 'Academic users',
        'lib_institute' => 'Institute members',
        'lib_patres'    => 'Patres',
    ];
    
    const SORT_TEXT_FORMAT_PARAMETER_COLLECTION_ABBREVIATION = 'collection-abbreviation';
    const SORT_TEXT_FORMAT_PARAMETER_REGEX_PARAMETERS = 'regex-parameters';
    
    const SORT_TEXT_FORMAT_PARAMETER_ORDER = [
        self::SORT_TEXT_FORMAT_PARAMETER_COLLECTION_ABBREVIATION,
        self::SORT_TEXT_FORMAT_PARAMETER_REGEX_PARAMETERS,
    ];
    
    const PROBLEM_BOOK_MISSING_CALL_NUMBER = 'book-missing-call-number';
    const PROBLEM_BOOK_INVALID_CALL_NUMBER = 'book-invalid-call-number';
    const PROBLEM_LIBRARY_MISSING_CALL_NUMBER_FORMAT = 'library-missing-call-number-format';
    const PROBLEM_LIBRARY_INVALID_CALL_NUMBER_FORMAT = 'library-invalid-call-number-format';
    const PROBLEM_LIBRARY_MISSING_SORT_TEXT_FORMAT = 'library-missing-sort-text-format';
    const PROBLEM_COLLECTION_MISSING_CALL_NUMBER_FORMAT = 'collection-missing-call-number-format';
    const PROBLEM_COLLECTION_INVALID_CALL_NUMBER_FORMAT = 'collection-invalid-call-number-format';
    const PROBLEM_COLLECTION_MISSING_SORT_TEXT_FORMAT = 'collection-missing-sort-text-format';

    /** @var UserTable $userTable */
    protected $userTable;

    protected $config;

    /**
     * @var int $libraryId
     */
    protected $libraryId;
    
    /**
     * Used to store an associative array mapping collectionIds to their names, assigned by getCollectionNames
     * @var string[]
     */
    protected $collectionNames;

    public function __construct(AdapterInterface $dbAdapter, $serviceLocator, $actingUserId, $config)
    {
        parent::__construct($dbAdapter, $serviceLocator, $actingUserId);
        $this->config = $config;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \SionModel\Db\Model\SionTable::getSelectPrototype()
     */
    protected function getSelectPrototype($entity)
    {
        $select = parent::getSelectPrototype($entity);
        if ('book' === $entity) {
            $entitySpec = $this->getEntitySpecification($entity);
            $columns = array_values($entitySpec->updateColumns);
            $columns['current_checkout_id'] = new Expression(
                '(SELECT MAX(`CheckoutId`) FROM `lib_checkouts` '
                .'WHERE (`BookId` = `book_id` AND ISNULL(`CheckedInOn`)))'
                );
            $select->columns($columns);
            $select->order(['library_id', 'sort_text']);
        } elseif ('library' === $entity) {
            $entitySpec = $this->getEntitySpecification($entity);
            $columns = array_values($entitySpec->updateColumns);
            $columns['BookCount'] = new Expression(
                '(SELECT COUNT(*) FROM `lib_books` b WHERE (`is_active` = TRUE AND b.`library_id` = LibraryId))'
                );
            $columns['MaxWithinLibraryId'] = new Expression(
                '(SELECT MAX(`original_id`) FROM `lib_books` b WHERE (b.`library_id` = LibraryId))'
                );
            $select->columns($columns);
            $select->order(['LibraryName']);
        } elseif ('collection' === $entity) {
//             (SELECT COUNT(*) FROM `lib_books` b WHERE (`is_active` = TRUE AND b.`collection_id` = c.CollectionId)) AS BookCount
//             FROM `lib_collections` c
//             ORDER BY `LibraryId`, `IsActive` DESC, `CollectionName`
            $entitySpec = $this->getEntitySpecification($entity);
            $columns = array_values($entitySpec->updateColumns);
            $columns['BookCount'] = new Expression(
                '(SELECT COUNT(*) FROM `lib_books` b WHERE (`is_active` = TRUE AND b.`collection_id` = CollectionId))'
                );
            $select->columns($columns);
            $select->order(['LibraryId', 'IsActive' => Select::ORDER_DESCENDING, 'CollectionName']);
        }
        return $select;
    }

    public function getLibraryValueOptions()
    {
        $libraries = $this->getObjects('library');
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
                    $return[$bookId] = $withinLibraryId.'-'.$book['authorsText'].' '.$book['title'];
                    break;
            }
        }
        return $return;
    }

    public function getAuthorsValueOptions($libraryId = null)
    {
        if (!isset($libraryId) && null === ($libraryId = $this->getLibraryId())) {
            throw new \InvalidArgumentException('This function can only be called for a specific library');
        }
        $cacheKey = 'library-author-texts-'.$libraryId;
        if (null !== ($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }

        $sql = "SELECT DISTINCT `author` FROM `lib_books` WHERE (`library_id` = ?) ORDER BY author";
        $params = [$libraryId];
        $results = $this->fetchSome(null, $sql, $params);

        $authors = [];
        $authorConcatenations = []; //these might be repeated so we have to check
        foreach ($results as $row) {
            $author = $row['author'];
            if (isset($author)) {
                if (false !== strpos($author, '|')) {
                    $authorsList = $this->filterDbArray($author);
                    foreach ($authorsList as $author) {
                        $authorConcatenations[$author] = $author;
                    }
                } else {
                    $authors[$author] = $author;
                }
            }
        }
        //factor in the concatenated authors, making sure not to push duplicates
        foreach ($authorConcatenations as $key => $value) {
            if (!isset($authors[$key])) {
                $authors[$key] = $value;
            }
        }
        $this->cacheEntityObjects($cacheKey, $authors, ['book']);
        return $authors;
    }

    public function getKeywordsValueOptions($libraryId = null)
    {
        if (!isset($libraryId) && null === ($libraryId = $this->getLibraryId())) {
            throw new \InvalidArgumentException('This function can only be called for a specific library');
        }
        $cacheKey = 'library-keywords-'.$libraryId;
        if (null !== ($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }

        $sql = "SELECT DISTINCT `public_tags` FROM `lib_books` WHERE (`library_id` = ?) ORDER BY public_tags";
        $params = [$libraryId];
        $results = $this->fetchSome(null, $sql, $params);

        $keywords = [];
        $keywordConcatenations = []; //these might be repeated so we have to check
        foreach ($results as $row) {
            $keyword = $row['public_tags'];
            if (isset($keyword)) {
                if (false !== strpos($keyword, '|')) {
                    $keywordList = $this->filterDbArray($keyword);
                    foreach ($keywordList as $keyword) {
                        $keywordConcatenations[$keyword] = $keyword;
                    }
                } else {
                    $keywords[$keyword] = $keyword;
                }
            }
        }
        //factor in the concatenated authors, making sure not to push duplicates
        foreach ($keywordConcatenations as $key => $value) {
            if (!isset($keywords[$key])) {
                $keywords[$key] = $value;
            }
        }
        $this->cacheEntityObjects($cacheKey, $keywords, ['book']);
        return $keywords;
    }

    public function getAdminKeywordsValueOptions($libraryId = null)
    {
        if (!isset($libraryId) && null === ($libraryId = $this->getLibraryId())) {
            throw new \InvalidArgumentException('This function can only be called for a specific library');
        }
        $cacheKey = 'library-admin-keywords-'.$libraryId;
        if (null !== ($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }

        $sql = "SELECT DISTINCT `admin_tags` FROM `lib_books` WHERE (`library_id` = ?) ORDER BY admin_tags";
        $params = [$libraryId];
        $results = $this->fetchSome(null, $sql, $params);

        $keywords = [];
        $keywordConcatenations = []; //these might be repeated so we have to check
        foreach ($results as $row) {
            $keyword = $row['admin_tags'];
            if (isset($keyword)) {
                if (false !== strpos($keyword, '|')) {
                    $keywordList = $this->filterDbArray($keyword);
                    foreach ($keywordList as $keyword) {
                        $keywordConcatenations[$keyword] = $keyword;
                    }
                } else {
                    $keywords[$keyword] = $keyword;
                }
            }
        }
        //factor in the concatenated authors, making sure not to push duplicates
        foreach ($keywordConcatenations as $key => $value) {
            if (!isset($keywords[$key])) {
                $keywords[$key] = $value;
            }
        }
        $this->cacheEntityObjects($cacheKey, $keywords, ['book']);
        return $keywords;
    }

    public function getPublishersValueOptions($libraryId = null)
    {
        if (!isset($libraryId) && null === ($libraryId = $this->getLibraryId())) {
            throw new \InvalidArgumentException('This function can only be called for a specific library');
        }
        $cacheKey = 'library-publishers-'.$libraryId;
        if (null !== ($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }

        $sql = "SELECT DISTINCT `publisher`
FROM `lib_books`
WHERE (`library_id` = ?)
ORDER BY `publisher`";
        $params = [$libraryId];
        $results = $this->fetchSome(null, $sql, $params);
        $values = [];
        foreach ($results as $row) {
            $publisher = $row['publisher'];
            if (isset($publisher)) {
                $values[$publisher] = $publisher;
            }
        }
        $this->cacheEntityObjects($cacheKey, $values, ['book']);
        return $values;
    }

    public function getCategoryValueOptions($libraryId = null)
    {
        if (!isset($libraryId) && null === ($libraryId = $this->getLibraryId())) {
            throw new \InvalidArgumentException('This function can only be called for a specific library');
        }
        $cacheKey = 'library-categories-'.$libraryId;
        if (null !== ($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }

        $sql = "SELECT DISTINCT `category` FROM `lib_books` WHERE (`library_id` = ?) ORDER BY category";
        $params = [$libraryId];
        $results = $this->fetchSome(null, $sql, $params);

        $categories = [];
        foreach ($results as $row) {
            $category = $row['category'];
            if (isset($category)) {
                $categories[$category] = $category;
            }
        }
        $this->cacheEntityObjects($cacheKey, $categories, ['book']);
        return $categories;
    }

    public function getCollectionValueOptions($libraryId = null)
    {
        if (!isset($libraryId) && null === ($libraryId = $this->getLibraryId())) {
            throw new \InvalidArgumentException('This function can only be called for a specific library');
        }
        $collections = $this->queryObjects('collection', ['libraryId' => $libraryId]);
        $valueOptions = [];
        foreach ($collections as $collectionId => $collection) {
            $valueOptions[$collectionId] = $collection['name'];
        }
        return $valueOptions;
    }

    /**
     * Search for books. Returns a list of books.
     * @param mixed[] $query search(string), libraryId(int|array)
     *      category(string|array)
     * @param mixed[] $options maxResults(int)
     * @return mixed[]
     */
    public function searchBooks($query, $options = [])
    {
        $libraryId = $this->getLibraryId();

//         $queryParameters = [
//             'title', 'author', 'search',
//             'isActive', 'isCheckedOut',
//             'collectionId', 'libraryId', 'category',
//             'publicationId'
//         ];
//         $possibleOptions = ['maxResults', 'page', 'resultsPerPage', 'onlyPendingBooks'];
        $onlyPendingBooks = isset($options['onlyPendingBooks']) ? (bool) $options['onlyPendingBooks'] : false;
        
        $fieldMap = $this->getEntitySpecification('book')->updateColumns;
        $gateway = $this->getTableGateway('lib_books');
        $select = $this->getSelectPrototype('book');
        $where = new Where();

        //Prepare the libraryId predicate
        $libraryClause = null;
        if (isset($query['libraryId'])) {
            if (is_array($query['libraryId'])) {
                $libraries = [];
                foreach ($query['libraryId'] as $value) {
                    if (is_numeric($value) && !in_array($value, $libraries)) {
                        $libraries[] = $value;
                    }
                }
                if (count($libraries) === 1) {
                    $query['libraryId'] = $libraries[0];
                } elseif (count($libraries) > 1) {
                    $libraryClause = new In($fieldMap['libraryId'], $libraries);
                }
            }
            if (is_numeric($query['libraryId'])) {
                $libraryClause = new Operator(
                    $fieldMap['libraryId'],
                    Operator::OPERATOR_EQUAL_TO,
                    $query['libraryId']
                );
            }
        } elseif (isset($libraryId)) { //if the caller didn't specify a libraryId query param, set the current library
            $libraryClause = new Operator($fieldMap['libraryId'], Operator::OPERATOR_EQUAL_TO, $libraryId);
        }
        if (isset($libraryClause)) {
            $where->addPredicate($libraryClause, PredicateSet::OP_AND);
        }

        //Prepare the search predicate
        if (isset($query['search'])) {
            $search = $query['search'];
            $searchLike = sprintf("%%%s%%", $search);
            $searchClause = new Predicate();
            $searchClause->addPredicates([
                new Like($fieldMap['authorsText'], $searchLike),
                new Like($fieldMap['title'], $searchLike),
                new Like($fieldMap['category'], $searchLike),
                new Like($fieldMap['callNumber'], $searchLike),
                new Operator($fieldMap['withinLibraryId'], Operator::OPERATOR_EQUAL_TO, $search),
            ], PredicateSet::OP_OR);
            $where->addPredicate($searchClause);
        }

        // Prepare collectionId predicate
        if (isset($query['collectionId'])) {
            $collectionIdClause = null;
            if (is_array($query['collectionId'])) {
                $collections = [];
                foreach ($query['collectionId'] as $value) {
                    if (is_numeric($value) && !in_array($value, $collections)) {
                        $collections[] = $value;
                    }
                }
                if (count($collections) === 1) {
                    $query['collectionId'] = $collections[0];
                } elseif (count($collections) > 1) {
                    $collectionIdClause= new In($fieldMap['collectionId'], $collections);
                }
            }
            if (is_numeric($query['collectionId'])) {
                $collectionIdClause= new Operator(
                    $fieldMap['collectionId'],
                    Operator::OPERATOR_EQUAL_TO,
                    $query['collectionId']
                );
            }
            if (isset($collectionIdClause)) {
                $where->addPredicate($collectionIdClause, PredicateSet::OP_AND);
            }
        }
        
        // Prepare withinLibraryId predicate
        if (isset($query['withinLibraryId'])) {
            $withinLibraryIdClause = null;
            if (is_array($query['withinLibraryId'])) {
                $withinLibraryIds = [];
                foreach ($query['withinLibraryId'] as $value) {
                    if (is_numeric($value) && !in_array($value, $withinLibraryIds)) {
                        $withinLibraryIds[] = $value;
                    }
                }
                if (count($withinLibraryIds) === 1) {
                    $query['withinLibraryId'] = $withinLibraryIds[0];
                } elseif (count($withinLibraryIds) > 1) {
                    $withinLibraryIdClause = new In($fieldMap['withinLibraryId'], $withinLibraryIds);
                }
            }
            if (is_numeric($query['withinLibraryId'])) {
                $withinLibraryIdClause = new Operator(
                    $fieldMap['withinLibraryId'],
                    Operator::OPERATOR_EQUAL_TO,
                    $query['withinLibraryId']
                    );
            }
            if (isset($withinLibraryIdClause)) {
                $where->addPredicate($withinLibraryIdClause, PredicateSet::OP_AND);
            }
        }

        // Prepare publicationId predicate
        if (isset($query['publicationId'])) {
            $publicationIdClause = null;
            if (is_array($query['publicationId'])) {
                $publications = [];
                foreach ($query['publicationId'] as $value) {
                    if (is_numeric($value) && !in_array($value, $publications)) {
                        $publications[] = $value;
                    }
                }
                if (count($publications) === 1) {
                    $query['publicationId'] = $publications[0];
                } elseif (count($publications) > 1) {
                    $publicationIdClause= new In($fieldMap['publicationId'], $publications);
                }
            }
            if (is_numeric($query['publicationId'])) {
                $publicationIdClause= new Operator(
                    $fieldMap['publicationId'],
                    Operator::OPERATOR_EQUAL_TO,
                    $query['publicationId']
                );
            }
            if (isset($publicationIdClause)) {
                $where->addPredicate($publicationIdClause, PredicateSet::OP_AND);
            }
        }

        //Prepare category predicate
        if (isset($query['category'])) {
            $categoryClause = null;
            if (is_array($query['category'])) {
                $categories = [];
                foreach ($query['category'] as $value) {
                    if (0 !== strlen($value) && !in_array($value, $categories)) {
                        $categories[] = $value;
                    }
                }
                if (count($categories) === 1) {
                    $query['category'] = $categories[0];
                } elseif (count($categories) > 1) {
                    $categoryClause= new In($fieldMap['category'], $categories);
                }
            }
            if (is_string($query['category']) && 0 !== strlen($query['category'])) {
                $categoryClause = new Operator(
                    $fieldMap['category'],
                    Operator::OPERATOR_EQUAL_TO,
                    $query['category']
                );
            }
            if (isset($categoryClause)) {
                $where->addPredicate($categoryClause, PredicateSet::OP_AND);
            }
        }

        //Prepare title predicate
        if (isset($query['title']) && 0 !== strlen($query['title'])) {
            $search = $query['title'];
            $searchLike = sprintf("%%%s%%", $search);
            $titleClause = new Operator($fieldMap['title'], Operator::OPERATOR_EQUAL_TO, $query['title']);
            $where->addPredicate($titleClause, PredicateSet::OP_AND);
        }

        //Prepare author predicate
        if (isset($query['authorsText']) && 0 !== strlen($query['author'])) {
            $search = $query['author'];
            $authorClause = new Operator($fieldMap['authorsText'], Operator::OPERATOR_EQUAL_TO, $query['author']);
            $where->addPredicate($authorClause, PredicateSet::OP_AND);
        }
        
        //Prepare title predicate
        if (isset($query['inLanguage']) && 0 !== strlen($query['inLanguage'])) {
            $search = $query['inLanguage'];
            $searchLike = sprintf("%%%s%%", $search);
            $titleClause = new Like($fieldMap['inLanguage'], $query['inLanguage']);
            $where->addPredicate($titleClause, PredicateSet::OP_AND);
        }
        
        //Prepare sortText predicate
        if (array_key_exists('sortText', $query)) {
            if (!isset($query['sortText'])) {
                $sortTextClause = new IsNull($fieldMap['sortText']);
            } else {
                $sortTextClause = new Operator($fieldMap['sortText'], Operator::OPERATOR_EQUAL_TO, $query['sortText']);
            }
            $where->addPredicate($sortTextClause, PredicateSet::OP_AND);
        }
        
        //Prepare isActive predicate, default to true unless caller sets it to null
        if (!array_key_exists('isActive', $query) ||
            (!is_bool($query['isActive']) && null !== $query['isActive'])
        ) {
            $query['isActive'] = true;
        }
        if (isset($query['isActive'])) {
            $isActiveClause= new Operator($fieldMap['isActive'], Operator::OPERATOR_EQUAL_TO, $query['isActive']);
            $where->addPredicate($isActiveClause, PredicateSet::OP_AND);
        }
        
        //Prepare onlyPendingBooks
        if ($onlyPendingBooks) {
            $pendingBooksClause = new IsNotNull($fieldMap['newCallNumber']);
            $where->addPredicate($pendingBooksClause, PredicateSet::OP_AND);
        }

        //@todo Prepare isCheckedOut

        //Set the where clause
        $select->where($where);

        $results = $gateway->selectWith($select);
        $entities = [];
        $checkoutsToGrab = [];

        foreach ($results as $row) {
            $processedRow = $this->processBookRow($row);
            if (isset($processedRow['currentCheckoutId'])) {
                $checkoutsToGrab[$processedRow['bookId']] = $processedRow['currentCheckoutId'];
            }
            $entities[$processedRow['bookId']] = $processedRow;
        }

        //grab checkouts to fill them in to entities
        $checkouts = $this->getCheckouts(array_values($checkoutsToGrab));
        foreach ($checkoutsToGrab as $bookId => $checkoutId) {
            if (isset($checkouts[$checkoutId])) {
                $entities[$bookId]['currentCheckout'] = $checkouts[$checkoutId];
            }
        }

        return $entities;
    }

    /**
     * @param array $bookIds
     * @return mixed[]
     */
//     public function getBooks(array $bookIds = [])
//     {
//         $entities = $this->getUnlinkedBooks($bookIds);
//         $libraries = $this->getObjects('library');
//         foreach ($entities as $entityId => $entity) {
//             $unset = false;
//             if (isset($libraries[$entity['libraryId']])) {
//                 $entities[$entityId]['library'] = $libraries[$entity['libraryId']];
//             } else {
//                 unset($entities[$entityId]); //all books should be in a library
//             }
//             if (!$unset && isset($entity['currentCheckoutId'])) {
//                 $entities[$entityId]['currentCheckout'] = $this->getCheckout($entity['currentCheckoutId']);
//             }
//         }
//         return $entities;
//     }

    /**
     * Get a book object(array) with its associated library and current checkout.
     * If these extra properties aren't necessary, use getObject('book', $id)
     * @param int $id
     * @return mixed[]
     */
    public function getBook($id)
    {
        $object = $this->getObject('book', $id);
        if (!isset($object)) {
            return null;
        }
        if (isset($object['libraryId'])) {
            $object['library'] = $this->getObject('library', $object['libraryId']);
        }
        if (isset($object['currentCheckoutId'])) {
            $checkout = $this->getObject('checkout', $object['currentCheckoutId']);
            if (isset($checkout)) {
                $object['currentCheckout'] = $checkout;
            }
        }
        return $object;
    }

    /**
     * Retrieve an array of book records. There are 3 possibilities of
     * selecting books. If the $bookIds parameter isn't empty, only these Ids will be retrieved.
     * If libraryId member is set, all books from that library, books from any/all libraries.
     * @param array $bookIds
     * @return array
     */
    protected function getUnlinkedBooks(array $bookIds = [])
    {
        if (empty($bookIds)) {
            $libraryId = $this->getLibraryId();
            $cacheKey = !isset($libraryId) ? 'unlinked-books' : 'unlinked-books-'.$libraryId;
            if (null !== $cache = $this->fetchCachedEntityObjects($cacheKey)) {
                return $cache;
            }
        }

        //@todo simplify this function, maybe take out the $bookIds function
        $gateway = $this->getTableGateway('lib_books');
        $where = [];
        if (!empty($bookIds)) {
            $where['book_id'] = $bookIds;
        }
        if (isset($libraryId)) {
            $select = $this->getSelectPrototype('book');
            $where['library_id'] = $libraryId;
            $select->where($where);
            $results = $gateway->selectWith($select);
        } else {
            $select = $this->getSelectPrototype('book');
            if (!empty($where)) {
                $select->where($where);
            }
            $results = $gateway->selectWith($select);
        }
        $entities = [];
        foreach ($results as $row) {
            $processedRow = $this->processBookRow($row);
            $entities[$processedRow['bookId']] = $processedRow;
        }
        if (empty($bookIds)) {
            $this->cacheEntityObjects($cacheKey, $entities, ['book']);
        }
        return $entities;
    }
    
    /**
     * Takes a list of books by reference and links up their checkout records
     * @param mixed[] $books
     */
    protected function linkCurrentCheckoutsToBook(&$books)
    {
        $checkoutIds = [];
        foreach ($books as $object) {
            if (isset($object['currentCheckoutId'])) {
                $checkoutIds[] = $object['currentCheckoutId'];
            }
        }
        
        if (empty($checkoutIds)) {
            return;
        }
        
        $checkouts = $this->queryObjects('checkout', ['checkoutId' => $checkoutIds]);
        foreach ($books as $id => $object) {
            if (isset($object['currentCheckoutId']) && isset($checkouts[$object['currentCheckoutId']])) {
                $books[$id]['currentCheckout'] = $checkouts[$object['currentCheckoutId']];
            }
        }
    }
    
    /**
     * Manipulate a database book row into a standardized row
     * @param array $row
     * @return array[]
     */
    protected function processBookRow($row)
    {
        static $collectionNames;
        if (!isset($collectionNames)) {
            $collectionNames = $this->getCollectionNames();
        }
        $id = $this->filterDbId($row['book_id']);
        $libraryId = $this->filterDbId($row['library_id']);
        $authorsText = $row['author'];
        $authors = $this->filterDbArray($authorsText);
        $authorsPrettyText = implode('; ', $authors);
        $title = $row['title'];
        $name = $authorsPrettyText . ($authorsText ? ' - ' : '') . $title;
        $isActive = $this->filterDbBool($row['is_active']);
        $collectionId = $this->filterDbId($row['collection_id']);
        $collectionName = null;
        if (isset($collectionId)
            && isset($collectionNames)
            && isset($collectionNames[$collectionId])
        ) {
            $collectionName = $collectionNames[$collectionId];
        }
        $processedRow = [
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
        ];
        return $processedRow;
    }

    /**
     * Process book data before putting into the database.
     * @param mixed[] $data
     * @param mixed[] $entityData
     * @return mixed[]
     */
    protected function preprocessBook($data, $entityData, $action)
    {
        if (isset($data['authors'])) {
            $data['authorsText'] = implode('|', $data['authors']);
        }
        
        //update the sortText
        if (!isset($data['sortText'])) {
            //make sure we pass everything fresh
            $completeData = $entityData;
            foreach ($data as $key => $value) {
                $completeData[$key] = $value;
            }
            $data['sortText'] = $this->getBookSortText($completeData);
        }
        
        return $data;
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
            throw new \InvalidArgumentException(
                'There must by a libraryId set to get the active library book lookup list.'
            );
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

    /**
     * Get a associative array to lookup bookIds according to the withinLibraryId.
     * Includes information on whether the book is active or not. Format:
     * [
     *     xxxxx => [ 'bookId' => yyyyy, 'isActive' => true],
     * ]
     * @param number $libraryId
     * @throws \InvalidArgumentException
     * @return array
     */
    public function getLibraryBookLookupWithActive($libraryId = null)
    {
        if (!isset($libraryId)) {
            $libraryId = $this->getLibraryId();
        }
        if (!isset($libraryId) || !is_numeric($libraryId)) {
            throw new \InvalidArgumentException(
                'There must by a libraryId set to get the active library book lookup list.'
            );
        }
        $select = new Select('lib_books');
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
        $libraries = $this->getObjects('library');

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
                    'checkedInIp'           => $_SERVER['REMOTE_ADDR'],
//                     'checkedInUserAgent'    => $row['CheckedInUserAgent']),
                ];
                //don't refresh the cache
                $this->updateEntity('checkout', $checkoutId, $data, [], false);
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
//                         'checkedOutUserAgent'   => $row['CheckedOutUserAgent']),
                        'dueOn'                 => $today,

                        'checkedInOn'           => $today,
                        'checkedInBy'           => $this->getActingUserId(),
                        'checkedInIp'           => $_SERVER['REMOTE_ADDR'],
//                     'checkedInUserAgent'    => $row['CheckedInUserAgent']),
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
    public function checkinWithinLibraryBooks(
        $libraryId,
        array $withinLibraryIds,
        $createCheckoutsForBooksWithNoCheckouts = true
    ) {
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
            throw new \InvalidArgumentException(
                'Data must be an associative array containing at least \'personId\' and \'withinLibraryIds\' keys.'
            );
        }
        $withinLibraryIdLookup = $this->getLibraryBookLookup($libraryId);
        //@todo instead just get the book records that are being referred to
        $books = $this->getUnlinkedBooks();

        //confirm all bookIds are valid
        $bookIds = [];
        $withinLibraryIdErrors = [];
        foreach ($data['withinLibraryIds'] as $withinLibraryId) {
            if (!isset($withinLibraryIdLookup[$withinLibraryId]) ||
                false === $books[$withinLibraryIdLookup[$withinLibraryId]]['isActive'] //make sure book is active
            ) {
                $withinLibraryIdErrors[] = $withinLibraryId;
            } else {
                $bookIds[] = $withinLibraryIdLookup[$withinLibraryId];
            }
        }

        if (!empty($withinLibraryIdErrors)) {
            throw new \InvalidArgumentException(sprintf(
                "The following book(s) don't exist or are inactivated: (%s) Please try again.",
                implode(', ', $withinLibraryIdErrors)
            ));
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
            if (!$this->createEntity('checkout', $currentBook, false)) {
                $badValues[] = $bookId;
            }
        }
        $this->removeDependentCacheItems('checkout'); //force cache refresh
        return empty($badValues) ? true : $badValues;
    }

    public function renewBook($checkoutId)
    {
        $checkout = $this->getCheckout($checkoutId);
        $book = $this->getObject('book', $checkout['bookId']);
        $library = $this->getObject('library', $book['libraryId']);
        static $today;
        //if the dueDate hasn't arrived, extend it; else, from today's date
        if (!isset($today)) {
            $tz = new \DateTimeZone('UTC');
            $today = Carbon::today($tz);
        }
        /** @var LibraryOptions $libraryOptions */
        $libraryOptions = $library['options'];
        $libraryOptions->defaultCheckoutTimePeriodInDays;
        $newDueOn = $today->addDays($libraryOptions->defaultCheckoutTimePeriodInDays);
        return $newDueOn;
    }

    /**
     * @todo it would be better to do a linkLibraryStatistics function, so we don't have to run
     * stats on all the libraries everytime we want to load the LibrariesController::showAction
     * @todo fill in monthlyCheckoutStatistics
     * @return mixed[]
     */
    public function getLibraries()
    {
        if (null !== ($cache = $this->fetchCachedEntityObjects('libraries'))) {
            return $cache;
        }

        $entities = $this->getObjects('library');
        $books = $this->getUnlinkedBooks();
        foreach ($books as $book) {
            if ($book['isActive'] && isset($book['libraryId']) && //don't do anything here with inactive books
                isset($entities[$book['libraryId']])
            ) {
//                 $entities[$book['libraryId']]['books'][$bookId] = $book;

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
//         $checkouts = $this->getUnlinkedCheckouts();
//         foreach ($checkouts as $checkout) {
//             if (null === $checkout['checkedInOn'] && isset($books[$checkout['bookId']]) &&
//                 isset($entities[$books[$checkout['bookId']]['libraryId']]['books'][$checkout['bookId']])
//             ) { //book is checked out
//                 $bookEntry = &$entities[$books[$checkout['bookId']]['libraryId']]['books'][$checkout['bookId']];
//                 $bookEntry['isAvailable'] = false;
//                 $bookEntry['checkedOut'] = true;
//                 $bookEntry['currentCheckout'] = $checkout;
//             }
//         }

        $this->cacheEntityObjects('libraries', $entities, ['library', 'book', 'checkout']);
        return $entities;
    }
    
    /**
     * Manipulate a database book row into a standardized row
     * @param array $row
     * @return array[]
     */
    protected function processLibraryRow($row)
    {
        static $options;
        if (!is_array($options)) {
            $options = [];
        }
        static $collections;
        if (!isset($collections)) {
            $collections = $this->getObjects('collection');
        }
        $id = $this->filterDbId($row['LibraryId']);
        $nextWithinLibraryId = $this->filterDbInt($row['MaxWithinLibraryId']);
        $nextWithinLibraryId = isset($nextWithinLibraryId) && is_numeric($nextWithinLibraryId) ?
        (int)$nextWithinLibraryId + 1 : null;
        $resourceId = 'library_'.$id;
        $processedRow = [
            'libraryId'             => $id,
            'name'                  => $row['LibraryName'],
            'description'           => $row['Description'],
            'requireCallNumbers'    => $this->filterDbBool($row['RequireCallNumbers']),
            'callNumberHelpText'    => $row['CallNumberHelpText'],
            'callNumberExplanation' => $row['CallNumberExplanation'],
            'callNumberPlaceholder' => $row['CallNumberPlaceholder'],
            'callNumberRegex'       => $row['CallNumberRegex'],
            'enforceCallNumberRegex'=> $this->filterDbBool($row['EnforceCallNumberRegex']),
            'filiationId'           => $this->filterDbId($row['FiliationId']),
            'contactPersonId'       => $this->filterDbId($row['ContactPerson']),
            'contactEmail'          => $this->filterEmailString($row['ContactEmail']),
            'sortTextFormat'        => $row['SortTextFormat'],
            'mainShowDisplay'       => $row['MainShowDisplay'],
            'useCollections'        => $this->filterDbBool($row['UseCollections']),
            'allowCollectionlessBooks'=> $this->filterDbBool($row['AllowCollectionlessBooks']),
            'mainCollectionId'      => $this->filterDbId($row['MainCollectionId']),
            'viewRole'              => $row['ViewRole'],
            'labelLine1'            => $row['LabelLine1'],
            'labelLine2'            => $row['LabelLine2'],
            'labelLine3'            => $row['LabelLine3'],
            'barcodeText'           => $row['BarcodeText'],
            'enableCheckouts'       => $this->filterDbBool($row['EnableCheckouts']),
            'defaultCheckoutTimePeriodInDays' => $this->filterDbInt($row['DefaultCheckoutTimePeriodInDays']),
            'checkoutBooksRole'     => $row['CheckoutBooksRole'],
            'createCheckoutsIfCheckingInANonCheckedOutBook' => $this->filterDbBool(
                $row['CreateCheckoutsIfCheckingInANonCheckedOutBook']
                ),
            'checkoutPersonListKind'=> $row['CheckoutPersonListKind'],
            'defaultCheckoutPersonId' => $this->filterDbId($row['DefaultCheckoutPersonId']),
            'isActive'              => $this->filterDbBool($row['IsActive']),
            'adminNotes'            => $row['AdminNotes'],
            'adminNotesUpdatedOn'   => $this->filterDbDate($row['AdminNotesUpdatedOn']),
            'adminNotesUpdatedBy'   => $this->filterDbId($row['AdminNotesUpdatedBy']),
            'updatedOn'             => $this->filterDbDate($row['UpdatedOn']),
            'updatedBy'             => $this->filterDbId($row['UpdatedBy']),
            'createdOn'             => $this->filterDbDate($row['CreatedOn']),
            'createdBy'             => $this->filterDbId($row['CreatedBy']),
            
            'resourceId'            => $resourceId,
            'nextWithinLibraryId'   => $nextWithinLibraryId,
            'bookCount'             => $this->filterDbInt($row['BookCount']),
            'books'                 => [], //to be filled in, in getLibraries() @deprecated
            'collections'           => [],
            'categoryStatistics'    => [],
            'collectionCategoryStatistics' => [],
            'monthlyCheckoutStatistics' => [],
            'contactPerson'         => null,
            'options'               => null,
        ];
        
        //this assures we don't have different instances of libraryOption floating around, just one per library
        $processedRow['options'] = isset($options[$id]) 
            ? $options[$id]
            : ($options[$id] = new LibraryOptions($processedRow));
        
        foreach ($collections as $collectionId => $collection) {
            if ($collection['libraryId'] == $id) {
                $collections[$collectionId] = &$collections[$collectionId];
                $processedRow['options']->collections[$collectionId] = $collections[$collectionId]['options'];
            }
        }
        return $processedRow;
    }

    /**
     * Get a Library instance for a given libraryId
     * @param number $libraryId
     * @throws \InvalidArgumentException
     * @return \Books\Model\LibraryOptions
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
        //don't cache because it's a quick operation
        $libraries = $this->getObjects('library');
        $entities = [];
        foreach ($libraries as $libraryId => $library) {
            $entities[$libraryId] = $library['options'];
        }
        return $entities;
    }
    
    protected function processCollectionRow($row)
    {
        $id = $this->filterDbId($row['CollectionId']);
        $libraryId = $this->filterDbId($row['LibraryId']);
        
        $processedRow = [
            'collectionId'          => $id,
            'libraryId'             => $libraryId,
            'name'                  => $row['CollectionName'],
            'abbreviation'          => $row['Abbreviation'],
            'description'           => $row['Description'],
            'sortTextFormat'        => $row['SortTextFormat'],
            'callNumberRegex'       => $row['CallNumberRegex'],
            'callNumberHelpText'    => $row['CallNumberHelpText'],
            'callNumberExplanation' => $row['CallNumberExplanation'],
            'mainShowDisplay'       => $row['MainShowDisplay'],
            'requireCallNumbers'    => $this->filterDbBool($row['RequireCallNumbers']),
            'enforceCallNumberRegex'=> $this->filterDbBool($row['EnforceCallNumberRegex']),
            'labelLine1'            => $row['LabelLine1'],
            'labelLine2'            => $row['LabelLine2'],
            'labelLine3'            => $row['LabelLine3'],
            'defaultCheckoutTimePeriodInDays' => $this->filterDbInt($row['DefaultCheckoutTimePeriodInDays']),
            'isActive'              => $this->filterDbBool($row['IsActive']),
            'adminNotes'            => $row['AdminNotes'],
            'adminNotesUpdatedOn'   => $this->filterDbDate($row['AdminNotesUpdatedOn']),
            'adminNotesUpdatedBy'   => $this->filterDbId($row['AdminNotesUpdatedBy']),
            'updatedOn'             => $this->filterDbDate($row['UpdatedOn']),
            'updatedBy'             => $this->filterDbId($row['UpdatedBy']),
            'createdOn'             => $this->filterDbDate($row['CreatedOn']),
            'createdBy'             => $this->filterDbId($row['CreatedBy']),
            
            'resourceId'            => 'library_'.$libraryId,
        ];
        $processedRow['options'] = new CollectionOptions($processedRow);
        return $processedRow;
    }
    
    /**
     * Get an associative array mapping collectionId to its name
     * @return string[]
     */
    public function getCollectionNames($libraryId = null)
    {
        if (isset($this->collectionNames) && !isset($libraryId)) {
            return $this->collectionNames;
        }
        $select = $this->getSelectPrototype('collection');
        $select->columns(['CollectionId', 'CollectionName', 'LibraryId']);
        $gateway = $this->getTableGateway('lib_collections');
        $results = $gateway->selectWith($select);
        
        $entities = [];
        foreach ($results as $row) {
            if (!isset($libraryId) || $row['LibraryId'] == $libraryId) {
                $entities[$row['CollectionId']] = $row['CollectionName'];
            }
        }
        $this->collectionNames = $entities;
        return $this->collectionNames;
    }

    public function getLibraryBooksStatuses($libraryId = null)
    {
        if (!isset($libraryId)) {
            $libraryId = $this->getLibraryId();
        }
        if (!isset($libraryId) || !is_numeric($libraryId)) {
            throw new \InvalidArgumentException('getLibraryBooksStatuses requires an active libraryId');
        }
        $books = $this->getObjects('book', ['libaryId' => $libraryId]);
        $this->linkCurrentCheckoutsToBook($books);
        
        $entities = [];
        foreach ($books as $entity) {
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
            throw new \InvalidArgumentException(
                'data must be an associative array containing at least the \'withinLibraryIds\' key.'
            );
        }
        $withinLibraryIdLookup = $this->getLibraryBookLookup($libraryId);

        //confirm all bookIds are valid
        $bookIds = [];
        $withinLibraryIdErrors = [];
        foreach ($data['withinLibraryIds'] as $withinLibraryId) {
            if (!isset($withinLibraryIdLookup[$withinLibraryId])) {
                $withinLibraryIdErrors[] = $withinLibraryId;
            } else {
                $bookIds[] = $withinLibraryIdLookup[$withinLibraryId];
            }
        }

        if (!empty($withinLibraryIdErrors)) {
            throw new \InvalidArgumentException(sprintf(
                "There was a problem inactivating one or more books: (%s) Please try again.",
                implode(', ', $withinLibraryIdErrors)
            ));
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
            if ($this->updateEntity('book', $bookId, $currentBook, [], false)) {
                $badValues[] = $bookId;
            }
        }
        $this->removeDependentCacheItems('book'); //refresh the cache
        return empty($badValues) ? true : $badValues;
    }
    
    /**
     * For a given book, formulate a string by which the library's books can be sorted
     * @todo this function needs a lot of work to accept all the library options
     * @param mixed[] $book
     * @param boolean $useNewCallNumber
     * @return NULL|NULL|string
     */
    public function getBookSortText($book, $useNewCallNumber = false)
    {
        static $libraries;
        //if the regex works, it gets true, else, false
        static $regexChecks = [];
        if (!isset($libraries)) {
            $libraries = $this->getObjects('library');
        }
        $callNumber = $useNewCallNumber ? $book['newCallNumber'] : $book['callNumber'];
        if (!isset($callNumber)) {
            return null;
        }
        $libraryId = $book['libraryId'];
        if (!isset($libraries[$libraryId])) {
            return null;
        }
        /** @var LibraryOptions $libraryOptions */
        $libraryOptions = $libraries[$libraryId]['options'];
        $collectionOptions = null;
        $regex = null;
        if (isset($book['collectionId']) && isset($libraryOptions->collections[$book['collectionId']])) {
            $collectionOptions = $libraryOptions->collections[$book['collectionId']];
            $regex = $collectionOptions->callNumberRegex;
        } else {
            $regex = $libraryOptions->callNumberRegex;
        }
        if (!isset($regex)) {
            return null;
        }
        // we've got some valid regex
        if (!isset($regexChecks[$regex])) {
            $regexChecks[$regex] = false !== @preg_match($regex, null);
        }
        if (!$regexChecks[$regex]) {
            return null;
        }
        
        //params to pass to sprintf
        $params = [];
        
        $matches = null;
        $regexParams = [];
        $result = preg_match($regex, $callNumber, $matches);
        if (1 === $result) {
            $regexParams = array_slice($matches, 1);
        }
        
        foreach (self::SORT_TEXT_FORMAT_PARAMETER_ORDER as $value) {
            switch ($value) {
                case self::SORT_TEXT_FORMAT_PARAMETER_COLLECTION_ABBREVIATION:
                    $collectionAbbreviation = '';
                    if (isset($collectionOptions)) {
                        $collectionAbbreviation = $collectionOptions->abbreviation;
                    }
                    $params[] = $collectionAbbreviation;
                    break;
                case self::SORT_TEXT_FORMAT_PARAMETER_REGEX_PARAMETERS:
                    $params = array_merge($params, $regexParams);
                    break;
            }
        }
        $format = '%1$s%2$-8s%3$04d%4$03d%5$03d';
        if (count($params) >= 5) {
            $return = vsprintf($format, $params);
        } else {
            $return = null;
        }
        return $return;
    }

    /**
     * @todo factor out
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
            $libraryId = $this->filterDbId($row['LibraryId']);
            $columnMappingSerialized = $row['ColumnMapping'];
            $filePath = $row['FilePath'];
            $fileAvailable = file_exists($filePath);
            $entities[$id] = [
                'importId'                  => $id,
                'name'                      => $row['ImportName'],
                'libraryId'                 => $libraryId,
                'status'                    => $row['Status'],
                'description'               => $row['Description'],
                'columnMappingSerialized'   => $columnMappingSerialized,
                'worksheet'                 => $row['Worksheet'],
                'filePath'                  => $filePath,
                'isCompleteImport'          => $this->filterDbBool($row['IsCompleteImport']),
                'booksUpdated'              => $this->filterDbInt($row['BooksUpdated']),
                'booksCreated'              => $this->filterDbInt($row['BooksCreated']),
                'booksInactivated'              => $this->filterDbInt($row['BooksDeleted']),
                'createdOn'                 => $this->filterDbDate($row['CreatedOn']),
                'createdBy'                 => $this->filterDbId($row['CreatedBy']),
                'updatedOn'                 => $this->filterDbDate($row['UpdatedOn']),
                'updatedBy'                 => $this->filterDbId($row['UpdatedBy']),

                'resourceId'                => 'library_'.$libraryId,
                'columnMapping'             => unserialize($columnMappingSerialized),
                'fileAvailable'             => $fileAvailable,
            ];
        }

        $this->cacheEntityObjects($cacheKey, $entities, ['library-import']);
        return $entities;
    }

    /**
     * @todo factor out
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
     * Get a standardized select object to retrieve records from the database
     * @todo factor out
     * @return \Zend\Db\Sql\Select
     */
    protected function getCheckoutSelectPrototype()
    {
        static $select;
        if (!isset($select)) {
            $select = new Select('lib_checkouts');
//         $select->columns(['TheMonth' => new Expression('MONTH(`modified_on`)'),
//          'TheYear' => new Expression('YEAR(`modified_on`)'), 'Count' => new Expression('Count(*)')]);
            $select->columns(['CheckoutId', 'PersonId', 'BookId', 'CheckedOutOn', 'CheckedOutBy',
                'CheckedOutIp', 'CheckedOutUserAgent', 'DueOn', 'TimesRenewed', 'LastRenewedOn', 'CheckedInOn',
                'CheckedInBy', 'CheckedInIp', 'CheckedInUserAgent', 'AdminNotes', 'AdminNotesUpdatedOn',
                'AdminNotesUpdatedBy', 'UpdatedOn', 'UpdatedBy']);
//, 'CurrentCheckouts' => new Expression('(SELECT MAX(`CheckoutId`)
//  FROM `lib_checkouts` WHERE (`BookId` = `book_id` AND ISNULL(`CheckedInOn`)))')]);
//         $select->group(['TheMonth', 'TheYear']);
//         $select->where($predicate->in('ChangedEntity', $tableEntities));
            $select->order(['CheckedInOn', 'CheckedOutOn' => 'DESC']);
        }

        return clone $select;
    }

    protected function processCheckoutRow($row)
    {
        static $today;
        if (!isset($today)) {
            $tz = new \DateTimeZone('UTC');
            $today = new \DateTime(null, $tz);
        }
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

        $processedRow = [
            'checkoutId'            => $id,
            'personId'              => $this->filterDbId($row['PersonId']),
            'bookId'                => $this->filterDbId($row['BookId']),
            'checkedOutOn'          => $this->filterDbDate($row['CheckedOutOn']),
            'checkedOutBy'          => $this->filterDbId($row['CheckedOutBy']),
            'checkedOutIp'          => $row['CheckedOutIp'],
            'checkedOutUserAgent'   => $row['CheckedOutUserAgent'],
            'dueOn'                 => $dueOn,
            'timesRenewed'          => $this->filterDbInt($row['TimesRenewed']),
            'lastRenewedOn'         => $this->filterDbDate($row['LastRenewedOn']),
            'checkedInOn'           => $checkedIn,
            'checkedInBy'           => $this->filterDbId($row['CheckedInBy']),
            'checkedInIp'           => $row['CheckedInIp'],
            'checkedInUserAgent'    => $row['CheckedInUserAgent'],
            'adminNotes'            => $row['AdminNotes'],
            'adminNotesUpdatedOn'   => $this->filterDbDate($row['AdminNotesUpdatedOn']),
            'adminNotesUpdatedBy'   => $this->filterDbId($row['AdminNotesUpdatedBy']),
            'updatedOn'             => $this->filterDbDate($row['UpdatedOn']),
            'updatedBy'             => $this->filterDbId($row['UpdatedBy']),

            'status'                => $status,
            'book'                  => null,
            'person'                => null,
        ];
        return $processedRow;
    }

    /**
     * Return an array of checkouts performed by a certain person
     * @todo maybe this function could be much much simpler if we create a little linkCheckouts function
     * @param int $personId
     * @return mixed[]
     */
    public function getCheckoutsForPerson($personId)
    {
        static $gateway;
        if (!isset($gateway)) {
            $gateway = $this->getTableGateway('lib_checkouts');
        }
        $select = $this->getCheckoutSelectPrototype();
        $select->where(['PersonId' => $personId]);
        $result = $gateway->selectWith($select);
        $results = $result->toArray();

        if (!isset($results[0])) {
            return null;
        }
        $objects = [];
        $libraryCache = [];
        foreach ($results as $row) {
            $object = $this->processCheckoutRow($row);
            if (isset($object['bookId'])) {
                // @todo we should actually condense this into one SQL query
                $object['book'] = $this->getObject('book', $object['bookId']);
            }
            $libraryId = $object['book']['libraryId'];
            if (isset($libraryId)) {
                if (isset($libraryCache[$libraryId])) {
                    $object['book']['library'] = $libraryCache[$libraryId];
                } else {
                    $library = $this->getObject('library', $libraryId);
                    $libraryCache[$libraryId] = $library;
                    $object['book']['library'] = $library;
                }
            }
            $objects[$object['checkoutId']] = $object;
        }

        return $objects;
    }

    /**
     * Get an array of checkouts for a given libraryId
     * @param int $libraryId
     * @param string $subset
     */
    public function getCheckoutsForLibrary($libraryId, $subset = 'all')
    {
        //@todo only query the database for particular checkouts (depending on the subset)
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
     * @todo create a linkCheckouts function to do a lot of the heavy lifting
     * @return mixed[]
     */
    public function getCheckouts(array $checkoutIds = [])
    {
        if (empty($checkoutIds) && null !== ($cache = $this->fetchCachedEntityObjects('checkouts'))) {
            return $cache;
        }

        $entities = $this->getUnlinkedCheckouts($checkoutIds);

        //ennumerate bookIds to avoid processing ALL books
        $bookIds = [];
        foreach ($entities as $checkout) {
            if (!isset($bookIds[$checkout['bookId']])) {
                $bookIds[$checkout['bookId']] = true;
            }
        }

        $books = $this->getUnlinkedBooks(array_keys($bookIds));
        $libraries = $this->getObjects('library');

        foreach ($entities as $entityId => $entity) {
            if (isset($books[$entity['bookId']]) &&
                isset($libraries[$books[$entity['bookId']]['libraryId']])
            ) {
                //get a reference to the library entry so we don't make a lot of array copies
                $books[$entity['bookId']]['library'] = &$libraries[$books[$entity['bookId']]['libraryId']];
                $entities[$entityId]['book'] = $books[$entity['bookId']];
            } else {
                unset($entities[$entityId]); //get rid of bad records
            }
        }

        if (empty($checkoutIds)) {
            $this->cacheEntityObjects('checkouts', $entities, ['checkout', 'book', 'library']);
        }
        return $entities;
    }

    /**
     *
     * @param int $id
     * @return mixed[]
     */
    public function getCheckout($id)
    {
        static $gateway;
        if (!isset($gateway)) {
            $gateway = $this->getTableGateway('lib_checkouts');
        }
        $select = $this->getCheckoutSelectPrototype();
        $select->where(['CheckoutId' => $id]);
        $result = $gateway->selectWith($select);
        $results = $result->toArray();

        if (!isset($results[0])) {
            return null;
        }
        $object = $this->processCheckoutRow($results[0]);
        if (isset($object['bookId'])) {
            $object['book'] = $this->getObject('book', $object['bookId']);
        }

        return $object;
    }

    /**
     * @todo factor out
     */
    protected function getUnlinkedCheckouts(array $checkoutIds = [])
    {
        if (empty($checkoutIds) && null !== ($cache = $this->fetchCachedEntityObjects('unlinked-checkouts'))) {
            return $cache;
        }
        $gateway = $this->getTableGateway('lib_checkouts');

        $where = [];
        if (!empty($checkoutIds)) {
            $where['CheckoutId'] = $checkoutIds;
        }

        $select = $this->getCheckoutSelectPrototype();
        if (!empty($where)) {
            $select->where($where);
        }
        $results = $gateway->selectWith($select);

        $entities = [];
        foreach ($results as $row) {
            $processedRow = $this->processCheckoutRow($row);
            $id = $processedRow['checkoutId'];
            $entities[$id] = $processedRow;
        }

        if (empty($checkoutIds)) {
            $this->cacheEntityObjects('unlinked-checkouts', $entities, ['checkout']);
        }
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
     * {@inheritDoc}
     * @see \SionModel\Problem\ProblemProviderInterface::getProblems()
     */
    public function getProblems($minimumSeverity = EntityProblem::SEVERITY_INFO)
    {
        return array_merge($this->getLibrariesProblems($minimumSeverity), $this->getCollectionProblems($minimumSeverity));
    }
    
    /**
     * @todo add problems for unacceptable call numbers
     * @param number $libraryId
     * @param string $minimumSeverity
     * @return array
     */
    public function getLibraryBookProblems($libraryId, $minimumSeverity = EntityProblem::SEVERITY_INFO)
    {
//         $persons = $this->getPersons();
        
        $problems = [];
//         foreach ($persons as $person) {
//             if (!isset($person['email'])) {
//                 $obj = clone $this->entityProblemPrototype;
//                 $obj->setProblem(self::PROBLEM_PERSON_NO_EMAIL)
//                 ->setData($person);
//                 $problems[] = $obj;
//             }
//         }
        return $problems;
    }
    
    public function getLibrariesProblems($minimumSeverity = EntityProblem::SEVERITY_INFO)
    {
        $problems = [];
        //look for configuration problems
        $objects = $this->getObjects('library');
        foreach ($objects as $object) {
            $problems = array_merge($problems, $this->getLibraryProblems($object, $minimumSeverity));
        }
        return $problems;
    }
    
    public function getLibraryProblems(array $libraryObject, $minimumSeverity = EntityProblem::SEVERITY_INFO)
    {
        $problems = [];
        /** @var \Books\Model\LibraryOptions $libraryOptions */
        $libraryOptions = $libraryObject['options'];
        if ($libraryOptions->requireCallNumbers
            && $libraryOptions->enforceCallNumberRegex
            && !isset($libraryOptions->callNumberRegex)
            //don't throw a problem if they require collections
            && ( $libraryOptions->allowCollectionlessBooks
                || empty($libraryOptions->collections))
        ) {
            $obj = clone $this->entityProblemPrototype;
            $obj->setProblem(self::PROBLEM_LIBRARY_MISSING_CALL_NUMBER_FORMAT)
            ->setData($libraryObject);
            $problems[] = $obj;
        } else {
            $regex = $libraryOptions->callNumberRegex;
            if (isset($regex) && false !== @preg_match($regex, null)) {
                $obj = clone $this->entityProblemPrototype;
                $obj->setProblem(self::PROBLEM_LIBRARY_INVALID_CALL_NUMBER_FORMAT)
                ->setData($libraryObject);
                $problems[] = $obj;
            }
        }
        $collectionsProblems = $this->getLibraryCollectionProblems($libraryObject, $minimumSeverity);
        $problems = array_merge($problems, $collectionsProblems);
        return $problems;
    }
    
    public function getCollectionProblems($minimumSeverity = EntityProblem::SEVERITY_INFO)
    {
        $problems = [];
        //look for configuration problems
        $objects = $this->getObjects('library');
        foreach ($objects as $object) {
            $problems = $this->getLibraryCollectionProblems($object);
        }
        return $problems;
    }
    
    protected function getLibraryCollectionProblems(array $libraryObject, $minimumSeverity = EntityProblem::SEVERITY_INFO)
    {
        $problems = [];
        /** @var \Books\Model\LibraryOptions $libraryOptions */
        $libraryOptions = $libraryObject['options'];
        
        $libraryHasRegex = isset($libraryOptions->callNumberRegex);
        foreach ($libraryOptions->collections as $collection) {
            if ($libraryHasRegex
                && $collection->requireCallNumbers
                && $collection->enforceCallNumberRegex
                && !isset($collection->callNumberRegex)
            ) {
                $obj = clone $this->entityProblemPrototype;
                $obj->setProblem(self::PROBLEM_COLLECTION_MISSING_CALL_NUMBER_FORMAT)
                ->setData($collection->getArrayCopy());
                $problems[] = $obj;
            }
            $regex = $collection->callNumberRegex;
            if (isset($regex) && false !== @preg_match($regex, null)) {
                $obj = clone $this->entityProblemPrototype;
                $obj->setProblem(self::PROBLEM_COLLECTION_INVALID_CALL_NUMBER_FORMAT)
                ->setData($collection->getArrayCopy());
                $problems[] = $obj;
            }
        }
        
        return $problems;
    }
    
    /**
     * {@inheritDoc}
     * @see \SionModel\Problem\ProblemProviderInterface::autoFixProblems()
     */
    public function autoFixProblems($simulate = true)
    {
        static $tableGateway;
        $problems = [];
        $books = $this->searchBooks(['isActive' => true, 'libraryId' => $this->libraryId, 'sortText' => null]);
        foreach ($books as $object) {
            $sortText = $this->getBookSortText($object);
            if (isset($sortText)) {
                $obj = clone $this->entityProblemPrototype;
                $obj->setProblem(self::PROBLEM_COLLECTION_INVALID_CALL_NUMBER_FORMAT)
                    ->setData($object);
                $problems[] = $obj;
                if (!$simulate) {
                    if (!isset($tableGateway)) {
                        $tableGateway = $this->getTableGateway('lib_books');
                    }
                    $tableGateway->update(['sort_text' => $sortText], ['book_id' => $object['bookId']]);
                }
            }
        }
        return $problems;
    }

    public function getResources()
    {
        $return = [];
        $libraries = $this->getObjects('library');
        foreach ($libraries as $libraryId => $object) {
            $return[] = new GenericResource($object['resourceId']);
        }
        return $return;
    }

    /**
     * {@inheritDoc}
     * @see \BjyAuthorize\Provider\Rule\ProviderInterface::getRules()
     * Format of the allow key is [['role1', 'role2'], 'resourceId', 'permission']
     */
    public function getRules()
    {
        $libraries = $this->getObjects('library');

        $allow = [];
        foreach ($libraries as $object) {
            if (isset($object['viewRole'])) {
                $viewRoles = [$object['viewRole']];
                if ($object['viewRole'] == 'guest') { //if it's free to guests, it should also be open to users.
                    $viewRoles[] = 'user';
                }
                $allow[] = [$viewRoles, $object['resourceId'], 'show'];
            }
            if (isset($object['checkoutBooksRole'])) {
                $roles = [$object['checkoutBooksRole']];
                if ($object['checkoutBooksRole'] == 'guest') { //if it's free to guests, it should also be open to users.
                    $roles[] = 'user';
                }
                $allow[] = [$roles, $object['resourceId'], 'checkout'];
            }
            /*
             * @todo create a way of adding a list of library administrators from a table
             * I imagine a table (ResourceId, PermissionId, RuleType['role', 'user', 'person'], Id
             * There could be a common SionModel form for adding/editing/viewing these rules.
             * The form would use protected values and an setData override to allow assertion of
             * only intended resource/permission modifications
             */
            $allow[] = [['lib_administrator'], $object['resourceId'], 'administrate'];
        }
        return ['allow' => $allow];
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
