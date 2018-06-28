<?php
namespace Books\Model;

use SionModel\Db\Model\SionTable;

class EventTextTable extends SionTable
{
    
    public function __construct(AdapterInterface $dbAdapter, $serviceLocator, $actingUserId, array $config)
    {
        parent::__construct($dbAdapter, $serviceLocator, $actingUserId);
        $this->config = $libraryConfig;
    }
    
    
    /**
     * Manipulate a database book row into a standardized row
     * @param array $row
     * @return array[]
     */
    protected function processEventRow($row)
    {
        $id = $this->filterDbId($row['book_id']);
        $authorText = $this->filterDbString($row['author']);
        $authors = $this->filterDbArray($authorText);
        $authorsPrettyText = implode('; ', $authors);
        $title = $this->filterDbString($row['title']);
        $name = $authorsPrettyText . ($authorText ? ' - ' : '') . $title;
        $libraryId = $this->filterDbId($row['library_id']);
        $isActive = $this->filterDbBool($row['is_active']);
        $processedRow = [
            'eventId'               => $this->filterDbId($row['EventId']),
            'titleEn'               => $row['TitleEn'],
            'titleEs'               => $row['TitleEs'],
            'titleDe'               => $row['TitleDe'],
            'titlePt'               => $row['TitlePt'],
            'titleFr'               => $row['TitleFr'],
            'country'               => $row['Country'],
            'originalLanguage'      => $row['OriginalLanguage'],
            'descriptionEn'         => $row['DescriptionEn'],
            'descriptionEs'         => $row['DescriptionEs'],
            'descriptionDe'         => $row['DescriptionDe'],
            'descriptionPt'         => $row['DescriptionPt'],
            'descriptionFr'         => $row['DescriptionFr'],
            'startDate'             => $this->filterDbDate($row['StartDate']),
            'durationInDays'        => $this->filterDbInt($row['DurationInDays']),
            'accuracy'              => $row['Accuracy'],
            'bestTextQuality'       => $row['BestTextQuality'],
            'place'                 => $row['Place'],
            'tags'                  => $row['Tags'],
            'adminTags'             => $row['AdminTags'],
            'audienceText'          => $row['AudienceText'],
            'abbreviationEn'        => $row['AbbreviationEn'],
            'abbreviationEs'        => $row['AbbreviationEs'],
            'abbreviationDe'        => $row['AbbreviationDe'],
            'abbreviationPt'        => $row['AbbreviationPt'],
            'abbreviationFr'        => $row['AbbreviationFr'],
            'aclResourceId'         => $row['AclResourceId'],
            'publicNotes'           => $row['PublicNotes'],
            'publicNotesUpdatedOn'  => $this->filterDbDate($row['PublicNotesUpdatedOn']),
            'publicNotesUpdatedBy'  => $this->filterDbId($row['PublicNotesUpdatedBy']),
            'adminNotes'            => $row['AdminNotes'],
            'adminNotesUpdatedOn'   => $this->filterDbDate($row['AdminNotesUpdatedOn']),
            'adminNotesUpdatedBy'   => $this->filterDbId($row['AdminNotesUpdatedBy']),
            'updatedOn'             => $this->filterDbDate($row['UpdatedOn']),
            'updatedBy'             => $this->filterDbId($row['UpdatedBy']),
            'createdOn'             => $this->filterDbDate($row['CreatedOn']),
            'createdBy'             => $this->filterDbId($row['CreatedBy']),
            'legacySource'          => $row['LegacySource'],
            'legacyFile'            => $row['LegacyFile'],
            'legacyFileDateModified' => $this->filterDbDate($row['LegacyFileDateModified']),
        ];
        return $processedRow;
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
        $libraryId = $this->getLibraryId();
        $cacheKey = !isset($libraryId) ? 'unlinked-books' : 'unlinked-books-'.$libraryId;
        if (null !== $cache = $this->fetchCachedEntityObjects($cacheKey)) {
            return $cache;
        }
        
        $gateway = $this->getTableGateway('lib_books');
        $where = [];
        if (!empty($bookIds)) {
            $where['book_id'] = $bookIds;
        }
        if (isset($libraryId)) {
            $select = $this->getBookSelectPrototype();
            $where['library_id'] = $libraryId;
            $select->where($where);
            $results = $gateway->selectWith($select);
        } else {
            $select = $this->getBookSelectPrototype();
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
        $this->cacheEntityObjects($cacheKey, $entities, ['book']);
        return $entities;
    }
    
    /**
     * Get a standardized select object to retrieve records from the database
     * @return \Zend\Db\Sql\Select
     */
    protected function getEventSelectPrototype()
    {
        static $select;
        if (!isset($select)) {
            $select = new Select('events');
            //         $select->columns(['TheMonth' => new Expression('MONTH(`modified_on`)'), 'TheYear' => new Expression('YEAR(`modified_on`)'), 'Count' => new Expression('Count(*)')]);
            $select->columns(['EventId', 'TitleEn', 'TitleEs', 'TitleDe', 'TitlePt', 'TitleFr', 
                'Country', 'OriginalLanguage', 'DescriptionEn', 'DescriptionEs', 'DescriptionDe', 
                'DescriptionPt', 'DescriptionFr', 'StartDate', 'DurationInDays', 'Accuracy', 
                'BestTextQuality', 'Place', 'Tags', 'AdminTags', 'AudienceText', 'AbbreviationEn', 
                'AbbreviationEs', 'AbbreviationDe', 'AbbreviationPt', 'AbbreviationFr', 'AclResourceId', 
                'PublicNotes', 'PublicNotesUpdatedOn', 'PublicNotesUpdatedBy', 'AdminNotes', 'AdminNotesUpdatedOn', 
                'AdminNotesUpdatedBy', 'UpdatedOn', 'UpdatedBy', 'CreatedOn', 'CreatedBy', 'LegacySource', 
                'LegacyFile', 'LegacyFileDateModified'
                //'admin_notes_updated_by', 'current_checkout_id' => new Expression('(SELECT MAX(`CheckoutId`) FROM `lib_checkouts` WHERE (`BookId` = `book_id` AND ISNULL(`CheckedInOn`)))')
            ]);
//         $select->group(['TheMonth', 'TheYear']);
//         $select->where($predicate->in('ChangedEntity', $tableEntities));
            $select->order(['StartDate']);
        }
        
        return clone $select;
    }
    
}