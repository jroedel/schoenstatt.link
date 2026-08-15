<?php
namespace Books\Model;

use SionModel\Db\Model\SionTable;
use Laminas\Db\Sql\Select;
use Laminas\Db\Adapter\AdapterInterface;
use BjyAuthorize\Provider\Resource\ProviderInterface as ResourceProviderInterface;
use Laminas\Permissions\Acl\Resource\GenericResource;
use voku\Html2Text\Html2Text;
use Schoenstatt\Filter\ToSchoenstattLinkIdentifier;
use Schoenstatt\Model\SchoenstattTable;
use SionModel\Service\ActingUserProviderInterface;
use voku\helper\UTF8;

class EventTextTable extends SionTable implements ResourceProviderInterface
{
    const TEXT_KIND_JK_TEXT = 'jk-text';

    const TEXT_KIND_OTHER = 'other';
    /**
     * @var array $config
     */
    protected $config;

    /**
     * @var string[] $usernames
     */
    protected $usernames;

    public function __construct(
        AdapterInterface $dbAdapter,
        $serviceLocator,
        ?ActingUserProviderInterface $actingUserProvider,
        array $config,
        $usernames
    ) {
        parent::__construct($dbAdapter, $serviceLocator, $actingUserProvider);
        $this->config = $config;
        $this->usernames = $usernames;
    }

