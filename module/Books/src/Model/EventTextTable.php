<?php
namespace Books\Model;

use SionModel\Db\Model\SionTable;
use Zend\Db\Sql\Where;

class EventTextTable extends SionTable
{
    
    public function __construct(AdapterInterface $dbAdapter, $serviceLocator, $actingUserId, array $config)
    {
        parent::__construct($dbAdapter, $serviceLocator, $actingUserId);
        $this->config = $config;
    }
    
    
    /**
     * Manipulate a database book row into a standardized row
     * @param array $row
     * @return array[]
     */
    protected function processEventRow($row)
    {
        $id = $this->filterDbId($row['EventId']);
        $processedRow = [
            'eventId'               => $id,
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
//            'abbreviationEs'        => $row['AbbreviationEs'],
            'abbreviationDe'        => $row['AbbreviationDe'],
//            'abbreviationPt'        => $row['AbbreviationPt'],
//            'abbreviationFr'        => $row['AbbreviationFr'],
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
    
    public function searchEvents($query, $options = [])
    {
        $queryParameters = [
            'title', 'search', //'startDate', 'endDate', 'period'
        ];
        $possibleOptions = ['maxResults', 'page', 'resultsPerPage'];
        
        $fieldMap = $this->getEntitySpecification('event')->updateColumns;
        
        $gateway = $this->getTableGateway('jk_events');
        $select = $this->getEventSelectPrototype();
        $where = new Where();
        
        //Prepare the libraryId predicate
//         $libraryClause = null;
//         if (isset($query['libraryId'])) {
//             if (is_array($query['libraryId'])) {
//                 $libaries = [];
//                 foreach ($query['libraryId'] as $value) {
//                     if (is_numeric($value) && !in_array($value, $libaries)) {
//                         $libaries[] = $value;
//                     }
//                 }
//                 if (count($libaries) === 1) {
//                     $query['libraryId'] = $libaries[0];
//                 } elseif (count($libraries) > 1) {
//                     $libraryClause = new In($fieldMap['libraryId'], $libaries);
//                 }
//             }
//             if (is_numeric($query['libraryId'])) {
//                 $libraryClause = new Operator($fieldMap['libraryId'], Operator::OPERATOR_EQUAL_TO, $query['libraryId']);
//             }
//         } elseif (isset($libraryId)) { //if the caller didn't specify a libraryId query param, set the current library
//             $libraryClause = new Operator($fieldMap['libraryId'], Operator::OPERATOR_EQUAL_TO, $libraryId);
//         }
//         if (isset($libraryClause)) {
//             $where->addPredicate($libraryClause, PredicateSet::OP_AND);
//         }
        
        //Prepare the search predicate
        if (isset($query['search'])) {
            $search = $query['search'];
            $searchLike = sprintf("%%%s%%",$search);
            $searchClause = new Predicate();
            $searchClause->addPredicates([
                new Like($fieldMap['titleEn'], $searchLike),
                new Like($fieldMap['titleEs'], $searchLike),
                new Like($fieldMap['titleDe'], $searchLike),
                new Like($fieldMap['titlePt'], $searchLike),
                new Like($fieldMap['titleFr'], $searchLike),
                //new Operator($fieldMap['withinLibraryId'], Operator::OPERATOR_EQUAL_TO, $search),
            ], PredicateSet::OP_OR);
            $where->addPredicate($searchClause);
        }
        
        // Prepare collectionId predicate, could be used to search for a period
//         if (isset($query['collectionId'])) {
//             $collectionIdClause = null;
//             if (is_array($query['collectionId'])) {
//                 $collections = [];
//                 foreach ($query['collectionId'] as $value) {
//                     if (is_numeric($value) && !in_array($value, $collections)) {
//                         $collections[] = $value;
//                     }
//                 }
//                 if (count($collections) === 1) {
//                     $query['collectionId'] = $collections[0];
//                 } elseif (count($collections) > 1) {
//                     $collectionIdClause= new In($fieldMap['collectionId'], $collections);
//                 }
//             }
//             if (is_numeric($query['collectionId'])) {
//                 $collectionIdClause= new Operator($fieldMap['collectionId'], Operator::OPERATOR_EQUAL_TO, $query['collectionId']);
//             }
//             if (isset($collectionIdClause)) {
//                 $where->addPredicate($collectionIdClause, PredicateSet::OP_AND);
//             }
//         }
        
        //Prepare category predicate
//         if (isset($query['category'])) {
//             $categoryClause = null;
//             if (is_array($query['category'])) {
//                 $categories = [];
//                 foreach ($query['category'] as $value) {
//                     if (0 !== strlen($value) && !in_array($value, $categories)) {
//                         $categories[] = $value;
//                     }
//                 }
//                 if (count($categories) === 1) {
//                     $query['category'] = $categories[0];
//                 } elseif (count($categories) > 1) {
//                     $categoryClause= new In($fieldMap['category'], $categories);
//                 }
//             }
//             if (is_string($query['category']) && 0 !== strlen($query['category'])) {
//                 $categoryClause = new Operator($fieldMap['category'], Operator::OPERATOR_EQUAL_TO, $query['category']);
//             }
//             if (isset($categoryClause)) {
//                 $where->addPredicate($categoryClause, PredicateSet::OP_AND);
//             }
//         }
        
        //Prepare title predicate
//         if (isset($query['title']) && 0 !== strlen($query['title'])) {
//             $search = $query['title'];
//             $searchLike = sprintf("%%%s%%",$search);
//             $titleClause = new Operator($fieldMap['title'], Operator::OPERATOR_EQUAL_TO, $query['title']);
//             $where->addPredicate($titleClause, PredicateSet::OP_AND);
//         }
        
        //Prepare isActive predicate, default to true unless caller sets it to null
//         if (!array_key_exists('isActive', $query) ||
//             (!is_bool($query['isActive']) && null !== $query['isActive'])
//         ) {
//             $query['isActive'] = true;
//         }
//         if (isset($query['isActive'])) {
//             $isActiveClause= new Operator($fieldMap['isActive'], Operator::OPERATOR_EQUAL_TO, $query['isActive']);
//             $where->addPredicate($isActiveClause, PredicateSet::OP_AND);
//         }
        
        //Set the where clause
        $select->where($where);
        
        $results = $gateway->selectWith($select);
        $entities = [];
//         $eventsToGrab = [];
        foreach ($results as $row) {
            $processedRow = $this->processBookRow($row);
//             if (isset($processedRow['currentCheckoutId'])) {
//                 $eventsToGrab[$processedRow['eventId']] = $processedRow['currentCheckoutId'];
//             }
            $entities[$processedRow['eventId']] = $processedRow;
        }
        
        //grab checkouts to fill them in to entities
//         $checkouts = $this->getCheckouts(array_values($eventsToGrab));
//         foreach ($eventsToGrab as $bookId => $checkoutId) {
//             if (isset($checkouts[$checkoutId])) {
//                 $entities[$bookId]['currentCheckout'] = $checkouts[$checkoutId];
//             }
//         }
        
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
//                'AbbreviationEs', 'AbbreviationPt', 'AbbreviationFr', 
                'AbbreviationDe', 'AclResourceId', 
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