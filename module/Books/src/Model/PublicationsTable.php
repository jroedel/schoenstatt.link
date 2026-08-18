<?php
namespace Books\Model;

use SionModel\Db\Model\SionTable;
use SionModel\Filter\ToAscii;
use Schoenstatt\Model\SchoenstattTable;
use Laminas\Db\Sql\Predicate\IsNotNull;
use Laminas\Db\Sql\Predicate\Literal;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Predicate\Expression;
use SionModel\Db\Model\PredicatesTable;
use Laminas\Db\Sql\Where;
use Laminas\Db\Sql\Predicate\PredicateSet;
use Laminas\Db\Sql\Predicate\Operator;
use Laminas\Db\Sql\Predicate\In;
use Laminas\Db\Sql\Predicate\IsNull;
use Laminas\Db\Sql\Predicate\Predicate;
use Laminas\Db\Sql\Predicate\Like;
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
            if (! $object['isAuthor']) {
                continue;
            }
            $authors['p' . $personId] = $object['fullName'];
        }
        return $authors;
    }

    public function getAuthorAssociationValueOptions()
    {
        $authors = [];
        $authorAssociations = $this->schoenstattTable->getObjects('association');
        foreach ($authorAssociations as $associationId => $object) {
            if (! $object['isAuthor']) {
                continue;
            }
            $authors['a' . $associationId] = $object['formattedName'];
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
            $author = $row['Author'];
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
            if (! isset($authors[$key])) {
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
            $publisher = $row['Publisher'];
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
                if (! isset($valueOptions[$keyword])) {
                    $valueOptions[$keyword] = $keyword;
                }
            }
        }
        return $valueOptions;
    }

    public function getEditionValueOptions()
    {
        $cacheKey = 'publication-edition-value-options';
        if (null !== ($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }
        $entities = $this->queryObjects('publication', new IsNull('DataSource'));
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
            if (! $entityObject['isPlaceholder'] || $includePlaceholders) {
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
        $cacheKey = 'publication-languages-' . ($includeUser ? '1' : '0') . ($includeInstitute ? '1' : '0') . ($includePatres ? '1' : '0');
        if (null !== ($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }
        $entities = $this->getObjects('publication');

        $languages = [];
        foreach ($entities as $object) {
            if (
                ('publication_public' === $object['resourceId']
                    && (null !== $this->getActingUserId() || $object['isRevisedWithBookInHand']))
                || ($includeUser && 'publication_user' === $object['resourceId'])
                || ($includeInstitute && 'publication_institute' === $object['resourceId'])
                || ($includePatres && 'publication_patres' === $object['resourceId'])
            ) {
                if (! isset($object['inLanguage'])) {
                    if (! isset($languages['xx'])) {
                        $languages['xx'] = 1;
                    } else {
                        $languages['xx']++;
                    }
                } elseif (is_array($object['inLanguage'])) {
                    foreach ($object['inLanguage'] as $lang) {
                        if (! isset($languages[$lang])) {
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
    public function searchPublications($query = [], $options = [])
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
//         $possibleOptions = ['maxResults', 'page', 'resultsPerPage', 'orCombination', 'noLink', 'noSubEditions',
//              'includeDataSources'];

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
                    if (is_numeric($value) && ! in_array($value, $categories)) {
                        $categories[] = $value;
                    }
                }
                if (count($categories) === 1) {
                    $query['categoryId'] = $categories[0];
                } elseif (count($categories) > 1) {
                    $categoryIdClause = new In($fieldMap['categoryId'], $categories);
                }
            }
            if (is_numeric($query['categoryId'])) {
                $categoryIdClause = new Operator($fieldMap['categoryId'], Operator::OPERATOR_EQUAL_TO, $query['categoryId']);
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
                    if (is_numeric($value) && ! in_array($value, $publications)) {
                        $publications[] = $value;
                    }
                }
                if (count($publications) === 1) {
                    $query['publicationId'] = $publications[0];
                } elseif (count($publications) > 1) {
                    $publicationIdClause = new In($fieldMap['publicationId'], $publications);
                }
            }
            if (is_numeric($query['publicationId'])) {
                $publicationIdClause = new Operator($fieldMap['publicationId'], Operator::OPERATOR_EQUAL_TO, $query['publicationId']);
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
                    if (is_numeric($value) && ! in_array($value, $mainPublications)) {
                        $mainPublications[] = $value;
                    }
                }
                if (count($mainPublications) === 1) {
                    $query['mainPublicationId'] = $mainPublications[0];
                } elseif (count($mainPublications) > 1) {
                    $mainPublicationIdClause = new In($fieldMap['mainPublicationId'], $mainPublications);
                }
            }
            if (is_numeric($query['mainPublicationId'])) {
                $mainPublicationIdClause = new Operator($fieldMap['mainPublicationId'], Operator::OPERATOR_EQUAL_TO, $query['mainPublicationId']);
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
                    if (is_numeric($value) && ! in_array($value, $translatedFromPublications)) {
                        $translatedFromPublications[] = $value;
                    }
                }
                if (count($translatedFromPublications) === 1) {
                    $query['translatedFromPublicationId'] = $translatedFromPublications[0];
                } elseif (count($translatedFromPublications) > 1) {
                    $translatedFromPublicationIdClause = new In($fieldMap['translatedFromPublicationId'], $translatedFromPublications);
                }
            }
            if (is_numeric($query['translatedFromPublicationId'])) {
                $translatedFromPublicationIdClause = new Operator($fieldMap['translatedFromPublicationId'], Operator::OPERATOR_EQUAL_TO, $query['translatedFromPublicationId']);
            }
            if (isset($translatedFromPublicationIdClause)) {
                $where->addPredicate($translatedFromPublicationIdClause, $combination);
            }
        }

        //Prepare title predicate
        if (isset($query['title']) && 0 !== strlen($query['title'])) {
            $search = $query['title'];
            $searchLike = sprintf("%%%s%%", $search);
            $titleClause = new Like($fieldMap['title'], $searchLike);
            $where->addPredicate($titleClause, $combination);
        }

        //Prepare author predicate
        if (isset($query['authorsText']) && 0 !== strlen($query['authorsText'])) {
            $search = $query['authorsText'];
            $searchLike = sprintf("%%%s%%", $search);
            $authorClause = new Like($fieldMap['authorsText'], $searchLike);
            $where->addPredicate($authorClause, $combination);
        }

        //Prepare inLanguage predicate
        if (isset($query['inLanguage'])) {
            $inLanguageClause = new In($fieldMap['inLanguage'], $query['inLanguage']);
            $where->addPredicate($inLanguageClause, PredicateSet::OP_AND); //I don't think it would ever make sense combine with OR here
        }

        //Prepare isSubEdition predicate, by default, don't filter
        if (isset($options['noSubEditions']) && $options['noSubEditions']) {
            $noSubEditionClause = new IsNull($fieldMap['mainPublicationId']);
            $where->addPredicate($noSubEditionClause, PredicateSet::OP_AND);
        }

        /*
         * @todo check if this really works
         *
         * Prepare isFromDataSource predicate, by default, don't admit records with dataSource
         *
         * Read like this: If the user hasn't set an option including data sources,
         * then add a predicate to filter them out
         */
        if (! isset($options['includeDataSources']) || ! $options['includeDataSources']) {
            $isFromDataSourceClause = new IsNull($fieldMap['dataSource']);
            $where->addPredicate($isFromDataSourceClause, PredicateSet::OP_AND);
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

        if (! isset($options['noLink']) || ! $options['noLink']) {
            $this->linkPublications($entities);
        }

        return $entities;
    }

    /**
     * Fetch the bare minimum needed to build the publications branch of the site navigation.
     *
     * The navigation only reads `title`, `slug`, `identifier` and `inLanguage`, keyed on the
     * publicationId. Going through getObjects('publication') to get that costs a full hydration
     * of ~10k 80-field entities and, worse, caches them under `query-objects-publication`, a
     * single APCu item well over 30 MiB against a 32 MiB segment. When that write fails the
     * adapter throws and (with apc.ttl=0) APCu drops everything, so the next request finds the
     * navigation cold and repeats the whole thing.
     *
     * This method deliberately duplicates the derivations processPublicationRow() applies to the
     * four fields the navigation uses:
     *   - `identifier` is derived (ToSchoenstattLinkIdentifier), it is not a column;
     *   - `slug` falls back to SchoenstattTable::getSlug($title) when the column is null;
     *   - `inLanguage` is the pipe-delimited `InLanguage` column exploded into an array.
     * The join and ORDER BY come from getSelectPrototype('publication'), so the row order matches
     * getObjects('publication') exactly. Only `publication_public` rows are returned, which is the
     * only resourceId the navigation renders.
     *
     * Unlike processPublicationRow(), a derived slug is NOT written back to the database here:
     * this runs during bootstrap on every cold-cache request and the derivation is deterministic,
     * so the navigation link is identical either way. The other read paths still persist it.
     *
     * @return array[] keyed on publicationId
     */
    public function getPublicationNavigationData()
    {
        $cacheKey = 'publication-navigation-data';
        if (null !== ($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }

        $select = $this->getSelectPrototype('publication');
        //narrow the column list to what the navigation reads. CategorySortOrder is a select alias
        //referenced by the prototype's ORDER BY, so it has to stay in the list.
        $select->columns([
            'PublicationId',
            'Title',
            'Slug',
            'InLanguage',
            'CategorySortOrder' => new Expression('IF(ISNULL(`SortOrder`), 1000, `SortOrder`)'),
        ]);
        $select->where(new Operator(
            'sch_publications.ResourceId',
            Operator::OPERATOR_EQUAL_TO,
            'publication_public'
        ));

        $gateway = $this->getTableGateway('sch_publications');
        $results = $gateway->selectWith($select);

        $swFilter = new ToSchoenstattLinkIdentifier('publication');
        $navigationData = [];
        foreach ($results as $row) {
            $id = $this->filterDbId($row['PublicationId']);
            if (! isset($id) || isset($navigationData[$id])) {
                continue;
            }
            $title = $row['Title'];
            $slug = $row['Slug'];
            if (! isset($slug)) {
                $slug = SchoenstattTable::getSlug($title);
            }
            $navigationData[$id] = [
                'title'      => $title,
                'slug'       => $slug,
                'identifier' => $swFilter->filter($id),
                'inLanguage' => $this->filterDbArray($row['InLanguage']),
            ];
        }

        $this->cacheEntityObjects($cacheKey, $navigationData, ['publication']);
        return $navigationData;
    }

    /**
     * The ids of every public publication that has been merged into another one.
     *
     * A merged publication is not a page: `PublicationsController::publicationAction()` and
     * its ported twin both answer a **301** to the surviving edition, so the URL exists only
     * to redirect. It is in the navigation anyway — getPublicationNavigationData() filters on
     * ResourceId alone — which is harmless in a menu and wrong in a sitemap, where 3,627 of
     * the 10,104 public publications here are permanent redirects offered to crawlers as
     * canonical pages.
     *
     * Deliberately its own narrow query rather than a column added to the navigation
     * projection: that projection's cached value is the largest single item in the
     * persistent cache (2.24 MB) and is read on every cold-cache laminas request, whereas
     * this list is read by the sitemap and nothing else.
     *
     * @return int[] publication ids, as a set keyed on the id
     */
    public function getMergedPublicationIds()
    {
        $cacheKey = 'merged-publication-ids';
        if (null !== ($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }

        $select = new Select('sch_publications');
        $select->columns(['PublicationId']);
        $select->where([
            new Operator('ResourceId', Operator::OPERATOR_EQUAL_TO, 'publication_public'),
            new IsNotNull('MergedIntoPublicationId'),
        ]);

        $gateway = $this->getTableGateway('sch_publications');
        $merged  = [];
        foreach ($gateway->selectWith($select) as $row) {
            $id = $this->filterDbId($row['PublicationId']);
            if (isset($id)) {
                $merged[$id] = $id;
            }
        }

        $this->cacheEntityObjects($cacheKey, $merged, ['publication']);
        return $merged;
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
    protected function processPublicationRow($row)
    {
        static $categories;
        static $swFilter;
//         static $count;
//         if (!isset($count)) {
//             $count = 0;
//             var_dump(memory_get_peak_usage(false));
//             var_dump($count);
//         }
//         $count++;
//         if ($count % 100 === 0) {
//             var_dump(memory_get_peak_usage(false));
//             var_dump($count);
//         }

        $id = $this->filterDbId($row['PublicationId']);

//         if (isset($this->unlinkedPublicationsMemoryCache[$id])) {
//             return $this->unlinkedPublicationsMemoryCache[$id];
//         }
        if (! isset($swFilter)) {
            $swFilter = new ToSchoenstattLinkIdentifier('publication');
        }
        $identifier = $swFilter->filter($id);

        $title = $row['Title'];
        $slug = $row['Slug'];
        if (! isset($slug)) {
            $slug = SchoenstattTable::getSlug($title);
            $this->slylyUpdatePublicationSlug($id, $slug);
        }

        if (! isset($categories)) {
            $categories = $this->getCategories();
        }
        //process URLs
        $unprocessedUrls = [
            ['url' => $row['Url1'], 'label' => $row['Url1Label']],
            ['url' => $row['Url2'], 'label' => $row['Url2Label']],
            ['url' => $row['Url3'], 'label' => $row['Url3Label']],
        ];
        $urls = $this::processUrls($unprocessedUrls);
        $mainPublicationId = $this->filterDbId($row['MainPublicationId']);

        $resourceId = $row['ResourceId'];

        //the text/all distinction exists because "all" will also include linked entities from other tables
        $authorsText = $this->filterDbArray($row['Authors']);
        $editorText = $this->filterDbArray($row['Editor']);
        $translatorText = $this->filterDbArray($row['Translator']);

        $bookFormatType = $row['BookFormatType'];
        $bookFormatTypeUrl = null;
        //`isset($array[null])` is an E_DEPRECATED on PHP 8.5 even though it answers false,
        //and most publications have no format, so this fired once per row. Invisible in
        //production, which narrows error_reporting, and noisy in every console run.
        if (null !== $bookFormatType && isset(self::BOOK_FORMAT_TYPE_URLS[$bookFormatType])) {
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
            $extraInfoParts = [];
            if (isset($bookEdition)) {
                $extraInfoParts[] = $bookEdition;
            }
            if (isset($datePublishedText)) {
                $extraInfoParts[] = $datePublishedText;
            } elseif (isset($copyrightYear)) {
                $extraInfoParts[] = (string)$copyrightYear;
            }
            if (count($extraInfoParts) > 0) {
                $disambiguatingTitle .= " [" . implode(', ', $extraInfoParts) . "]";
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
            'description'               => $row['Description'],
            'isbn'                      => $row['Isbn'],
            'editorsText'               => $editorText,
            'editorsNoAccents'          => $row['EditorNoAccents'],
            'translatorsText'           => $translatorText,
            'numberOfPages'             => $this->filterDbInt($row['NumberOfPages']),
            'copyrightYear'             => $copyrightYear,
            'copyrightInfo'             => $row['CopyrightInfo'],
            'datePublishedText'         => $datePublishedText,
            'publisher'                 => $row['Publisher'],
            'publishingPlace'           => $row['PublishingPlace'],
            'publishingStatus'          => $row['PublishingStatus'],
            'bookFormatType'            => $bookFormatType,
            'mainPublicationId'         => $mainPublicationId,
            'translatedFromPublicationId' => $this->filterDbId($row['TranslatedFromPublicationId']),
            'volumeNumber'              => $row['VolumeNumber'],
            'containedIn'               => $row['ContainedIn'],
            'containedInIsbn'           => $row['ContainedInIsbn'],
            'genre'                     => $row['Genre'],
            'keywords'                  => $this->filterDbArray($row['PublicTags']),
            'adminTags'                 => $this->filterDbArray($row['AdminTags']),
            'isAccessibleForFree'       => $this->filterDbBool($row['IsAccessableForFree']),
            'isScientificWork'          => $this->filterDbBool($row['IsScientificWork']),

            'hasNoExplictEditionNumber' => $this->filterDbBool($row['HasNoExplictEditionNumber']),
            'hasNoISBN'                 => $this->filterDbBool($row['HasNoISBN']),
            'isRevisedWithBookInHand'   => $this->filterDbBool($row['IsRevisedWithBookInHand']),
            'isFormallyPublished'       => $this->filterDbBool($row['IsFormallyPublished']),

            //@todo delete fields hasBeenMerged, isAwaitingMerge
            'hasBeenMerged'             => $this->filterDbBool($row['HasBeenMerged']),
            'isAwaitingMerge'           => $this->filterDbBool($row['IsAwaitingMerge']),
            'mergedIntoPublicationId'   => $this->filterDbId($row['MergedIntoPublicationId']),
            'dataSource'                => $row['DataSource'],
            'dataSourceId'              => $this->filterDbId($row['DataSourceId']),
            'dataSourceUpdatedOn'       => $this->filterDbDate($row['DataSourceUpdatedOn']),

            'jkQuality'                 => $row['JkQuality'],
            'jkQualityNotes'            => $row['JkQualityNotes'],
            'jkPeriodId'                => $this->filterDbId($row['JkPeriod']),
            'jkEventId'                 => $this->filterDbId($row['JkEventId']),
            'urls'                      => $urls,
            'url1'                      => $row['Url1'],
            'url1Label'                 => $row['Url1Label'],
            'url2'                      => $row['Url2'],
            'url2Label'                 => $row['Url2Label'],
            'url3'                      => $row['Url3'],
            'url3Label'                 => $row['Url3Label'],

            'editionNotes'              => $row['EditionNotes'],
            'publicNotes'               => $row['PublicNotes'],
            'publicNotesUpdatedOn'      => $this->filterDbDate($row['PublicNotesUpdatedOn']),
            'publicNotesUpdatedBy'      => $this->filterDbId($row['PublicNotesUpdatedBy']),
            'adminNotes'                => $row['AdminNotes'],
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
//         $this->unlinkedPublicationsMemoryCache[$id] = &$processedRow;
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
        $object = $this->tryGettingObject('publication', $id);
        if (! isset($object)) {
            return null;
        }
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
        if (! key_exists('authorsText', $data) && key_exists('authorsAll', $data)) {
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
        if (! key_exists('editorsText', $data) && ! key_exists('editorPerson1Id', $data) &&
            ! key_exists('editorPerson2Id', $data) &&  ! key_exists('editorPerson3Id', $data) &&
            ! key_exists('editorAssociationId', $data) &&
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
        if (! key_exists('translatorsText', $data) && ! key_exists('translatorPerson1Id', $data) &&
            ! key_exists('translatorPerson2Id', $data) &&  ! key_exists('translatorPerson3Id', $data) &&
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

    /**
     * Update the categories of all related books at the same time
     * @param array $data
     * @param array $newEntityData
     * @param string $action
     */
    protected function postprocessPublication($data, $newEntityData, $action)
    {
        if ($action === self::ENTITY_ACTION_SUGGEST || ! isset($newEntityData['categoryId'])
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
            $clause = new Operator('PublicationId', Operator::OPERATOR_EQUAL_TO, $translatedFromPublicationId);
            $where->addPredicate($clause, PredicateSet::OP_OR);
        }

        $clause = new Operator('MainPublicationId', Operator::OPERATOR_EQUAL_TO, $publicationId);
        $where->addPredicate($clause, PredicateSet::OP_OR);
        $clause = new Operator('TranslatedFromPublicationId', Operator::OPERATOR_EQUAL_TO, $publicationId);
        $where->addPredicate($clause, PredicateSet::OP_OR);
        $gateway->update(['CategoryId' => $categoryId], $where);
    }

    // copyDataSourcedRowToFirstClassCitizen() lived here until 2026-08-17. It was the bulk
    // half of the 2020 data-source migration, driven by
    // /admin/literature-maintenance/copy-data-sourced-row-to-first-class-citizen. Measured
    // before removal it matched 0 rows: the 2,333 publications still carrying a DataSource
    // sit at IsAwaitingMerge = 1, which it skipped by design. They are merged one at a time
    // by copyPublicationToMainCorpus() below, which stays.

    public function copyPublicationToMainCorpus($publicationId)
    {
        $results = $this->queryObjects('publication', ['publicationId' => $publicationId]);
        if (! is_array($results) || 1 !== count($results)) {
            throw new \Exception('No publication found');
        }
        $publication = current($results);

        if (! isset($publication['dataSource'])) {
            throw new \Exception('Publication is not a data sourced row');
        }
        //insert a duplicate row unsetting several fields
        unset($publication['publicationId']);
        unset($publication['dataSource']);
        unset($publication['dataSourceId']);
        unset($publication['dataSourceUpdatedOn']);
        unset($publication['mergedIntoPublicationId']);
        unset($publication['createdBy']);
        unset($publication['createdOn']);
        unset($publication['updatedBy']);
        unset($publication['updatedOn']);

        //@todo double check if mainPublicationId needs to be remapped
        //@todo double check if translatedFromPublicationId needs to be remapped

//        var_dump($publication);

        $newId = $this->createEntity('publication', $publication);
//        var_dump("New id is $newId");
        if (! is_numeric($newId)) {
            throw new \Exception('We were expecting a numeric result from the creation of a new publication');
        }
        //with the resulting PublicationId, update the old record
        $this->updateEntity('publication', $publicationId, ['mergedIntoPublicationId' => $newId], [], false);
        return $newId;
    }

    // Four more methods lived here until 2026-08-17, all reachable only from the retired
    // /admin/literature-maintenance routes: updateMainPublicationIdReferences(),
    // updateTranslatedFromPublicationIdReferences(), updateCoverImages() and
    // compileMapFromDataSourcedRecordsToFirstClassCitizens(), which built the old-id =>
    // new-id map the other three followed. Between them they had three database references
    // and ten cover files left to fix. database/db8.2.sql did the references, written
    // against the same predicate rather than the ids measured that day; the covers were a
    // one-off script, applied by hand on 2026-08-18 and deleted afterwards, because
    // public/covers is gitignored and lives in shared/ where no deploy reaches it.

    public function getCategories()
    {
        if (null !== ($cache = $this->fetchCachedEntityObjects('publication-categories'))) {
            return $cache;
        }
        static $gateway;
        if (! isset($gateway)) {
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
            $name = $row['CategoryName'];
            $categories[$id] = [
                'categoryId'    => $id,
                'parentId'      => $row['ParentId'],
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

    public function getAuthors()
    {
        $publications = $this->getObjects('publication');
        $authors = [];
        foreach ($publications as $publicationId => $publication) {
            if (! isset($publication['authorsText'])) {
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

    protected function whichKentenichPeriod($date)
    {
        //@todo validate input
        if (null === $date) {
            return null;
        }
        $tz = new \DateTimeZone('UTC');
        static $periodEnd;
        if (! isset($periodEnd)) {
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
        if ($dateIntStr == '0') {
            $dateIntStr = null;
        }
        if (isset($dateIntStr)) {
            if (mb_substr($dateIntStr, 4) == '0000') {
                $dateIntStr = mb_substr($dateIntStr, 0, 4) . '0101';
            } elseif (mb_substr($dateIntStr, 6) == '00') {
                $dateIntStr = mb_substr($dateIntStr, 0, 6) . '01';
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
        if (! isset($this->predicatesTable)) {
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
