<?php
namespace Books\Model;

use SionModel\Db\Model\SionTable;

class BooksTable extends SionTable
{

    protected $publicationsCache;
    /**
     * @todo test this
     * @return mixed[]
     */
    public function getPublications()
    {
        if (!is_null($cache = $this->getPublicationsCache())) {
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
FROM `sch_publications` WHERE 1";

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
                'mainPublicationId' 		=> $this->filterDbId($row['MainPublicationId']),
                'volumeNumber' 				=> $this->filterDbString($row['VolumeNumber']),
                'cntainedIn' 				=> $this->filterDbString($row['ContainedIn']),
                'containedInIsbn' 			=> $this->filterDbString($row['ContainedInIsbn']),
                'genre' 					=> $this->filterDbString($row['Genre']),
                'keywords' 					=> $this->filterDbArray($row['Keywords']),
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
            ];
        }

        $this->setPublicationsCache($entities);
        return $entities;
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
     * @return mixed[]
     */
    public function importPublications()
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

            $keywords = explode(',', $this->filterDbString($row['Stichworte']));
            $keywords = array_map('trim', $keywords);
            $keywords = implode('|', $keywords);

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
            $entities[] = [
                'publicationId'          => null,

                'title'                     => $this->filterDbString($row['titel']),
//                 'authorPerson1'             => 'AuthorPerson1',
//                 'authorPerson2'             => 'AuthorPerson2',
//                 'authorPerson3'             => 'AuthorPerson3',
                'resourceId'                => 'pub',
                'authors'                   => $author,
                'bookEdition'               => null, //'BookEdition',
                'inLanuage'                 => $language,
                'description'               => $this->filterDbString($row['Inhalt']),
//                 'isbn'                      => 'Isbn',
//                 'illustrator'               => 'Illustrator',
//                 'translator'                => 'Translator',
//                 'numberOfPages'             => 'NumberOfPages',
                'copyrightYear'             => $yearPublished,
                'publisher'                 => $this->filterDbString($row['verlag']),
                'publishingPlace'           => $this->filterDbString($row['ort']),
                'datePublished'             => $dateFromYear,
                'publishingStatus'          => $this->filterDbString($row['herausgabe']),
//                 'genre'                     => $this->filterDbString($row['Genre',
                'url'                       => $this->filterDbString('download'),
//                 'textId'                    => 'TextId',
//                 'mainPublication'           => 'MainPublication', //mainEntity
//                 'quality'                   => $this->filterDbString($row['Quality',
//                 'qualityNotes'              => $this->filterDbString($row['QualityNotes',
                'volumeNumber'              => $this->filterDbString($row['BandNr']),
                'containedIn'               => $this->filterDbString($row['NameZeitschrift']),
                'containedInIsbn'           => $this->filterDbString($row['ISBN_ZeitschriftNr']),
                'keywords'                  => $keywords,

                'isAccessableForFree'       => false,
                'isInternalForPatres'       => false,
                'isScientificWork'          => $isScientific,
                'isAwaitingMerge'           => true,
                'hasBeenMerged'             => false,
                'adminTags'                 => $this->filterDbString($row['kategorie']),

//                 'jkPeriod'                  => 'JkPeriod', //Should be passed to the JkEvent table
//                 'jkEventId'                 => 'JkEventId',
//                 'location'                  => $this->filterDbString($row['Location',
//                 'jkCategory'                => $this->filterDbString($row['JkCategory', //DEPRECATED

                'dataSource'                => 'b_bibsek',
                'dataSourceId'              => $this->filterDbId($row['id']),
                'dataSourceUpdatedOn'       => $now,
//                 'publicNotes'               => $this->filterDbString($row['PublicNotes']),
//                 'publicNotesUpdatedOn'      => $this->filterDbDate($row['PublicNotesUpdatedOn']),
//                 'publicNotesUpdatedBy'      => $this->filterDbId($row['PublicNotesUpdatedBy']),
                'adminNotes'                => $originalSource,
                'adminNotesUpdatedOn'       => $originalSource ? $now : null,
                'adminNotesUpdatedBy'       => $originalSource ? $this->actingUserId : null,
                'createdOn'                 => $now,
                'createdBy'                 => $this->actingUserId,
                'updatedOn'                 => $now,
                'updatedBy'                 => $this->actingUserId,
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
//             $yearPublished = $this->filterDbString($row['jahr']);
//             $dateFromYear = !is_null($yearPublished) ? date_create_from_format('Y', $yearPublished) : null;

            $keywords = explode(',', $this->filterDbString($row['ev_keywords']));
            $keywords = array_map('trim', $keywords);
            $keywords = implode('|', $keywords);

            $isInternal = $this->filterDbString($row['ed_internpatres']) == 'yes';

            $startDate = $this->filterDbString($row['ev_datestart']);
            if ($startDate == '0') {
                $startDate = null;
            }
            if (!is_null($startDate)) {
                $startDate = date_create_from_format('Ymd', $startDate);
            }
            $endDate = $this->filterDbString($row['ev_dateend']);
            if ($endDate == '0') {
                $endDate = null;
            }
            if (!is_null($endDate)) {
                $endDate = date_create_from_format('Ymd', $endDate);
            }
            //@todo add in Event name info

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
            $entities[] = [
                'publicationId'          => null,

                'title'                     => $this->filterDbString($row['ev_name']),
                'authorPerson1'             => 1, //@todo create a record for Fr. Kentenich
//                 'authorPerson2'             => 'AuthorPerson2',
//                 'authorPerson3'             => 'AuthorPerson3',
                'authors'                   => 'Kentenich, Josef',
                'resourceId'                => $isInternal ? 'pub_patres' : 'pub',
                'bookEdition'               => null,//'BookEdition',
                'inLanuage'                 => $language,
//                 'description'               => $this->filterDbString($row['Inhalt']),
//                 'isbn'                      => 'Isbn',
//                 'illustrator'               => 'Illustrator',
//                 'translator'                => 'Translator',
//                 'numberOfPages'             => 'NumberOfPages',
                'copyrightYear'             => $yearPublished,
//                 'publisher'                 => $this->filterDbString($row['verlag']),
//                 'publishingPlace'           => $this->filterDbString($row['ort']),
//                 'datePublished'             => $dateFromYear,
//                 'publishingStatus'          => $this->filterDbString($row['herausgabe']),
//                 'genre'                     => $this->filterDbString($row['Genre',
//                 'url'                       => $this->filterDbString('download'),
//                 'textId'                    => 'TextId',
//                 'mainPublication'           => 'MainPublication', //mainEntity
                'jkQuality'                 => $this->filterDbString($row['ed_quality']),
                'jkQualityNotes'            => $this->filterDbString($row['ed_qualityremark']),
//                 'volumeNumber'              => $this->filterDbString($row['BandNr']),
//                 'containedIn'               => $this->filterDbString($row['NameZeitschrift']),
//                 'containedInIsbn'           => $this->filterDbString($row['ISBN_ZeitschriftNr']),
                'keywords'                  => $keywords,

                'isAccessableForFree'       => false,
                'isInternalForPatres'       => $isInternal,
                'isScientificWork'          => $isScientific,
                'isAwaitingMerge'           => true,
                'hasBeenMerged'             => false,
//                 'adminTags'                 => $this->filterDbString($row['kategorie']),

                'jkPeriod'                  => 'JkPeriod', //Should be passed to the JkEvent table
                'jkEventId'                 => 'JkEventId',
                'dataSource'                => 'b_bibprim_edition',
                'dataSourceId'              => $this->filterDbId($row['ed_id']),
                'dataSourceUpdatedOn'       => $now,
//                 'publicNotes'               => $this->filterDbString($row['PublicNotes']),
//                 'publicNotesUpdatedOn'      => $this->filterDbDate($row['PublicNotesUpdatedOn']),
//                 'publicNotesUpdatedBy'      => $this->filterDbId($row['PublicNotesUpdatedBy']),
                'adminNotes'                => $originalSource,
                'adminNotesUpdatedOn'       => $originalSource ? $now : null,
                'adminNotesUpdatedBy'       => $originalSource ? $this->actingUserId : null,
                'createdOn'                 => $now,
                'createdBy'                 => $this->actingUserId,
                'updatedOn'                 => $now,
                'updatedBy'                 => $this->actingUserId,
            ];
        }
        return $entities;
    }
}