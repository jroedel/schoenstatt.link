<?php
namespace Books\Model;

use SionModel\Db\Model\SionTable;
use Laminas\Db\Adapter\AdapterInterface;
use Cocur\Slugify\Slugify;
use Laminas\Db\ResultSet\ResultSetInterface;
use Books\Exception\DuplicateKeyException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Expression;
use SionModel\Service\ActingUserProviderInterface;
use SionModel\Service\EntitiesService;

class DictionaryTable extends SionTable
{

    const DICTIONARY_TITLE_FORMAT = "Fr. Kentenich dictionary German to %s";

    /**
     * @var UrlGeneratorInterface $urls
     */
    protected $urls;

    /** Scheme and host for the absolute URLs the schema.org projection carries. */
    protected string $canonicalBaseUrl;

    /**
     * The URL generator arrives as an argument. It used to be pulled out of the container this
     * constructor was handed, which is the pattern SionTable stopped supporting when it
     * stopped taking one: a data class asking a container for a router is a factory's job
     * done in the wrong place.
     *
     * @param array<string, mixed> $config
     */
    public function __construct(
        AdapterInterface $dbAdapter,
        EntitiesService $entities,
        array $config,
        ?ActingUserProviderInterface $actingUserProvider,
        UrlGeneratorInterface $urls,
        string $canonicalBaseUrl = ''
    ) {
        $this->urls             = $urls;
        $this->canonicalBaseUrl = rtrim($canonicalBaseUrl, '/');
        parent::__construct($dbAdapter, $entities, $config, $actingUserProvider);
    }

    protected function preprocessDictionaryEntry($data, $entityData, $action)
    {
        //calculate slug
        static $filter;
        if (! isset($data['key'])) {
            return $data;
        }
        if (! isset($filter)) {
            $filter = new Slugify();
        }
        $slug = $filter->slugify($data['key']);
        $data['slug'] = $slug;

        //check for a duplicate slug-locale
        if (self::ENTITY_ACTION_CREATE === $action
            && isset($data['slug'])
            && isset($data['locale'])
            && $this->doesDictionaryEntryAlreadyExist($data['slug'], $data['locale'])
        ) {
            throw new DuplicateKeyException('There is already a dictionary entry for given key and locale');
        }

        return $data;
    }

    protected function postprocessDictionaryEntry($data, $newEntityData, $action)
    {
        //update the links of all the same slug
        if (isset($data['links'])) {
            $gateway = $this->getTableGateway('sch_dictionary_entries');
            $gateway->update(['Links' => $this->formatDbArray($data['links'])], ['Slug' => $newEntityData['slug']]);
        }
    }

    /**
     * Check if there's a pre-existing slug-locale pair in the database
     * @param string $slug
     * @param string $locale
     * @return boolean
     */
    protected function doesDictionaryEntryAlreadyExist($slug, $locale)
    {
        $entitySpec = $this->getEntitySpecification('dictionary-entry');
        $tableName  = $entitySpec->tableName;
        $gateway    = $this->getTableGateway($tableName);
        $result     = $gateway->select(['Slug' => $slug, 'Locale' => $locale]);
        if (! $result instanceof ResultSetInterface || 0 === $result->count()) {
            return false;
        }
        return true;
    }

    protected function formatDictionaryEntrySchema($object)
    {
        //@todo also add a universal id to dictionary items
        $inLanguage = isset($object['inLanguage']) ? $object['inLanguage'] : null;

        $schema = new DefinedTerm();
        $schema->name($object['key'])
            ->description($object['entry']);
        $url = $this->getDictionaryUrl($inLanguage);
        if (isset($url)) {
            $schema->inDefinedTermSet($url);//"https://schoenstatt.link/en/dictionary/es"); //@todo universalize this
        }
        return $schema;
    }

