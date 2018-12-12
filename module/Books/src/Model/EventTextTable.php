<?php
namespace Books\Model;

use SionModel\Db\Model\SionTable;
use Zend\Db\Sql\Where;
use Zend\Db\Sql\Predicate\Like;
use Zend\Db\Sql\Predicate\Predicate;
use Zend\Db\Sql\Predicate\PredicateSet;
use Zend\Db\Sql\Select;
use Zend\Db\Adapter\AdapterInterface;
use BjyAuthorize\Provider\Resource\ProviderInterface as ResourceProviderInterface;
use BjyAuthorize\Provider\Rule\ProviderInterface as RuleProviderInterface;
use Zend\Permissions\Acl\Resource\GenericResource;
use Schoenstatt\Filter\BlogPostUserIdFilter;
use Cocur\Slugify\Slugify;
use voku\Html2Text\Html2Text;
use Zend\Db\Sql\Predicate\Operator;
use Zend\Db\Sql\Predicate\In;

class EventTextTable extends SionTable implements
    ResourceProviderInterface,
    RuleProviderInterface
{
    //kind of text for blog posts
    const TEXT_KIND_BLOG = 'blog';
    //kind of text for saving blog drafts
    const TEXT_KIND_BLOG_DRAFT = 'blog-draft';
    //kind of text for translating blog posts
    const TEXT_KIND_BLOG_TRANSLATION = 'blog-translation';
    
    /**
     * @var array $config
     */
    protected $config;
    
    /**
     * @var string[] $usernames
     */
    protected $usernames;
    
    public function __construct(AdapterInterface $dbAdapter, $serviceLocator, $actingUserId, array $config, $usernames)
    {
        parent::__construct($dbAdapter, $serviceLocator, $actingUserId);
        $this->config = $config;
        $this->usernames = $usernames;
    }
    
    /**
     * Returns a list of existing text tags according to the kind of text
     * @param string|array $kind
     * @return array
     */
    public function getTextTagsOptions($kind = '')
    {
        return [];
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
//         $queryParameters = [
//             'title', 'search', //'startDate', 'endDate', 'period'
//         ];
//         $possibleOptions = ['maxResults', 'page', 'resultsPerPage'];
        
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
            $searchLike = sprintf("%%%s%%", $search);
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
//                     $categoryClaus$queryFullTexte= new In($fieldMap['category'], $categories);
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
    
    /**
     * Manipulate a database book row into a standardized row
     * @param array $row
     * @return array[]
     */
    protected function processTextRow($row)
    {
        $id = $this->filterDbId($row['TextId']);
        $createdBy = $this->filterDbId($row['CreatedBy']);
        $createdByUsername = null;
        if (isset($createdBy) && isset($this->usernames[$createdBy])) {
            $createdByUsername = $this->usernames[$createdBy];
        }
        $processedRow = [
            'textId'                => $id,
            'title'                 => $row['Title'],
            'kind'                  => $row['TextKind'],
            'inLanguage'            => $row['Language'],
            'slug'                  => $row['Slug'],
            'isDraft'               => $this->filterDbBool($row['IsDraft']),
            'markdownText'          => isset($row['MarkdownText']) ? $row['MarkdownText'] : null,
            'htmlText'              => isset($row['HtmlText']) ? $row['HtmlText'] : null,
            'plainText'             => isset($row['PlainText']) ? $row['PlainText'] : null,
            'wordCount'             => $this->filterDbInt($row['WordCount']),
            'jkTextQuality'         => $row['JkTextQuality'],
            'tags'                  => $this->filterDbArray($row['Tags']),
            'adminTags'             => $this->filterDbArray($row['AdminTags']),
            'aclResourceId'         => $row['AclResourceId'],
            'publicNotes'           => $row['PublicNotes'],
            'publicNotesUpdatedOn'  => $this->filterDbDate($row['PublicNotesUpdatedOn']),
            'publicNotesUpdatedBy'  => $this->filterDbId($row['PublicNotesUpdatedBy']),
            'adminNotes'            => $row['AdminNotes'],
            'adminNotesUpdatedOn'   => $this->filterDbDate($row['AdminNotesUpdatedOn']),
            'adminNotesUpdatedBy'   => $this->filterDbId($row['AdminNotesUpdatedBy']),
            'legacyEventId'         => $row['LegacyEventId'],
            'legacyFile'            => $row['LegacyFile'],
            'legacyPathDate'        => $row['LegacyPathDate'],
            'legacyFileDateModified'=> $row['LegacyFileDateModified'],
            'updatedOn'             => $this->filterDbDate($row['UpdatedOn']),
            'updatedBy'             => $this->filterDbId($row['UpdatedBy']),
            'createdOn'             => $this->filterDbDate($row['CreatedOn']),
            'createdBy'             => $this->filterDbId($row['CreatedBy']),
            
            'createdByUsername'     => $createdByUsername,
        ];
        return $processedRow;
    }
    
    /**
     * Process text data before putting into the database.
     * @param mixed[] $data
     * @param mixed[] $entityData
     * @return mixed[]
     */
    protected function preprocessText($data, $entityData, $action)
    {
        static $slugFilter;
        static $mdParser;
        static $html2Text;
        static $now;
        //generate slug
        if (!isset($data['slug']) && isset($data['title'])) {
            if (!isset($slugFilter)) {
                $slugFilter = new Slugify();
            }
            $data['slug'] = $slugFilter->slugify($data['title']);
            //@todo check here that the slug doesn't exist, if it does try adding different numbers until it works
        }
        
        if (isset($data['markdownText'])) {
            //generate HTML
            if (!isset($mdParser)) {
                $parsedown = new \Parsedown();
                $parsedown->setSafeMode(true);
            }
            $data['htmlText'] = $parsedown->text($data['markdownText']);
            
            //generate plain text
            if (!isset($html2Text)) {
                $html2Text = new Html2Text();
            }
            $html2Text->setHtml($data['htmlText']);
            $data['plainText'] = $html2Text->getText();
        }

        //generate resourceId when creating blog posts
        if (self::ENTITY_ACTION_CREATE === $action && !isset($data['resourceId'])) {
            $kind = isset($data['kind']) ? $data['kind'] : null;
            if (isset($kind) && self::TEXT_KIND_BLOG === $kind) {
                if (isset($this->actingUserId)) {
                    $userId = $this->actingUserId;
                    $data['resourceId'] = "blog_post_$userId";
                } else {
                    $data['resourceId'] = "blog_post";
                }
            }
        }
        
        //if changing blog post from draft to published, reset the creation date
        if (self::ENTITY_ACTION_UPDATE === $action
            && $entityData['isDraft']
            && isset($data['isDraft'])
            && !$data['isDraft']
        ) {
            if (!isset($now)) {
                $now = (new \DateTime(null, new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
            }
            $data['createdOn'] = $now;
        }
        
        return $data;
    }
    
    public function getTexts($query = [], $options = [])
    {
        $entitySpec = $this->getEntitySpecification('text');
        $fieldMap = $entitySpec->updateColumns;
        $gateway = $this->getTableGateway($entitySpec->tableName);
        $select = $this->getTextSelectPrototype();
        $where = new Where();
        
        $queryFullText = isset($options['fullText']) ? (bool)$options['fullText'] : false;
        if (!$queryFullText) {
            $columns = array_values($fieldMap);
            $columns = array_diff(
                $columns,
                [$fieldMap['markdownText'], $fieldMap['htmlText'], $fieldMap['plainText']]
            );
            $select->columns($columns);
        }
        if (isset($query['kind'])) {
            if (is_string($query['kind'])) {
                $kindClause = new Operator($fieldMap['kind'], Operator::OPERATOR_EQUAL_TO, $query['kind']);
            } elseif (is_array($query['kind'])) {
                $kindClause = new In($fieldMap['kind'], $query['kind']);
            }
            $where->addPredicate($kindClause);
        }
        
        if (isset($options['limit'])) {
            $select->limit($options['limit']);
        }
        
        $select->where($where);
        $results = $gateway->selectWith($select);
        
        $objects = [];
        foreach ($results as $row) {
            $processedRow = $this->processTextRow($row);
            $id = $processedRow['textId'];
            $objects[$id] = $processedRow;
        }
        
//         $this->cacheEntityObjects('unlinked-publications', $objects, ['publication']);
        return $objects;
    }
    
    /**
     *
     * @param int $id
     * @return mixed[]
     */
    public function getText($id)
    {
        static $gateway;
        if (!isset($gateway)) {
            $gateway = $this->getTableGateway('texts');
        }
        $select = $this->getTextSelectPrototype();
        $select->where(['TextId' => $id]);
        /** @var ResultSet $result */
        $result = $gateway->selectWith($select);
        $results = $result->toArray();
        
        if (!isset($results[0])) {
            return null;
        }
        $object = $this->processTextRow($results[0]);
//         $this->linkPublication($object);
        
        return $object;
    }
    
    /**
     * Get an array of publications
     * @param array $ids
     * @return array
     */
    public function getUnlinkedTexts(array $ids = [])
    {
        if (null !== ($cache = $this->fetchCachedEntityObjects('unlinked-texts'))) {
            return $cache;
        }
        $gateway = $this->getTableGateway('texts');
        $select = $this->getTextSelectPrototype();
        if (!empty($ids)) {
            $select->where(['TextId' => $ids]);
        }
        $results = $gateway->selectWith($select);
        
        $entities = [];
        foreach ($results as $row) {
            $processedRow = $this->processTextRow($row);
            $id = $processedRow['textId'];
            $entities[$id] = $processedRow;
        }
        
        $this->cacheEntityObjects('unlinked-texts', $entities, ['text']);
        return $entities;
    }
    /**
     * Get a standardized select object to retrieve records from the database
     * @return \Zend\Db\Sql\Select
     */
    protected function getTextSelectPrototype()
    {
        static $select;
        if (!isset($select)) {
            $entitySpec = $this->getEntitySpecification('text');
            $select = new Select($entitySpec->tableName);
            $fieldMap = $entitySpec->updateColumns;
            $select->columns(array_values($fieldMap));
            $select->order(['UpdatedOn' => Select::ORDER_DESCENDING]);
        }
        
        return clone $select;
    }
    
    /**
     * Create a resource for each blog user, in the format "blog_post_3", where 3 is the userId
     * Each resource will be a child of the "blog_post" resource
     * @return \Zend\Permissions\Acl\Resource\GenericResource[]
     */
    public function getResources()
    {
        $cacheKey = 'event-text-resources';
        if (null !== ($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }
        
        $sql = "SELECT DISTINCT `AclResourceId` FROM `texts` ORDER BY AclResourceId";
        $results = $this->fetchSome(null, $sql, null);
        
        $return = [
            'blog_post' => [],
        ];
        foreach ($results as $row) {
            $resourceId = $this->filterDbString($row['AclResourceId']);
            if (isset($resourceId)) {
                if (false !== strpos($resourceId, 'blog_post_')) {
                    $return['blog_post'][] = new GenericResource($resourceId);
                } else {
                    $return[] = new GenericResource($resourceId);
                }
            }
        }
        $this->cacheEntityObjects($cacheKey, $return, ['text']);
        return $return;
    }
    
    /**
     * {@inheritDoc}
     * @see \BjyAuthorize\Provider\Rule\ProviderInterface::getRules()
     * Format of the allow key is [['role1', 'role2'], 'resourceId', 'permission']
     */
    public function getRules()
    {
        $resources = $this->getResources();
        $allow = [ // blog admins can do whatever to whichever post
            [['blog_administrator'], 'blog_post', 'show'],
            [['blog_administrator'], 'blog_post', 'edit'],
            [['blog_administrator'], 'blog_post', 'delete'],
        ];
        if (!isset($resources['blog_post']) || !is_array($resources['blog_post']) || empty($resources['blog_post'])) {
            return ['allow' => $allow];
        }
        $blogPosts = $resources['blog_post'];
        $userIdFilter = new BlogPostUserIdFilter();
        
        foreach ($blogPosts as $postResource) {
            //check if post is public
            $allow[] = [['user'], $postResource, 'show'];
            
            //extract userId
            $userId = $userIdFilter->filter($postResource);
            
            if (is_numeric($userId)) {
                //set permissions for individual user
                $allow[] = [["user_$userId"], $postResource, 'show'];
            }
        }
        return ['allow' => $allow];
    }
}
