<?php
namespace Books\Model;

use SionModel\Db\Model\SionTable;
use SionModel\Filter\ToAscii;
use Schoenstatt\Model\SchoenstattTable;
use Zend\Db\Sql\Select;
use Zend\Validator\Regex;

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

    /**
    * @var SchoenstattTable $schoenstattTable
    */
    protected $schoenstattTable;

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
        $authorAssociations = $this->schoenstattTable->getUnlinkedAssociations();
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
            $return[$entityId] = $entityObject['title'].
                (isset($entityObject['bookEdition']) ? '"'.$entityObject['bookEdition'].'"' : '');
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
        foreach ($entities as $entityId => $object) {
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
            //         $select->columns(['TheMonth' => new Expression('MONTH(`modified_on`)'), 'TheYear' => new Expression('YEAR(`modified_on`)'), 'Count' => new Expression('Count(*)')]);
            $select->columns(['PublicationId', 'Title', 'ResourceId', 'AuthorPerson1', 'AuthorPerson2',
                'AuthorPerson3', 'AuthorPerson4', 'AuthorPerson5', 'Authors', 'AuthorAssociationId1',
                'AuthorAssociationId2', 'AuthorAssociationId3', 'BookEdition', 'InLanguage', 'Description',
                'Isbn', 'Translator', 'Illustrator', 'Editor', 'IllustratorId', 'TranslatorId',
                'Translator2Id', 'Translator3Id', 'EditorId', 'Editor2Id', 'Editor3Id', 'EditorAssociationId1',
                'NumberOfPages', 'CopyrightYear', 'Publisher', 'PublisherAssociationId', 'PublishingPlace',
                'DatePublished', 'PublishingStatus', 'BookFormatType', 'MainPublicationId', 'VolumeNumber',
                'ContainedIn', 'ContainedInIsbn', 'Genre', 'PublicTags', 'AdminTags', 'IsAccessableForFree',
                'IsInternalForPatres', 'IsScientificWork', 'IsAwaitingMerge', 'HasBeenMerged', 'JkQuality',
                'JkQualityNotes', 'JkPeriod', 'JkEventId', 'Url1', 'Url1Label', 'Url2', 'Url2Label', 'Url3',
                'Url3Label', 'DataSource', 'DataSourceId', 'DataSourceUpdatedOn', 'PublicNotes',
                'PublicNotesUpdatedOn', 'PublicNotesUpdatedBy', 'AdminNotes', 'AdminNotesUpdatedOn',
                'AdminNotesUpdatedBy', 'UpdatedOn', 'UpdatedBy', 'CreatedOn', 'CreatedBy', 'HasNoISBN',
                'IsRevisedWithBookInHand', 'PublishDataAsJsonLd', 'IsFormallyPublished',
                'TranslatedFromPublicationId', 'HasNoExplictEditionNumber', 'EditionNotes', 'CategoryId']);//, 'CurrentCheckouts' => new Expression('(SELECT MAX(`CheckoutId`) FROM `lib_checkouts` WHERE (`BookId` = `book_id` AND ISNULL(`CheckedInOn`)))')]);
            //         $select->group(['TheMonth', 'TheYear']);
            //         $select->where($predicate->in('ChangedEntity', $tableEntities));
            $select->order(['Authors', 'InLanguage', 'Title']);
        }

        return clone $select;
    }

    /**
     * Search for books. Returns a list of publications. The query parameters are:
     * search(string), maxResults(int), displaySubEditions(bool), searchSubEditions(bool),
     * author(string|int|array[or], inLanguage(string|array[or])
     * @param mixed[] $query
     * @return mixed[]
     */
    public function searchPublications($query)
    {
        $filter = new ToAscii();
        if (isset($query['search'])) {
            $query['search'] = $filter->filter($query['search']);
        }

        $searchSubEditions = isset($query['searchSubEditions']) && is_bool($query['searchSubEditions']) ?
            $query['searchSubEditions'] : false;
        $displaySubEditions = isset($query['displaySubEditions']) && is_bool($query['displaySubEditions']) ?
            $query['displaySubEditions'] : false;

        $entities = $this->getPublications();
        $subEditions = [];
        $results = [];
        $count = 0;
        foreach ($entities as $publicationId => $publication) {
            //isAvailable
            if (!$searchSubEditions && $publication['isSubEdition']) {
                continue;
            }

            //language
            if (isset($query['inLanguage']) && is_string($query['inLanguage']) &&
                $query['inLanguage'] != $publication['inLanguage']
            ) {
                continue;
            }
            if (isset($query['inLanguage']) && is_array($query['inLanguage']) &&
                !in_array($publication['inLanguage'], $query['inLanguage'])
            ) {
                continue;
            }
            //keywords
            if (isset($query['keywords']) && is_string($query['keywords']) &&
                !in_array($query['keywords'], $publication['keywords'])
            ) {
                continue;
            }
            if (isset($query['keywords']) && is_array($query['keywords'])
            ) {
                $found = false;
                foreach ($query['keywords'] as $searchKeyword) {
                    if (in_array($searchKeyword, $publication['keywords'])) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    continue;
                }
            }

            if (isset($query['search']) &&
                false === stripos($filter->filter($publication['authorsText']), $query['search']) &&
                false === stripos($filter->filter($publication['title']), $query['search']) &&
                false === stripos($filter->filter($publication['bookEdition']), $query['search']) &&
                false === stripos($filter->filter($publication['description']), $query['search']) &&
                false === stripos($filter->filter($publication['publisher']), $query['search']) &&
                false === stripos($filter->filter($publication['volumeNumber']), $query['search'])
            ) {
                continue;
            }
            $count++;
            if (isset($query['maxResults']) && is_numeric($query['maxResults']) &&
                $count > $query['maxResults']
            ) {
                break;
            }
            if ($displaySubEditions || !$publication['isSubEdition']) {
                $results[$publicationId] = $publication;
            } else {
                $subEditions[$publicationId] = $publication;
            }
        }
        //add the subEdition or the mainEdition depending on $displaySubEditions
        foreach ($subEditions as $publicationId => $publication) {
            if (isset($publication['mainPublicationId']) &&
                isset($entities[$publication['mainPublicationId']])
            ) {
                if (!$displaySubEditions && !isset($results[$publication['mainPublicationId']])) {
                    $results[$publication['mainPublicationId']] = $entities[$publication['mainPublicationId']];
                } elseif ($displaySubEditions) {
                    $results[$publicationId] = $publication;
                }
            }
        }
        return $results;
    }

    public function getPublications()
    {
        if (null !== ($cache = $this->fetchCachedEntityObjects('publications'))) {
            return $cache;
        }

        $entities = $this->getUnlinkedPublications();
        $persons = $this->schoenstattTable->getUnlinkedPersons();
        $associations = $this->schoenstattTable->getUnlinkedAssociations();

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

            foreach ($entityObject['authorPersonIds'] as $personId) {
                if (isset($persons[$personId])) {
                    $entities[$entityId]['authorPersons'][$personId] = $persons[$personId];
                }
            }
            foreach ($entityObject['authorAssociationIds'] as $associationId) {
                if (isset($associations[$associationId])) {
                    $entities[$entityId]['authorAssociations'][$associationId] = $associations[$associationId];
                }
            }
            foreach ($entityObject['editorPersonIds'] as $personId) {
                if (isset($persons[$personId])) {
                    $entities[$entityId]['editorPersons'][$personId] = $persons[$personId];
                }
            }
            if (isset($associations[$entityObject['editorAssociationId']])) {
                $entities[$entityId]['editorAssociation'] = $associations[$entityObject['editorAssociationId']];
            }
            foreach ($entityObject['translatorPersonIds'] as $personId) {
                if (isset($persons[$personId])) {
                    $entities[$entityId]['translatorPersons'][$personId] = $persons[$personId];
                }
            }
            if (isset($persons[$entityObject['illustratorPersonId']])) {
                $entities[$entityId]['illustratorPerson'] = $persons[$entityObject['illustratorPersonId']];
            }
            if (isset($associations[$entityObject['publisherAssociationId']])) {
                $entities[$entityId]['publisherAssociation'] = $associations[$entityObject['publisherAssociationId']];
            }
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
     * @return mixed[]
     */
    protected function processPublicationRow($row)
    {
        static $categories;

        if (!isset($categories)) {
            $categories = $this->getCategories();
        }
            $id = $this->filterDbId($row['PublicationId']);
            //process URLs
            $unprocessedUrls = [
                ['url' => $row['Url1'], 'label' => $this->filterDbString($row['Url1Label'])],
                ['url' => $row['Url2'], 'label' => $this->filterDbString($row['Url2Label'])],
                ['url' => $row['Url3'], 'label' => $this->filterDbString($row['Url3Label'])],
            ];
            $urls = $this::processUrls($unprocessedUrls);
            $mainPublicationId = $this->filterDbId($row['MainPublicationId']);

//             $publishDataAsJsonLd = $this->filterDbBool($row['PublishDataAsJsonLd']);
            /*
             * For now, make public those that have the publishDataAsJsonFlag.
             */
            $resourceId = $this->filterDbString($row['ResourceId']);
//             if ('publication_public' === $resourceId && !$publishDataAsJsonLd) {
//                 $resourceId = 'publication_user';
//             }

            $authorsAll = [];

            $authorAssociationIds = [];
            $authorAssociation1Id = $this->filterDbId($row['AuthorAssociationId1']);
            $authorAssociation2Id = $this->filterDbId($row['AuthorAssociationId2']);
            $authorAssociation3Id = $this->filterDbId($row['AuthorAssociationId3']);
            if (isset($authorAssociation1Id)) {
                $authorAssociationIds[] = $authorAssociation1Id;
                $authorsAll[] = 'a'.$authorAssociation1Id;
            }
            if (isset($authorAssociation2Id)) {
                $authorAssociationIds[] = $authorAssociation2Id;
                $authorsAll[] = 'a'.$authorAssociation2Id;
            }
            if (isset($authorAssociation3Id)) {
                $authorAssociationIds[] = $authorAssociation3Id;
                $authorsAll[] = 'a'.$authorAssociation3Id;
            }

            $authorPersonIds = [];
            $authorPerson1Id =  $this->filterDbId($row['AuthorPerson1']);
            $authorPerson2Id = $this->filterDbId($row['AuthorPerson2']);
            $authorPerson3Id = $this->filterDbId($row['AuthorPerson3']);
            $authorPerson4Id = $this->filterDbId($row['AuthorPerson4']);
            $authorPerson5Id = $this->filterDbId($row['AuthorPerson5']);
            if (isset($authorPerson1Id)) {
                $authorPersonIds[] = $authorPerson1Id;
                $authorsAll[] = 'p'.$authorPerson1Id;
            }
            if (isset($authorPerson2Id)) {
                $authorPersonIds[] = $authorPerson2Id;
                $authorsAll[] = 'p'.$authorPerson2Id;
            }
            if (isset($authorPerson3Id)) {
                $authorPersonIds[] = $authorPerson3Id;
                $authorsAll[] = 'p'.$authorPerson3Id;
            }
            if (isset($authorPerson4Id)) {
                $authorPersonIds[] = $authorPerson4Id;
                $authorsAll[] = 'p'.$authorPerson4Id;
            }
            if (isset($authorPerson5Id)) {
                $authorPersonIds[] = $authorPerson5Id;
                $authorsAll[] = 'p'.$authorPerson5Id;
            }

            $authorsText = $this->filterDbArray($row['Authors']);
            foreach ($authorsText as $author) {
                $authorsAll[] = $author;
            }

            $editorsAll = [];
            $editorPersonIds = [];
            $editorPerson1Id = $this->filterDbId($row['EditorId']);
            $editorPerson2Id = $this->filterDbId($row['Editor2Id']);
            $editorPerson3Id = $this->filterDbId($row['Editor3Id']);
            $editorAssociationId= $this->filterDbId($row['EditorAssociationId1']);
            $editorText = $this->filterDbArray($row['Editor']);
            if (isset($editorPerson1Id)) {
                $editorPersonIds[] = $editorPerson1Id;
                $editorsAll[] = 'p'.$editorPerson1Id;
            }
            if (isset($editorPerson2Id)) {
                $editorPersonIds[] = $editorPerson2Id;
                $editorsAll[] = 'p'.$editorPerson2Id;
            }
            if (isset($editorPerson3Id)) {
                $editorPersonIds[] = $editorPerson3Id;
                $editorsAll[] = 'p'.$editorPerson3Id;
            }
            if (isset($editorAssociationId)) {
                $editorsAll[] = 'a'.$editorAssociationId;
            }
            if (isset($editorText)) {
                foreach ($editorText as $value) {
                    $editorsAll[] = $value;
                }
            }

            $translatorsAll = [];
            $translatorPersonIds = [];
            $translatorPerson1Id = $this->filterDbId($row['TranslatorId']);
            $translatorPerson2Id = $this->filterDbId($row['Translator2Id']);
            $translatorPerson3Id = $this->filterDbId($row['Translator3Id']);
            $translatorText = $this->filterDbArray($row['Translator']);
            if (isset($translatorPerson1Id)) {
                $translatorPersonIds[] = $translatorPerson1Id;
                $translatorsAll[] = 'p'.$translatorPerson1Id;
            }
            if (isset($translatorPerson2Id)) {
                $translatorPersonIds[] = $translatorPerson2Id;
                $translatorsAll[] = 'p'.$translatorPerson2Id;
            }
            if (isset($translatorPerson3Id)) {
                $translatorPersonIds[] = $translatorPerson3Id;
                $translatorsAll[] = 'p'.$translatorPerson3Id;
            }
            if (isset($translatorText)) {
                foreach ($translatorText as $value) {
                    $translatorsAll[] = $value;
                }
            }

            $illustratorsAll = [];
            $illustratorPersonId = $this->filterDbId($row['IllustratorId']);
            $illustratorText = $this->filterDbArray($row['Illustrator']);
            if (isset($illustratorPersonId)) {
                $illustratorsAll[] = 'p'.$illustratorPersonId;
            }
            if (isset($illustratorText)) {
                foreach ($illustratorText as $value) {
                    $illustratorsAll[] = $value;
                }
            }

            $bookFormatType = $this->filterDbString($row['BookFormatType']);
            $bookFormatTypeUrl = null;
            if (isset(self::BOOK_FORMAT_TYPE_URLS[$bookFormatType]))
            {
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

            $processedRow = [
                'publicationId'             => $id,
                'title'                     => $row['Title'],
                'resourceId'                => $resourceId,
                'authorPerson1Id'           => $authorPerson1Id,
                'authorPerson2Id'           => $authorPerson2Id,
                'authorPerson3Id'           => $authorPerson3Id,
                'authorPerson4Id'           => $authorPerson4Id,
                'authorPerson5Id'           => $authorPerson5Id,
                'authorAssociation1Id'      => $authorAssociation1Id,
                'authorAssociation2Id'      => $authorAssociation2Id,
                'authorAssociation3Id'      => $authorAssociation3Id,
                'authorsText'               => $authorsText,
                'bookEdition'               => $this->filterDbString($row['BookEdition']),
                'categoryId'                => $categoryId,

                'inLanguage'                => $inLanguage,
                'description'               => $this->filterDbString($row['Description']),
                'isbn'                      => $this->filterDbString($row['Isbn']),
                'editorPerson1Id'           => $editorPerson1Id,
                'editorPerson2Id'           => $editorPerson2Id,
                'editorPerson3Id'           => $editorPerson3Id,
                'editorAssociationId'       => $editorAssociationId,
                'editorsText'               => $editorText,
                'translatorPerson1Id'       => $translatorPerson1Id,
                'translatorPerson2Id'       => $translatorPerson2Id,
                'translatorPerson3Id'       => $translatorPerson3Id,
                'translatorsText'           => $translatorText,
                'illustratorPersonId'       => $illustratorPersonId,
                'illustratorsText'          => $illustratorText,
                'numberOfPages'             => $this->filterDbInt($row['NumberOfPages']),
                'copyrightYear'             => $this->filterDbInt($row['CopyrightYear']),
                'publisher'                 => $this->filterDbString($row['Publisher']),
                'publisherAssociationId'    => $this->filterDbId($row['PublisherAssociationId']),
                'publishingPlace'           => $this->filterDbString($row['PublishingPlace']),
                'datePublished'             => $this->filterDbDate($row['DatePublished']),
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
    //                 'publishDataAsJsonLd'       => $publishDataAsJsonLd,
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
                'authorPersonIds'           => $authorPersonIds,
                'authorAssociationIds'      => $authorAssociationIds,
                'authorPersons'             => [],
                'authorAssociations'        => [],

                'editorsAll'                => $editorsAll,
                'editorPersons'             => [],
                'editorPersonIds'           => $editorPersonIds,
                'editorAssociation'         => null,

                'translatorsAll'            => $translatorsAll,
                'translatorPersons'         => [],
                'translatorPersonIds'       => $translatorPersonIds,
                //translator associations are not allowed

                'illustratorsAll'           => $illustratorsAll,
                'illustratorPerson'         => null,

                'publisherAssociation'      => null,
                'isSubEdition'              => isset($mainPublicationId),
                'mainPublication'           => null,
                'subEditions'               => [],
                'translatedFromPublication' => null,
                'bookCoverFileId'           => null,
                'bookCoverFile'             => null,
            ];
        return $processedRow;
    }

    /**
     *
     * @param int $id
     * @return mixed[]
     */
    public function getPublication($id)
    {
        $publications = $this->getPublications();

        if (!isset($publications[$id]) || !($publication = $publications[$id])) {
            return null;
        }

        return $publication;
    }

    /**
     * Preprocess data bound for the database on the publication entities or its sub-entities
     * @param array $data
     * @return array
     */
    protected function preprocessPublication($data, $entityData, $action)
    {
        //don't allow the mainPublicationId to be set to itself, also, correct it
        if (isset($data['mainPublicationId']) &&
            $data['mainPublicationId'] == $entityData['publicationId']
        ) {
            $data['mainPublicationId'] = null;
        }

        static $entityDetector;
        static $personDetector;
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
            $persons = [];
            $associations = [];
            if (!isset($entityDetector)) {
                $entityDetector = new Regex('/^(p|a)\d{1,5}$/');
            }
            if (null !== $data['authorsAll']) {
                foreach ($data['authorsAll'] as $value) {
                    if ($entityDetector->isValid($value)) { //we've got a person or association
                        if ($value[0] == 'p') {
                            $persons[] = substr($value, 1);
                        } elseif ($value[0] == 'a') {
                            $associations[] = substr($value, 1);
                        }
                    } else { //we've just a regular text author
                        $text[] = $value;
                    }
                }
            }
            $data['authorsText'] = $text;
            $data['authorAssociation1Id'] = isset($associations[0]) ? $associations[0] : null;
            $data['authorAssociation2Id'] = isset($associations[1]) ? $associations[1] : null;
            $data['authorAssociation3Id'] = isset($associations[2]) ? $associations[2] : null;
            if (isset($associations[3])) {
                throw new \Exception('Only 3 author associations are allowed');
            }

            $data['authorPerson1Id'] = isset($persons[0]) ? $persons[0] : null;
            $data['authorPerson2Id'] = isset($persons[1]) ? $persons[1] : null;
            $data['authorPerson3Id'] = isset($persons[2]) ? $persons[2] : null;
            $data['authorPerson4Id'] = isset($persons[3]) ? $persons[3] : null;
            $data['authorPerson5Id'] = isset($persons[4]) ? $persons[4] : null;
            if (isset($persons[5])) {
                throw new \Exception('Only 5 author persons are allowed');
            }
        }
        if (!key_exists('editorsText', $data) && !key_exists('editorPerson1Id', $data) &&
            !key_exists('editorPerson2Id', $data) &&  !key_exists('editorPerson3Id', $data) &&
            !key_exists('editorAssociationId', $data) &&
            key_exists('editorsAll', $data)
        ) {
            $text = [];
            $persons = [];
            $associations = [];
            if (null !== $data['editorsAll']) {
                if (!isset($entityDetector)) {
                    $entityDetector = new Regex('/^(p|a)\d{1,5}$/');
                }
                foreach ($data['editorsAll'] as $value) {
                    if ($entityDetector->isValid($value)) { //we've got a person or association
                        if ($value[0] == 'p') {
                            $persons[] = substr($value, 1);
                        } elseif ($value[0] == 'a') {
                            $associations[] = substr($value, 1);
                        }
                    } else { //we've just a regular text author
                        $text[] = $value;
                    }
                }
            }
            $data['editorsText'] = $text;
            $data['editorAssociationId'] = isset($associations[0]) ? $associations[0] : null;
            if (isset($associations[1])) {
                throw new \Exception('Only 1 editor association is allowed');
            }

            $data['editorPerson1Id'] = isset($persons[0]) ? $persons[0] : null;
            $data['editorPerson2Id'] = isset($persons[1]) ? $persons[1] : null;
            $data['editorPerson3Id'] = isset($persons[2]) ? $persons[2] : null;
            if (isset($persons[3])) {
                throw new \Exception('Only 3 editor persons are allowed');
            }
        }
        if (!key_exists('translatorsText', $data) && !key_exists('translatorPerson1Id', $data) &&
            !key_exists('translatorPerson2Id', $data) &&  !key_exists('translatorPerson3Id', $data) &&
            key_exists('translatorsAll', $data)
        ) {
            $text = [];
            $persons = [];
            if (null !== $data['translatorsAll']) {
                if (!isset($personDetector)) {
                    $personDetector= new Regex('/^p\d{1,5}$/');
                }
                foreach ($data['translatorsAll'] as $value) {
                    if ($personDetector->isValid($value)) { //we've got a person or association
                        $persons[] = substr($value, 1);
                    } else { //we've just a regular text author
                        $text[] = $value;
                    }
                }
            }
            $data['translatorsText'] = $text;

            $data['translatorPerson1Id'] = isset($persons[0]) ? $persons[0] : null;
            $data['translatorPerson2Id'] = isset($persons[1]) ? $persons[1] : null;
            $data['translatorPerson3Id'] = isset($persons[2]) ? $persons[2] : null;
            if (isset($persons[3])) {
                throw new \Exception('Only 3 translator persons are allowed');
            }
        }
        if (!key_exists('illustratorsText', $data) && !key_exists('illustratorPersonId', $data) &&
            key_exists('illustratorsAll', $data)
        ) {
            $text = [];
            $persons = [];
            if (null !== $data['illustratorsAll']) {
                if (!isset($personDetector)) {
                    $personDetector= new Regex('/^p\d{1,5}$/');
                }
                foreach ($data['illustratorsAll'] as $value) {
                    if ($personDetector->isValid($value)) { //we've got a person or association
                        $persons[] = substr($value, 1);
                    } else { //we've just a regular text author
                        $text[] = $value;
                    }
                }
            }
            $data['illustratorsText'] = $text;

            $data['illustratorPersonId'] = isset($persons[0]) ? $persons[0] : null;
            if (isset($persons[1])) {
                throw new \Exception('Only one illustrator person is allowed');
            }
        }

//         if (isset($data['automaticTitle']) && $data['automaticTitle'] === true) {
//             $data['title'] = null;
//         }
        return $data;
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
     * @return NULL[][]|number[][]|string[][]|boolean[][]|unknown[][]|\DateTime[][]|mixed[][]
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
                'bookFormatType'            => $row['herausgabe'] == 'digital' ? self::BOOK_FORMAT_TYPE_EBOOK : self::BOOK_FORMAT_TYPE_PAPERBACK,
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

        if (!$simulate)
        {
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

        $publicationUpdates = [];
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
                1 => date_create_from_format('Y-m-d', '1913-01-01'),
                2 => date_create_from_format('Y-m-d', '1920-01-01'),
                3 => date_create_from_format('Y-m-d', '1925-01-01'),
                4 => date_create_from_format('Y-m-d', '1942-01-01'),
                5 => date_create_from_format('Y-m-d', '1946-01-01'),
                6 => date_create_from_format('Y-m-d', '1952-01-01'),
                7 => date_create_from_format('Y-m-d', '1966-01-01'),
                8 => date_create_from_format('Y-m-d', '1969-01-01'),
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
                $dateIntStr = mb_substr($dateIntStr, 0,4).'0101';
            } elseif (mb_substr($dateIntStr, 6) == '00') {
                $dateIntStr = mb_substr($dateIntStr, 0,6).'01';
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
}