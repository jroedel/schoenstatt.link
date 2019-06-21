<?php
namespace Books\Model;

use SionModel\Db\Model\SionTable;
use SionModel\Filter\ToAscii;
use Schoenstatt\Model\SchoenstattTable;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Predicate\Expression;
use SionModel\Db\Model\PredicatesTable;
use Zend\Db\Sql\Where;
use Zend\Db\Sql\Predicate\PredicateSet;
use Zend\Db\Sql\Predicate\Operator;
use Zend\Db\Sql\Predicate\In;
use Zend\Db\Sql\Predicate\IsNull;
use Zend\Db\Sql\Predicate\Predicate;
use Zend\Db\Sql\Predicate\Like;

class PublicationsTable extends SionTable
{
    const BOOK_FORMAT_TYPE_HARDCOVER        = 'Hardcover';
    const BOOK_FORMAT_TYPE_PAPERBACK        = 'Paperback';
    const BOOK_FORMAT_TYPE_AUDIOBOOKFORMAT  = 'AudiobookFormat';
    const BOOK_FORMAT_TYPE_EBOOK            = 'EBook';

    const BOOK_FORMAT_TYPE_URLS = [
        self::BOOK_FORMAT_TYPE_HARDCOVER => 'http://schema.org/Hardcover',
        self::BOOK_FORMAT_TYPE_PAPERBACK => 'http://schema.org/Paperback',
        self::BOOK_FORMAT_TYPE_AUDIOBOOKFORMAT => 'http://schema.org/AudiobookFormat',
        self::BOOK_FORMAT_TYPE_EBOOK => 'http://schema.org/EBook',
    ];

    const DEFAULT_RESULTS_PER_PAGE = 25;

    /**
    * @var SchoenstattTable $schoenstattTable
    */
    protected $schoenstattTable;

    /**f
     * @var PredicatesTable $predicatesTable
     */
    protected $predicatesTable;

    /**
     * @var array $publicationsMemoryCache
     */
    protected $unlinkedPublicationsMemoryCache = [];

