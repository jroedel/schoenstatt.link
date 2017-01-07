<?php
namespace Books\Model;

use SionModel\Db\Model\SionTable;

class BooksTable extends SionTable
{

    protected $publicationsCache;
    /**
     * @return mixed[]
     */
    public function getPublications()
    {

        if (!is_null($this->publicationsCache)) {
            return $this->publicationsCache;
        }

        $sql = "";

        $results = $this->fetchSome(null, $sql, null);
        $entities = [];
        foreach ($results as $row) {
            $id = $this->filterDbId($row['PublicationId']);
            $entities[$id] = [
                'publicationId'          => $id,
                'name'                  => $this->filterDbString($row['Name']),
                'celebrationDate'       => $this->filterDbDate($row['Date']),
                'sort'                  => $this->filterDbInt($row['Number']),
                'publicNotes'           => $this->filterDbString($row['PublicNotes']),
                'publicNotesUpdatedOn'  => $this->filterDbDate($row['PublicNotesUpdatedOn']),
                'publicNotesUpdatedBy'  => $this->filterDbId($row['PublicNotesUpdatedBy']),
                'adminNotes'            => $this->filterDbString($row['AdminNotes']),
                'adminNotesUpdatedOn'   => $this->filterDbDate($row['AdminNotesUpdatedOn']),
                'adminNotesUpdatedBy'   => $this->filterDbId($row['AdminNotesUpdatedBy']),
                'createdOn'             => $this->filterDbDate($row['CreatedOn']),
                'createdBy'             => $this->filterDbId($row['CreatedBy']),
                'updatedOn'             => $this->filterDbDate($row['UpdatedOn']),
                'updatedBy'             => $this->filterDbId($row['UpdatedBy']),
            ];
        }

        return $this->publicationsCache = $entities;
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
                'quality'                   => $this->filterDbString($row['ed_quality']),
                'qualityNotes'              => $this->filterDbString($row['ed_qualityremark']),
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