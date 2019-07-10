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
use Spatie\SchemaOrg\BlogPosting;

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
    
    protected function getSelectPrototype($entity)
    {
        $select = parent::getSelectPrototype($entity);
        if ('text' === $entity) {
            $select->order(['UpdatedOn' => Select::ORDER_DESCENDING]);
        } elseif ('event' === $entity) {
            $select->order(['StartDate']);
        }
        return $select;
    }
    
    /**
     * Returns a list of existing text tags according to the kind of text
     * @param string|array $kind
     * @return array
     */
    public function getTextTagsOptions($kind = null)
    {
        $cacheKey = 'text-tags';
        if (isset($kind)) {
            $cacheKey.='-'.$kind;
        }
        if (null !== ($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }
        $gateway = $this->getTableGateway('texts');
        $select = $this->getSelectPrototype('text');
        $select->columns(['Tags']);
        $select->group(['Tags']);
        $select->reset(Select::ORDER);
        if (isset($kind)) {
            $select->where(['TextKind' => $kind]);
        }
        $results = $gateway->selectWith($select)->toArray();
        $tags = [];
        foreach ($results as $row) {
            $theseTags = $this->filterDbArray($row['Tags']);
            foreach ($theseTags as $tag) {
                if (!isset($tags[$tag])) {
                    $tags[$tag] = $tag;
                }
            }
        }
        ksort($tags);
        $this->cacheEntityObjects($cacheKey, $tags, ['text']);
        return $tags;
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
    
    public static function getBlogPostSchema(array $textObject)
    {
        $schema = new BlogPosting();
        $schema->setProperty('headline', $textObject['title'])
        ->setProperty('inLanguage', $textObject['inLanguage'])
        ->setProperty('author', $textObject['createdByUsername'])
        ->setProperty('image', "https://schoenstatt.link/favicon-310.png")
        ->setProperty('articleBody', $textObject['plainText'])
        ->setProperty('publisher', ['@id' => '#identity'])
        ->setProperty('mainEntityOfPage', ['@id' => '#webpage']);
        if (isset($textObject['createdOn'])) {
            $schema->setProperty('datePublished', $textObject['createdOn']->format('Y-m-d'));
        }
        if (isset($textObject['updatedOn'])) {
            $schema->setProperty('dateModified', $textObject['updatedOn']->format('Y-m-d')); //? 'Y-m-d H:i:s'
        }
        //@todo add url
        if (!empty($textObject['tags'])) {
            $schema->setProperty('keywords', implode(',', $textObject['tags']));
        }
        return $schema;
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