    protected function getSelectPrototype($entity)
    {
        $select = parent::getSelectPrototype($entity);
        if ('text' === $entity) {
            $select->where(['TextKind' => self::TEXT_KIND_JK_TEXT]);
            $select->order(['LegacyFile']);
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
            $cacheKey .= '-' . $kind;
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
                if (! isset($tags[$tag])) {
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
            'titleIt'               => $row['TitleIt'],
            'titleFr'               => $row['TitleFr'],
            'zoom'                  => $row['Zoom'],
            'country'               => $row['Country'],
            'place'                 => $row['Place'],
            'originalLanguage'      => $row['OriginalLanguage'], //this should only apply to writings, speeches
            'slugEn'                => $row['SlugEn'],
            'slugEs'                => $row['SlugEs'],
            'slugDe'                => $row['SlugDe'],
            'slugPt'                => $row['SlugPt'],
            'slugIt'                => $row['SlugIt'],
            'slugFr'                => $row['SlugFr'],
            'wikidataSubjectId'     => $row['WikidataSubjectId'],
            'wikidataPropertyId'    => $row['WikidataPropertyId'],
            'wikidataLinkByDefault' => $this->filterDbBool($row['WikidataLinkByDefault']),
            'descriptionEn'         => $row['DescriptionEn'],
            'descriptionEs'         => $row['DescriptionEs'],
            'descriptionDe'         => $row['DescriptionDe'],
            'descriptionPt'         => $row['DescriptionPt'],
            'descriptionIt'         => $row['DescriptionIt'],
            'descriptionFr'         => $row['DescriptionFr'],
            'startDate'             => $this->filterDbDate($row['StartDate']),
            'startDatePrecision'    => $row['StartDatePrecision'],
            'duration'              => $this->filterDbInt($row['Duration']),
            'durationUnit'          => $row['DurationUnit'],
            'bestTextQuality'       => $row['BestTextQuality'],
            'tags'                  => $row['Tags'],
            'adminTags'             => $row['AdminTags'],
            'audienceText'          => $row['AudienceText'],
            'abbreviationEn'        => $row['AbbreviationEn'],
            'abbreviationEs'        => $row['AbbreviationEs'],
            'abbreviationDe'        => $row['AbbreviationDe'],
            'abbreviationPt'        => $row['AbbreviationPt'],
            'abbreviationIt'        => $row['AbbreviationIt'],
            'abbreviationFr'        => $row['AbbreviationFr'],
            'aclResourceId'         => $row['AclResourceId'],
            'url1' => 'Url1',
            'url1Label' => 'Url1Label',
            'url2' => 'Url2',
            'url2Label' => 'Url2Label',
            'url3' => 'Url3',
            'url3Label' => 'Url3Label',
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

        if (! isset($swFilter)) {
            $swFilter = new ToSchoenstattLinkIdentifier('text');
        }
        $identifier = $swFilter->filter($id);
        $title = $row['Title'];
        $slug = $row['Slug'];
        if (! isset($slug)) {
            $slug = SchoenstattTable::getSlug($title);
            $this->slylyUpdateTextSlug($id, $slug);
        }

        $legacyFile = $row['LegacyFile'];
        $filenamePlusTitle = '';
        if (isset($legacyFile)) {
            $filenamePlusTitle .= str_replace('.md', '', $legacyFile);
        }
        if (strlen($filenamePlusTitle) > 0 && $title !== $legacyFile) {
            $filenamePlusTitle .= ': ' . $title;
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
            'legacyFileDateModified' => $row['LegacyFileDateModified'],
            'updatedOn'             => $this->filterDbDate($row['UpdatedOn']),
            'updatedBy'             => $this->filterDbId($row['UpdatedBy']),
            'createdOn'             => $this->filterDbDate($row['CreatedOn']),
            'createdBy'             => $this->filterDbId($row['CreatedBy']),

            'identifier'            => $identifier,
            'createdByUsername'     => $createdByUsername,
            'filenamePlusTitle'     => $filenamePlusTitle,
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
        static $parsedown;
        static $html2Text;
        //generate slug
        if (! isset($data['slug']) && isset($data['title'])) {
            $data['slug'] = SchoenstattTable::getSlug($data['title']);
            //@todo check here that the slug doesn't exist, if it does try adding different numbers until it works
        }

        /*
         * Render the markdown, unless this row's HTML came from somewhere better.
         *
         * Two clauses, and the second one used to read `$entityData['kind']` — the kind of
         * the row being written. On a **create** there is no such row: createEntity() passes
         * `[]`, so PHP emitted `Undefined array key "kind"`, and because a create happens
         * before the redirect is sent, that warning reached the page ahead of the `Location`
         * header. The text was written and the moderator saw a 154-byte blank page, so
         * pressing submit again made a second text. Measured 2026-08-15.
         *
         * Keying on `legacyFile` instead of `kind` fixes that and one other thing.
         *
         * The clause exists because of `importJkTexts()` below, which reads paired `.md` and
         * `.html` files and must not have its imported HTML overwritten by Parsedown's
         * rendering of the same markdown — those are not the same document. Measured across
         * the corpus: regenerating would change the visible text of **2,740 of 2,756** rows,
         * some to twice the length. But `! isset($data['htmlText'])` already protects the
         * importer, which passes both. What the kind clause protected in addition was the
         * *edit* form, and there it did harm: every text is a jk-text now that the blog is
         * gone, so editing a text's markdown never regenerated its HTML and the form's main
         * field had no visible effect at all.
         *
         * `legacyFile` separates the two cases exactly, and the data says so rather than the
         * naming: all 2,753 imported rows carry one and all 4 rows authored in the app carry
         * none. So an imported text keeps the HTML it was imported with — byte-identical to
         * the behaviour above for every row that exists — while a text written here renders
         * its markdown, on create and on every later edit. `isset()` also means the missing
         * key on create is no longer a read at all.
         */
        if (isset($data['markdownText']) && ! isset($data['htmlText'])
            && ! isset($entityData['legacyFile'])
        ) {
            //generate HTML
            if (! isset($parsedown)) {
                $parsedown = new \Parsedown();
                $parsedown->setSafeMode(true);
            }
            $data['htmlText'] = $parsedown->text($data['markdownText']);

            //generate plain text
            if (! isset($html2Text)) {
                $html2Text = new Html2Text();
            }
            $html2Text->setHtml($data['htmlText']);
            $data['plainText'] = $html2Text->getText();
        }

        return $data;
    }

    /**
     * One ACL resource per distinct `AclResourceId` in `texts` — `txt_institute` and
     * `txt_public` today, which is what gates the Fr. Kentenich texts.
     *
     * Until the blog was removed this also bucketed `blog_post_*` ids under a
     * `blog_post` parent, for a per-author permission scheme. No row ever carried one:
     * every `AclResourceId` in the table is `txt_institute` or `txt_public`, so that
     * branch had never fired. It went with the feature.
     *
     * @return \Laminas\Permissions\Acl\Resource\GenericResource[]
     */
    public function getResources()
    {
        $cacheKey = 'event-text-resources';
        if (null !== ($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }

        $sql = "SELECT DISTINCT `AclResourceId` FROM `texts` ORDER BY AclResourceId";
        $results = $this->fetchSome(null, $sql, null);

        $return = [];
        foreach ($results as $row) {
            $resourceId = $this->filterDbString($row['AclResourceId']);
            if (isset($resourceId)) {
                $return[] = new GenericResource($resourceId);
            }
        }
        $this->cacheEntityObjects($cacheKey, $return, ['text']);
        return $return;
    }
}