    /**
     * Get the URL identifier for a particular language's dictionary
     * @param string $inLanguage
     * @return NULL|string
     */
    protected function getDictionaryUrl($inLanguage)
    {
        if (! isset($inLanguage) || '' === $this->canonicalBaseUrl) {
            return null;
        }

        //The **unprefixed** route deliberately: `dictionary/inLanguage` serves
        //`/dictionary/{inLanguage}`, its `.locale` twin serves `/{_locale}/dictionary/…`,
        //and this URL has never carried a locale segment. It is a schema.org
        //`inDefinedTermSet` — one public identifier for the term set, not one per language
        //the site is read in — so generating the twin here would silently split it five ways.
        //
        //The host is the canonical one rather than the request's, which is what
        //`Laminas\View\Helper\ServerUrl` used to supply: the identifier must not vary with
        //the host that happened to serve the page. In production the two are the same string.
        if (! isset($this->inLanguageUrls[$inLanguage])) {
            $this->inLanguageUrls[$inLanguage] = $this->canonicalBaseUrl
                . $this->urls->generate('dictionary/inLanguage', ['inLanguage' => $inLanguage]);
        }
        return $this->inLanguageUrls[$inLanguage];
    }

    /** @var array<string, string> memoized per language, as the static locals used to be */
    private array $inLanguageUrls = [];

    /**
     *
     * {@inheritDoc}
     * @see \SionModel\Db\Model\SionTable::processEntityRow()
     */
    protected function processEntityRow($entity, array $row)
    {
        if ('dictionary-entry' === $entity) {
            $locale = $row['Locale'];
            $inLanguage = \Locale::getPrimaryLanguage($locale);
            $data = [
                'entryId' => $this->filterDbInt($row['EntryId']),
                'key' => $row['KeyDe'],
                'slug' => $row['Slug'],
                'locale' => $locale,
                'inLanguage' => $inLanguage,
                'directTranslation' => $row['DirectTranslation'],
                'entry' => $row['Entry'],
                'isActive' => $this->filterDbBool($row['IsActive']),
                'links' => $this->filterDbArray($row['Links']),
            ];
            $schema = $this->formatDictionaryEntrySchema($data);
            $data['schema'] = $schema;
        } else {
            $data = parent::processEntityRow($entity, $row);
        }
        return $data;
    }

    public function getDictionarySchema($inLanguage)
    {
        if (! isset($inLanguage)) {
            throw new \InvalidArgumentException('Pass a language to get dictionary schema');
        }
        $url = $this->getDictionaryUrl($inLanguage);
        $languageName = $this->getLanguageName($inLanguage);
        $descriptionPattern = "Father Joseph Kentenich's terminology: German to %s";
        $description = sprintf($descriptionPattern, $languageName);
        $schema = new DefinedTermSet();
        $schema->identifier($url);
        if (isset($description)) {
            $schema->description($description);
        }
        return $schema;
    }

    public function getLinksValueOptions()
    {
        $cacheKey = 'links-value-options';
        if (null !== ($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }
        $objects = $this->getObjects('dictionary-entry');
        $result = [];
        foreach ($objects as $object) {
            $result[$object['slug']] = $object['key'];
        }
        $this->cacheEntityObjects($cacheKey, $result, ['dictionary-entry']);
        return $result;
    }

    /**
     * Returns an associative array mapping locale to an array of other information
     * including 2-digit ISO639 language codes
     * @return string[]
     */
    public function getAvailableDictionaryLanguages()
    {
        $cacheKey = 'available-dictionary-languages';
        if (null !== ($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }
        $select = $this->getSelectPrototype('dictionary-entry');
        $select->columns(['Locale', 'Count' => new Expression('COUNT(*)')])
            ->group(['Locale'])
            ->where(['IsActive' => '1'])
            ->reset(Select::ORDER);

        $gateway = $this->getTableGateway('sch_dictionary_entries');
        $results = $gateway->selectWith($select);
        $languageNames = $this->getLanguageNames();
        $dictionaries = [];

        foreach ($results as $row) {
            $locale = $row['Locale'];
            $inLanguage = \Locale::getPrimaryLanguage($locale);
            $dictionaries[$locale] = [
                'locale' => $locale,
                'inLanguage' => $inLanguage,
                'inLanguageName' => isset($languageNames[$inLanguage]) ? $languageNames[$inLanguage] : null,
                'count' => $this->filterDbInt($row['Count']),
            ];
        }
        $this->cacheEntityObjects($cacheKey, $dictionaries, ['dictionary-entry']);
        return $dictionaries;
    }

    /**
     *
     * {@inheritDoc}
     * @see \SionModel\Db\Model\SionTable::getSelectPrototype()
     */
    protected function getSelectPrototype($entity)
    {
        $select = parent::getSelectPrototype($entity);
        if ('dictionary-entry' === $entity) {
            $select->order(['IsActive' => Select::ORDER_DESCENDING, 'KeyDe']);
        }
        return $select;
    }
}
