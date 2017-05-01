<?php
namespace Books\Model;

use SionModel\Db\Model\SionTable;
use SionModel\Filter\ToAscii;

class PublicationsTable extends SionTable
{
    const BOOK_FORMAT_TYPE_HARDCOVER        = 'Hardcover';
    const BOOK_FORMAT_TYPE_PAPERBACK        = 'Paperback';
    const BOOK_FORMAT_TYPE_AUDIOBOOKFORMAT  = 'AudiobookFormat';
    const BOOK_FORMAT_TYPE_EBOOK            = 'EBook';

    protected $publicationsCache;

    /**
     * @var mixed $unlinkedPublicationsCache
     */
    protected $unlinkedPublicationsCache;

    public function getAuthorsValueOptions()
    {
        $sql = "SELECT DISTINCT `Authors`
FROM `sch_publications`
WHERE (`Authors` NOT LIKE '%;%')
ORDER BY `Authors`";
        $results = $this->fetchSome(null, $sql, null);
        $authors = null;
        foreach ($results as $row) {
            $author = $this->filterDbString($row['Authors']);
            if (!is_null($author)) {
                $authors[$author] = $author;
            }
        }
        return $authors;
    }

    public function getPublishersValueOptions()
    {
        $sql = "SELECT DISTINCT `Publisher`
FROM `sch_publications`
ORDER BY `Publisher`";
        $results = $this->fetchSome(null, $sql, null);
        $authors = null;
        foreach ($results as $row) {
            $publisher = $this->filterDbString($row['Publisher']);
            if (!is_null($publisher)) {
                $authors[$publisher] = $publisher;
            }
        }
        return $authors;
    }