    public function getAuthorsValueOptions()
    {
        $cacheKey = 'publication-authors';
        if (null !== ($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }
        $authorPersons = $this->getAuthorsNoAssociationsValueOptions();
        $authorAssociations = $this->getAuthorAssociationValueOptions();

        $authors = array_merge($authorPersons, $authorAssociations);
        asort($authors);
        $this->cacheEntityObjects($cacheKey, $authors, ['publication']);
        return $authors;
    }

    public function getAuthorsNoAssociationsValueOptions()
    {
        $cacheKey = 'publication-authors-no-associations';
        if (null !== ($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }
        $authorPersons = $this->getAuthorPersonValueOptions();
        $authorTexts = $this->getAuthorTextValueOptions();

        $authors = array_merge($authorPersons, $authorTexts);
        asort($authors);
        $this->cacheEntityObjects($cacheKey, $authors, ['publication']);
        return $authors;
    }

    public function getAuthorPersonValueOptions()
    {
        $authors = [];
        $authorPersons = $this->schoenstattTable->getUnlinkedPersons();
        foreach ($authorPersons as $personId => $object) {
            if (!$object['isAuthor']) {
                continue;
            }
            $authors['p'.$personId] = $object['fullName'];
        }
        return $authors;
    }

    public function getAuthorAssociationValueOptions()
    {
        $authors = [];
        $authorAssociations = $this->schoenstattTable->getObjects('association');
        foreach ($authorAssociations as $associationId => $object) {
            if (!$object['isAuthor']) {
                continue;
            }
            $authors['a'.$associationId] = $object['formattedName'];
        }
        return $authors;
    }

    public function getAuthorTextValueOptions()
    {
        $cacheKey = 'publication-author-texts';
        if (null !== ($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }
        $sql = "SELECT Author FROM
(SELECT DISTINCT `Authors` AS Author FROM `sch_publications` a
UNION SELECT DISTINCT `Editor` AS Author FROM `sch_publications` b
UNION SELECT DISTINCT `Illustrator` AS Author FROM `sch_publications` c
UNION SELECT DISTINCT `Translator` AS Author FROM `sch_publications` d ) e
GROUP BY Author ORDER BY Author";
        $results = $this->fetchSome(null, $sql, null);

        $authors = [];
        $authorConcatenations = []; //these might be repeated so we have to check
        foreach ($results as $row) {
            $author = $this->filterDbString($row['Author']);
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
        $this->cacheEntityObjects($cacheKey, $authors, ['publication']);
        return $authors;
    }

    // @todo add publisher associations
    public function getPublishersValueOptions()
    {
        $sql = "SELECT DISTINCT `Publisher`
FROM `sch_publications`
ORDER BY `Publisher`";
        $results = $this->fetchSome(null, $sql, null);
        $values = null;
        foreach ($results as $row) {
            $publisher = $this->filterDbString($row['Publisher']);
            if (isset($publisher)) {
                $values[$publisher] = $publisher;
            }
        }
        return $values;
    }

    public function getKeywordsValueOptions()
    {
        $publications = $this->getUnlinkedPublications();
        $valueOptions = [];
        foreach ($publications as $publication) {
            foreach ($publication['keywords'] as $keyword) {
                if (!isset($valueOptions[$keyword])) {
                    $valueOptions[$keyword] = $keyword;
                }
            }
        }
        return $valueOptions;
    }

    public function getEditionValueOptions($onlyMainEditions = false)
    {
        $entities = $this->getUnlinkedPublications();
        $return = [];
        foreach ($entities as $entityId => $entityObject) {
            $entry = $entityObject['title'];
            if (isset($entityObject['bookEdition']) || isset($entityObject['copyrightYear'])) {
                if (isset($entityObject['bookEdition']) && isset($entityObject['copyrightYear'])) {
                    $entry .= ('['.$entityObject['bookEdition'].', '.$entityObject['copyrightYear']. ']');
                } else {
                    $entry .= ('['.$entityObject['bookEdition'].$entityObject['copyrightYear']. ']');
                }
            }
            $return[$entityId] = $entry;
        }
        return $return;
    }

    public function getCategoryValueOptions($includePlaceholders = false)
    {
        $entities = $this->getCategories();
        $return = [];
        foreach ($entities as $entityId => $entityObject) {
            if (!$entityObject['isPlaceholder'] || $includePlaceholders) {
                $return[$entityId] = $entityObject['fullName'];
            }
        }
        return $return;
    }

    /**
     * Get an associated array where language codes are given as the key and the count of
     * associated publications as the value
     * @param string $includeUser
     * @param string $includeInstitute
     * @param string $includePatres
     * @return array
     *
     * @todo reduce this to an sql query
     */
    public function getPublicationLanguageCounts($includeUser = false, $includeInstitute = false, $includePatres = false)
    {
        $cacheKey = 'publication-languages-'.($includeUser ? '1':'0').($includeInstitute? '1':'0').($includePatres? '1':'0');
        if (null !== ($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }
        $entities = $this->getUnlinkedPublications();

        $languages = [];
        foreach ($entities as $object) {
            if (('publication_public' ===  $object['resourceId'] && (isset($this->actingUserId) || $object['isRevisedWithBookInHand'])) ||
                ($includeUser && 'publication_user' === $object['resourceId']) ||
                ($includeInstitute && 'publication_institute' === $object['resourceId']) ||
                ($includePatres && 'publication_patres' === $object['resourceId'])
            ) {
                if (!isset($object['inLanguage'])) {
                    if (!isset($languages['xx'])) {
                        $languages['xx'] = 1;
                    } else {
                        $languages['xx']++;
                    }
                } else {
                    if (!isset($languages[$object['inLanguage']])) {
                        $languages[$object['inLanguage']] = 1;
                    } else {
                        $languages[$object['inLanguage']]++;
                    }
                }
            }
        }
        $this->cacheEntityObjects($cacheKey, $languages, ['publication']);
        return $languages;
    }

    /**
     * Get a standardized select object to retrieve records from the database
     * @return \Zend\Db\Sql\Select
     */
    protected function getPublicationsSelectPrototype()
    {
        static $select;
        if (!isset($select)) {
            $select = new Select('sch_publications');
            $select->columns(['PublicationId', 'Title', 'TitleNoAccents', 'Subtitle', 'SubtitleNoAccents',
                'ResourceId', 'Authors', 'AuthorsNoAccents', 'BookEdition', 'InLanguage', 'Description',
                'Isbn', 'Translator', 'Illustrator', 'Editor', 'EditorNoAccents',
                'NumberOfPages', 'CopyrightYear', 'CopyrightInfo', 'Publisher', 'PublishingPlace', 'DatePublishedText',
                'DatePublished', 'PublishingStatus', 'BookFormatType', 'MainPublicationId', 'VolumeNumber',
                'ContainedIn', 'ContainedInIsbn', 'Genre', 'PublicTags', 'AdminTags', 'IsAccessableForFree',
                'IsInternalForPatres', 'IsScientificWork', 'IsAwaitingMerge', 'HasBeenMerged', 'JkQuality',
                'JkQualityNotes', 'JkPeriod', 'JkEventId', 'Url1', 'Url1Label', 'Url2', 'Url2Label', 'Url3',
                'Url3Label', 'DataSource', 'DataSourceId', 'DataSourceUpdatedOn', 'PublicNotes',
                'PublicNotesUpdatedOn', 'PublicNotesUpdatedBy', 'AdminNotes', 'AdminNotesUpdatedOn',
                'AdminNotesUpdatedBy', 'UpdatedOn', 'UpdatedBy', 'CreatedOn', 'CreatedBy', 'HasNoISBN',
                'IsRevisedWithBookInHand', 'PublishDataAsJsonLd', 'IsFormallyPublished',
                'TranslatedFromPublicationId', 'HasNoExplictEditionNumber', 'EditionNotes', 'CategoryId',
                'CategorySortOrder' => new Expression('IF(ISNULL(`SortOrder`), 1000, `SortOrder`)')]);
                //@todo add a boolean expression whether the user likes/watches/has-read each particular book.
                //I think we can do it with a left join

            $select->join(
                'sch_pub_categories',
                'sch_pub_categories.PublicationCategoryId = sch_publications.CategoryId',
                ['SortOrder', 'CategoryName', 'CategoryParentId' => 'ParentId'],
                Select::JOIN_LEFT
            );
            $select->order(['CategorySortOrder', 'Authors', 'InLanguage', 'Title']);
        }

        return clone $select;
    }

    /**
     * Search for publications. Returns a list of publications.
     * @param mixed[] $query
     * @return mixed[]
     */
    public function searchPublications($query, $options = [])
    {
        //@todo we're not searching authors described by author personId's
        $filter = new ToAscii();
        if (isset($query['search'])) {
            $query['search'] = $filter->filter($query['search']);
        }

//         $queryParameters = [
//             'title', 'authorsText', 'search', 'publisher',
//             'description', 'categoryId', 'inLanguage',
//             'mainPublicationId', 'translatedFromPublicationId', 'publicationId',
//             'resourceId' //@todo finish this (we should be limiting on the search action)
//         ];
//         $possibleOptions = ['maxResults', 'page', 'resultsPerPage', 'orCombination', 'noLink', 'noSubEditions'];

        $fieldMap = $this->getEntitySpecification('publication')->updateColumns;
        $fieldMap['category'] = 'CategoryName';

        $gateway = $this->getTableGateway('sch_publications');
        $select = $this->getPublicationsSelectPrototype();
        $where = new Where();

        $combination = (isset($options['orCombination']) && $options['orCombination']) ? PredicateSet::OP_OR : PredicateSet::OP_AND;

        //Prepare the search predicate
        if (isset($query['search'])) {
            $search = $query['search'];
            $searchLike = sprintf("%%%s%%", $search);
            $searchClause = new Predicate();
            $searchClause->addPredicates([
                new Like($fieldMap['title'], $searchLike),
                new Like($fieldMap['authorsText'], $searchLike),
                new Like($fieldMap['category'], $searchLike),
                new Like($fieldMap['publisher'], $searchLike),
                new Like($fieldMap['description'], $searchLike),
            ], PredicateSet::OP_OR);
            $where->addPredicate($searchClause);
        }

        // Prepare collectionId predicate
        if (isset($query['categoryId'])) {
            $categoryIdClause = null;
            if (is_array($query['categoryId'])) {
                $categories = [];
                foreach ($query['categoryId'] as $value) {
                    if (is_numeric($value) && !in_array($value, $categories)) {
                        $categories[] = $value;
                    }
                }
                if (count($categories) === 1) {
                    $query['categoryId'] = $categories[0];
                } elseif (count($categories) > 1) {
                    $categoryIdClause= new In($fieldMap['categoryId'], $categories);
                }
            }
            if (is_numeric($query['categoryId'])) {
                $categoryIdClause= new Operator($fieldMap['categoryId'], Operator::OPERATOR_EQUAL_TO, $query['categoryId']);
            }
            if (isset($categoryIdClause)) {
                $where->addPredicate($categoryIdClause, $combination);
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
                $publicationIdClause= new Operator($fieldMap['publicationId'], Operator::OPERATOR_EQUAL_TO, $query['publicationId']);
            }
            if (isset($publicationIdClause)) {
                $where->addPredicate($publicationIdClause, $combination);
            }
        }

        // Prepare mainPublicationId predicate
        if (isset($query['mainPublicationId'])) {
            $mainPublicationIdClause = null;
            if (is_array($query['mainPublicationId'])) {
                $mainPublications = [];
                foreach ($query['mainPublicationId'] as $value) {
                    if (is_numeric($value) && !in_array($value, $mainPublications)) {
                        $mainPublications[] = $value;
                    }
                }
                if (count($mainPublications) === 1) {
                    $query['mainPublicationId'] = $mainPublications[0];
                } elseif (count($mainPublications) > 1) {
                    $mainPublicationIdClause= new In($fieldMap['mainPublicationId'], $mainPublications);
                }
            }
            if (is_numeric($query['mainPublicationId'])) {
                $mainPublicationIdClause= new Operator($fieldMap['mainPublicationId'], Operator::OPERATOR_EQUAL_TO, $query['mainPublicationId']);
            }
            if (isset($mainPublicationIdClause)) {
                $where->addPredicate($mainPublicationIdClause, $combination);
            }
        }

        // Prepare translatedFromPublicationId predicate
        if (isset($query['translatedFromPublicationId'])) {
            $translatedFromPublicationIdClause = null;
            if (is_array($query['translatedFromPublicationId'])) {
                $translatedFromPublications = [];
                foreach ($query['translatedFromPublicationId'] as $value) {
                    if (is_numeric($value) && !in_array($value, $translatedFromPublications)) {
                        $translatedFromPublications[] = $value;
                    }
                }
                if (count($translatedFromPublications) === 1) {
                    $query['translatedFromPublicationId'] = $translatedFromPublications[0];
                } elseif (count($translatedFromPublications) > 1) {
                    $translatedFromPublicationIdClause= new In($fieldMap['translatedFromPublicationId'], $translatedFromPublications);
                }
            }
            if (is_numeric($query['translatedFromPublicationId'])) {
                $translatedFromPublicationIdClause= new Operator($fieldMap['translatedFromPublicationId'], Operator::OPERATOR_EQUAL_TO, $query['translatedFromPublicationId']);
            }
            if (isset($translatedFromPublicationIdClause)) {
                $where->addPredicate($translatedFromPublicationIdClause, $combination);
            }
        }

        //@todo this should be LIKE
        //Prepare title predicate
        if (isset($query['title']) && 0 !== strlen($query['title'])) {
            $search = $query['title'];
            $searchLike = sprintf("%%%s%%", $search);
            $titleClause = new Like($fieldMap['title'], $searchLike);
            $where->addPredicate($titleClause, $combination);
        }

        //@todo this should be LIKE
        //Prepare author predicate
        if (isset($query['authorsText']) && 0 !== strlen($query['authorsText'])) {
            $search = $query['authorsText'];
            $searchLike = sprintf("%%%s%%", $search);
            $authorClause = new Like($fieldMap['authorsText'], $searchLike);
            $where->addPredicate($authorClause, $combination);
        }

        //Prepare inLanguage predicate
        if (isset($query['inLanguage']) && 0 !== strlen($query['inLanguage'])) {
            $inLanguageClause = new Operator($fieldMap['inLanguage'], Operator::OPERATOR_EQUAL_TO, $query['inLanguage']);
            $where->addPredicate($inLanguageClause, PredicateSet::OP_AND); //I don't think it would ever make sense combine with OR here
        }

        //@todo check if this really works
        //Prepare isSubEdition predicate, by default, don't filter
        if (isset($options['noSubEditions']) && $options['noSubEditions']) {
            $noSubEditionClause= new IsNull($fieldMap['mainPublicationId']);
            $where->addPredicate($noSubEditionClause, PredicateSet::OP_AND);
        }

        //Set the where clause
        $select->where($where);

        if (isset($options['maxResults']) && is_numeric($options['maxResults'])) {
            $select->limit($options['maxResults']);
        }

        if (isset($options['page']) && is_numeric($options['page'])) {
            $resultsPerPage = isset($options['resultsPerPage']) && is_numeric($options['resultsPerPage']) ? $options['resultsPerPage'] : self::DEFAULT_RESULTS_PER_PAGE;
            $select->offset($options['page'] * $resultsPerPage);
        }

        $results = $gateway->selectWith($select);
        $entities = [];

        foreach ($results as $row) {
            $processedRow = $this->processPublicationRow($row);
            $entities[$processedRow['publicationId']] = $processedRow;
        }

        if (!isset($options['noLink']) || !$options['noLink']) {
            $this->linkPublications($entities);
        }

        return $entities;
    }

    public function getPublications()
    {
        if (null !== ($cache = $this->fetchCachedEntityObjects('publications'))) {
            return $cache;
        }

        $entities = $this->getUnlinkedPublications();
//         $persons = $this->schoenstattTable->getUnlinkedPersons();
//         $associations = $this->schoenstattTable->getUnlinkedAssociations();

        foreach ($entities as $entityId => $entityObject) {
            if (isset($entityObject['mainPublicationId']) &&
                $entityObject['mainPublicationId'] != $entityId &&
                isset($entities[$entityObject['mainPublicationId']])
            ) {
                $entities[$entityId]['mainPublication'] = $entities[$entityObject['mainPublicationId']];
                $entities[$entityObject['mainPublicationId']]['subEditions'][$entityId] = &$entities[$entityId];
            }
            if (isset($entityObject['translatedFromPublicationId']) &&
                $entityObject['translatedFromPublicationId'] != $entityId &&
                isset($entities[$entityObject['translatedFromPublicationId']])
            ) {
                $entities[$entityId]['translatedFromPublication'] = $entities[$entityObject['translatedFromPublicationId']];
            }

//             foreach ($entityObject['authorPersonIds'] as $personId) {
//                 if (isset($persons[$personId])) {
//                     $entities[$entityId]['authorPersons'][$personId] = $persons[$personId];
//                 }
//             }
//             foreach ($entityObject['authorAssociationIds'] as $associationId) {
//                 if (isset($associations[$associationId])) {
//                     $entities[$entityId]['authorAssociations'][$associationId] = $associations[$associationId];
//                 }
//             }
//             foreach ($entityObject['editorPersonIds'] as $personId) {
//                 if (isset($persons[$personId])) {
//                     $entities[$entityId]['editorPersons'][$personId] = $persons[$personId];
//                 }
//             }
//             if (isset($associations[$entityObject['editorAssociationId']])) {
//                 $entities[$entityId]['editorAssociation'] = $associations[$entityObject['editorAssociationId']];
//             }
//             foreach ($entityObject['translatorPersonIds'] as $personId) {
//                 if (isset($persons[$personId])) {
//                     $entities[$entityId]['translatorPersons'][$personId] = $persons[$personId];
//                 }
//             }
//             if (isset($persons[$entityObject['illustratorPersonId']])) {
//                 $entities[$entityId]['illustratorPerson'] = $persons[$entityObject['illustratorPersonId']];
//             }
//             if (isset($associations[$entityObject['publisherAssociationId']])) {
//                 $entities[$entityId]['publisherAssociation'] = $associations[$entityObject['publisherAssociationId']];
//             }
        }

        $this->cacheEntityObjects('publications', $entities, ['publication']);
        return $entities;
    }

    /**
     * Get an array of publications
     * @param array $ids
     * @return array
     */
    public function getUnlinkedPublications(array $ids = [])
    {
        if (null !== ($cache = $this->fetchCachedEntityObjects('unlinked-publications'))) {
            return $cache;
        }
        $gateway = $this->getTableGateway('sch_publications');
        $select = $this->getPublicationsSelectPrototype();
        if (!empty($ids)) {
            $select->where(['publicationId' => $ids]);
        }
        $results = $gateway->selectWith($select);

        $entities = [];
        foreach ($results as $row) {
            $processedRow = $this->processPublicationRow($row);
            $id = $processedRow['publicationId'];
            $entities[$id] = $processedRow;
        }

        $this->cacheEntityObjects('unlinked-publications', $entities, ['publication']);
        return $entities;
    }

    /**
     * @todo this function could first check if the publicationId is in the memory cache and just return a reference
     * @return mixed[]
     */
    protected function &processPublicationRow($row)
    {
        static $categories;
        $id = $this->filterDbId($row['PublicationId']);

        if (isset($this->unlinkedPublicationsMemoryCache[$id])) {
            return $this->unlinkedPublicationsMemoryCache[$id];
        }

        if (!isset($categories)) {
            $categories = $this->getCategories();
        }
            //process URLs
            $unprocessedUrls = [
                ['url' => $row['Url1'], 'label' => $this->filterDbString($row['Url1Label'])],
                ['url' => $row['Url2'], 'label' => $this->filterDbString($row['Url2Label'])],
                ['url' => $row['Url3'], 'label' => $this->filterDbString($row['Url3Label'])],
            ];
            $urls = $this::processUrls($unprocessedUrls);
            $mainPublicationId = $this->filterDbId($row['MainPublicationId']);

            $resourceId = $this->filterDbString($row['ResourceId']);

            $authorsAll = [];

            $authorsText = $this->filterDbArray($row['Authors'], '; ');
            foreach ($authorsText as $author) {
                $authorsAll[] = $author;
            }

            $editorsAll = [];
            $editorText = $this->filterDbArray($row['Editor']);
            if (isset($editorText)) {
                foreach ($editorText as $value) {
                    $editorsAll[] = $value;
                }
            }

            $translatorsAll = [];
            $translatorText = $this->filterDbArray($row['Translator']);
            if (isset($translatorText)) {
                foreach ($translatorText as $value) {
                    $translatorsAll[] = $value;
                }
            }

            $illustratorsAll = [];
            $illustratorText = $this->filterDbArray($row['Illustrator']);
            if (isset($illustratorText)) {
                foreach ($illustratorText as $value) {
                    $illustratorsAll[] = $value;
                }
            }

            $bookFormatType = $this->filterDbString($row['BookFormatType']);
            $bookFormatTypeUrl = null;
            if (isset(self::BOOK_FORMAT_TYPE_URLS[$bookFormatType])) {
                $bookFormatTypeUrl = self::BOOK_FORMAT_TYPE_URLS[$bookFormatType];
            }
            $inLanguage = $this->filterDbString($row['InLanguage']);
            if (!isset($inLanguage)) {
                $inLanguage = 'xx';
            }

            if (file_exists(sprintf('public/covers/%s.jpg', $id))) {
                $coverImage = sprintf('/covers/%s.jpg', $id);
                if (file_exists(sprintf('public/covers/%s-80px.jpg', $id))) {
                    $coverThumbnail80 = sprintf('/covers/%s-80px.jpg', $id);
                } else {
                    $coverThumbnail80 = null;
                }
                if (file_exists(sprintf('public/covers/%s-200px.jpg', $id))) {
                    $coverThumbnail200 = sprintf('/covers/%s-200px.jpg', $id);
                } else {
                    $coverThumbnail200 = null;
                }
            } else {
                $coverImage = null;
                $coverThumbnail80 = null;
                $coverThumbnail200 = null;
            }

            $categoryId = $this->filterDbId($row['CategoryId']);

            $datePublished = $this->filterDbDate($row['DatePublished']);


            $processedRow = [
                'publicationId'             => $id,
                'title'                     => $row['Title'],
                'titleNoAccents'            => $row['TitleNoAccents'],
                'subtitle'                  => $row['Subtitle'],
                'subtitleNoAccents'         => $row['SubtitleNoAccents'],
                'resourceId'                => $resourceId,
                'authorsText'               => $authorsText,
                'authorsNoAccents'          => $row['AuthorsNoAccents'],
                'bookEdition'               => $this->filterDbString($row['BookEdition']),
                'categoryId'                => $categoryId,

                'inLanguage'                => $inLanguage,
                'description'               => $this->filterDbString($row['Description']),
                'isbn'                      => $this->filterDbString($row['Isbn']),
                'editorsText'               => $editorText,
                'editorsNoAccents'          => $row['EditorNoAccents'],
                'translatorsText'           => $translatorText,
                'illustratorsText'          => $illustratorText,
                'numberOfPages'             => $this->filterDbInt($row['NumberOfPages']),
                'copyrightYear'             => $this->filterDbInt($row['CopyrightYear']),
                'copyrightInfo'             => $row['CopyrightInfo'],
                'datePublishedText'         => $row['DatePublishedText'],
                'publisher'                 => $this->filterDbString($row['Publisher']),
                'publishingPlace'           => $this->filterDbString($row['PublishingPlace']),
                'datePublished'             => $datePublished,
                'publishingStatus'          => $this->filterDbString($row['PublishingStatus']),
                'bookFormatType'            => $bookFormatType,
                'mainPublicationId'         => $mainPublicationId,
                'translatedFromPublicationId'=> $this->filterDbId($row['TranslatedFromPublicationId']),
                'volumeNumber'              => $this->filterDbString($row['VolumeNumber']),
                'containedIn'               => $this->filterDbString($row['ContainedIn']),
                'containedInIsbn'           => $this->filterDbString($row['ContainedInIsbn']),
                'genre'                     => $this->filterDbString($row['Genre']),
                'keywords'                  => $this->filterDbArray($row['PublicTags']),
                'adminTags'                 => $this->filterDbArray($row['AdminTags']),
                'isAccessibleForFree'       => $this->filterDbBool($row['IsAccessableForFree']),
                'isScientificWork'          => $this->filterDbBool($row['IsScientificWork']),
                'isAwaitingMerge'           => $this->filterDbBool($row['IsAwaitingMerge']),

                'hasNoExplictEditionNumber' => $this->filterDbBool($row['HasNoExplictEditionNumber']),
                'hasNoISBN'                 => $this->filterDbBool($row['HasNoISBN']),
                'isRevisedWithBookInHand'   => $this->filterDbBool($row['IsRevisedWithBookInHand']),
                'isFormallyPublished'       => $this->filterDbBool($row['IsFormallyPublished']),

                'hasBeenMerged'             => $this->filterDbBool($row['HasBeenMerged']),
                'jkQuality'                 => $this->filterDbString($row['JkQuality']),
                'jkQualityNotes'            => $this->filterDbString($row['JkQualityNotes']),
                'jkPeriodId'                => $this->filterDbId($row['JkPeriod']),
                'jkEventId'                 => $this->filterDbId($row['JkEventId']),
                'urls'                      => $urls,
                'url1'                      => $this->filterDbString($row['Url1']),
                'url1Label'                 => $this->filterDbString($row['Url1Label']),
                'url2'                      => $this->filterDbString($row['Url2']),
                'url2Label'                 => $this->filterDbString($row['Url2Label']),
                'url3'                      => $this->filterDbString($row['Url3']),
                'url3Label'                 => $this->filterDbString($row['Url3Label']),
                'dataSource'                => $this->filterDbString($row['DataSource']),
                'dataSourceId'              => $this->filterDbId($row['DataSourceId']),
                'dataSourceUpdatedOn'       => $this->filterDbDate($row['DataSourceUpdatedOn']),

                'editionNotes'              => $this->filterDbString($row['EditionNotes']),
                'publicNotes'               => $this->filterDbString($row['PublicNotes']),
                'publicNotesUpdatedOn'      => $this->filterDbDate($row['PublicNotesUpdatedOn']),
                'publicNotesUpdatedBy'      => $this->filterDbId($row['PublicNotesUpdatedBy']),
                'adminNotes'                => $this->filterDbString($row['AdminNotes']),
                'adminNotesUpdatedOn'       => $this->filterDbDate($row['AdminNotesUpdatedOn']),
                'adminNotesUpdatedBy'       => $this->filterDbId($row['AdminNotesUpdatedBy']),
                'createdOn'                 => $this->filterDbDate($row['CreatedOn']),
                'createdBy'                 => $this->filterDbId($row['CreatedBy']),
                'updatedOn'                 => $this->filterDbDate($row['UpdatedOn']),
                'updatedBy'                 => $this->filterDbId($row['UpdatedBy']),

                'category'                  => (isset($categoryId) && isset($categories[$categoryId])) ? $categories[$categoryId] : null,

                'coverImageUri'             => $coverImage,
                'coverThumbnail80pxUri'     => $coverThumbnail80,
                'coverThumbnail200pxUri'    => $coverThumbnail200,
                'bookFormatTypeUrl'         => $bookFormatTypeUrl,

                'authorsAll'                => $authorsAll,

                'editorsAll'                => $editorsAll,

                'translatorsAll'            => $translatorsAll,

                'illustratorsAll'           => $illustratorsAll,

                'categoryName'              => $row['CategoryName'],
                'categorySort'              => $this->filterDbInt($row['CategorySortOrder']),
                'categoryParentId'          => $this->filterDbId($row['CategoryParentId']),

                'isSubEdition'              => isset($mainPublicationId),
                'mainPublication'           => null,
                'subEditions'               => [], //list of publications
                'translations'              => [], //list of publications
                'translatedFromPublication' => null,
                'bookCoverFileId'           => null,
                'bookCoverFile'             => null,

                'files'                     => [],
            ];
            $this->unlinkedPublicationsMemoryCache[$id] = &$processedRow;
            return $processedRow;
    }

    /**
     * Link up the mainPublication and the translatedFromPublication to a publication object
     * @param array $object
     * @param bool $noLookup don't do any searching, just use what's in the memoryCache
     */
    protected function linkPublication(?array &$object, $noLookup = false)
    {
        $objectId = $object['publicationId'];
        $translatedFromPublicationId = $object['translatedFromPublicationId'];
        $interestingIds = [$object['publicationId']]; //this allows us get the sub editions of the object

        //@todo work with the memcache

        if (isset($object['mainPublicationId']) &&
            $object['mainPublicationId'] != $objectId
        ) {
            $interestingIds[] = $object['mainPublicationId'];
        }
        if (isset($object['translatedFromPublicationId']) &&
            $object['translatedFromPublicationId'] != $objectId
        ) {
            $interestingIds[] = $object['translatedFromPublicationId'];
        }

        //see if we can get the publications we're looking for
        $results = $this->searchPublications([
            'mainPublicationId' => $objectId,
            'translatedFromPublicationId' => isset($translatedFromPublicationId) ? [$objectId, $translatedFromPublicationId] : $objectId, //this fetches books that were also translated from the same original
            'publicationId' => $interestingIds,
        ], ['orCombination' => true]);


        if (isset($object['mainPublicationId']) &&
            $object['mainPublicationId'] != $object['publicationId'] &&
            isset($results[$object['mainPublicationId']])
        ) {
            $object['mainPublication'] = $results[$object['mainPublicationId']];
        }
        if (isset($object['translatedFromPublicationId']) &&
            $object['translatedFromPublicationId'] != $object['publicationId'] &&
            isset($results[$object['translatedFromPublicationId']])
        ) {
            $object['translatedFromPublication'] = $results[$object['translatedFromPublicationId']];
        }

        //check for subEditions and translations
        foreach ($results as $resultId => $result) {
            if ($resultId == $objectId) { //never add itself to this list
                continue;
            }
            if ($result['mainPublicationId'] == $objectId) {
                $object['subEditions'][$resultId] = &$results[$resultId];
            } elseif ($result['translatedFromPublicationId'] == $objectId) {
                //if this is a translation of the book in question
                $object['translations'][$resultId] = &$results[$resultId];
            } elseif (isset($translatedFromPublicationId)
                //a translation of this book:
                && ($result['translatedFromPublicationId'] == $objectId
                    //or another book that translates the same as this one
                    || $result['translatedFromPublicationId'] == $translatedFromPublicationId
                    )
            ) {
                //@todo add the following logic also to the linkPublications function?
                //if this is a book translated from the same original as the book in question
                $object['translations'][$resultId] = &$results[$resultId];
            } elseif (isset($translatedFromPublicationId)
                && $result['mainPublicationId'] == $translatedFromPublicationId
            ) {
                //if this is a subEdition of a book also translated from the same original
                $object['translations'][$resultId] = &$results[$resultId];
            }
        }
    }

    protected function linkPublications(array &$objects)
    {
        $objectIds = array_keys($objects);

        //collect list of "interesting" publicationIds
        $interestingIds = []; //starting point
        foreach ($objects as $entityId => $object) {
            if (isset($object['mainPublicationId']) &&
                $object['mainPublicationId'] != $entityId
            ) {
                $interestingIds[] = $object['mainPublicationId'];
            }
            if (isset($object['translatedFromPublicationId']) &&
                $object['translatedFromPublicationId'] != $entityId
            ) {
                $interestingIds[] = $object['translatedFromPublicationId'];
            }
        }

        //search for all these publicationIds
        $results = $this->searchPublications([
            'mainPublicationId' => $objectIds,
            'translatedFromPublicationId' => $objectIds,
            'publicationId' => $interestingIds,
        ], ['orCombination' => true, 'noLink' => true]);

        //link 'em up
        foreach ($objects as $entityId => $object) {
            if (isset($object['mainPublicationId']) &&
                $object['mainPublicationId'] != $entityId &&
                isset($results[$object['mainPublicationId']])
            ) {
                $objects[$entityId]['mainPublication'] = &$results[$object['mainPublicationId']];
//                 $objects[$object['mainPublicationId']]['subEditions'][$entityId] = &$entities[$entityId];
            }
            if (isset($object['translatedFromPublicationId']) &&
                $object['translatedFromPublicationId'] != $entityId &&
                isset($results[$object['translatedFromPublicationId']])
            ) {
                $objects[$entityId]['translatedFromPublication'] = &$results[$object['translatedFromPublicationId']];
            }
        }

        //check for subEditions and translations
        foreach ($results as $resultId => $result) {
            if (in_array($result['mainPublicationId'], $objectIds)) {
                $objects[$result['mainPublicationId']]['subEditions'][$resultId] = &$results[$resultId];
            }
            if (in_array($result['translatedFromPublicationId'], $objectIds)) {
                $objects[$result['translatedFromPublicationId']]['translations'][$resultId] = &$results[$resultId];
            }
        }

        //no return, by ref
    }

    /**
     *
     * @param int $id
     * @return mixed[]
     */
    public function getPublication($id)
    {
        static $gateway;
        if (!isset($gateway)) {
            $gateway = $this->getTableGateway('sch_publications');
        }
        $select = $this->getPublicationsSelectPrototype();
        $select->where(['PublicationId' => $id]);
        /** @var ResultSet $result */
        $result = $gateway->selectWith($select);
        $results = $result->toArray();

        if (!isset($results[0])) {
            return null;
        }
        $object = $this->processPublicationRow($results[0]);
        $this->linkPublication($object);

        return $object;
    }

    /**
     * Preprocess data bound for the database on the publication entities or its sub-entities
     * @param array $data
     * @return array
     */
    protected function preprocessPublication($data, $entityData, $action)
    {
        //don't allow the mainPublicationId to be set to itself, also, correct it
        if (isset($data['mainPublicationId']) && isset($entityData['publicationId']) &&
            $data['mainPublicationId'] == $entityData['publicationId']
        ) {
            $data['mainPublicationId'] = null;
        }

//         static $entityDetector;
//         static $personDetector;
        /*
         * Break out the authorsAll field from the form
         *
         * technically there's a potential security bug because the user could pass in any
         * association or person and be able to expose the whole list of persons/associations.
         * The fix is to create a custom Validator. This validator should also check how many
         * associations and persons are allowed.
         */
        //use key_exists here because nulls or empty arrays should be included
        if (!key_exists('authorsText', $data) && key_exists('authorsAll', $data)) {
            $text = [];
//             $persons = [];
//             $associations = [];
//             if (!isset($entityDetector)) {
//                 $entityDetector = new Regex('/^(p|a)\d{1,5}$/');
//             }
            if (null !== $data['authorsAll']) {
                foreach ($data['authorsAll'] as $value) {
//                     if ($entityDetector->isValid($value)) { //we've got a person or association
//                         if ($value[0] == 'p') {
//                             $persons[] = substr($value, 1);
//                         } elseif ($value[0] == 'a') {
//                             $associations[] = substr($value, 1);
//                         }
//                     } else { //we've just a regular text author
                        $text[] = $value;
//                     }
                }
            }
            $data['authorsText'] = $text;
//             $data['authorAssociation1Id'] = isset($associations[0]) ? $associations[0] : null;
//             $data['authorAssociation2Id'] = isset($associations[1]) ? $associations[1] : null;
//             $data['authorAssociation3Id'] = isset($associations[2]) ? $associations[2] : null;
//             if (isset($associations[3])) {
//                 throw new \Exception('Only 3 author associations are allowed');
//             }

//             $data['authorPerson1Id'] = isset($persons[0]) ? $persons[0] : null;
//             $data['authorPerson2Id'] = isset($persons[1]) ? $persons[1] : null;
//             $data['authorPerson3Id'] = isset($persons[2]) ? $persons[2] : null;
//             $data['authorPerson4Id'] = isset($persons[3]) ? $persons[3] : null;
//             $data['authorPerson5Id'] = isset($persons[4]) ? $persons[4] : null;
//             if (isset($persons[5])) {
//                 throw new \Exception('Only 5 author persons are allowed');
//             }
        }
        if (!key_exists('editorsText', $data) && !key_exists('editorPerson1Id', $data) &&
            !key_exists('editorPerson2Id', $data) &&  !key_exists('editorPerson3Id', $data) &&
            !key_exists('editorAssociationId', $data) &&
            key_exists('editorsAll', $data)
        ) {
            $text = [];
//             $persons = [];
//             $associations = [];
            if (null !== $data['editorsAll']) {
//                 if (!isset($entityDetector)) {
//                     $entityDetector = new Regex('/^(p|a)\d{1,5}$/');
//                 }
                foreach ($data['editorsAll'] as $value) {
//                     if ($entityDetector->isValid($value)) { //we've got a person or association
//                         if ($value[0] == 'p') {
//                             $persons[] = substr($value, 1);
//                         } elseif ($value[0] == 'a') {
//                             $associations[] = substr($value, 1);
//                         }
//                     } else { //we've just a regular text author
                        $text[] = $value;
//                     }
                }
            }
            $data['editorsText'] = $text;
//             $data['editorAssociationId'] = isset($associations[0]) ? $associations[0] : null;
//             if (isset($associations[1])) {
//                 throw new \Exception('Only 1 editor association is allowed');
//             }

//             $data['editorPerson1Id'] = isset($persons[0]) ? $persons[0] : null;
//             $data['editorPerson2Id'] = isset($persons[1]) ? $persons[1] : null;
//             $data['editorPerson3Id'] = isset($persons[2]) ? $persons[2] : null;
//             if (isset($persons[3])) {
//                 throw new \Exception('Only 3 editor persons are allowed');
//             }
        }
        if (!key_exists('translatorsText', $data) && !key_exists('translatorPerson1Id', $data) &&
            !key_exists('translatorPerson2Id', $data) &&  !key_exists('translatorPerson3Id', $data) &&
            key_exists('translatorsAll', $data)
        ) {
            $text = [];
//             $persons = [];
            if (null !== $data['translatorsAll']) {
//                 if (!isset($personDetector)) {
//                     $personDetector= new Regex('/^p\d{1,5}$/');
//                 }
                foreach ($data['translatorsAll'] as $value) {
//                     if ($personDetector->isValid($value)) { //we've got a person or association
//                         $persons[] = substr($value, 1);
//                     } else { //we've just a regular text author
                        $text[] = $value;
//                     }
                }
            }
            $data['translatorsText'] = $text;

//             $data['translatorPerson1Id'] = isset($persons[0]) ? $persons[0] : null;
//             $data['translatorPerson2Id'] = isset($persons[1]) ? $persons[1] : null;
//             $data['translatorPerson3Id'] = isset($persons[2]) ? $persons[2] : null;
//             if (isset($persons[3])) {
//                 throw new \Exception('Only 3 translator persons are allowed');
//             }
        }
        if (!key_exists('illustratorsText', $data) && !key_exists('illustratorPersonId', $data) &&
            key_exists('illustratorsAll', $data)
        ) {
            $text = [];
//             $persons = [];
            if (null !== $data['illustratorsAll']) {
//                 if (!isset($personDetector)) {
//                     $personDetector= new Regex('/^p\d{1,5}$/');
//                 }
                foreach ($data['illustratorsAll'] as $value) {
//                     if ($personDetector->isValid($value)) { //we've got a person or association
//                         $persons[] = substr($value, 1);
//                     } else { //we've just a regular text author
                        $text[] = $value;
//                     }
                }
            }
            $data['illustratorsText'] = $text;

//             $data['illustratorPersonId'] = isset($persons[0]) ? $persons[0] : null;
//             if (isset($persons[1])) {
//                 throw new \Exception('Only one illustrator person is allowed');
//             }
        }

//         if (isset($data['automaticTitle']) && $data['automaticTitle'] === true) {
//             $data['title'] = null;
//         }
        return $data;
    }

    /**
     * Update the categories of all related books at the same time
     * @param array $data
     * @param array $newEntityData
     * @param string $action
     */
    protected function postprocessPublication($data, $newEntityData, $action)
    {
        if ($action === self::ENTITY_ACTION_SUGGEST || !isset($newEntityData['categoryId'])
            || ($action === self::ENTITY_ACTION_UPDATE && isset($newEntityData['categoryId'])
            && isset($data['categoryId'])
            && $newEntityData['categoryId'] == $data['categoryId'])
        ) {
            return;
        }

        $categoryId = $newEntityData['categoryId'];
        $publicationId = $newEntityData['publicationId'];
        $mainPublicationId = $newEntityData['mainPublicationId'];
        $translatedFromPublicationId = $newEntityData['translatedFromPublicationId'];

        //put all the connected books in the same category
        $gateway = $this->getTableGateway('sch_publications');
        $where = new Where();
        if (isset($mainPublicationId)) {
            $clause = new Operator('PublicationId', Operator::OPERATOR_EQUAL_TO, $mainPublicationId);
            $where->addPredicate($clause, PredicateSet::OP_OR);
        }
        if (isset($translatedFromPublicationId)) {
            $clause= new Operator('PublicationId', Operator::OPERATOR_EQUAL_TO, $translatedFromPublicationId);
            $where->addPredicate($clause, PredicateSet::OP_OR);
        }

        $clause= new Operator('MainPublicationId', Operator::OPERATOR_EQUAL_TO, $publicationId);
        $where->addPredicate($clause, PredicateSet::OP_OR);
        $clause= new Operator('TranslatedFromPublicationId', Operator::OPERATOR_EQUAL_TO, $publicationId);
        $where->addPredicate($clause, PredicateSet::OP_OR);
        $gateway->update(['CategoryId' => $categoryId], $where);
    }

    public function getCategories()
    {
        if (null !== ($cache = $this->fetchCachedEntityObjects('publication-categories'))) {
            return $cache;
        }
        static $gateway;
        if (!isset($gateway)) {
            $gateway = $this->getTableGateway('sch_pub_categories');
        }
        $select = new Select('sch_pub_categories');
        $select->columns(['PublicationCategoryId', 'ParentId', 'CategoryName', 'SortOrder', 'IsPlaceholder']);
        $select->order(['SortOrder']);
        /** @var ResultSet $result */
        $result = $gateway->selectWith($select);

        $categories = [];
        foreach ($result as $row) {
            $id = $this->filterDbId($row['PublicationCategoryId']);
            $name = $this->filterDbString($row['CategoryName']);
            $categories[$id] = [
                'categoryId'    => $id,
                'parentId'      => $this->filterDbString($row['ParentId']),
                'name'          => $name,
                'sort'          => $this->filterDbInt($row['SortOrder']),
                'isPlaceholder' => $this->filterDbBool($row['IsPlaceholder']),

                'fullName'      => null,
                'fullNameStack' => [],
            ];
        }

        foreach ($categories as $categoryId => $object) {
            if (empty($categories[$categoryId]['fullNameStack'])) {
                $stack = $this->getCategoriesNameStack($categoryId, $categories);
                $categories[$categoryId]['fullNameStack'] = $stack;
                $categories[$categoryId]['fullName'] = implode(' - ', $stack);
            }
        }

        $this->cacheEntityObjects('publication-categories', $categories, []);
        return $categories;
    }

    private function getCategoriesNameStack($categoryId, &$categories)
    {
        if (null === $categories[$categoryId]['parentId']) {
            return [$categories[$categoryId]['name']];
        } else {
            $stack = $this->getCategoriesNameStack($categories[$categoryId]['parentId'], $categories);
            $stack[] = $categories[$categoryId]['name'];
        }
        return $stack;
    }

    /**
     * Import records from old sion publication tables to the new one.
     * If simulate is true(default), the records will be returned without modification to the db.
     * Records will only be imported if the UpdatedOn of the current record is after the last import.
     *
     * @todo For this we should also make an import entity. We also accept user comments regarding the entity.
     *
     * @param string $simulate
     * @param mixed[] $importData user information regarding this import
     * @return mixed[][]
     */
    public function importPublications($simulate = true, $importData = null)
    {
        //don't use auflage, Reihe, umfang (it has a little data, but not worth it)
        $sql = "SELECT `id`, `name`, `vorname`, `mitauthoren`, `titel`, `ort`,
`verlag`, `auflage`, `jahr`, `umfang`, `NameZeitschrift`, `ISBN_ZeitschriftNr`,
`Reihe`, `BandNr`, `sprache`, `art`, `herausgabe`, `kategorie`, `Stichworte`, `Inhalt`,
`download`, `quelle` FROM `b_bibsek` WHERE 1";

        $results = $this->fetchSome(null, $sql, null);

        $now = new \DateTime(null, new \DateTimeZone('UTC'));
        $entities = [];
        foreach ($results as $row) {
//             $id = $this->filterDbId($row['PublicationId']);
            $yearPublished = $this->filterDbString($row['jahr']);
            $dateFromYear = isset($yearPublished) ? date_create_from_format('Y', $yearPublished) : null;

            $keywords = $this->filterDbString($row['Stichworte']);
            if (issett($keywords)) {
                $keywords = explode(',', $keywords);
                $keywords = array_map('trim', $keywords);
                $keywords = implode('|', $keywords);
            }
            $category = $this->filterDbString($row['kategorie']);
            if (isset($category) && !isset($keywords)) {
                $keywords = $category;
            } elseif (isset($category)) {
                $keywords .= '|'.$category;
            }

            $authorFirst = $this->filterDbString($row['vorname']);
            $authorLast = $this->filterDbString($row['name']);
            $author = null;
            if ($authorLast && $authorFirst) {
                $author = $authorLast .', '.$authorFirst;
            } elseif ($authorFirst || $authorLast) {
                $author = $authorLast .$authorFirst;
            }
            $otherAuthors = $this->filterDbString($row['mitauthoren']);

            if ($author && $otherAuthors) {
                $author .= '; '. $otherAuthors;
            } elseif (!$author && $otherAuthors) {
                $author = $otherAuthors;
            }

            $isScientific = $this->filterDbString($row['art']) == 'scientific';

            $originalSource = $this->filterDbString($row['quelle']);
            if (isset($originalSource)) {
                $originalSource = 'Original source: '. $originalSource;
            }
            $originalLanguage = $this->filterDbString($row['sprache']);
            $language = null;
            switch ($originalLanguage) {
                case 'esp':
                    $language = 'es';
                    break;
                case 'deu':
                    $language = 'de';
                    break;
                case 'port':
                    $language = 'pt';
                    break;
                case 'engl':
                    $language = 'en';
                    break;
            }
            $download = $this->filterDbString($row['download']);

            $entities[] = [
//                 'publicationId'             => null,

                'title'                     => $this->filterDbString($row['titel']),
                'authorPerson1Id'           => null,
                'authorPerson2Id'           => null,
                'authorPerson3Id'           => null,
                'resourceId'                => 'pub',
                'authorsText'               => $author,
                'bookEdition'               => null, //'BookEdition',
                'inLanguage'                => $language,
                'description'               => $this->filterDbString($row['Inhalt']),
                'isbn'                      => null,
                'illustrator'               => null,
                'translator'                => null,
                'numberOfPages'             => null,
                'copyrightYear'             => $yearPublished,
                'publisher'                 => $this->filterDbString($row['verlag']),
                'publishingPlace'           => $this->filterDbString($row['ort']),
                'datePublished'             => $dateFromYear,
                'publishingStatus'          => $this->filterDbString($row['herausgabe']),
                'bookFormatType'            => $row['herausgabe'] == 'digital'
                    ? self::BOOK_FORMAT_TYPE_EBOOK : self::BOOK_FORMAT_TYPE_PAPERBACK,
                'genre'                     => null,
                'url1'                      => $download,
                'url1Label'                 => isset($download) ? 'Download' : null,
                'textId'                    => null,
                'mainPublication'           => null,
//                 'volumeNumber'              => $this->filterDbString($row['BandNr']),
//                 'containedIn'               => $this->filterDbString($row['NameZeitschrift']),
//                 'containedInIsbn'           => $this->filterDbString($row['ISBN_ZeitschriftNr']),
                'keywords'                  => $keywords,

                'isAccessibleForFree'       => false,
                'isScientificWork'          => $isScientific,
                'isAwaitingMerge'           => true, //these should depend on the imported records already in db
                'hasBeenMerged'             => false, //these should depend on the imported records already in db
                'adminTags'                 => null,

                'jkPeriod'                  => null,
                'jkEventId'                 => null,
                'location'                  => null,
//                 'jkCategory'                => null, //DEPRECATED

                'dataSource'                => 'b_bibsek',
                'dataSourceId'              => $this->filterDbId($row['id']),
                'dataSourceUpdatedOn'       => $now,
                'publicNotes'               => null,
                'publicNotesUpdatedOn'      => null,
                'publicNotesUpdatedBy'      => null,
                'adminNotes'                => $originalSource,
                'adminNotesUpdatedOn'       => $originalSource ? $now : null,
                'adminNotesUpdatedBy'       => $originalSource ? $this->getActingUserId(): null,
                'createdOn'                 => $now,
                'createdBy'                 => $this->getActingUserId(),
                'updatedOn'                 => $now,
                'updatedBy'                 => $this->getActingUserId(),
            ];
        }

        $sql = "SELECT `ed_id`, `ed_evid`, `ed_title`, `ed_remark`, `ed_location`,
`ed_lang`, `ed_internpatres`, `ed_quality`, `ed_qualityremark`,
`ev_id`, `ev_kuerzelvaut`, `ev_shortname`, `ev_name`, `ev_datestart`, `ev_dateend`,
`ev_dateremark`, `ev_intern`, `ev_keywords`
FROM `b_bibprim_edition` ed LEFT JOIN `b_bibprim_event` ev ON ed.`ed_evid` = ev.`ev_id`
WHERE 1";

        $results = $this->fetchSome(null, $sql, null);

        foreach ($results as $row) {
            $publicNotes = '';
//             $yearPublished = $this->filterDbString($row['jahr']);
//             $dateFromYear = isset($yearPublished) ? date_create_from_format('Y', $yearPublished) : null;

            $isInternal = $this->filterDbString($row['ed_internpatres']) == 'yes' ||
                $this->filterDbString($row['ev_intern']) == 'yes';

            if (null !== ($remarks = $this->filterDbString($row['ed_remark']))) {
                $publicNotes .= sprintf("Edition remarks: %s\r\n", $remarks);
            }

            if (null !== ($evName = $this->filterDbString($row['ev_name']))) {
                $publicNotes .= sprintf("Event name: %s\r\n", $evName);
            }

            $startDate = $this->filterDbDateInt($row['ev_datestart']);
            $endDate = $this->filterDbDateInt($row['ev_dateend']);
            if (isset($startDate)) {
                $dateString = date_format($startDate, 'Y-m-d');
                if (isset($endDate)) {
                    $dateString .= ' - '.date_format($endDate, 'Y-m-d');
                }
                if (null !== ($dateRemarks = $this->filterDbString($row['ev_dateremark']))) {
                    $dateString .= sprintf(" (%s)", $dateRemarks);
                }
                $publicNotes .= sprintf("Text from %s\r\n", $dateString);
            }

            if (null !== ($shortName = $this->filterDbString($row['ev_shortname']))) {
                $publicNotes .= sprintf("Short name: %s\r\n", $shortName);
            }

            if (null !== ($abbreviation = $this->filterDbString($row['ev_kuerzelvaut']))) {
                $publicNotes .= sprintf("Abbreviation: %s\r\n", $abbreviation);
            }

            $originalSource = $this->filterDbString($row['ed_location']);
            if (isset($originalSource)) {
                $originalSource = 'Original source: '. $originalSource;
            }
            $originalLanguage = $this->filterDbString($row['ed_lang']);
            $language = null;
            switch ($originalLanguage) {
                case 'esp':
                    $language = 'es';
                    break;
                case 'deu':
                    $language = 'de';
                    break;
                case 'port':
                    $language = 'pt';
                    break;
                case 'engl':
                    $language = 'en';
                    break;
            }

            if (null !== ($quality = $this->filterDbString(ltrim($row['ed_quality'])))) {
                $quality = ucfirst(mb_substr($quality, 0, 1));
                $qualityChr = ord($quality);
                if ($qualityChr < 65 || $quality > 72) {
                    $quality = null;
                }
            }
            $entities[] = [
//                 'publicationId'          => null,

                'title'                     => $this->filterDbString($row['ed_title']),
                'authorPerson1Id'           => null,
                'authorPerson2Id'           => null,
                'authorPerson3Id'           => null,
                'authorsText'               => 'Kentenich, Josef',
                'resourceId'                => $isInternal ? 'publication_patres' : 'publication_public',
                'bookEdition'               => null,
                'inLanguage'                => $language,
                'description'               => null,
                'isbn'                      => null,
                'illustrator'               => null,
                'translator'                => null,
                'numberOfPages'             => null,
                'copyrightYear'             => null,
                'publisher'                 => null,
                'publishingPlace'           => null,
                'datePublished'             => null,
                'publishingStatus'          => null,
                'genre'                     => null,
                'url1'                      => null,
                'textId'                    => null, //'TextId',
                'mainPublication'           => null, //'MainPublication', //mainEntity
                'volumeNumber'              => null,
                'containedIn'               => null,
                'containedInIsbn'           => null,
                'keywords'                  => null,// $keywords,

                'isAccessibleForFree'       => false,
                'isScientificWork'          => $isScientific,
                'isAwaitingMerge'           => true, //these should depend on the imported records already in db
                'hasBeenMerged'             => false, //these should depend on the imported records already in db
                'adminTags'                 => null,

                'jkQuality'                 => $quality,
                'jkQualityNotes'            => $this->filterDbString($row['ed_qualityremark']),
                'jkPeriod'                  => $this->whichKentenichPeriod($startDate),
                'jkEventId'                 => $this->filterDbId($row['ev_id']),
                'dataSource'                => 'b_bibprim_edition',
                'dataSourceId'              => $this->filterDbId($row['ed_id']),
                'dataSourceUpdatedOn'       => $now,
                'publicNotes'               => $publicNotes,
                'publicNotesUpdatedOn'      => $publicNotes ? $now : null,
                'publicNotesUpdatedBy'      => $publicNotes ? $this->actingUserId : null,
                'adminNotes'                => $originalSource,
                'adminNotesUpdatedOn'       => $originalSource ? $now : null,
                'adminNotesUpdatedBy'       => $originalSource ? $this->actingUserId : null,
                'createdOn'                 => $now,
                'createdBy'                 => $this->actingUserId,
                'updatedOn'                 => $now,
                'updatedBy'                 => $this->actingUserId,
            ];
        }

        if (!$simulate) {
            foreach ($entities as $key => $data) {
                if (isset($data['title'])) {
                    $this->createEntity('publication', $data);
                } else {
                    unset($entities[$key]);
                }
            }
        }
        return $entities;
    }

    /**
     * Performs some data changes on the publications table:
     * 1. Fill the 4 `NoAccents` columns
     */
    public function fillNoAccentsColumns()
    {
        $gateway = $this->getTableGateway('sch_publications');
        $select = $this->getPublicationsSelectPrototype();
        $select->where(['InLanguage' => 'es']);
        $results = $gateway->selectWith($select);
        $asciiFilter = new ToAscii();
        $updates = [];
        foreach ($results as $row) {
            $id = $row['PublicationId'];
            $updates[$id] = [
                'TitleNoAccents' => $asciiFilter->filter($row['Title']),
                'SubtitleNoAccents' => $asciiFilter->filter($row['Subtitle']),
                'AuthorsNoAccents' => $asciiFilter->filter($row['Authors']),
                'EditorNoAccents' => $asciiFilter->filter($row['Editor']),
            ];
        }
        return $updates;
    }
    /**
     * Fill the DatePublishedText column
     *      Use the DatePublished column if we have data
     *      If not, use the copyright year
     */
    public function fillDatePublished()
    {
    }

    /**
     * Remove most CopyrightYear data. Leave the CopyrightYear data in the following two cases:
     *      a. There's both a DatePublished and CopyrightYear in the database
     *      b. Data comes from Deutsche Bibliothek
     */
    public function clearCopyrightYear()
    {
    }

    public function getAuthors()
    {
        $publications = $this->getUnlinkedPublications();
        $authors = [];
        foreach ($publications as $publicationId => $publication) {
            if (!isset($publication['authorsText'])) {
                continue;
            }
            $authorTexts = $publication['authorsText'];
            foreach ($authorTexts as $authorText) {
                if (isset($authors[$authorText])) {
                    $authors[$authorText][$publicationId] = $publication;
                } else {
                    $authors[$authorText] = [$publicationId => $publication];
                }
            }
        }
        return $authors;
    }

    public function importAuthors($simulate)
    {
        //1. get author list

        //2. parse author names
        //2a. Find Fr./Sr. prefixes

        //2b. Find last name/first name

//         $publicationUpdates = [];
        //3. look for preexisting authors in person table, add them to the list of author links to insert

        //4. Insert new authors

        //5. Update publication records
    }

    protected function whichKentenichPeriod($date)
    {
        if (null === $date) {
            return null;
        }
        $tz = new \DateTimeZone('UTC');
        static $periodEnd;
        if (!isset($periodEnd)) {
            $periodEnd = [
                1 => date_create_from_format('Y-m-d', '1913-01-01', $tz),
                2 => date_create_from_format('Y-m-d', '1920-01-01', $tz),
                3 => date_create_from_format('Y-m-d', '1925-01-01', $tz),
                4 => date_create_from_format('Y-m-d', '1942-01-01', $tz),
                5 => date_create_from_format('Y-m-d', '1946-01-01', $tz),
                6 => date_create_from_format('Y-m-d', '1952-01-01', $tz),
                7 => date_create_from_format('Y-m-d', '1966-01-01', $tz),
                8 => date_create_from_format('Y-m-d', '1969-01-01', $tz),
            ];
        }
        foreach ($periodEnd as $period => $endDate) {
            if ($date < $endDate) {
                return $period;
            }
        }
        return null;
    }

    protected function filterDbDateInt($dateIntStr)
    {
        $dateIntStr= $this->filterDbString($dateIntStr);
        if ($dateIntStr == '0') {
            $dateIntStr = null;
        }
        if (isset($dateIntStr)) {
            if (mb_substr($dateIntStr, 4) == '0000') {
                $dateIntStr = mb_substr($dateIntStr, 0, 4).'0101';
            } elseif (mb_substr($dateIntStr, 6) == '00') {
                $dateIntStr = mb_substr($dateIntStr, 0, 6).'01';
            }
            $dateIntStr = date_create_from_format('Ymd', $dateIntStr);
        }
        return $dateIntStr;
    }

    /**
     * Get the schoenstattTable value
     * @return SchoenstattTable
     */
    public function getSchoenstattTable()
    {
        return $this->schoenstattTable;
    }

    /**
     *
     * @param SchoenstattTable $schoenstattTable
     * @return self
     */
    public function setSchoenstattTable($schoenstattTable)
    {
        $this->schoenstattTable = $schoenstattTable;
        return $this;
    }

    /**
     * Get the predicatesTable value
     * @return PredicatesTable
     */
    public function getPredicatesTable()
    {
        if (!isset($this->predicatesTable)) {
            throw new \Exception('Something went wrong, no predicatesTable available');
        }
        return $this->predicatesTable;
    }

    /**
     * Set the predicatesTable value
     * @param PredicatesTable $predicatesTable
     * @return self
     */
    public function setPredicatesTable(PredicatesTable $predicatesTable)
    {
        $this->predicatesTable = $predicatesTable;
        return $this;
    }
}
