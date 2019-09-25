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
use Schoenstatt\Filter\ToSchoenstattLinkIdentifier;

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
        $publications = $this->getObjects('publication');
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
        $cacheKey = 'publication-edition-value-options';
        if (null !== ($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }
        $entities = $this->getObjects('publication');
        $return = [];
        foreach ($entities as $entityId => $entityObject) {
            $return[$entityId] = $entityObject['disambiguatingTitle'];
        }
        $this->cacheEntityObjects($cacheKey, $return, ['publication']);
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
        $entities = $this->getObjects('publication');

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
                } elseif (is_array($object['inLanguage'])) {
                    foreach ($object['inLanguage'] as $lang) {
                        if (!isset($languages[$lang])) {
                            $languages[$lang] = 1;
                        } else {
                            $languages[$lang]++;
                        }
                    }
                } else {
                    throw new \Exception('Publication `inLanguage` should be either an array or null');
                }
            }
        }
        $this->cacheEntityObjects($cacheKey, $languages, ['publication']);
        return $languages;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \SionModel\Db\Model\SionTable::getSelectPrototype()
     */
    protected function getSelectPrototype($entity)
    {
        $select = parent::getSelectPrototype($entity);
        if ('publication' === $entity) {
            $entitySpec = $this->getEntitySpecification($entity);
            $columns = array_values($entitySpec->updateColumns);
            $columns['CategorySortOrder'] = new Expression('IF(ISNULL(`SortOrder`), 1000, `SortOrder`)');
            //@todo add a boolean expression whether the user likes/watches/has-read each particular book.
            //I think we can do it with a left join; though it will no longer be cachable. Rethink this idea
            $select->columns($columns);
            $select->join(
                'sch_pub_categories',
                'sch_pub_categories.PublicationCategoryId = sch_publications.CategoryId',
                ['SortOrder', 'CategoryName', 'CategoryParentId' => 'ParentId'],
                Select::JOIN_LEFT
                );
            $select->order(['CategorySortOrder', 'Authors', 'InLanguage', 'Title']);
        }
        return $select;
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
        $select = $this->getSelectPrototype('publication');
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
        if (key_exists('inLanguage', $query)) {
            if (null === $query['inLanguage']) {
                $inLanguageClause = new IsNull($fieldMap['inLanguage']);
            } else {
                $inLanuageText = $query['inLanguage'];
                $inLanguageClause = new Like($fieldMap['inLanguage'], "%$inLanuageText%");
            }
            $where->addPredicate($inLanguageClause, PredicateSet::OP_AND); //I don't think it would ever make sense combine with OR here
        }

        //@todo check if this really works
        //Prepare isSubEdition predicate, by default, don't filter
        if (isset($options['noSubEditions']) && $options['noSubEditions']) {
            $noSubEditionClause= new IsNull($fieldMap['mainPublicationId']);
            $where->addPredicate($noSubEditionClause, PredicateSet::OP_AND);
        }
        
        //@todo check if this really works
        //Prepare NOT isAwaitingMerger predicate, by default, don't filter
        if (isset($options['isAwaitingMerger'])) {
            $isAwaitingMergerClause= new Operator(
                $fieldMap['isAwaitingMerge'], 
                Operator::OPERATOR_EQUAL_TO, 
                $options['isAwaitingMerger'] ? '1' : '0'
                );
            $where->addPredicate($isAwaitingMergerClause, PredicateSet::OP_AND);
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

        $entities = $this->getObjects('publication');
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
//             if (isset($associations[$entityObject['publisherAssociationId']])) {
//                 $entities[$entityId]['publisherAssociation'] = $associations[$entityObject['publisherAssociationId']];
//             }
        }

        $this->cacheEntityObjects('publications', $entities, ['publication']);
        return $entities;
    }

    /**
     * @return mixed[]
     */
    protected function &processPublicationRow($row)
    {
        static $categories;
        static $swFilter;
        $id = $this->filterDbId($row['PublicationId']);

        if (isset($this->unlinkedPublicationsMemoryCache[$id])) {
            return $this->unlinkedPublicationsMemoryCache[$id];
        }
        if (!isset($swFilter)) {
            $swFilter = new ToSchoenstattLinkIdentifier('publication');
        }
        $identifier = $swFilter->filter($id);
        
        $title = $row['Title'];
        $slug = $this->filterDbString($row['Slug']);
        if (!isset($slug)) {
            $slug = SchoenstattTable::getSlug($title);
            $this->slylyUpdatePublicationSlug($id, $slug);
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

        //the text/all distinction exists because "all" will also include linked entities from other tables
        $authorsText = $this->filterDbArray($row['Authors']);
        $editorText = $this->filterDbArray($row['Editor']);
        $translatorText = $this->filterDbArray($row['Translator']);

        $bookFormatType = $this->filterDbString($row['BookFormatType']);
        $bookFormatTypeUrl = null;
        if (isset(self::BOOK_FORMAT_TYPE_URLS[$bookFormatType])) {
            $bookFormatTypeUrl = self::BOOK_FORMAT_TYPE_URLS[$bookFormatType];
        }
        $inLanguage = $this->filterDbArray($row['InLanguage']);

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
            if (file_exists(sprintf('public/covers/%s-400px.jpg', $id))) {
                $coverThumbnail400 = sprintf('/covers/%s-400px.jpg', $id);
            } else {
                $coverThumbnail400 = null;
            }
        } else {
            $coverImage = null;
            $coverThumbnail80 = null;
            $coverThumbnail200 = null;
            $coverThumbnail400 = null;
        }

        $categoryId = $this->filterDbId($row['CategoryId']);
        $bookEdition = $row['BookEdition'];
        $copyrightYear = $this->filterDbInt($row['CopyrightYear']);
        $datePublishedText = $row['DatePublishedText'];
        $datePublished = $this->filterDbDate($row['DatePublished']);
        
        $disambiguatingTitle = $title;
        if (isset($bookEdition)
            || isset($copyrightYear)
            || isset($datePublishedText)
        ) {
            $extraInfo = '';
            if (isset($bookEdition)) {
                $extraInfo = $bookEdition;
            }
            if (isset($datePublishedText)) {
                if ('' === $extraInfo) {
                    $extraInfo = $datePublishedText;
                } else {
                    $extraInfo .= (", ".$datePublishedText);
                }
            } elseif (isset($copyrightYear)) {
                if ('' === $extraInfo) {
                    $extraInfo = (string)$copyrightYear;
                } else {
                    $extraInfo .= (", ".(string)$copyrightYear);
                }
            }
            if (strlen($extraInfo) > 0) {
                $disambiguatingTitle = "$disambiguatingTitle [$extraInfo]";
            }
        }

        $processedRow = [
            'publicationId'             => $id,
            'title'                     => $title,
            'titleNoAccents'            => $row['TitleNoAccents'],
            'slug'                      => $slug,
            'subtitle'                  => $row['Subtitle'],
            'subtitleNoAccents'         => $row['SubtitleNoAccents'],
            'resourceId'                => $resourceId,
            'authorsText'               => $authorsText,
            'authorsNoAccents'          => $row['AuthorsNoAccents'],
            'bookEdition'               => $bookEdition,
            'categoryId'                => $categoryId,

            'inLanguage'                => $inLanguage,
            'description'               => $this->filterDbString($row['Description']),
            'isbn'                      => $this->filterDbString($row['Isbn']),
            'editorsText'               => $editorText,
            'editorsNoAccents'          => $row['EditorNoAccents'],
            'translatorsText'           => $translatorText,
            'numberOfPages'             => $this->filterDbInt($row['NumberOfPages']),
            'copyrightYear'             => $copyrightYear,
            'copyrightInfo'             => $row['CopyrightInfo'],
            'datePublishedText'         => $datePublishedText,
            'publisher'                 => $this->filterDbString($row['Publisher']),
            'publishingPlace'           => $this->filterDbString($row['PublishingPlace']),
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
            'coverThumbnail400pxUri'    => $coverThumbnail400,
            'bookFormatTypeUrl'         => $bookFormatTypeUrl,

            'authorsAll'                => $authorsText,
            'editorsAll'                => $editorText,
            'translatorsAll'            => $translatorText,

            'categoryName'              => $row['CategoryName'],
            'categorySort'              => $this->filterDbInt($row['CategorySortOrder']),
            'categoryParentId'          => $this->filterDbId($row['CategoryParentId']),
            'disambiguatingTitle'       => $disambiguatingTitle,
            'datePublished'             => $datePublished,
            'identifier'                => $identifier,
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
    
    protected function slylyUpdatePublicationSlug($publicationId, $slug)
    {
        $gateway = $this->getTableGatewayForEntity('publication');
        $result = $gateway->update(['Slug' => $slug], ['PublicationId' => $publicationId]);
        return $result;
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
        $select = $this->getSelectPrototype('publication');
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
        if (isset($data['title'])) {
            $data['slug'] = SchoenstattTable::getSlug($data['title']);
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

//         if (isset($data['automaticTitle']) && $data['automaticTitle'] === true) {
//             $data['title'] = null;
//         }
        return $data;
    }

    public function importForschungs(array $jsonArray, $idToShow = null)
    {
        $bookList = $jsonArray['bookinfo']['booklist']['book'];
        $publications = [];
//         $libraryBooks = [];
        $languages = [];
        $existingPublications = $this->queryObjects('publication', ['dataSource' => 'forschungsbibliothek']);
        $existingPublications = $this->rekeyPublicationsByDataSourceId(
            $existingPublications
            );
        $count = 0;
        foreach ($bookList as $book) {
            $lang = isset($book['language']) ? $book['language']['displayname'] : null;
            if (isset($lang) && !in_array($lang, $languages)) {
                $languages[] = $lang;
            }
            $pub = $this->processForschungsPublicationRow($book);
            if (!isset($pub['title'])) {
                if (isset($this->logger)) {
                    $this->logger->warn('Forschung importer: Row skipped due to missing title', ['record' => $book]);
                }
                continue;
            }
            if (isset($pub['bookEdition']) && is_string($pub['bookEdition']) && strlen($pub['bookEdition']) > 50) {
                if (isset($this->logger)) {
                    $this->logger->warn('Forschung importer: Book edition truncated', ['record' => $book]);
                }
                $pub['bookEdition'] = substr($pub['bookEdition'], 0, 50);
                continue;
            }
            $dataSourceId = $pub['dataSourceId'];
            if (isset($idToShow) && $dataSourceId == $idToShow) {
                echo '<pre>';
                print_r($book);
                print_r($pub);
                echo '</pre>';
            }
            $publications[$dataSourceId] = $pub;
            if (!isset($existingPublications[$dataSourceId])) {
                $this->createEntity('publication', $pub, false);
            }
            $count++;
        }
        $this->removeDependentCacheItems('publication');
        return $publications;
    }
    
    protected function rekeyPublicationsByDataSourceId($publications)
    {
        $result = [];
        foreach ($publications as $object) {
            if (!isset($object['dataSourceId'])) {
                throw new \Exception('All rows must have a dataSourceId');
            }
            $result[$object['dataSourceId']] = $object;
        }
        return $result;
    }
    
    protected function processForschungsPublicationRow($row)
    {
        static $languageMap;
        static $nonAuthorNames;
        static $editorIndicators;
        static $editorInString;
        static $dateConverters;
        static $now;
        static $debugCount;
        if (!isset($languageMap)) {
            $languageMap = [
                'German' => ['de'],
                'Spanish' => ['es'],
                'English / German' => ['en', 'de'],
                'Czech' => ['cs'],
                'French' => ['fr'],
                'English' => ['en'],
                'Portuguese' => ['pt'],
                'Français' => ['fr'],
                'Latin - German' => ['la', 'de'],
                'Hungarian' => ['hu'],
                'Italian' => ['it'],
                'German/Spanish' => ['de', 'es'],
                'Croatian' => ['hr'],
                'Polish' => ['pl'],
                'Latin - German – English – Italian' => ['la', 'de', 'en', 'it'],
                'Spanish / Portuguese' => ['es', 'pt'],
                'Spanish/English/Italian' => ['es', 'en', 'it'],
                'Spanish / German' => ['es', 'de'],
                'German, English' => ['de', 'en'],
                'German/French' => ['de', 'fr'],
                'Latin' => ['la'],
                'Lateinisch - Deutsch' => ['la', 'de'],
                'German u. a.' => ['de'],
            ];
        }
        if (!isset($editorIndicators)) {
            $editorIndicators = [
                'Hrsg.' => 'displayname',
                'Editor' => 'displayname',
            ];
        }
        if (!isset($editorInString)) {
            $editorInString = [
                '(Hrsg.)',
                '(Hrsg./Ed.)',
                'hrsg. vom ',
                '– Editor',
            ];
        }
        if (!isset($nonAuthorNames)) {
            $nonAuthorNames = [
                'u. a.' => 'displayname',
                'u.a.' => 'displayname',
                'ohne Autor' => 'displayname',
                'Autor: keine Angabe' => 'displayname',
            ];
        }
        if (!isset($dateConverters)) {
            $dateConverters = [
                '18. April 2005' => '2005-04-18',
                '2. Februar 2014' => '2014-02-02',
                'April 1991' => '1991-04',
                'April 2003' => '2003-04',
                'April 2010' => '2010-04',
                'April 2014' => '2014-04',
                'August 2006' => '2006-08',
                'August 2008' => '2008-08',
                'August 2014' => '2014-08',
                'Dezember 1982' => '1982-12',
                'Dezember 2012' => '2012-12',
                'Dezember 2014' => '2014-12',
                'Februar 2012' => '2012-02',
                'Februar 2015' => '2015-02',
                'Januar 1995' => '1995-01',
                'Januar 2009' => '2009-01',
                'Januar 2010' => '2010-01',
                'Januar 2012' => '2012-01',
                'Juli 1995' => '1995-07',
                'Juli 2011' => '2011-06',
                'Juni 2014' => '2014-06',
                'Mai 2005' => '2005-03',
                'März 2014' => '2014-03',
                'März 2015' => '2015-03',
                'November 2014' => '2014-11',
                'Oktober 2009' => '2009-10',
                '18. Juli 1983' => '1983-08-18',
                '13. Dezember 1965' => '1965-',
                '13. Mai 1976' => '1976-',
                '15. August 1985' => '1985-',
                '15. August 1987' => '1987-',
                '18. Oktober 1980' => '1980-',
                '18. Oktober 1981' => '1981-',
                '19. März 1977' => '1977-',
                '21. November 2005' => '2005-',
                '23. Oktober 2004' => '2004-',
                'April 1977' => '1977-04',
                'April 1982' => '1982-04',
                'April 1986' => '1986-04',
                'April 1989' => '1989-04',
                'April 1992' => '1992-04',
                'April 1997' => '1997-04',
                'April 1998' => '1998-04',
                'April 2004' => '2004-04',
                'April 2005' => '2005-04',
                'April 2006' => '2006-04',
                'April 2008' => '2008-04',
                'April 2009' => '2009-04',
                'April 2011' => '2011-04',
                'August 1969' => '1969-08',
                'August 1982' => '1982-08',
                'August 1984' => '1984-08',
                'August 1985' => '1985-08',
                'August 1986' => '1986-08',
                'August 1988' => '1988-08',
                'August 1990' => '1990-08',
                'August 1991' => '1991-08',
                'August 1992' => '1992-08',
                'August 1993' => '1993-08',
                'August 1995' => '1995-08',
                'August 1996' => '1996-08',
                'August 1999' => '1999-08',
                'August 2002' => '2002-08',
                'August 2003' => '2003-08',
                'August 2004' => '2004-08',
                'August 2010' => '2010-08',
                'Dezember 1971' => '1971-12',
                'Dezember 1981' => '1981-12',
                'Dezember 1984' => '1984-12',
                'Dezember 1985' => '1985-12',
                'Dezember 1986' => '1986-12',
                'Dezember 1994' => '1994-12',
                'Dezember 1995' => '1995-12',
                'Dezember 1996' => '1996-12',
                'Dezember 1997' => '1997-12',
                'Dezember 1999' => '1999-12',
                'Dezember 2001' => '2001-12',
                'Dezember 2004' => '2004-12',
                'Dezember 2006' => '2006-12',
                'Dezember 2009' => '2009-12',
                'Dezember 2010' => '2010-12',
                'Dezember 2011' => '2011-12',
                'Februar 1999' => '1999-02',
                'Februar 2001' => '2001-02',
                'Februar 2003' => '2003-02',
                'Februar 2004' => '2004-02',
                'Februar 2005' => '2005-02',
                'Februar 2006' => '2006-02',
                'Januar 1984' => '1984-01',
                'Januar 1985' => '1985-01',
                'Januar 1997' => '1997-01',
                'Januar 2001' => '2001-01',
                'Januar 2006' => '2006-01',
                'Januar 2007' => '2007-01',
                'Januar 2008' => '2008-01',
                'Juli 1978' => '1978-07',
                'Juli 1983' => '1983-07',
                'Juli 1984' => '1984-07',
                'Juli 1988' => '1988-07',
                'Juli 1989' => '1989-07',
                'Juli 1990' => '1990-07',
                'Juli 1997' => '1997-07',
                'Juli 1998' => '1998-07',
                'Juli 1999' => '1999-07',
                'Juli 2000' => '2000-07',
                'Juli 2001' => '2001-07',
                'Juli 2004' => '2004-07',
                'Juli 2006' => '2006-07',
                'Juli 2007' => '2007-07',
                'Juli 2008' => '2008-07',
                'Juli 2009' => '2009-07',
                'Juni 1968' => '1968-06',
                'Juni 1976' => '1976-06',
                'Juni 1977' => '1977-06',
                'Juni 1984' => '1984-06',
                'Juni 1985' => '1985-06',
                'Juni 1986' => '1986-06',
                'Juni 1990' => '1990-06',
                'Juni 1992' => '1992-06',
                'Juni 1994' => '1994-06',
                'Juni 1996' => '1996-06',
                'Juni 1997' => '1997-06',
                'Juni 1998' => '1998-06',
                'Juni 2000' => '2000-06',
                'Juni 2001' => '2001-06',
                'Juni 2002' => '2002-06',
                'Juni 2003' => '2003-06',
                'Juni 2005' => '2005-06',
                'Juni 2006' => '2006-06',
                'Juni 2007' => '2007-06',
                'Juni 2008' => '2008-06',
                'Juni 2009' => '2009-06',
                'Juni 2010' => '2010-06',
                'Mai 1973' => '1973-05',
                'Mai 1974' => '1974-05',
                'Mai 1984' => '1984-05',
                'Mai 1985' => '1985-05',
                'Mai 1986' => '1986-05',
                'Mai 1987' => '1987-05',
                'Mai 1988' => '1988-05',
                'Mai 1993' => '1993-05',
                'Mai 1996' => '1996-05',
                'Mai 1998' => '1998-05',
                'Mai 1999' => '1999-05',
                'Mai 2000' => '2000-05',
                'Mai 2001' => '2001-05',
                'Mai 2002' => '2002-05',
                'Mai 2003' => '2003-05',
                'Mai 2006' => '2006-05',
                'Mai 2007' => '2007-05',
                'Mai 2008' => '2008-05',
                'Mai 2009' => '2009-05',
                'Mai 2011' => '2011-05',
                'März 1970' => '1970-03',
                'März 1979' => '1979-03',
                'März 1981' => '1981-03',
                'März 1985' => '1985-03',
                'März 1987' => '1987-03',
                'März 1988' => '1988-03',
                'März 1991' => '1991-03',
                'März 1994' => '1994-03',
                'März 1995' => '1995-03',
                'März 1996' => '1996-03',
                'März 1997' => '1997-03',
                'März 2000' => '2000-03',
                'März 2001' => '2001-03',
                'März 2002' => '2002-03',
                'März 2003' => '2003-03',
                'März 2007' => '2007-03',
                'März 2008' => '2008-03',
                'März 2009' => '2009-03',
                'März 2010' => '2010-03',
                'November 1971' => '1971-11',
                'November 1978' => '1978-11',
                'November 1979' => '1979-11',
                'November 1980' => '1980-11',
                'November 1985' => '1985-11',
                'November 1987' => '1987-11',
                'November 1990' => '1990-11',
                'November 1991' => '1991-11',
                'November 1993' => '1993-11',
                'November 1995' => '1995-11',
                'November 1997' => '1997-11',
                'November 2001' => '2001-11',
                'November 2003' => '2003-11',
                'November 2004' => '2004-11',
                'November 2007' => '2007-11',
                'November 2009' => '2009-11',
                'Oktober 1969' => '1969-10',
                'Oktober 1973' => '1973-10',
                'Oktober 1981' => '1981-10',
                'Oktober 1983' => '1983-10',
                'Oktober 1991' => '1991-10',
                'Oktober 1995' => '1995-10',
                'Oktober 1997' => '1997-10',
                'Oktober 1998' => '1998-10',
                'Oktober 2002' => '2002-10',
                'Oktober 2003' => '2003-10',
                'Oktober 2004' => '2004-10',
                'Oktober 2005' => '2005-10',
                'Oktober 2006' => '2006-10',
                'Oktober 2007' => '2007-10',
                'Oktober 2008' => '2008-10',
                'Oktober 2012' => '2012-10',
                'September 1971' => '1971-09',
                'September 1974' => '1974-09',
                'September 1985' => '1985-09',
                'September 1989' => '1989-09',
                'September 1990' => '1990-09',
                'September 1993' => '1993-09',
                'September 1995' => '1995-09',
                'September 1997' => '1997-09',
                'September 1998' => '1998-09',
                'September 1999' => '1999-09',
                'September 2000' => '2000-09',
                'September 2004' => '2004-09',
                'September 2005' => '2005-09',
                'September 2006' => '2006-09',
                'September 2007' => '2007-09',
                'September 2008' => '2008-09',
                'September 2009' => '2009-09',
            ];
        }
        if (!isset($now)) {
            $now = new \DateTime(null, new \DateTimeZone('UTC'));
        }
        if (!isset($debugCount)) {
            $debugCount = 0;
        }
        
        if (!isset($row['id'])) {
            var_dump($row);
            throw new \Exception('Every entry should have an id');
        }
        
        /*
         * userdefinedvalues(@todo check), id, index, hash, mainsection, collectionstatus (nothing important), rare,
         * format(@todo), country, language, purchasedate, owner, issuenr(no), publicationdate,
         * location, genres(@todo), tags (nothing important), links, lastmodified, thumbfilepath, clzbookid,
         * bpbooklastreceivedrevision, lccn (@todo), printing, pagecount, edition, firstedition,
         * extras (notes @todo), subjects, units(no), readtimes(no), readit(no), readingdate(no), submissiondate,
         * quantity, abridged, sections (@todo)
         */
        $id = $row['id'];
        $inLanguage = null;
        if (isset($row['language']) && isset($row['language']['displayname'])) {
            $langDisplay = $row['language']['displayname'];
            if (!isset($languageMap[$langDisplay])) {
                throw new \Exception("unknown language `$langDisplay`");
            }
            $inLanguage = $languageMap[$langDisplay];
        }
        $keywords = [];
        if (isset($row['subjects']) && isset($row['subjects']['subject']) && is_array($row['subjects']['subject'])) {
            foreach ($row['subjects']['subject'] as $subject) {
                $keyword = isset($subject['displayname']) ? $subject['displayname'] : $subject;
                if (is_string($keyword)) {
                    $keywords[] = $keyword;
                }
            }
        }
        $edition = null;
        if (isset($row['edition'])) {
            if (!isset($row['edition']['displayname'])) {
                var_dump($row['edition']);
                throw new \Exception('Unexpected edition');
            }
            $edition = $row['edition']['displayname'];
        }
        
        $title = null;
        $subtitle = null;
        $slug = null;
        $containedIn = null;
        $authorsText = [];
        $editorsText = [];
        if (isset($row['mainsection'])) {
            //title
            if (isset($row['mainsection']['title']) && is_string($row['mainsection']['title'])) {
                $title = $row['mainsection']['title'];
                $slug = SchoenstattTable::getSlug($title);
            }
            
            //subtitle
            if (isset($row['mainsection']['subtitle']) && is_string($row['mainsection']['subtitle'])) {
                $subtitle = $row['mainsection']['subtitle'];
            }
            
            //authors
            if (isset($row['mainsection']['authors']) && isset($row['mainsection']['authors']['author'])) {
                $authorEntries = [];
                if (isset($row['mainsection']['authors']['author']['role'])) {
                    $authorEntries[] = $row['mainsection']['authors']['author'];
                } elseif (isset($row['mainsection']['authors']['author'][0])) {
                    $authorEntries = $row['mainsection']['authors']['author'];
                }
                $areEditors = false;
                foreach ($authorEntries as $authorEntry) {
                    if ($authorEntry['role']['$t'] !== 'Author') {
                        throw new \Exception('It looks like we started specifying other author types');
                    }
                    
                    //check if we're really looking at editors
                    $disregardThisOne = false;
                    foreach ($editorIndicators as $editorText => $fieldToCheck) {
                        if ($authorEntry['person'][$fieldToCheck] === $editorText) {
                            $areEditors = true;
                            $disregardThisOne = true;
                            break;
                        }
                    }
                    
                    //check if this is a non-@author user
                    foreach ($nonAuthorNames as $nonAuthorText => $fieldToCheck) {
                        if ($authorEntry['person'][$fieldToCheck] === $nonAuthorText) {
                            $disregardThisOne = true;
                            break;
                        }
                    }
                    
                    if (!$disregardThisOne) {
                        $name = $authorEntry['person']['displayname'];
                        //check if it's actually an editor
                        $editorStringToReplace = false;
                        foreach ($editorInString as $stringToCheck) {
                            if (false !== stripos($name, $stringToCheck)) {
                                $editorStringToReplace = $stringToCheck;
                                break;
                            }
                        }
                        if (false !== $editorStringToReplace) { //this one's an editor
                            $name = trim(str_replace($editorStringToReplace, '', $name));
                            $editorsText[] = $name;
                        } else {
                            $authorsText[] = $name;
                        }
                    }
                }
                if ($areEditors) {
                    $editorsText = array_merge($authorsText, $editorsText);
                    $authorsText = [];
                }
            }
            
            //containedIn
            if (isset($row['mainsection']['series']) && isset($row['mainsection']['series']['displayname'])) {
                $containedIn = $row['mainsection']['series']['displayname'];
                if (isset($row['mainsection']['series']['issuecount']) 
                    && is_numeric($row['mainsection']['series']['issuecount'])
                    && '1' !== $row['mainsection']['series']['issuecount']
                ) {
                    $issueCount = $row['mainsection']['series']['issuecount'];
                    $containedIn = "$containedIn ($issueCount)";
                }
            }
        }
        
        $publisher = null;
        if (isset($row['publisher']) && isset($row['publisher']['displayname'])) {
            $publisher = $row['publisher']['displayname'];
        }
        $publishingDate = null;
        $publishedYear = null;
        if (isset($row['publicationdate']) && isset($row['publicationdate']['date'])) {
            $publishingDate = $row['publicationdate']['date'];
            if (1 !== preg_match('/^\d{4,4}$/', $publishingDate)) {
                if (isset($dateConverters[$publishingDate])) {
                    $publishingDate = $dateConverters[$publishingDate];
                } else {
                    var_dump($publishingDate);
                    throw new \Exception('There are new dates to manually process');
                }
            }
            //extract just the year for the library table
            $re = '/((?:19|20)\d{2,2})/';
            $matches = null;
            if (preg_match($re, $publishingDate, $matches, PREG_OFFSET_CAPTURE, 0)) {
                $publishedYear = $matches[1][0];
            }
        }
        $publishingPlace = null;
        if (isset($row['country']) && isset($row['country']['displayname'])) {
            $publishingPlace = $row['country']['displayname'];
        }
        
        $copyrightYear = null;
        if (isset($row['firstedition']) 
            && isset($row['firstedition']['boolvalue']) 
            && '1' === $row['firstedition']['boolvalue']
        ) {
            if (!isset($edition)) {
                $edition = '1';
            }
            //if this is the first edition, fill the published year as copyright year
            if (isset($publishedYear)) {
                $copyrightYear = $publishedYear;
            }
        }
        
        $lastModified = null;
        if (isset($row['lastmodified']) && isset($row['latmodified']['date'])) {
            try {
                $lastModified = new \DateTime($row['latmodified']['date'], new \DateTimeZone('Europe/Berlin'));
            } catch (\Exception $e) {}
        }
        if (!isset($lastModified)) {
            $lastModified = clone $now;
        }
        
        if (isset($row['lccn'])) {
            $callNumber = $row['lccn'];
//             $debugCount++;
//             if ($debugCount < 15) {
//                 echo '<pre>';
//                 var_dump($callNumber);
//                 echo '</pre>';
//             }
        }
        
        $data = [
            'publicationId'             => 0, //@todo don't forget to remove this
            'title'                     => $title,
//             'titleNoAccents'            => $row['TitleNoAccents'],
            'slug'                      => $slug,
            'subtitle'                  => $subtitle,
//             'subtitleNoAccents'         => $row['SubtitleNoAccents'],
            'resourceId'                => 'publication_public',
            'authorsText'               => $authorsText,
//             'authorsNoAccents'          => $row['AuthorsNoAccents'],
            'bookEdition'               => $edition,
//             'categoryId'                => $categoryId,
            
            'inLanguage' => $inLanguage,
//             'description'               => $this->filterDbString($row['Description']),
            'isbn'                      => isset($row['isbn']) ? $row['isbn'] : null,
            'editorsText'               => $editorsText,
//             'editorsNoAccents'          => $row['EditorNoAccents'],
//             'translatorsText'           => $translatorText,
            'numberOfPages'             => isset($row['pagecount']) ? $row['pagecount'] : null,
            'copyrightYear'             => $copyrightYear,
//             'copyrightInfo'             => $row['CopyrightInfo'],
            'datePublishedText'         => $publishingDate,
            'publisher'                 => $publisher,
            'publishingPlace'           => $publishingPlace,
//             'publishingStatus'          => $this->filterDbString($row['PublishingStatus']),
//             'bookFormatType'            => $bookFormatType,
//             'mainPublicationId'         => $mainPublicationId,
//             'translatedFromPublicationId'=> $this->filterDbId($row['TranslatedFromPublicationId']),
//             'volumeNumber'              => $this->filterDbString($row['VolumeNumber']),
            'containedIn'               => $containedIn,
//             'containedInIsbn'           => $this->filterDbString($row['ContainedInIsbn']),
//             'genre'                     => $this->filterDbString($row['Genre']),
            'keywords'                  => $keywords,
//             'adminTags'                 => $this->filterDbArray($row['AdminTags']),
//             'isAccessibleForFree'       => $this->filterDbBool($row['IsAccessableForFree']),
//             'isScientificWork'          => $this->filterDbBool($row['IsScientificWork']),
            //if its in english or spanish, we need to merge it later
            'isAwaitingMerge'           => !isset($inLanguage) || in_array('en', $inLanguage) 
                                                || in_array('es', $inLanguage),
            
//             'hasNoExplictEditionNumber' => $this->filterDbBool($row['HasNoExplictEditionNumber']),
//             'hasNoISBN'                 => $this->filterDbBool($row['HasNoISBN']),
            'isRevisedWithBookInHand'   => true,
//             'isFormallyPublished'       => $this->filterDbBool($row['IsFormallyPublished']),
            
            'hasBeenMerged'             => false,
//             'jkQuality'                 => $this->filterDbString($row['JkQuality']),
//             'jkQualityNotes'            => $this->filterDbString($row['JkQualityNotes']),
//             'jkPeriodId'                => $this->filterDbId($row['JkPeriod']),
//             'jkEventId'                 => $this->filterDbId($row['JkEventId']),
//             'urls'                      => $urls,
//             'url1'                      => $this->filterDbString($row['Url1']),
//             'url1Label'                 => $this->filterDbString($row['Url1Label']),
//             'url2'                      => $this->filterDbString($row['Url2']),
//             'url2Label'                 => $this->filterDbString($row['Url2Label']),
//             'url3'                      => $this->filterDbString($row['Url3']),
//             'url3Label'                 => $this->filterDbString($row['Url3Label']),
            'dataSource'                => 'forschungsbibliothek',
            'dataSourceId'              => $id,
            'dataSourceUpdatedOn'       => $lastModified,
            
//             'editionNotes'              => $this->filterDbString($row['EditionNotes']),
//             'publicNotes'               => $this->filterDbString($row['PublicNotes']),
//             'publicNotesUpdatedOn'      => $this->filterDbDate($row['PublicNotesUpdatedOn']),
//             'publicNotesUpdatedBy'      => $this->filterDbId($row['PublicNotesUpdatedBy']),
//             'adminNotes'                => $this->filterDbString($row['AdminNotes']),
//             'adminNotesUpdatedOn'       => $this->filterDbDate($row['AdminNotesUpdatedOn']),
//             'adminNotesUpdatedBy'       => $this->filterDbId($row['AdminNotesUpdatedBy']),
//             'createdOn'                 => $this->filterDbDate($row['CreatedOn']),
//             'createdBy'                 => $this->filterDbId($row['CreatedBy']),
//             'updatedOn'                 => $this->filterDbDate($row['UpdatedOn']),
//             'updatedBy'                 => $this->filterDbId($row['UpdatedBy']),

            //columns for library books. Shouldn't affect inserting into publications
            'libraryId' => 5,
            'withinLibraryId' => $id,
            'authors' => $authorsText,
            'callNumber' => $callNumber,
            'publishedYear' => $publishedYear,
        ];
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

        $categoryIds = array_keys($categories);
        foreach ($categoryIds as $categoryId) {
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
        $select = $this->getSelectPrototype('publication');
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
        $publications = $this->getObjects('publication');
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