    public function getKeywordsValueOptions()
    {
        $publications = $this->getUnlinkedPublications();
        $valueOptions = [];
        foreach ($publications as $publication) {
            foreach ($publication['keywords'] as $keyword) {
                if (!key_exists($keyword, $valueOptions)) {
                    $valueOptions[$keyword] = $keyword;
                }
            }
        }
        return $valueOptions;
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
        if (isset($query['search']) && !is_null($query['search'])) {
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
            if (isset($query['inLanguage']) && !is_null($query['inLanguage']) && is_string($query['inLanguage']) &&
                $query['inLanguage'] != $publication['inLanguage']
            ) {
                continue;
            }
            if (isset($query['inLanguage']) && !is_null($query['inLanguage']) && is_array($query['inLanguage']) &&
                !in_array($publication['inLanguage'], $query['inLanguage'])
            ) {
                continue;
            }
            //keywords
            if (isset($query['keywords']) && !is_null($query['keywords']) && is_string($query['keywords']) &&
                !in_array($query['keywords'], $publication['keywords'])
            ) {
                continue;
            }
            if (isset($query['keywords']) && !is_null($query['keywords']) && is_array($query['keywords'])
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

            if (isset($query['search']) && !is_null($query['search']) &&
                false === stripos($filter->filter($publication['authors']), $query['search']) &&
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
            if (!is_null($publication['mainPublicationId']) &&
                key_exists($publication['mainPublicationId'], $entities)
            ) {
                if (!$displaySubEditions && !key_exists($publication['mainPublicationId'], $results)) {
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
        if (!is_null($cache = $this->getUnlinkedPublicationsCache())) {
            return $cache;
        }

        $entities = $this->getUnlinkedPublications();

        $this->setUnlinkedPublicationsCache($entities);
        return $entities;
    }

    /**
     * @return mixed[]
     */
    public function getUnlinkedPublications()
    {
        if (!is_null($cache = $this->getUnlinkedPublicationsCache())) {
            return $cache;
        }
        $sql = "SELECT `PublicationId`, `Title`, `ResourceId`, `AuthorPerson1`, `AuthorPerson2`,
`AuthorPerson3`, `Authors`, `BookEdition`, `InLanguage`, `Description`, `Isbn`, `Translator`,
`Illustrator`, `NumberOfPages`, `CopyrightYear`, `Publisher`, `PublishingPlace`, `DatePublished`,
`PublishingStatus`, `BookFormatType`, `MainPublicationId`, `VolumeNumber`, `ContainedIn`,
`ContainedInIsbn`, `Genre`, `PublicTags`, `AdminTags`, `IsAccessableForFree`, `IsInternalForPatres`,
`IsScientificWork`, `IsAwaitingMerge`, `HasBeenMerged`, `JkQuality`, `JkQualityNotes`, `JkPeriod`,
`JkEventId`, `Url1`, `Url1Label`, `Url2`, `Url2Label`, `Url3`, `Url3Label`, `DataSource`,
`DataSourceId`, `DataSourceUpdatedOn`, `PublicNotes`, `PublicNotesUpdatedOn`, `PublicNotesUpdatedBy`,
`AdminNotes`, `AdminNotesUpdatedOn`, `AdminNotesUpdatedBy`, `UpdatedOn`, `UpdatedBy`,
`CreatedOn`, `CreatedBy`
FROM `sch_publications`
ORDER BY `Authors`, `Title`";
        $results = $this->fetchSome(null, $sql, null);
        $entities = [];
        foreach ($results as $row) {
            $id = $this->filterDbId($row['PublicationId']);
            //process URLs
            $unprocessedUrls = [
                ['url' => $row['Url1'], 'label' => $this->filterDbString($row['Url1Label'])],
                ['url' => $row['Url2'], 'label' => $this->filterDbString($row['Url2Label'])],
                ['url' => $row['Url3'], 'label' => $this->filterDbString($row['Url3Label'])],
            ];
            $urls = $this::processUrls($unprocessedUrls);
            $mainPublicationId = $this->filterDbId($row['MainPublicationId']);
            $entities[$id] = [
                'publicationId'             => $id,
                'title' 					=> $this->filterDbString($row['Title']),
                'resourceId'				=> $this->filterDbString($row['ResourceId']),
                'authorPerson1' 			=> $this->filterDbId($row['AuthorPerson1']),
                'authorPerson2' 			=> $this->filterDbId($row['AuthorPerson2']),
                'authorPerson3' 			=> $this->filterDbId($row['AuthorPerson3']),
                'authors' 					=> $this->filterDbString($row['Authors']),
                'bookEdition' 				=> $this->filterDbString($row['BookEdition']),
                'inLanguage' 				=> $this->filterDbString($row['InLanguage']),
                'description' 				=> $this->filterDbString($row['Description']),
                'isbn' 						=> $this->filterDbString($row['Isbn']),
                'translator' 				=> $this->filterDbString($row['Translator']),
                'illustrator' 				=> $this->filterDbString($row['Illustrator']),
                'numberOfPages' 			=> $this->filterDbInt($row['NumberOfPages']),
                'copyrightYear' 			=> $this->filterDbInt($row['CopyrightYear']),
                'publisher' 				=> $this->filterDbString($row['Publisher']),
                'publishingPlace' 			=> $this->filterDbString($row['PublishingPlace']),
                'datePublished' 			=> $this->filterDbDate($row['DatePublished']),
                'publishingStatus' 			=> $this->filterDbString($row['PublishingStatus']),
                'bookFormatType'			=> $this->filterDbString($row['BookFormatType']),
                'mainPublicationId' 		=> $mainPublicationId,
                'volumeNumber' 				=> $this->filterDbString($row['VolumeNumber']),
                'cntainedIn' 				=> $this->filterDbString($row['ContainedIn']),
                'containedInIsbn' 			=> $this->filterDbString($row['ContainedInIsbn']),
                'genre' 					=> $this->filterDbString($row['Genre']),
                'keywords' 					=> $this->filterDbArray($row['PublicTags']),
                'adminTags'					=> $this->filterDbArray($row['AdminTags']),
                'isAccessableForFree'       => $this->filterDbBool($row['IsAccessableForFree']),
                'isInternalForPatres'       => $this->filterDbBool($row['IsInternalForPatres']),
                'isScientificWork'          => $this->filterDbBool($row['IsScientificWork']),
                'isAwaitingMerge'           => $this->filterDbBool($row['IsAwaitingMerge']),
                'hasBeenMerged'             => $this->filterDbBool($row['HasBeenMerged']),
                'jkQuality' 				=> $this->filterDbString($row['JkQuality']),
                'jkQualityNotes' 			=> $this->filterDbString($row['JkQualityNotes']),
                'jkPeriodId' 				=> $this->filterDbId($row['JkPeriod']),
                'jkEventId' 				=> $this->filterDbId($row['JkEventId']),
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

                'isSubEdition'              => !is_null($mainPublicationId),
                'mainPublication'           => null,
            ];
        }

        $this->setUnlinkedPublicationsCache($entities);
        return $entities;
    }

    /**
    * Get the unlinkedPublicationsCache value
    * @return mixed
    */
    public function getUnlinkedPublicationsCache()
    {
        return $this->unlinkedPublicationsCache;
    }

    /**
    *
    * @param mixed $unlinkedPublicationsCache
    * @return self
    */
    public function setUnlinkedPublicationsCache($unlinkedPublicationsCache)
    {
        $this->unlinkedPublicationsCache = $unlinkedPublicationsCache;
        return $this;
    }

    /**
     * @return null|array
     */
    protected function getPublicationsCache()
    {
        return $this->publicationsCache;
    }

    /**
     * @param array $publications
     */
    protected function setPublicationsCache($publications)
    {
        $this->publicationsCache = $publications;
        return $this;
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
            $dateFromYear = !is_null($yearPublished) ? date_create_from_format('Y', $yearPublished) : null;

            $keywords = $this->filterDbString($row['Stichworte']);
            if (!is_null($keywords)) {
                $keywords = explode(',', $keywords);
                $keywords = array_map('trim', $keywords);
                $keywords = implode('|', $keywords);
            }
            $category = $this->filterDbString($row['kategorie']);
            if (!is_null($category) && is_null($keywords)) {
                $keywords = $category;
            } elseif (!is_null($category)) {
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
            if (!is_null($originalSource)) {
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
                'authorPerson1'             => null,
                'authorPerson2'             => null,
                'authorPerson3'             => null,
                'resourceId'                => 'pub',
                'authors'                   => $author,
                'bookEdition'               => null, //'BookEdition',
                'inLanuage'                 => $language,
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
                'url1Label'                 => !is_null($download) ? 'Download' : null,
                'textId'                    => null,
                'mainPublication'           => null,
//                 'volumeNumber'              => $this->filterDbString($row['BandNr']),
//                 'containedIn'               => $this->filterDbString($row['NameZeitschrift']),
//                 'containedInIsbn'           => $this->filterDbString($row['ISBN_ZeitschriftNr']),
                'keywords'                  => $keywords,

                'isAccessableForFree'       => false,
                'isInternalForPatres'       => false,
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
//             $dateFromYear = !is_null($yearPublished) ? date_create_from_format('Y', $yearPublished) : null;

            $isInternal = $this->filterDbString($row['ed_internpatres']) == 'yes' ||
                $this->filterDbString($row['ev_intern']) == 'yes';

            if (!is_null($remarks = $this->filterDbString($row['ed_remark']))) {
                $publicNotes .= sprintf("Edition remarks: %s\r\n", $remarks);
            }

            if (!is_null($evName = $this->filterDbString($row['ev_name']))) {
                $publicNotes .= sprintf("Event name: %s\r\n", $evName);
            }

            $startDate = $this->filterDbDateInt($row['ev_datestart']);
            $endDate = $this->filterDbDateInt($row['ev_dateend']);
            if (!is_null($startDate)) {
                $dateString = date_format($startDate, 'Y-m-d');
                if (!is_null($endDate)) {
                    $dateString .= ' - '.date_format($endDate, 'Y-m-d');
                }
                if (!is_null($dateRemarks = $this->filterDbString($row['ev_dateremark']))) {
                    $dateString .= sprintf(" (%s)", $dateRemarks);
                }
                $publicNotes .= sprintf("Text from %s\r\n", $dateString);
            }

            if (!is_null($shortName = $this->filterDbString($row['ev_shortname']))) {
                $publicNotes .= sprintf("Short name: %s\r\n", $shortName);
            }

            if (!is_null($abbreviation = $this->filterDbString($row['ev_kuerzelvaut']))) {
                $publicNotes .= sprintf("Abbreviation: %s\r\n", $abbreviation);
            }

            $originalSource = $this->filterDbString($row['ed_location']);
            if (!is_null($originalSource)) {
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

            if (!is_null($quality = $this->filterDbString(ltrim($row['ed_quality'])))) {
                $quality = ucfirst(substr($quality, 0, 1));
                $qualityChr = ord($quality);
                if ($qualityChr < 65 || $quality > 72) {
                    $quality = null;
                }
            }
            $entities[] = [
//                 'publicationId'          => null,

                'title'                     => $this->filterDbString($row['ed_title']),
                'authorPerson1'             => null,
                'authorPerson2'             => null,
                'authorPerson3'             => null,
                'authors'                   => 'Kentenich, Josef',
                'resourceId'                => $isInternal ? 'pub_patres' : 'pub',
                'bookEdition'               => null,
                'inLanuage'                 => $language,
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

                'isAccessableForFree'       => false,
                'isInternalForPatres'       => $isInternal,
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
                if (!is_null($data['title'])) {
                    $this->createEntity('publication', $data);
                } else {
                    unset($entities[$key]);
                }
            }
        }
        return $entities;
    }

    protected function whichKentenichPeriod($date)
    {
        if (is_null($date)) {
            return null;
        }
        $tz = new \DateTimeZone('UTC');
        static $periodEnd;
        if (is_null($periodEnd)) {
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
        if (!is_null($dateIntStr)) {
            if (substr($dateIntStr, 4) == '0000') {
                $dateIntStr = substr($dateIntStr, 0,4).'0101';
            } elseif (substr($dateIntStr, 6) == '00') {
                $dateIntStr = substr($dateIntStr, 0,6).'01';
            }
            $dateIntStr = date_create_from_format('Ymd', $dateIntStr);
        }
        return $dateIntStr;
    }
}