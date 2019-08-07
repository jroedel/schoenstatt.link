<?php
namespace Books\Model;

use SionModel\Db\Model\SionTable;
use Zend\Db\Sql\Select;
use Zend\Db\Adapter\AdapterInterface;
use BjyAuthorize\Provider\Resource\ProviderInterface as ResourceProviderInterface;
use BjyAuthorize\Provider\Rule\ProviderInterface as RuleProviderInterface;
use Zend\Permissions\Acl\Resource\GenericResource;
use Schoenstatt\Filter\BlogPostUserIdFilter;
use voku\Html2Text\Html2Text;
use Spatie\SchemaOrg\BlogPosting;
use Schoenstatt\Filter\ToSchoenstattLinkIdentifier;
use Schoenstatt\Model\SchoenstattTable;
use voku\helper\UTF8;

class EventTextTable extends SionTable implements
    ResourceProviderInterface,
    RuleProviderInterface
{
    //kind of text for blog posts
    const TEXT_KIND_BLOG = 'blog';
    //kind of text for auto-saving blog drafts
    const TEXT_KIND_BLOG_DRAFT = 'blog-draft';
    //kind of text for translating blog posts
    const TEXT_KIND_BLOG_TRANSLATION = 'blog-translation';
    
    const TEXT_KIND_JK_TEXT = 'jk-text';
    
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
            $select->where(['TextKind' => self::TEXT_KIND_JK_TEXT]);
            $select->order(['UpdatedOn' => Select::ORDER_DESCENDING]);
        } elseif ('blog-post' === $entity) {
            $select->where(['TextKind' => self::TEXT_KIND_BLOG]);
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
        static $swFilter;
        $id = $this->filterDbId($row['TextId']);
        $createdBy = $this->filterDbId($row['CreatedBy']);
        $createdByUsername = null;
        if (isset($createdBy) && isset($this->usernames[$createdBy])) {
            $createdByUsername = $this->usernames[$createdBy];
        }
        
        if (!isset($swFilter)) {
            $swFilter = new ToSchoenstattLinkIdentifier('text');
        }
        $identifier = $swFilter->filter($id);
        $title = $row['Title'];
        $slug = $row['Slug'];
        if (!isset($slug)) {
            $slug = SchoenstattTable::getSlug($title);
            $this->slylyUpdateTextSlug($id, $slug);
        }
        
        $processedRow = [
            'textId'                => $id,
            'title'                 => $title,
            'kind'                  => $row['TextKind'],
            'inLanguage'            => $row['Language'],
            'slug'                  => $slug,
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
            
            'identifier'            => $identifier,
            'createdByUsername'     => $createdByUsername,
        ];
        return $processedRow;
    }
    
    protected function slylyUpdateTextSlug($textId, $slug)
    {
        $gateway = $this->getTableGatewayForEntity('text');
        $result = $gateway->update(['Slug' => $slug], ['TextId' => $textId]);
        return $result;
    }
    
    /**
     * Process text data before putting into the database.
     * @param mixed[] $data
     * @param mixed[] $entityData
     * @return mixed[]
     */
    protected function preprocessText($data, $entityData, $action)
    {
        static $mdParser;
        static $html2Text;
        static $now;
        //generate slug
        if (!isset($data['slug']) && isset($data['title'])) {
            $data['slug'] = SchoenstattTable::getSlug($data['title']);
            //@todo check here that the slug doesn't exist, if it does try adding different numbers until it works
        }
        
        if (isset($data['markdownText']) && !isset($data['htmlText']) 
            && self::TEXT_KIND_JK_TEXT !== $entityData['kind']
        ) {
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
    
    /**
     * Import markdown and html files into the JK text db
     * @param boolean $simulate
     * @return string[][]|boolean[][]
     */
    public function importJkTexts($simulate = true)
    {
        $objects = $this->queryObjects('text', [
            'kind' => self::TEXT_KIND_JK_TEXT,
//             'markdownText' => null,
//             'htmlText' => null,
        ]);
        $results = [];
        foreach ($objects as $objectId => $object) {
            $filename = $object['legacyFile'];
            if (!isset($filename)) {
                continue;
            }
            $data = [];
            if (!isset($object['markdownText']) || !isset($object['htmlText']) || !isset($object['plainText'])) {
                //check if we have the md file,
                $filePath = 'data/texts/'.$filename;
                if (file_exists($filePath)) {
                    try {
                        //import it,
                        $file = fopen($filePath, "r");
                        $markdownText = fread($file,filesize($filePath));
                        //normalize utf8
                        $data['markdownText'] = UTF8::filter($markdownText);
                        fclose($file);
                        
                        $pathInfo = pathinfo($filePath);
                        $pathWithoutExtension = 'data/texts/'.$pathInfo['filename'];
                        
                        //also the html
                        $htmlFilePath = $pathWithoutExtension.'.html';
                        $file = fopen($htmlFilePath, "r");
                        $data['htmlText'] = fread($file,filesize($htmlFilePath));
                        fclose($file);
                        
                        //also the plain text for a better word count
                        $plainFilePath = $pathWithoutExtension.'.txt';
                        $file = fopen($plainFilePath, "r");
                        $data['plainText'] = fread($file,filesize($plainFilePath));
                        $data['wordCount'] = str_word_count($data['plainText']);
                        fclose($file);
                        
                        //@todo for search text: replace word chars, strip non word chars
                    } catch (\Exception $e) {
                        if (isset($this->logger)) {
                            $this->logger->err("Trying to import file `$filename`, but we were unsuccessful");
                        }
                    }
                }
            }
            
            if (!empty($data)) {
                if (false === $simulate) {
                    //looks like we've got some updating to do
                    $result = $this->updateEntity('text', $objectId, $data, [], false);
                    $data['result'] = $result;
                } else {
                    $data['result'] = 'simulated-update';
                }
                $results[$objectId] = $data;
            }
        }
        return $results;
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
