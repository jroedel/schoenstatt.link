<?php
namespace Schoenstatt\Model;

use SionModel\Filter\ToAscii;
use SionModel\Db\Model\SionTable;
use SionModel\Problem\EntityProblem;
use SionModel\Problem\ProblemProviderInterface;
use Zend\I18n\Translator\TranslatorInterface;
use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\TableGateway\TableGatewayInterface;
use Zend\Db\TableGateway\TableGateway;
use JUser\Model\PersonValueOptionsProviderInterface;
use BjyAuthorize\Provider\Resource\ProviderInterface as ResourceProviderInterface;
use Zend\Permissions\Acl\Resource\GenericResource;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Expression;
use Schoenstatt\Service\AssociationKindsService;
use SionModel\Db\GeoPoint;
use Zend\Validator\GpsPoint;
use JTranslate\Model\CountriesInfo;
use Spatie\SchemaOrg\Schema;
use Zend\Db\Sql\Predicate\PredicateSet;
use Schoenstatt\Filter\SchoenstattLinkIdentifier;
use GeoJson\Feature\Feature;
use GeoJson\Geometry\Point;
use GeoJson\Feature\FeatureCollection;
use Spatie\SchemaOrg\PropertyValue;
use Spatie\SchemaOrg\CatholicChurch;
use Schoenstatt\Validator\OpeningHoursSpecificationJson;
use Spatie\OpeningHours\OpeningHours;
use Zend\Json\Json;
use Zend\Validator\Timezone;
use Zend\Db\Sql\Predicate\IsNull;
use Zend\Db\Sql\Predicate\IsNotNull;
use Spatie\SchemaOrg\Place;
use Spatie\SchemaOrg\PlaceOfWorship;
use Schoenstatt\Filter\ToSchoenstattLinkIdentifier;
use Spatie\SchemaOrg\Organization;
use Schoenstatt\Validator\EventsJson;
use JTranslate\Model\TranslationsTable;
use Cocur\Slugify\Slugify;

class SchoenstattTable extends SionTable implements
    ProblemProviderInterface,
    PersonValueOptionsProviderInterface,
    ResourceProviderInterface
{
    const TRANSLATOR_DOMAIN = 'Schoenstatt';
    
    const LOCALES_TO_MD5_COLUMN_NAME = [
        'en_US' => 'SchemaOrgJsonMd5V1En',
        'es_ES' => 'SchemaOrgJsonMd5V1Es',
        'pt_BR' => 'SchemaOrgJsonMd5V1Pt',
        'de_DE' => 'SchemaOrgJsonMd5V1De',
        'it_IT' => 'SchemaOrgJsonMd5V1It',
    ];
    
    const LOCALES_TO_SLUG_COLUMN_NAME = [
        'en_US' => 'SlugEn',
        'es_ES' => 'SlugEs',
        'pt_BR' => 'SlugPt',
        'de_DE' => 'SlugDe',
        'it_IT' => 'SlugIt',
    ];
    
    const DEFAULT_PLACE_FORMAT = ':zip :cityState';

    const CATEGORY_DECEASED     = 'deceased';
    const CATEGORY_EXMEMBER     = 'ex-member';
    const CATEGORY_BISHOP       = 'bishop';
    const CATEGORY_PRIEST       = 'priest';
    const CATEGORY_DEACON       = 'deacon';
    const CATEGORY_OTHER        = 'other';

    const CATEGORY_PLURALS = [
        self::CATEGORY_BISHOP    => 'bishops',
        self::CATEGORY_PRIEST    => 'priests',
        self::CATEGORY_DEACON    => 'deacons',
        self::CATEGORY_DECEASED  => 'deceased',
        self::CATEGORY_EXMEMBER  => 'ex-members',
        self::CATEGORY_OTHER     => 'others',
    ];

    const TITLE_BISHOP = 'Most Rev.';
    const TITLE_PRIEST = 'Fr.';
    const TITLE_MONSIGNOR = 'Msgr.';
    const TITLE_DEACON = 'Dn.';
    const TITLE_BROTHER = 'Br.';
    const TITLE_SISTER = 'Sr.';
    const TITLE_DOCTOR = 'Dr.';
    const TITLE_PROFESSOR = 'Prof.';

    const PROBLEM_PERSON_NO_EMAIL = 'person-no-email';
    const PROBLEM_ASSOCIATION_NO_MAIN_ROLE = 'association-no-main-role';
    const PROBLEM_ASSOCIATION_MULTIPLE_MAIN_ROLE = 'association-multi-main-role';

    /**
     * This will be used upon creation of diocesan movements to autocreate league branches
     * @var array
     */
    const ADD_LEAGUE_KINDS = [
        'addShrineMinistry' => 'sch-shrine-ministry',
        'addPilgrimMovement' => 'sch-diocesan-pilgrim-movement',
        'addPilgrimMother' => 'sch-diocesan-pilgrim-mother',
        'addProfessionalsBranch' => 'sch-professionals-branch',
        'addMadrugadores' => 'sch-madrugadores-branch',
        'addWomensYouthBranch' => 'sch-young-womens-league-branch',
        'addMensYouthBranch' => 'sch-young-mens-league-branch',
        'addWomensBranch' => 'sch-womens-league-branch',
        'addMothersBranch' => 'sch-mothers-league-branch',
        'addMensBranch' => 'sch-mens-league-branch',
        'addFamilyBranch' => 'sch-family-league-branch',
    ];
    
    /**
     * Application config
     * @var mixed[]
     */
    protected $config;

    /**
     * An associative array mapping ISO language codes to locales
     * @var string[]
     */
    protected $languageLocaleMap;
    /**
     * Prototype to be cloned when specifying new problems
     * @var EntityProblem $entityProblemPrototype
     */
    protected $entityProblemPrototype;

    /**
     * @var TranslatorInterface $translator
     */
    protected $translator;
    
    /**
     * @var TranslationsTable $translationsTable
     */
    protected $translationsTable;

    protected $countryNameTranslations;

    /**
    * @var string $locale
    */
    protected $locale;

    /**
    * @var AssociationKind[] $associationKinds
    */
    protected $associationKinds;

    /**
    * @var CountriesInfo $countriesInfo
    */
    protected $countriesInfo;

    protected $countryLanguageMap = [];

    protected $addressFieldMap = [];

    /**
     * @var SchoenstattLinkIdentifier $swFilter
     */
    protected $swFilter;

    /**
     * @var \Schoenstatt\Validator\SchoenstattLinkIdentifier $swValidator
     */
    protected $swValidator;

    /**
     * @var \Schoenstatt\Validator\SchoenstattLinkIdentifier[] $swValidators
     */
    protected $swValidators = [];

    public function __construct(
        AdapterInterface $dbAdapter,
        $serviceLocator,
        $actingUserId,
        $config,
        $countriesInfo,
        $translator,
        TranslationsTable $translationsTable
    ) {
        parent::__construct($dbAdapter, $serviceLocator, $actingUserId);
        $this->config = $config;
        
        $this->languageLocaleMap = $config['slm_locale']['aliases'];

        /** @var AssociationKindsService $kindsService */
        $kindsService = $serviceLocator->get(AssociationKindsService::class);
        $this->associationKinds = $kindsService->getAssociationKinds();

        if (!$this->countryNameTranslations = $this->fetchCachedEntityObjects('country-name-translations')) {
            $this->countryNameTranslations = $countriesInfo->getCountryNameTranslations();
            $this->cacheEntityObjects('country-name-translations', $this->countryNameTranslations);
        }
        $this->countriesInfo = $countriesInfo;
        $this->addressFieldMap = [
            'street'    => 'streetAddress',
            'cityState' => 'addressLocality',
            'zip'       => 'postalCode',
            'country'   => 'addressCountry',
        ];
        $this->swFilter = new SchoenstattLinkIdentifier();
        $this->swValidator = new \Schoenstatt\Validator\SchoenstattLinkIdentifier();
        $this->translator = $translator;
        $this->translationsTable = $translationsTable;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \SionModel\Db\Model\SionTable::existsEntity()
     */
//     public function existsEntity($entity, $id)
//     {
//         if ('association' === $entity && is_string($id) && substr($id, 0, 2) === "SL") {
//             $id = $this->filterSwId($id, 'association');
//         }
//         return parent::existsEntity($entity, $id);
//     }
    
    /**
     * Validate and filter a site-wide identifier. Return false if it's not valid.
     *
     * @param string $swId
     * @param string $entityType
     * @return boolean|number
     */
    public function filterSwId($swId, $entityType = null)
    {
        //first validate
        if (!isset($entityType)) {
            if (!$this->swValidator->isValid($swId)) {
                return false;
            }
        } else {
            if (!isset($this->swValidators[$entityType])) {
                $this->swValidators[$entityType] = new \Schoenstatt\Validator\SchoenstattLinkIdentifier($entityType);
            }
            if (!$this->swValidators[$entityType]->isValid($swId)) {
                return false;
            }
        }

        //then filter
        $id = $this->swFilter->filter($swId);
        if ($id < 0) {
            return false;
        }
        return $id;
    }

    /**
     * Gets a simple key => value array
     * @param bool $includeInactive
     */
    public function getPersonValueOptions($includeInactive = false)
    {
        $persons = $this->getUnlinkedPersons();
        $result = [];
        foreach ($persons as $per) {
            if ($includeInactive || $per['isActive']) { //put it in
                if ($per['title']) {
                    $per['firstName'] = $per['title'].' '.$per['firstName'];
                }
                if ($per['lastName']) {
                    $result[$per['personId']] = $per['lastName'].', '.$per['firstName'];
                } else {
                    $result[$per['personId']] = $per['firstName'];
                }
            }
        }
        asort($result);
        return $result;
    }

    /**
     * Gets a simple key => value array of the generation
     * @param TranslatorInterface $translator
     * @param string $includeInactive
     * @param string $includeNonLifeLongMembership
     * @return string[]
     */
    public function getAssociationValueOptions($includeInactive = false, $includeNonLifeLongMembership = true)
    {
        $locale = $this->getLocale();
        $cacheKey = 'association-value-options-'
            .($includeInactive ? 'inactive-':'active-')
            .($includeNonLifeLongMembership ? 'life-':'nonlife-')
            .$locale;
        if (null !== ($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }
        $entities = $this->getObjects('association');
        $valueOptions = [];
        foreach ($entities as $entityId => $object) {
            if (!$includeNonLifeLongMembership && !$object['isLifeCommunity']) {
                continue;
            }
            if (!$includeInactive && !$object['isActive']) {
                continue;
            }
            $valueOptions[$entityId] = $object['nameByLocale'][$locale];
        }
        asort($valueOptions);
        $this->cacheEntityObjects($cacheKey, $valueOptions, ['association']);
        return $valueOptions;
    }

    /**
     * Gets a simple key => value array of the role titles
     * Must return even inactive role titles in case someone tries to edit an inactive one
     * @param TranslatorInterface $translator
     * @return mixed[]
     */
    public function getRoleTitleValueOptions()
    {
        $sql = "SELECT DISTINCT `RoleTitle` FROM `sch_roles` ORDER BY `RoleTitle`";
        $results = $this->fetchSome(null, $sql, null);
        $valueOptions = [];
        foreach ($results as $row) {
            $valueOptions[$row['RoleTitle']] = $row['RoleTitle'];
        }
//         asort($valueOptions);
        return $valueOptions;
    }

    /**
     * Each key of the return array will be an array of value options. The idea is that
     * as a user selects an associationId, the associationId will be used to lookup the
     * corresponding value option array.
     */
    public function getJavascriptRoleTitleValueOptions($includeInactive = false)
    {
        $roles = $this->getUnlinkedRoles();

        $valueOptions = [];
        foreach ($roles as $role) {
            if ($includeInactive || $role['isActive']) {
                if (isset($valueOptions[$role['associationId']])) {
                    if (!isset($valueOptions[$role['associationId']][$role['roleTitle']])) {
                        $valueOptions[$role['associationId']][$role['roleId']] = $role['roleTitle'];
                    }
                } else {
                    $valueOptions[$role['associationId']] = [
                        $role['roleId'] => $role['roleTitle'],
                    ];
                }
            }
        }
        return $valueOptions;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \SionModel\Db\Model\SionTable::getSelectPrototype()
     */
    protected function getSelectPrototype($entity)
    {
        $select = parent::getSelectPrototype($entity);
        if ('association' === $entity) {
            $entitySpec = $this->getEntitySpecification($entity);
            $columns = array_values($entitySpec->updateColumns);
            $columns = array_merge($columns, ['SchemaOrgJsonMd5V1En','SchemaOrgJsonMd5V1Es','SchemaOrgJsonMd5V1Pt','SchemaOrgJsonMd5V1De',
                'SchemaOrgJsonMd5V1It', 'SlugEn', 'SlugEs', 'SlugDe', 'SlugPt', 'SlugIt']);
            $columns['GeoPoint'] = new Expression('AsText(`Location`)');
//             $columns['TotalViews'] = new Expression('AsText(`Location`)');
            $select->columns($columns);
        }
        return $select;
    }
    
    public function getShrineGeoJson()
    {
        $objects = $this->getShrines();
        $features = [];
        $firstTime = true;
        foreach ($objects as $object) {
            /** @var GeoPoint $geoPoint */
            $geoPoint = $object['geoPoint'];
            if ($firstTime) {
                $firstTime = false;
            }
            if (isset($geoPoint)) {
                $geometry = new Point([(float)$geoPoint->longitude, (float)$geoPoint->latitude]);
                $feature = new Feature($geometry, [
                    'name' => $object['name'],
                ]);
                $features[] = $feature;
            }
        }
        $geoJson = new FeatureCollection($features);
        return $geoJson;
    }

    /**
     * @return mixed[]
     */
    public function getAssociations()
    {
        //don't cache, too heavy
        $objects = $this->getObjects('association');
        $this->linkAssociations($objects);
        return $objects;
    }

    /**
     * //@todo factor out this function
     * @param array $query
     * @param array $options
     * @return mixed[]
     */
    public function searchAssociations($query, $options = [])
    {
        $entities = $this->queryObjects('association', $query, $options);

        if (!isset($options['noLink']) || !$options['noLink']) {
            $this->linkAssociations($entities);
            $this->sortAssociationRowData($entities);
        }

        return $entities;
    }

    protected function linkAssociations(array &$objects)
    {
        $query = ['parentId' => array_keys($objects)];

        //collect list of "interesting" associationIds
        $interestingIds = []; //starting point
        foreach ($objects as $entityId => $object) {
            if (isset($object['parentId']) &&
                $object['parentId'] != $entityId
            ) {
                $interestingIds[] = $object['parentId'];
            }
        }
        if (!empty($interestingIds)) {
            $query['associationId'] = array_unique($interestingIds);
        }

        //search for all these publicationIds
        $results = $this->queryObjects(
            'association',
            $query, 
            ['orCombination' => true, 'noLink' => true]
            );

        //link parents of our objects
        foreach ($objects as $objectId => $object) {
            if (isset($object['parentId']) && isset($results[$object['parentId']])) {
                $objects[$objectId]['parent'] = &$results[$object['parentId']];
            }
        }
        
        //link children of our objects
        foreach ($results as $entityId => $entity) {
            if (isset($entity['parentId']) && isset($objects[$entity['parentId']])) {
                $objects[$entity['parentId']]['childAssociations'][$entityId] = &$results[$entityId];
            }
        }

        $this->connectEntityRolesAndAssignments('association', $objects);

        //no return, by ref
    }

    protected function sortAssociationRowData(&$results)
    {
        uasort($results, [self::class, 'associationCompare']);
    }

    protected static function associationCompare($a, $b)
    {
        if ($a['sort'] == $b['sort']) {
            return strcmp($a['name'], $b['name']);
        }
        return ($a['sort'] < $b['sort']) ? -1 : 1;
    }

    protected function processAssociationRow($row)
    {
        static $swFilter;
        static $swOldFilter;
        static $twitterUrlPattern;
        static $instagramUrlPattern;
        static $googlePlacePattern;
        static $urlLabelLogos;
        static $tzValidator;
        
        static $phrases;
        static $phrasesToIdMap;
        static $translationPhraseTranslationCounts;
        
        $id = $this->filterDbId($row['AssociationId']);
        if (!$this->translator instanceof TranslatorInterface) {
            throw new \Exception('No translator instance!');
        }
        $areCountryTranslationsReady = isset($this->countryNameTranslations);
        $country = $row['Country'];
        
        //@todo region should be stored in the table for easier sorting
        $countryInfo = $this->countriesInfo->getCountry($country);
        $countryRegion = isset($countryInfo) ? $countryInfo->region : null;

        $locale = $this->getLocale();
        $kind = $row['Kind'];
        if (!isset($this->associationKinds[$kind])) {
            throw new \Exception("Invalid association kind found for id $id");
        }
        $associationKindSpec = $this->associationKinds[$kind];

        if (!isset($swFilter)) {
            $swFilter = new ToSchoenstattLinkIdentifier('association');
        }
        if (!isset($swOldFilter)) {
            $swOldFilter = new ToSchoenstattLinkIdentifier('association', true);
        }
        $identifier = $swFilter->filter($id);
        $identifierPreApril2020 = $swOldFilter->filter($id);

        //process URLs
        $unprocessedUrls = [
            ['url' => $row['Url1'], 'label' => $row['Url1Label']],
            ['url' => $row['Url2'], 'label' => $row['Url2Label']],
            ['url' => $row['Url3'], 'label' => $row['Url3Label']],
        ];

        $facebookUrl = $row['FacebookUrl'];
        $twitterUser = $row['TwitterUser'];
        $instagramUser = $row['InstagramUser'];
        $googlePlaceId = $row['GooglePlaceId'];
        if (isset($facebookUrl)) {
            $unprocessedUrls[] = ['url' => $facebookUrl, 'label' => 'Facebook'];
        }
        if (isset($twitterUser)) {
            if (!isset($twitterUrlPattern)) {
                $twitterUrlPattern = "https://twitter.com/%s";
            }
            $unprocessedUrls[] = ['url' => sprintf($twitterUrlPattern, $twitterUser), 'label' => 'Twitter'];
        }
        if (isset($instagramUser)) {
            if (!isset($instagramUrlPattern)) {
                $instagramUrlPattern = "https://instagram.com/%s";
            }
            $unprocessedUrls[] = ['url' => sprintf($instagramUrlPattern, $instagramUser), 'label' => 'Instagram'];
        }
        if (isset($googlePlaceId)) {
            if (!isset($googlePlacePattern)) {
                $googlePlacePattern = "https://search.google.com/local/writereview?placeid=%s";
            }
            $unprocessedUrls[] = ['url' => sprintf($googlePlacePattern, $googlePlaceId), 'label' => 'Review'];
        }

        $urls = SionTable::processUrls($unprocessedUrls);
        if (!isset($urlLabelLogos)) {
            if (isset($this->config['schoenstatt']['url_map'])) {
                if (!is_array($this->config['schoenstatt']['url_map'])) {
                    throw new \Exception('url_map config should be an array');
                }
                $urlLabelLogos = [];
                foreach ($this->config['schoenstatt']['url_map'] as $urlConfig) {
                    if (isset($urlConfig['label']) && isset($urlConfig['logo'])) {
                        $urlLabelLogos[$urlConfig['label']] = $urlConfig['logo'];
                    }
                }
            }
        }
        if (is_array($urlLabelLogos) && !empty($urlLabelLogos)) {
            foreach ($urls as $key => $url) {
                if (isset($urlLabelLogos[$url['label']])) {
                    $urls[$key]['logo'] = $urlLabelLogos[$url['label']];
                }
            }
        }
        
        //urls to list as sameAs on schema
        $jsonSameAs = SionTable::processJsonUrls($unprocessedUrls, ['media', 'map']);
        
        $phones = [];
        $jsonTelephone = [];
        if (null !== ($phone1 = $row['Phone1'])) {
            $phones[] = [
                'number' => $phone1,
                'label' => null !== ($phone1Label = $row['Phone1Label'])
                    ? $phone1Label : 'Other',
            ];
            $jsonTelephone[] = $phone1;
        }
        if (null !== ($phone2 = $row['Phone2'])) {
            $phones[] = [
                'number' => $phone2,
                'label' => null !== ($phone2Label = $row['Phone2Label'])
                    ? $phone2Label : 'Other',
            ];
            if (!in_array($phone2, $jsonTelephone)) {
                $jsonTelephone[] = $phone2;
            }
        }
        if (null !== ($phone3 = $row['Phone3'])) {
            $phones[] = [
                'number' => $phone3,
                'label' => null !== ($phone3Label = $row['Phone3Label'])
                    ? $phone3Label : 'Other',
            ];
            if (!in_array($phone3, $jsonTelephone)) {
                $jsonTelephone[] = $phone3;
            }
        }
        if (0 === count($jsonTelephone)) {
            $jsonTelephone = null;
        } elseif (1 === count($jsonTelephone)) {
            $jsonTelephone = $jsonTelephone[0];
        }

        //abstract address elements
        $street1   = $row['Post1Street1'];
        $street2   = $row['Post1Street2'];
        $cityState = $row['Post1CityState'];
        $zip       = $row['Post1Zip'];

        $address = null;
        if (isset($street1) || isset($street2) || isset($cityState)) {
            //in case we need to put the streets on one line
            $streets = [];
            if (isset($street1)) {
                $streets[] = $street1;
            }
            if (isset($street2)) {
                $streets[] = $street2;
            }
            $address = [
                'street1'   => $street1,
                'street2'   => $street2,
                'street'    => implode(', ', $streets),
                'cityState' => $cityState,
                'zip'       => $zip,
                'country'   => $country,
            ];
        }
        
        if (!isset($phrasesToIdMap)) {
            $phrasesToIdMap = [];
            $translationPhraseTranslationCounts = [];
            if (!isset($this->translationsTable)) {
                throw new \Exception('Translation table is not properly configured.');
            }
            $phrases = $this->translationsTable->getTranslations();
            foreach ($phrases as $key => $phrase) {
                if (self::TRANSLATOR_DOMAIN === $phrase['textDomain']) {
                    $phrasesToIdMap[$phrase['phrase']] = $key;
                    $translationPhraseTranslationCounts[$key] = 0;
                    foreach ($this->languageLocaleMap as $localeMapped) {
                        if (isset($phrase[$localeMapped])) {
                            $translationPhraseTranslationCounts[$key]++;
                        }
                    }
                }
            }
        }
        
        $needTranslationCount = 0;
        $hasTranslationCount = 0;
        $name = $row['AssociationName'];
        $overrideNameFormat = $this->filterDbBool($row['OverrideNameFormat']);
        $isNameTranslateable = $this->filterDbBool($row['IsNameTranslateable']);
        $namesByLocale = [];
        $associationTranslationPhrases = [];
        
        $needsTranslation = (!$overrideNameFormat
            && $associationKindSpec->hasNameFormat()
            && $associationKindSpec->shouldTranslateNameParameter
            ) || $isNameTranslateable;
        
        if ($needsTranslation && !isset($this->countryNameTranslations[$name])) {
            $needTranslationCount += count($this->languageLocaleMap);
            if (isset($phrasesToIdMap[$name]) 
                && isset($translationPhraseTranslationCounts[$phrasesToIdMap[$name]])
            ) {
                $hasTranslationCount += $translationPhraseTranslationCounts[$phrasesToIdMap[$name]];
            }
        }
        
        //translate the name to each locale
        foreach ($this->languageLocaleMap as $localeMapped) {
            $tempName = null;
            if (!$overrideNameFormat && $associationKindSpec->hasNameFormat()) { //by format
                $token = $name;
                $tempToken = $token;
                if ($associationKindSpec->shouldTranslateNameParameter || $isNameTranslateable) {
                    if ($areCountryTranslationsReady &&
                        isset($this->countryNameTranslations[$token]) &&
                        isset($this->countryNameTranslations[$token][$localeMapped])
                    ) {
                        $tempToken = $this->countryNameTranslations[$token][$localeMapped];
                    } else {
                        if (isset($phrasesToIdMap[$token]) && !isset($associationTranslationPhrases[$token])) {
                            $associationTranslationPhrases[$phrasesToIdMap[$token]] = $token;
                        }
                        $tempToken = $this->translator->translate($token, self::TRANSLATOR_DOMAIN, $localeMapped);
                    }
                }
                $tempName = sprintf(
                    $associationKindSpec->nameFormatByLocale[$localeMapped], 
                    isset($tempToken) ? $tempToken : $token
                    );
            } else { //no name format
                if ($isNameTranslateable) {
                    if (isset($phrasesToIdMap[$name]) && !isset($associationTranslationPhrases[$name])) {
                        $associationTranslationPhrases[$phrasesToIdMap[$name]] = $name;
                    }
                    $tempName = $this->translator->translate($name, self::TRANSLATOR_DOMAIN, $localeMapped);
                } else {
                    $tempName = $name;
                }
            }
            $namesByLocale[$localeMapped] = $tempName;
        }

        $internalName = $row['InternalName'];
        $isInternalNameTranslateable = $this->filterDbBool($row['IsInternalNameTranslateable']);
        
        if ($isInternalNameTranslateable
            && isset($phrasesToIdMap[$internalName]) 
            && !isset($associationTranslationPhrases[$internalName])
        ) {
            $associationTranslationPhrases[$phrasesToIdMap[$internalName]] = $internalName;
            $needTranslationCount += count($this->languageLocaleMap);
            if (isset($translationPhraseTranslationCounts[$phrasesToIdMap[$internalName]])) {
                $hasTranslationCount += $translationPhraseTranslationCounts[$phrasesToIdMap[$internalName]];
            }
        }
        
        $internalNameByLocale = [];
        foreach ($this->languageLocaleMap as $localeMapped) {
            if (isset($internalName)) {
                if ($isInternalNameTranslateable) {
                    $tempName = $this->translator->translate(
                        $internalName, 
                        self::TRANSLATOR_DOMAIN, 
                        $localeMapped
                        );
                    $internalNameByLocale[$localeMapped] = $tempName;
                } else {
                    $internalNameByLocale[$localeMapped] = $internalName;
                }
            } else {
                //if we don't have an internal name, just use the association-provided name, without the name format
                if ($associationKindSpec->shouldTranslateNameParameter || $isNameTranslateable) {
                    $internalNameByLocale[$localeMapped] = $this->translator->translate(
                        $name,
                        self::TRANSLATOR_DOMAIN,
                        $localeMapped
                        );
                } else {
                    $internalNameByLocale[$localeMapped] = $name;
                }
                
            }
        }
        
        if (!isset($tzValidator)) {
            $tzValidator = new Timezone();
        }
        $timeZoneId = $row['TimeZone'];
        if (!$tzValidator->isValid($timeZoneId)) {
            $timeZoneId = null;
        }
        
        $publicNotes = $row['PublicNotes'];
        $publicNotesByLocale = [];
        foreach ($this->languageLocaleMap as $localeMapped) {
            if (isset($publicNotes)) {
                $publicNotesByLocale[$localeMapped] = $this->translator->translate(
                    $publicNotes, 
                    self::TRANSLATOR_DOMAIN, 
                    $localeMapped
                    );
            } else {
                $publicNotesByLocale[$localeMapped] = null;
            }
        }
        
        //photos
        $photos = [];
        $filenameBase = "/associations/shrine_images/".$identifier;
        $filename80 = $filenameBase.'-80px.jpg';
        if (file_exists("public".$filename80)) {
            $photos[] = [
                'original' => $filenameBase.'.jpg',
                '80px' => $filename80,
                '180px' => $filenameBase.'-180px.jpg',
                '400px' => $filenameBase.'-400px.jpg',
                '2000px' => $filenameBase.'-2000px.jpg',
            ];
        }
        for ($i = 1; $i <= 20; $i++) {
            $filenameOtherBase = $filenameBase.sprintf("-%02d", $i);
            $filename80 = $filenameOtherBase.'-80px.jpg';
            if (file_exists("public".$filename80)) {
                $photos[] = [
                    'original' => $filenameOtherBase.'.jpg',
                    '80px' => $filename80,
                    '180px' => $filenameOtherBase.'-180px.jpg',
                    '400px' => $filenameOtherBase.'-400px.jpg',
                    '2000px' => $filenameOtherBase.'-2000px.jpg',
                ];
            } else {
                break;
            }
        }
        
        $parentId = $this->filterDbId($row['Parent']);
        //@todo this shouldn't go here
        $parentJsonId = "https://schoenstatt.link/en/associations/"
            .(isset($parentId) ? $swFilter->filter($parentId) : null);
        
        $translationPoints = ($needTranslationCount === 0
            ? 3 : (floor((float)$hasTranslationCount / (float)$needTranslationCount * 3)));
        $score = (!empty($jsonSameAs) ? 2 : 0)
            + (!empty($photos) ? 1 : 0)
            + (isset($row['OpeningHoursHuman']) || isset($row['OpeningHoursSpecification']) 
                ? 2 : 0)
            + (isset($row['EventsHuman']) || isset($row['EventsJson'])
                ? 2 : 0)
            + $translationPoints;
        
        $slugByLocale = [];
        $missingSlugs = [];
        foreach (self::LOCALES_TO_SLUG_COLUMN_NAME as $localeMapped => $slugColumn) {
            $slug = $row[$slugColumn];
            if (!isset($slug)) {
                $slug = self::getSlug($namesByLocale[$localeMapped]);
                $missingSlugs[$slugColumn] = $slug;
            }
            $slugByLocale[$localeMapped] = $slug;
        }
        if (!empty($missingSlugs)) {
            $this->insertMissingAssociationSlugs($id, $missingSlugs);
        }
        
        $processedRow = [
            'associationId'         => $id,
            'identifier'            => $identifier,
            'identifierPreApril2020'=> $identifierPreApril2020,
            'name'                  => $name, //non-translated field
            'overrideNameFormat'    => $overrideNameFormat,
            'internalName'          => $internalName, //non-translated field
            'isInternalNameTranslateable' => $isInternalNameTranslateable,
            'parentId'              => $parentId,
            'kind'                  => $kind,
            'country'               => $country,
            'timeZoneId'            => $timeZoneId,
            
            'openingHoursHuman'         => $row['OpeningHoursHuman'],
            'openingHoursHumanUpdatedOn'=> $this->filterDbDate($row['OpeningHoursHumanUpdatedOn']),
            'openingHoursHumanUpdatedBy'=> $row['OpeningHoursHumanUpdatedBy'],
            'openingHoursSpecificationJson' => $row['OpeningHoursSpecification'],
            'openingHoursSpecificationJsonUpdatedOn' => $this->filterDbDate(
                $row['OpeningHoursSpecificationUpdatedOn']),
            'openingHoursSpecificationJsonUpdatedBy' => $row['OpeningHoursSpecificationUpdatedBy'],
            
            'eventsHuman'           => $row['EventsHuman'],
            'eventsHumanUpdatedOn'  => $this->filterDbDate($row['EventsHumanUpdatedOn']),
            'eventsHumanUpdatedBy'  => $row['EventsHumanUpdatedBy'],
            'eventsJson'            => $row['EventsJson'],
            'eventsJsonUpdatedOn'   => $this->filterDbDate($row['EventsJsonUpdatedOn']),
            'eventsJsonUpdatedBy'   => $row['EventsJsonUpdatedBy'],
            'foundationDate'        => $this->filterDbDate($row['FoundationDate']),
            'suppressionDate'       => $this->filterDbDate($row['SuppressionDate']),
            'isLifeCommunity'       => $this->filterDbBool($row['IsLifeCommunity']),
            'isNameTranslateable'   => $isNameTranslateable,
            'isAuthor'              => $this->filterDbBool($row['IsAuthor']),
            'isActive'              => $this->filterDbBool($row['IsActive']),

            'geoPoint'              => $this->filterDbGeoPoint($row['GeoPoint']),

            'sort'                  => $associationKindSpec->sort,
            'isSubDiocesan'         => $associationKindSpec->isSubDiocesanAssociation,
            'roles'                 => [],
            'assignments'           => [],
            'mainRole'              => null,
            'mainAssignment'        => null,
            'mainPerson'            => null,
            'mainContactRole'       => null,
            'mainContactAssignment' => null,
            'mainContactPerson'     => null,
            'childAssociations'     => [],
            'parent'                => null,
            'parentJsonId'          => $parentJsonId,
            /**
             * Contact fields
             */
            'email'                 => $this->filterEmailString($row['Email']),
//             'email2'                => $this->filterEmailString($row['Email2']),
            'emailsUpdatedOn'       => $this->filterDbDate($row['EmailsUpdatedOn']),
            'emailsUpdatedBy'       => $this->filterDbId($row['EmailsUpdatedBy']),
            'phone1'                => $phone1,
            'phone1Label'           => $row['Phone1Label'],
            'phone2'                => $phone2,
            'phone2Label'           => $row['Phone2Label'],
            'phone3'                => $phone3,
            'phone3Label'           => $row['Phone3Label'],
            'phonesUpdatedOn'       => $this->filterDbDate($row['PhonesUpdatedOn']),
            'phonesUpdatedBy'       => $this->filterDbId($row['PhonesUpdatedBy']),
            'url1'                  => $row['Url1'],
            'url1Label'             => $row['Url1Label'],
            'url2'                  => $row['Url2'],
            'url2Label'             => $row['Url2Label'],
            'url3'                  => $row['Url3'],
            'url3Label'             => $row['Url3Label'],
            'facebookUrl'           => $facebookUrl,
            'twitterUser'           => $twitterUser,
            'instagramUser'         => $instagramUser,
            'googlePlaceId'         => $googlePlaceId,
            'street1'               => $street1,
            'street2'               => $street2,
            'cityState'             => $cityState,
            'zip'                   => $zip,
            
            'contactInfoUpdatedOn'  => $this->filterDbDate($row['ContactInfoUpdatedOn']),
            'contactInfoUpdatedBy'  => $this->filterDbDate($row['ContactInfoUpdatedBy']),

            'publicNotes'           => $publicNotes,
            'publicNotesUpdatedOn'  => $this->filterDbDate($row['PublicNotesUpdatedOn']),
            'publicNotesUpdatedBy'  => $this->filterDbId($row['PublicNotesUpdatedBy']),
            'adminNotes'            => $row['AdminNotes'],
            'adminNotesUpdatedOn'   => $this->filterDbDate($row['AdminNotesUpdatedOn']),
            'adminNotesUpdatedBy'   => $this->filterDbId($row['AdminNotesUpdatedBy']),
            'createdOn'             => $this->filterDbDate($row['CreatedOn']),
            'createdBy'             => $this->filterDbId($row['CreatedBy']),
            'updatedOn'             => $this->filterDbDate($row['UpdatedOn']),
            'updatedBy'             => $this->filterDbId($row['UpdatedBy']),
            
            //@todo add V2 Jsons
            'schemaOrgJsonMd5V1En'  => $row['SchemaOrgJsonMd5V1En'],
            'schemaOrgJsonMd5V1Es'  => $row['SchemaOrgJsonMd5V1Es'],
            'schemaOrgJsonMd5V1Pt'  => $row['SchemaOrgJsonMd5V1Pt'],
            'schemaOrgJsonMd5V1De'  => $row['SchemaOrgJsonMd5V1De'],
            'schemaOrgJsonMd5V1It'  => $row['SchemaOrgJsonMd5V1It'],
            'schemaOrgJsonMd5V1ByLocale' => [
                'en_US' => $row['SchemaOrgJsonMd5V1En'],
                'es_ES' => $row['SchemaOrgJsonMd5V1Es'],
                'pt_BR' => $row['SchemaOrgJsonMd5V1Pt'],
                'de_DE' => $row['SchemaOrgJsonMd5V1De'],
                'it_IT' => $row['SchemaOrgJsonMd5V1It'],
            ],
            'slugByLocale' => $slugByLocale,
//             'jsonId'                => "https://schoenstatt.link/en/associations/".$identifier,
            'nameByLocale'          => $namesByLocale, //should never be null
            'internalNameByLocale'  => $internalNameByLocale, //should never be null
            'publicNotesByLocale'   => $publicNotesByLocale,
            'countryRegion'         => $countryRegion, //@todo move into table
            'address'               => $address,
            'resourceId'            => 'association_'.$id,
            'phones'                => $phones,
            'urls'                  => $urls,
            'jsonSameAs'            => $jsonSameAs,
            'translationPhrases'    => $associationTranslationPhrases,
            'needTranslationCount'  => $needTranslationCount,
            'hasTranslationCount'   => $hasTranslationCount,
            'dataScore'             => $score,
            'photos'                => $photos,
        ];
        return $processedRow;
    }

    public static function getSlug($text)
    {
        static $slugFilter;
        if (!isset($slugFilter)) {
            $slugFilter = new Slugify();
        }
        $slug = $slugFilter->slugify($text);
        if (strlen($slug) > 50) {
            $slug = trim(substr($slug, 0, 50), '-');
        }
        return $slug;
    }
    
    protected function insertMissingAssociationSlugs($associationId, $missingSlugs)
    {
        static $gateway;
        if (!isset($gateway)) {
            $gateway = $this->getTableGatewayForEntity('association');
        }
        $gateway->update($missingSlugs, ['AssociationId' => $associationId]);
    }
    
    public function nullOutAssociationSlugs($associationId)
    {
        $gateway = $this->getTableGatewayForEntity('association');
        $data = [];
        foreach (self::LOCALES_TO_SLUG_COLUMN_NAME as $column) {
            $data[$column] = null;
        }
        $gateway->update($data, ['AssociationId' => $associationId]);
    }
    
    /**
     *
     * @param mixed[] $object
     * @return \Spatie\SchemaOrg\Thing
     */
    public function getAssociationSchemaV1($object, $locale = null, $onlyBasicProperties = false)
    {
        static $markdownParser;
        static $openingHoursValidator;
        static $eventsJsonValidator;
        if (!isset($this->associationKinds[$object['kind']])) {
            throw new \Exception(sprintf("No known association kind `%s`", $object['kind']));
        }
        $schemaType = $this->associationKinds[$object['kind']]->schemaType;
        if (!isset($schemaType) || !class_exists($schemaType)) {
            throw new \Exception(sprintf("No schema type exists for association kind `%s`", $object['kind']));
        }
        if (!isset($locale)) {
            $locale = $this->getLocale();
        }
        $schema = new $schemaType;
        $jsonId = "https://schoenstatt.link/en/associations/".$object['identifierPreApril2020'];
        //@id
        $schema->setProperty('@id', $jsonId);
        $schema->setProperty('url', $jsonId);
        
        //name
        $name = $object['nameByLocale'][$locale];
        $schema->setProperty('name', $name);
        
        //return early if we just want the basics
        if ($onlyBasicProperties) {
            return $schema;
        }
        
        //alternateName
        $jsonAlternateName = [];
        foreach ($this->languageLocaleMap as $aLocale) {
            if ($object['nameByLocale'][$aLocale] !== $name
                && !in_array($object['nameByLocale'][$aLocale], $jsonAlternateName, TRUE)
            ) {
                $jsonAlternateName[] = $object['nameByLocale'][$aLocale];
            }
            if ($object['internalNameByLocale'][$aLocale] !== $name 
                && !in_array($object['internalNameByLocale'][$aLocale], $jsonAlternateName, TRUE)
            ) {
                $jsonAlternateName[] = $object['internalNameByLocale'][$aLocale];
            }
        }
        if (empty($jsonAlternateName)) {
            $jsonAlternateName = null;
        } else {
            sort($jsonAlternateName);
            $schema->setProperty('alternateName', $jsonAlternateName);
        }
        
        //disambiguatingDescription (used for internal name)
        $schema->setProperty('disambiguatingDescription', $object['internalNameByLocale'][$locale]);
        
        //description
        if (isset($object['publicNotesByLocale'][$locale])) {
            if (!isset($markdownParser)) {
                $markdownParser = new \Parsedown();
                $markdownParser->setSafeMode(true);
            }
            $descriptionText = $markdownParser->text($object['publicNotesByLocale'][$locale]);
            $schema->setProperty(
                'description', 
                $descriptionText
                );
        }
        
        //email
        if (isset($object['email'])) {
            $schema->setProperty('email', $object['email']);
        }
        
        //identifier
        $siteIdentifier = new PropertyValue();
        $siteIdentifier->propertyID('Schoenstatt Link ID')
        ->value($object['identifierPreApril2020']);
        $jsonIdentifier = [$siteIdentifier];
        if (isset($object['googlePlaceId'])) {
            $placeIdentifier = new PropertyValue();
            $placeIdentifier->propertyID('Google Maps Place ID')
            ->value($object['googlePlaceId']);
            $jsonIdentifier[] = $placeIdentifier;
        }
        $schema->setProperty('identifier', $jsonIdentifier);
        
        //location
        //@todo derive this code to kind configs
        if ('sch-shrine' === $object['kind']) {
            $location = new CatholicChurch();
            //mark shrines as free public places
            $location->isAccessibleForFree(true);
            $location->publicAccess(true);
        } elseif ('sch-wayside-shrine' === $object['kind']) {
            $location = new PlaceOfWorship();
            //mark shrines as free public places
            $location->isAccessibleForFree(true);
            $location->publicAccess(true);
        } else {
            $location = new Place();
        }
        $location->setProperty('@id', $jsonId.'#location');
        $location->setProperty('name', $name);
        
        //geo
        if (isset($object['geoPoint'])) {
            $jsonGeo = Schema::geoCoordinates();
            $jsonGeo->latitude($object['geoPoint']->latitude);
            $jsonGeo->longitude($object['geoPoint']->longitude);
            if (isset($object['country'])) {
                $jsonGeo->addressCountry($object['country']);
            }
            $location->geo($jsonGeo);
        }
        
        //openingHours
        $openingHours = null;
        if (!isset($openingHoursValidator)) {
            $openingHoursValidator = new OpeningHoursSpecificationJson();
        }
        if ($openingHoursValidator->isValid($object['openingHoursSpecificationJson'])) {
            try {
                $spec = Json::decode($object['openingHoursSpecificationJson'], Json::TYPE_ARRAY);
                if (isset($object['timeZoneId'])) {
                    $openingHours = OpeningHours::create($spec, $object['timeZoneId']);
                } else {
                    $openingHours = OpeningHours::create($spec);
                }
            } catch (\Exception $e) {}
            if (isset($openingHours)) {
                $format = isset($object['timeZoneId']) ? 'H:iP' : 'H:i';
                $location->setProperty('openingHoursSpecification', $openingHours->asStructuredData($format));
            }
        }
        
        //address
        if (isset($object['street1']) || isset($object['street2']) || isset($object['cityState'])) {
            $jsonAddress = Schema::postalAddress();
            if (isset($object['country'])) {
                $jsonAddress->setProperty('addressCountry', $object['country']);
            }
            if (isset($object['cityState'])) {
                $jsonAddress->setProperty('addressLocality', $object['cityState']);
            }
            if (isset($object['zip'])) {
                $jsonAddress->setProperty('postalCode', $object['zip']);
            }
            if (isset($object['street1']) || isset($object['street2'])) {
                $streets = [];
                if (isset($object['street1'])) {
                    $streets[] = $object['street1'];
                }
                if (isset($object['street2'])) {
                    $streets[] = $object['street2'];
                }
                $jsonAddress->setProperty('streetAddress', implode(', ', $streets));
            }
            $location->setProperty('address', $jsonAddress);
        }
        
        //foundingDate
        if (isset($object['foundationDate']) && $object['foundationDate'] instanceof \DateTimeInterface) {
            $dateString = $object['foundationDate']->format('Y-m-d');
            $schema->setProperty('foundingDate', $dateString);
        }
        
        //sameAs
        if (isset($object['jsonSameAs']) && !empty($object['jsonSameAs'])) {
            $schema->setProperty('sameAs', $object['jsonSameAs']);
        }
        
        //look for a mapUrl
        foreach ($object['urls'] as $url) {
            if ('map' === strtolower($url['label'])) {
                $location->hasMap($url['url']);
            }
        }
        $schema->setProperty('location', $location);
        
        // telephone & fax
        $fax = null;
        $telephone = [];
        foreach ($object['phones'] as $labelNumber) {
            if ('Fax' === $labelNumber['label']) {
                $fax = $labelNumber['number'];
            } else {
                $telephone[] = $labelNumber['number'];
            }
        }
        if (!empty($telephone)) {
            $schema->setProperty('telephone', $telephone);
        }
        if (isset($fax)) {
            $schema->setProperty('faxNumber', $fax);
        }
        
        //parentOrganization
        if (isset($object['parentId'])) {
            if (isset($object['parent'])) {
                $parent = $this->getAssociationSchemaV1($object['parent'], $locale, true);
            } else {
                $parent = new Organization();
                $parent->setProperty('@id', $object['parentJsonId']);
            }
            $schema->setProperty('parentOrganization', $parent);
        }
        
        //subOrganization
        if (isset($object['childAssociations'])) {
            $childAssociations = [];
            foreach ($object['childAssociations'] as $association) {
                $childAssociations[] = $this->getAssociationSchemaV1($association, $locale, true);
            }
            if (!empty($childAssociations)) {
                $schema->setProperty('subOrganization', $childAssociations);
            }
        }
    
        //event
        if (isset($object['eventsJson'])) {
            if (!isset($eventsJsonValidator)) {
                $eventsJsonValidator = new EventsJson();
            }
            if ($eventsJsonValidator->isValid($object['eventsJson'])) {
                $eventsJson = Json::decode($object['eventsJson'], Json::TYPE_ARRAY);
                $schema->event($eventsJson);
            }
        }
        
        //add human text
        $additionalProperties = [];
        if (isset($object['openingHoursHuman'])) {
            if (!isset($markdownParser)) {
                $markdownParser = new \Parsedown();
                $markdownParser->setSafeMode(true);
            }
            $openingHoursHuman = new PropertyValue();
            $openingHoursHuman->propertyID('Opening hours text')
            ->value($markdownParser->text($object['openingHoursHuman']));
            $additionalProperties[] = $openingHoursHuman;
        }
        if (isset($object['eventsHuman'])) {
            if (!isset($markdownParser)) {
                $markdownParser = new \Parsedown();
                $markdownParser->setSafeMode(true);
            }
            $eventsHuman = new PropertyValue();
            $eventsHuman->propertyID('Events text')
            ->value($markdownParser->text($object['eventsHuman']));
            $additionalProperties[] = $eventsHuman;
        }
        if (!empty($additionalProperties)) {
            $schema->setProperty('additionalProperty', $additionalProperties);
        }
        
        return $schema;
    }

    public function getAssociationListSchemaV1($objects, &$resultingMd5s, $locale = null)
    {
        if (!isset($locale)) {
            $locale = $this->getLocale();
        }
        $schemata = [];
        $resultingMd5s = [];
        foreach ($objects as $object) {
            $schema = $this->getAssociationSchemaV1($object, $locale);
            $jsonId = $schema->getProperty('@id');
            if (!isset($jsonId) || !is_string($jsonId)) {
                throw new \Exception("We didn't get a proper json Id");
            }
            $resultingMd5s[$jsonId] = $object['schemaOrgJsonMd5V1ByLocale'][$locale];
            $array = $schema->toArray();
            $schemata[] = $array;
        }
        return $schemata;
    }
    
    /**
     *
     * @param mixed[] $object
     * @return \Spatie\SchemaOrg\Thing
     */
    public function getAssociationSchemaV2($object, $locale = null, $onlyBasicProperties = false)
    {
        static $markdownParser;
        static $openingHoursValidator;
        static $eventsJsonValidator;
        if (!isset($this->associationKinds[$object['kind']])) {
            throw new \Exception(sprintf("No known association kind `%s`", $object['kind']));
        }
        $schemaType = $this->associationKinds[$object['kind']]->schemaType;
        if (!isset($schemaType) || !class_exists($schemaType)) {
            throw new \Exception(sprintf("No schema type exists for association kind `%s`", $object['kind']));
        }
        if (!isset($locale)) {
            $locale = $this->getLocale();
        }
        $schema = new $schemaType;
        $jsonId = "https://schoenstatt.link/en/".$object['identifier'];
        //@id
        $schema->setProperty('@id', $jsonId);
        $schema->setProperty('url', $jsonId);
        
        //name
        $name = $object['nameByLocale'][$locale];
        $schema->setProperty('name', $name);
        
        //return early if we just want the basics
        if ($onlyBasicProperties) {
            return $schema;
        }
        
        //alternateName
        $jsonAlternateName = [];
        foreach ($this->languageLocaleMap as $aLocale) {
            if ($object['nameByLocale'][$aLocale] !== $name
                && !in_array($object['nameByLocale'][$aLocale], $jsonAlternateName, TRUE)
                ) {
                    $jsonAlternateName[] = $object['nameByLocale'][$aLocale];
                }
                if ($object['internalNameByLocale'][$aLocale] !== $name
                    && !in_array($object['internalNameByLocale'][$aLocale], $jsonAlternateName, TRUE)
                    ) {
                        $jsonAlternateName[] = $object['internalNameByLocale'][$aLocale];
                    }
        }
        if (empty($jsonAlternateName)) {
            $jsonAlternateName = null;
        } else {
            sort($jsonAlternateName);
            $schema->setProperty('alternateName', $jsonAlternateName);
        }
        
        //disambiguatingDescription (used for internal name)
        $schema->setProperty('disambiguatingDescription', $object['internalNameByLocale'][$locale]);
        
        //description
        if (isset($object['publicNotesByLocale'][$locale])) {
            if (!isset($markdownParser)) {
                $markdownParser = new \Parsedown();
                $markdownParser->setSafeMode(true);
            }
            $descriptionText = $markdownParser->text($object['publicNotesByLocale'][$locale]);
            $schema->setProperty(
                'description',
                $descriptionText
                );
        }
        
        //email
        if (isset($object['email'])) {
            $schema->setProperty('email', $object['email']);
        }
        
        //identifier
        $siteIdentifier = new PropertyValue();
        $siteIdentifier->propertyID('Schoenstatt Link ID')
        ->value($object['identifier']);
        $jsonIdentifier = [$siteIdentifier];
        if (isset($object['googlePlaceId'])) {
            $placeIdentifier = new PropertyValue();
            $placeIdentifier->propertyID('Google Maps Place ID')
            ->value($object['googlePlaceId']);
            $jsonIdentifier[] = $placeIdentifier;
        }
        $schema->setProperty('identifier', $jsonIdentifier);
        
        //location
        //@todo derive this code to kind configs
        if ('sch-shrine' === $object['kind']) {
            $location = new CatholicChurch();
            //mark shrines as free public places
            $location->isAccessibleForFree(true);
            $location->publicAccess(true);
        } elseif ('sch-wayside-shrine' === $object['kind']) {
            $location = new PlaceOfWorship();
            //mark shrines as free public places
            $location->isAccessibleForFree(true);
            $location->publicAccess(true);
        } else {
            $location = new Place();
        }
        $location->setProperty('@id', $jsonId.'#location');
        $location->setProperty('name', $name);
        
        //geo
        if (isset($object['geoPoint'])) {
            $jsonGeo = Schema::geoCoordinates();
            $jsonGeo->latitude($object['geoPoint']->latitude);
            $jsonGeo->longitude($object['geoPoint']->longitude);
            if (isset($object['country'])) {
                $jsonGeo->addressCountry($object['country']);
            }
            $location->geo($jsonGeo);
        }
        
        //openingHours
        $openingHours = null;
        if (!isset($openingHoursValidator)) {
            $openingHoursValidator = new OpeningHoursSpecificationJson();
        }
        if ($openingHoursValidator->isValid($object['openingHoursSpecificationJson'])) {
            try {
                $spec = Json::decode($object['openingHoursSpecificationJson'], Json::TYPE_ARRAY);
                if (isset($object['timeZoneId'])) {
                    $openingHours = OpeningHours::create($spec, $object['timeZoneId']);
                } else {
                    $openingHours = OpeningHours::create($spec);
                }
            } catch (\Exception $e) {}
            if (isset($openingHours)) {
                $format = isset($object['timeZoneId']) ? 'H:iP' : 'H:i';
                $location->setProperty('openingHoursSpecification', $openingHours->asStructuredData($format));
            }
        }
        
        //address
        if (isset($object['street1']) || isset($object['street2']) || isset($object['cityState'])) {
            $jsonAddress = Schema::postalAddress();
            if (isset($object['country'])) {
                $jsonAddress->setProperty('addressCountry', $object['country']);
            }
            if (isset($object['cityState'])) {
                $jsonAddress->setProperty('addressLocality', $object['cityState']);
            }
            if (isset($object['zip'])) {
                $jsonAddress->setProperty('postalCode', $object['zip']);
            }
            if (isset($object['street1']) || isset($object['street2'])) {
                $streets = [];
                if (isset($object['street1'])) {
                    $streets[] = $object['street1'];
                }
                if (isset($object['street2'])) {
                    $streets[] = $object['street2'];
                }
                $jsonAddress->setProperty('streetAddress', implode(', ', $streets));
            }
            $location->setProperty('address', $jsonAddress);
        }
        
        //foundingDate
        if (isset($object['foundationDate']) && $object['foundationDate'] instanceof \DateTimeInterface) {
            $dateString = $object['foundationDate']->format('Y-m-d');
            $schema->setProperty('foundingDate', $dateString);
        }
        
        //sameAs
        if (isset($object['jsonSameAs']) && !empty($object['jsonSameAs'])) {
            $schema->setProperty('sameAs', $object['jsonSameAs']);
        }
        
        //look for a mapUrl
        foreach ($object['urls'] as $url) {
            if ('map' === strtolower($url['label'])) {
                $location->hasMap($url['url']);
            }
        }
        $schema->setProperty('location', $location);
        
        // telephone & fax
        $fax = null;
        $telephone = [];
        foreach ($object['phones'] as $labelNumber) {
            if ('Fax' === $labelNumber['label']) {
                $fax = $labelNumber['number'];
            } else {
                $telephone[] = $labelNumber['number'];
            }
        }
        if (!empty($telephone)) {
            $schema->setProperty('telephone', $telephone);
        }
        if (isset($fax)) {
            $schema->setProperty('faxNumber', $fax);
        }
        
        //parentOrganization
        if (isset($object['parentId'])) {
            if (isset($object['parent'])) {
                $parent = $this->getAssociationSchemaV1($object['parent'], $locale, true);
            } else {
                $parent = new Organization();
                $parent->setProperty('@id', $object['parentJsonId']);
            }
            $schema->setProperty('parentOrganization', $parent);
        }
        
        //subOrganization
        if (isset($object['childAssociations'])) {
            $childAssociations = [];
            foreach ($object['childAssociations'] as $association) {
                $childAssociations[] = $this->getAssociationSchemaV1($association, $locale, true);
            }
            if (!empty($childAssociations)) {
                $schema->setProperty('subOrganization', $childAssociations);
            }
        }
        
        //event
        if (isset($object['eventsJson'])) {
            if (!isset($eventsJsonValidator)) {
                $eventsJsonValidator = new EventsJson();
            }
            if ($eventsJsonValidator->isValid($object['eventsJson'])) {
                $eventsJson = Json::decode($object['eventsJson'], Json::TYPE_ARRAY);
                $schema->event($eventsJson);
            }
        }
        
        //add human text
        $additionalProperties = [];
        if (isset($object['openingHoursHuman'])) {
            if (!isset($markdownParser)) {
                $markdownParser = new \Parsedown();
                $markdownParser->setSafeMode(true);
            }
            $openingHoursHuman = new PropertyValue();
            $openingHoursHuman->propertyID('Opening hours text')
            ->value($markdownParser->text($object['openingHoursHuman']));
            $additionalProperties[] = $openingHoursHuman;
        }
        if (isset($object['eventsHuman'])) {
            if (!isset($markdownParser)) {
                $markdownParser = new \Parsedown();
                $markdownParser->setSafeMode(true);
            }
            $eventsHuman = new PropertyValue();
            $eventsHuman->propertyID('Events text')
            ->value($markdownParser->text($object['eventsHuman']));
            $additionalProperties[] = $eventsHuman;
        }
        if (!empty($additionalProperties)) {
            $schema->setProperty('additionalProperty', $additionalProperties);
        }
        
        return $schema;
    }
    
    public function getAssociationListSchemaV2($objects, &$resultingMd5s, $locale = null)
    {
        if (!isset($locale)) {
            $locale = $this->getLocale();
        }
        $schemata = [];
        $resultingMd5s = [];
        foreach ($objects as $object) {
            $schema = $this->getAssociationSchemaV2($object, $locale);
            $jsonId = $schema->getProperty('@id');
            if (!isset($jsonId) || !is_string($jsonId)) {
                throw new \Exception("We didn't get a proper json Id");
            }
            //@todo update with v2 jsons
            $resultingMd5s[$jsonId] = $object['schemaOrgJsonMd5V1ByLocale'][$locale];
            $array = $schema->toArray();
            $schemata[] = $array;
        }
        return $schemata;
    }
    
    /**
     * @todo we shouldn't need to query the whole table to get 1 association. Create a linkAssociation function
     * @param int $id
     * @return mixed[]
     */
    public function getAssociation($id)
    {
        $associations = $this->getAssociations();

        if (!isset($associations[$id]) || !($association = $associations[$id])) {
            return null;
        }

        return $association;
    }

    /**
     * Preprocess data bound for the database on the person entities or its sub-entities
     * @param array $data
     * @return array
     */
    protected function associationPreprocessor($data, $entityData, $action)
    {
        static $validator;
        if (!isset($data['geoPoint']) && isset($data['longitude']) && isset($data['latitude']) &&
            (0 != $data['longitude'] || 0 != $data['latitude'])
        ) {
            if (!isset($validator)) {
                $validator = new GpsPoint();
            }
            if ($validator->isValid($data['latitude'].','.$data['longitude'])) {
                $data['geoPoint'] = new GeoPoint($data['longitude'], $data['latitude']);
            }
        } elseif (isset($data['geoPoint']) && $data['geoPoint'] instanceof GeoPoint &&
            !isset($data['longitude']) && !isset($data['latitude'])
        ) {
            $data['longitude'] = $data['geoPoint']->longitude;
            $data['latitude'] = $data['geoPoint']->latitude;
        }
        if (isset($entityData['associationId'])) {
            $this->nullOutAssociationSlugs($entityData['associationId']);
        }
        return $data;
    }

    /**
     * Create association generation roles upon generation creation
     * @param mixed[] $data
     * @param mixed[] $newData
     * @param string $entityAction
     * @return number
     */
    protected function associationPostprocessor($data, $newData, $entityAction)
    {
        //if we're creating a diocesan movement, check if auto-creation of any league branches were requested
        if ($entityAction === SionTable::ENTITY_ACTION_CREATE) {
            if (!isset($newData['associationId'])) {
                throw new \Exception('There was an unexpectedly no associationId on a new association');
            }
            $this->createAssociatedRoles($newData['associationId'], $newData['kind']);
            if ($newData['kind'] === 'sch-diocesan-movement') {
                foreach ($data as $key => $value) {
                    if (isset(self::ADD_LEAGUE_KINDS[$key]) && $value) {
                        $branchData = [
                            'name' => $newData['name'],
                            'kind' => self::ADD_LEAGUE_KINDS[$key],
                            'country' => $newData['country'],
                            'parentId' => $newData['associationId'],
                        ];
                        $this->createEntity('association', $branchData);
                    }
                }
            }
        }
        //Set the MD5 sum
        $localeMd5Columns = self::LOCALES_TO_MD5_COLUMN_NAME;
        $data = [];
        foreach ($localeMd5Columns as $locale => $column) {
            $schema = $this->getAssociationSchemaV1($newData);
            $array = $schema->toArray();
            $md5 = md5(json_encode($array));
            $adapter = $this->getTableGateway('sch_associations');
            $data[$column] = $md5;
        }
        $adapter->update($data, ['AssociationId' => $newData['associationId']]);
    }

    /**
     * Update the SchemaOrgJsonMd5 with the latest schema digests
     */
    public function updateAssociationMd5s(array $associationIds = [])
    {
        $localeMd5Columns = self::LOCALES_TO_MD5_COLUMN_NAME;
        //@todo afterwards, do all associations, not just shrines
        $associations = $this->getAssociations();
        $return = [];
        foreach ($associations as $object) {
            $data = [];
            foreach ($localeMd5Columns as $locale => $column) {
                $schema = $this->getAssociationSchemaV1($object, $locale);
                $array = $schema->toArray();
                $md5 = md5(json_encode($array));
                $return[$object['associationId']] = $md5;
                $adapter = $this->getTableGateway('sch_associations');
                $data[$column] = $md5;
            }
            $adapter->update($data, ['AssociationId' => $object['associationId']]);
        }
        return $return;
    }
    
    public function autoFillTimeZones()
    {
        $objects = $this->queryObjects('association', [
            new PredicateSet([
                new IsNull('TimeZone'),
                new IsNotNull('Country')
                ])
        ]);
        $countryTzs = [];
        $count = count($objects);
        var_dump("$count associations are missing time zones");
        foreach ($objects as $object) {
            $country = $object['country'];
            if (isset($country) && 'GB-SCT' !== $country) {
                if (!isset($countryTzs[$country])) {
                    $countryTzs[$country] = 
                        array_keys(\Schoenstatt\Validator\TimeZone::getTimeZoneValueOptions($country));
                    if ('CL' === $country) {
                        var_dump($countryTzs[$country]);
                    }
                }
                if (1 === count($countryTzs[$country])) {
                    $tz = $countryTzs[$country][0];
                    var_dump("$country => $tz");
                    $this->updateEntity('association', $object['associationId'], [
                        'timeZoneId' => $tz
                    ]);
                }
            }
        }
    }

    /**
     * Get a list of all non-sub-diocesan associations in a country
     * @todo See if there's a way to adapt this for regions
     *
     * @param string $country
     * @return mixed[]
     */
    public function getNationalAssociations($country)
    {
        $associationConfig = $this->config['schoenstatt']['association_kinds'];
        $entities = $this->getAssociations();
        foreach ($entities as $entityId => $entity) {
            //don't include sub-diocesan associations
            if ($entity['country'] != $country) {
                unset($entities[$entityId]);
            } elseif (isset($associationConfig[$entity['kind']]) &&
                isset($associationConfig[$entity['kind']]['is_sub_diocesan_association']) &&
                true === $associationConfig[$entity['kind']]['is_sub_diocesan_association']
            ) {
                unset($entities[$entityId]);
            }
        }
        return $entities;
    }

    /**
     * Inserts new roles for a newly created entity
     * Returns the number of roles inserted
     * @param int $associationId
     * @param string $kind
     * @return int
     */
    public function createAssociatedRoles($associationId, $kind)
    {
        if (!isset($associationId) || (!is_int($associationId) && !is_numeric($associationId))) {
            throw new \InvalidArgumentException('Invalid argument passed to createAssociatedRoles.');
        }
        $associatedRoles = $this->getDefaultAssociatedRoles($kind);

        $i=0;
        foreach ($associatedRoles as $role) {
            if (!isset($role['roleTitle'])) {
                throw new \InvalidArgumentException('Default role for association kind '.$kind.' has no role title.');
            }
            $params = [
                'roleTitle'                 => $role['roleTitle'],
                'associationId'             => $associationId,
                'isMainRole'                => isset($role['isMainRole']) ? $role['isMainRole'] : false,
                'isMainContact'             => isset($role['isMainContact']) ? $role['isMainContact'] : false,
                'isSinglePosition'          => isset($role['isSinglePosition']) ? $role['isSinglePosition'] : false,
                'shouldAlwaysBeFilled'      => isset($role['shouldAlwaysBeFilled'])
                    ? $role['shouldAlwaysBeFilled'] : false,
                'sort'                      => isset($role['sort']) ? $role['sort'] : 100,
                'isActive'                  => true,
            ];

            $this->createEntity('role', $params);
            $i++;
        }
        return $i;
    }

    /**
     *
     * @return mixed[]
     */

    public function getPerson($id)
    {
        $persons = $this->getPersons();
        if (!isset($persons[$id]) || !($person = $persons[$id])) {
            return null;
        }
        return $person;
    }

    /**
     * Get a parsed array of all the persons in the database
     * Results are cached
     * Default order is deathDate, country, lastName
     * @return array
     */
    public function getPersons()
    {
        if ($cache = $this->fetchCachedEntityObjects('persons')) {
            return $cache;
        }
        $entities = $this->getUnlinkedPersons();
//         $this->connectEntityRolesAndAssignments('person', $entities);

        //Calculate if the person has any active assignments and set the hasActiveAssignment flag

        //connect spouses
        foreach ($entities as $personId => $person) {
            if (isset($person['spousePersonId']) && isset($entities[$person['spousePersonId']])) {
                $entities[$personId]['spousePerson'] = $entities[$person['spousePersonId']];
            }
        }
        $this->cacheEntityObjects('persons', $entities, ['person', 'assignent']);
        return $entities;
    }

    public function getUnlinkedPersons()
    {
        if ($cache = $this->fetchCachedEntityObjects('unlinked-persons')) {
            return $cache;
        }
        $sqlPers = "SELECT `PersonId`, `LastName`, `FirstName`, `LastNameWithoutAccents`,
`FirstNameWithoutAccents`, `PersonTags`, `LifeCommunity`, `Title`, `TitleAutomatic`, `Country`,
`BirthDate`, `NameDay`, `DeathDate`, `PublicNotes`, `PublicNotesUpdatedOn`, `PublicNotesUpdatedBy`,
`PersonalInfoUpdatedOn`, `PersonalInfoUpdatedBy`, `AdminTags`, `AdminNotes`, `AdminNotesUpdatedOn`,
`AdminNotesUpdatedBy`, `Email`, `Email2`, `EmailsUpdatedOn`, `EmailsUpdatedBy`, `CellPhone`,
`CellPhoneHasWhatsApp`, `Phone1`, `Phone1Label`, `Phone2`, `Phone2Label`, `Phone3`, `Phone3Label`,
`PhonesUpdatedOn`, `PhonesUpdatedBy`, `Url1`, `Url1Label`, `Url2`, `Url2Label`, `Url3`, `Url3Label`,
`FacebookUrl`, `SkypeUser`, `TwitterUser`, `InstagramUser`, `SlackUser`, `PostStreet1`, `PostStreet2`,
`PostCityState`, `PostZip`, `PostCountry`, `ContactNotes`, `ContactInfoUpdatedOn`,
`ContactInfoUpdatedBy`, `DataSource`, `DataSourceId`, `DataSourceUpdatedOn`, `UpdatedOn`,
`UpdatedBy`, `CreatedOn`, `CreatedBy`, `SpousePersonId`, `PriestDate`, `BishopDate`, `PrimaryLocale`,
`IsAuthor`, `IsBorrower` FROM `sch_persons` WHERE 1
ORDER BY `LastName`, `FirstName`";
        $results = $this->fetchSome(null, $sqlPers, null);
        if (!isset($results) || 0 == count($results)) {
            return null;
        }

        //sort list beforehand to not mess up the array key
        $sort = [];
        foreach ($results as $k => $v) {
            $sort['LastName'][$k] = $v['LastName'];
        }
        # sort by event_type desc and then title asc
        array_multisort($sort['LastName'], SORT_ASC, $results);

        $entities = [];
        $possibleLocales = ['en'=>'en_US', 'de'=>'de_DE','es'=>'es_ES','pt'=>'pt_BR', 'fr'=>'fr_FR', 'it' => 'it_IT'];
                           //real language conversions
        $language3to2Map = ['eng'=>'en','deu'=>'de','spa'=>'es','por'=>'pt', 'gsw'=>'de', 'fra'=>'fr',
            //similar language conversions
            'bar'=>'de',
            'pol'=>'de',
            'hun'=>'de',
            'ces'=>'de',
        ];
        $tz = new \DateTimeZone('UTC');
        $today = new \DateTime(null, $tz);
        foreach ($results as $row) {
            $id = $this->filterDbId($row['PersonId']);
            $deathDate = $this->filterDbDate($row['DeathDate']);
            $birthDate = $this->filterDbDate($row['BirthDate']);
            $nameDay = null;
            if (isset($row['NameDay']) && $row['NameDay'] != '0000-00-00') {
                try {
                    $nameDay = new \DateTime('1900'.substr($row['NameDay'], 4), $tz);
                } catch (\Exception $e) {
                    $nameDay = null;
                }
            }
            $age = null;
            if (is_object($birthDate)) {
                $interval = $today->diff($birthDate);
                $age = $interval->y;
            }

            //process URLs
            $unprocessedUrls = [
                ['url' => $row['Url1'], 'label' => $row['Url1Label']],
                ['url' => $row['Url2'], 'label' => $row['Url2Label']],
                ['url' => $row['Url3'], 'label' => $row['Url3Label']],
            ];
            $urls = SionTable::processUrls($unprocessedUrls);

            $today = new \DateTime(null, $tz);
            $isLiving = !isset($deathDate);
            //             $category = null;
            //             $condition = null;
            $personTags = $this->filterDbArray($row['PersonTags']);

            $title = null;
            $automaticTitle = $this->filterDbBool($row['TitleAutomatic']);
            $manualTitle = $row['Title'];
            if (!$automaticTitle) {
                $title = $manualTitle;
            } else {
                $personTagConfig = $this->config['schoenstatt']['person_tags'];
                //sort tag config according to sort order
                $sort = [];
                foreach ($personTagConfig as $k => $v) {
                    $sort['sort'][$k] = $v['sort'];
                }
                # sort by event_type desc and then title asc
                array_multisort($sort['sort'], SORT_ASC, $personTagConfig);

                $titles = [];
                foreach ($personTagConfig as $tag => $tagConfig) {
                    if (!isset($tagConfig['title'])) { //exclude tags without titles
                        continue;
                    }
                    if (in_array($tag, $personTags)) {
                        $titles[] = $tagConfig['title'];
                    }
                }
                if (!empty($titles)) {
                    $title = implode(' ', $titles);
                }
            }

            $lastName = $row['LastName'];
            $firstName = $row['FirstName'];
            $fullName = $firstName . ' ' . $lastName;
            $sort = strtoupper(substr($lastName.$firstName, 0, 4));

            $phones = [];
            if (null !== ($cellPhone = $row['CellPhone'])) {
                $phones[] = [
                    'number' => $cellPhone,
                    'label' => 'Main cell phone',
                    'whatsApp' => $this->filterDbBool($row['CellPhoneHasWhatsApp']),
                ];
            }
            if (null !== ($phone1 = $row['Phone1'])) {
                $phones[] = [
                    'number' => $phone1,
                    'label' => null !== ($phone1Label = $row['Phone1Label'])
                        ? $phone1Label : 'Other',
                ];
            }
            if (null !== ($phone2 = $row['Phone2'])) {
                $phones[] = [
                    'number' => $phone2,
                    'label' => null !== ($phone2Label = $row['Phone2Label'])
                        ? $phone2Label : 'Other',
                ];
            }
            if (null !== ($phone3 = $row['Phone3'])) {
                $phones[] = [
                    'number' => $phone3,
                    'label' => null !== ($phone3Label = $row['Phone3Label'])
                        ? $phone3Label : 'Other',
                ];
            }

            $address = [
                'street1'               => $row['PostStreet1'],
                'street2'               => $row['PostStreet2'],
                'cityState'             => $row['PostCityState'],
                'zip'                   => $row['PostZip'],
                'country'               => $row['PostCountry'],
            ];

            $country = $row['Country'];

            $primaryLocale = $row['PrimaryLocale'];
            //not a valid locale, find another
            if (!isset($primaryLocale) || !isset($possibleLocales[$primaryLocale]) && isset($country)) {
                if (isset($this->countryLanguageMap[$country])) {
                    $primaryLocale = $this->countryLanguageMap[$country];
                } else {
                    $countryInfo = $this->countriesInfo->getCountry($country);
                    if (isset($countryInfo) && is_object($countryInfo) && property_exists($countryInfo, 'languages')) {
                        $languages = $countryInfo->languages;
                        if (isset($languages) && is_object($languages)) {
                            foreach ($language3to2Map as $language3 => $language2) {
                                if (property_exists($languages, $language3)) {
                                    $primaryLocale = $possibleLocales[$language2];
                                    $this->countryLanguageMap[$country] = $primaryLocale;
                                    break;
                                }
                            }
                        }
                    }
                }
            }

            $entities[$id] = [
                'personId'                  => $id,
                'isAuthor'                  => $this->filterDbBool($row['IsAuthor']),
                'isBorrower'                => $this->filterDbBool($row['IsBorrower']),
                'updatedOn'                 => $this->filterDbDate($row['UpdatedOn']),
                'updatedBy'                 => $this->filterDbId($row['UpdatedBy']),
                'createdOn'                 => $this->filterDbDate($row['CreatedOn']),
                'createdBy'                 => $this->filterDbId($row['CreatedBy']),

                'isLiving'                  => $isLiving,
                'isActive'                  => $isLiving,
                'title'                     => $title, //this is a calculated field, not for updating
                'hasActiveAssignment'       => true, //assume so until proved otherwise
                'spousePerson'              => null,
                'assignments'               => [],
                'aclRoles'                  => [],
                'resourceId'                => 'person_'.$id,
                'sort'                      => $sort,

                /**
                 * Personal fields
                */
                'lastName'                  => $lastName,
                'firstName'                 => $firstName,
                'fullName'                  => $fullName,
                'searchName'                => $firstName.' '.$lastName, //@todo remove accents
//                 'searchName'                => $row['SearchName'],
                'firstNameWithoutAccents'   => $row['FirstNameWithoutAccents'],
                'lastNameWithoutAccents'    => $row['LastNameWithoutAccents'],
                'fullFriendlyName'          => $fullName,
                'spousePersonId'            => $this->filterDbId($row['SpousePersonId']),
                'personTags'                => $personTags,
                'automaticTitle'            => $automaticTitle,
                'country'                   => $country,
                'lifeCommunity'             => $this->filterDbId($row['LifeCommunity']),
                'manualTitle'               => $manualTitle,
                'primaryLocale'             => $primaryLocale,
                'priestDate'                => $this->filterDbDate($row['PriestDate']),
                'bishopDate'                => $this->filterDbDate($row['BishopDate']),
                'deathDate'                 => $deathDate,
                'birthDate'                 => $birthDate,
                'age'                       => $age,
                'nameDay'                   => $nameDay,

                'publicNotes'               => $row['PublicNotes'],
                'publicNotesUpdatedOn'      => $this->filterDbDate($row['PublicNotesUpdatedOn']),
                'publicNotesUpdatedBy'      => $this->filterDbId($row['PublicNotesUpdatedBy']),

                'personalInfoUpdatedOn'     => $this->filterDbDate($row['PersonalInfoUpdatedOn']),
                'personalInfoUpdatedBy'     => $this->filterDbDate($row['PersonalInfoUpdatedBy']),
                /**
                 * Contact fields
                */
                'email'                     => $this->filterEmailString($row['Email']),
                'email2'                    => $this->filterEmailString($row['Email2']),
                'emailsUpdatedOn'           => $this->filterDbDate($row['EmailsUpdatedOn']),
                'emailsUpdatedBy'           => $this->filterDbId($row['EmailsUpdatedBy']),
                'phones'                    => $phones,
                'cellPhone'                 => $cellPhone,
                'cellPhoneHasWhatsApp'      => $this->filterDbBool($row['CellPhoneHasWhatsApp']),
                'phone1'                    => $phone1,
                'phone1Label'               => $row['Phone1Label'],
                'phone2'                    => $phone2,
                'phone2Label'               => $row['Phone2Label'],
                'phone3'                    => $phone3,
                'phone3Label'               => $row['Phone3Label'],
                'phonesUpdatedOn'           => $this->filterDbDate($row['PhonesUpdatedOn']),
                'phonesUpdatedBy'           => $this->filterDbId($row['PhonesUpdatedBy']),
                'urls'                      => $urls,
                'url1'                      => $row['Url1'],
                'url1Label'                 => $row['Url1Label'],
                'url2'                      => $row['Url2'],
                'url2Label'                 => $row['Url2Label'],
                'url3'                      => $row['Url3'],
                'url3Label'                 => $row['Url3Label'],
                'facebookUrl'               => $row['FacebookUrl'],
                'skypeUser'                 => $row['SkypeUser'],
                'twitterUser'               => $row['TwitterUser'],
                'instagramUser'             => $row['InstagramUser'],
                'slackUser'                 => $row['SlackUser'],

                'address'                   => $address,

                'postStreet1'               => $address['street1'],
                'postStreet2'               => $address['street2'],
                'postCityState'             => $address['cityState'],
                'postZip'                   => $address['zip'],
                'postCountry'               => $address['country'],

                'contactNotes'              => $row['ContactNotes'],
//                 'contactNotesUpdatedOn'     => $this->filterDbDate($row['ContactNotesUpdatedOn']),
//                 'contactNotesUpdatedBy'     => $this->filterDbId($row['ContactNotesUpdatedBy']),

                'contactInfoUpdatedOn'      => $this->filterDbDate($row['ContactInfoUpdatedOn']),
                'contactInfoUpdatedBy'      => $this->filterDbDate($row['ContactInfoUpdatedBy']),

                /**
                 * Private info
                */
                'dataSource'                => $row['DataSource'],
                'dataSourceId'              => $this->filterDbId($row['DataSourceId']),
                'dataSourceUpdatedOn'       => $this->filterDbDate($row['DataSourceUpdatedOn']),
                'adminTags'                 => $this->filterDbArray(strtolower($row['AdminTags'])),
                'adminNotes'                => $row['AdminNotes'],
                'adminNotesUpdatedOn'       => $this->filterDbDate($row['AdminNotesUpdatedOn']),
                'adminNotesUpdatedBy'       => $this->filterDbId($row['AdminNotesUpdatedBy']),
            ];
        }
        $this->cacheEntityObjects('unlinked-persons', $entities, ['person']);
        return $entities;
    }

    /**
     *
     * @return mixed[]
     */

    public function getSimplePerson($id)
    {
        $persons = $this->getUnlinkedPersons();
        if (!isset($persons[$id]) || !($person = $persons[$id])) {
            return null;
        }
        return $person;
    }

    /**
     * Get all roles linked to their corresponding associations
     * @return mixed[]
     */
    public function getRoles()
    {
        $cacheKey = 'roles-'.$this->getLocale();
        if ($cache = $this->fetchCachedEntityObjects($cacheKey)) {
            return $cache;
        }
        $entities = $this->getUnlinkedRoles();
        $associations = $this->getObjects('association');
        foreach ($entities as $key => $role) {
            if ($role['associationId'] && isset($associations[$role['associationId']])) {
                $entities[$key]['association'] = $associations[$role['associationId']];
            } else {
                unset($entities[$key]);
            }
        }

        $this->cacheEntityObjects($cacheKey, $entities, ['role', 'association']);
        return $entities;
    }

    /**
     * Get role entities unlinked from their Association relations.
     * Entities come presorted by associationId, isActive, isMainRole, sort
     * @return mixed[][]
     */
    protected function getUnlinkedRoles()
    {
        $cacheKey = 'unlinked-roles-'.$this->getLocale();
        if ($cache = $this->fetchCachedEntityObjects($cacheKey)) {
            return $cache;
        }
        $sql = "SELECT `RoleId`, `RoleTitle`, `AssociationId`,
`IsMainRole`, `IsMainContact`, `IsSinglePosition`, `ShouldAlwaysBeFilled`, `Sort`, `IsActive`, `UpdatedOn`,
`UpdatedBy`, `CreatedOn`, `CreatedBy` FROM `sch_roles` WHERE 1
ORDER BY `AssociationId`, `IsActive` DESC, `IsMainRole` DESC, `Sort`";

        $results = $this->fetchSome(null, $sql, null);
        $isTranslatorReady = $this->translator instanceof TranslatorInterface;
        $entities = [];
        foreach ($results as $row) {
            $id = $this->filterDbId($row['RoleId']);
            $roleTitle = $row['RoleTitle'];
            if ($isTranslatorReady) {
                $formattedRoleTitle = $this->translator->translate($roleTitle, self::TRANSLATOR_DOMAIN);
            } else {
                $formattedRoleTitle = $roleTitle;
            }
            $entities[$id] = [
                'roleId'                    => $id,
                'roleTitle'                 => $roleTitle,
                'formattedRoleTitle'        => $formattedRoleTitle,
                'associationId'             => $this->filterDbId($row['AssociationId']),
                'sort'                      => $this->filterDbInt($row['Sort']),
                'isMainRole'                => $this->filterDbBool($row['IsMainRole']),
                'isMainContact'             => $this->filterDbBool($row['IsMainContact']),
                'isSinglePosition'          => $this->filterDbBool($row['IsSinglePosition']),
                'shouldAlwaysBeFilled'      => $this->filterDbBool($row['ShouldAlwaysBeFilled']),
                'isActive'                  => $this->filterDbBool($row['IsActive']),
                'createdOn'                 => $this->filterDbDate($row['CreatedOn']),
                'createdBy'                 => $this->filterDbId($row['CreatedBy']),
                'updatedOn'                 => $this->filterDbDate($row['UpdatedOn']),
                'updatedBy'                 => $this->filterDbId($row['UpdatedBy']),

                'association'               => null,
            ];
        }
        $this->cacheEntityObjects($cacheKey, $entities, ['role']);
        return $entities;
    }
    /**
     * Get a role entity by id
     * @param int $id
     * @return mixed[]
     */
    public function getRole($id)
    {
        $roles = $this->getRoles();

        if (!isset($roles[$id]) || !($role = $roles[$id])) {
            return null;
        }

        return $role;
    }

    /**
     * Return an array of default roles associated with a kind of association
     * @param string $associationKind
     * @return mixed[]
     */
    public function getDefaultAssociatedRoles($associationKind)
    {
        if (!isset($this->associationKinds[$associationKind])) {
            return [];
        }
        return $this->associationKinds[$associationKind]->defaultRoles;
    }


    /**
     * Get a list of assignments which also includes all associations and persons
     * who aren't represented in the active assignments. The list is sorted by
     * association.
     * @return mixed[]
     */
    public function getAssignmentPersonAssociations()
    {
        $cacheKey = 'assignment-person-associations'.$this->getLocale();
        if ($cache = $this->fetchCachedEntityObjects($cacheKey)) {
            return $cache;
        }
        /*
         * New plan: We'll use getAssignments as a base, and then:
         * 1. reimplement sorting
         * 2. Include active associations who were left out
         * 3. Include active persons who where left out
         */
        $entities       = $this->getAssignments();
        $associations   = $this->getObjects('association');
        $persons        = $this->getUnlinkedPersons();

        //first mark the "found" persons and associations in assignments
        foreach ($entities as $assignment) {
            $persons[$assignment['personId']]['found'] = true;
            $associations[$assignment['associationId']]['found'] = true;
        }

        //these are the associations without assignments
        foreach ($associations as $associationId => $association) {
            if (!isset($association['found']) && $association['isActive']) {
                $newAssignment = $this->getAssignmentPrototype();
                $newAssignment['associationId'] = $associationId;
                $newAssignment['association'] = $association;
                $newAssignment['associationSort'] = $association['sort'];
                $entities[] = $newAssignment;
            }
        }

        //these are the persons without assignments
        foreach ($persons as $personId => $person) {
            if (!isset($person['found']) && $person['isActive']) {
                $newAssignment = $this->getAssignmentPrototype();
                $newAssignment['personId'] = $personId;
                $newAssignment['person'] = $person;
                $newAssignment['personSort'] = $person['sort'];
                $entities[] = $newAssignment;
            }
        }
        $sort = [];
        foreach ($entities as $k => $v) {
            $sort['associationSort'][$k] = $v['associationSort'];
            $sort['personSort'][$k] = $v['personSort'];
        }
        # sort by event_type desc and then title asc
        array_multisort($sort['associationSort'], SORT_ASC, $sort['personSort'], SORT_ASC, $entities);
        $this->cacheEntityObjects($cacheKey, $entities, ['assignment', 'role', 'association', 'person']);
        return $entities;
    }

    /**
     * Get a blank assignment array
     * @return mixed[]
     */
    private function getAssignmentPrototype()
    {
        static $prototype;
        if (!isset($prototype)) {
            $prototype = [
                'assignmentId'          => null,
                'roleId'                => null,
                'roleTitle'             => null,
                'associationId'         => null,
                'startDate'             => null,
                'endDate'               => null,
                'isMainRole'            => false,
                'isMainContact'         => false,
                'isSinglePosition'      => false,
                'shouldAlwaysBeFilled'  => false,
                'roleIsActive'          => true,
                'personId'              => null,
                'createdOn'             => null,
                'createdBy'             => null,
                'updatedOn'             => null,
                'updatedBy'             => null,
                'formattedRoleTitle'    => null,

                'association'           => null,
                'person'                => null,

                'isActive'              => true,
                'associationSort'       => '9999ZZZZ',
                'personSort'            => 'ZZZZ',
            ];
        }
        return $prototype;
    }

    /**
     * @return mixed[]
     */
    public function getAssignments()
    {
        $cacheKey = 'assignments'.$this->getLocale();
        if (null !== ($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }
        $entities       = $this->getUnlinkedAssignments();
        $persons        = $this->getUnlinkedPersons();
        $associations   = $this->getObjects('association');
        $locale = $this->getLocale();

        foreach ($entities as $assignmentId => $assignment) {
            if (!isset($persons[$assignment['personId']]) ||
                !isset($associations[$assignment['associationId']])
            ) {
                unset($entities[$assignmentId]);
            } else {
                $entities[$assignmentId]['person'] = $persons[$assignment['personId']];
                $entities[$assignmentId]['personSort'] = $persons[$assignment['personId']]['sort'];
                $entities[$assignmentId]['association'] = $associations[$assignment['associationId']];
                $entities[$assignmentId]['associationSort'] =
                    $this->strPad($associations[$assignment['associationId']]['sort'], 4, '0', STR_PAD_LEFT).
                    $associations[$assignment['associationId']]['nameByLocale'][$locale];
            }
        }

        $this->cacheEntityObjects('assignments', $entities, ['assignment']);
        return $entities;
    }

    protected function getUnlinkedAssignments(array $ids = [], $locale = null)
    {
        if (!isset($locale)) {
            $locale = $this->getLocale();
        }
        $cacheKey = 'unlinked-assignments'.$locale;
        if (null !== ($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }
        $sql = "SELECT a.`AssignmentId`, a.`RoleId`, a.`PersonId`,
a.`StartDate`, a.`EndDate`, a.`CreatedOn`, a.`CreatedBy`, a.`UpdatedOn`, a.`UpdatedBy`,
r.`RoleTitle`, r.`AssociationId`, r.`IsMainRole`, r.`IsSinglePosition`, r.`Sort`, r.`IsActive`,
r.`ShouldAlwaysBeFilled`, r.`IsMainContact`
FROM `sch_assignments` a
INNER JOIN `sch_roles` r ON a.`RoleId` = r.`RoleId` WHERE 1
ORDER BY `AssociationId`, `IsActive` DESC, `IsMainRole` DESC, `Sort`";

        $results = $this->fetchSome(null, $sql, null);
        $entities = [];
        foreach ($results as $row) {
            $object = $this->processAssignmentRow($row);
            $entities[$object['assignmentId']] = $object;
        }
        $this->cacheEntityObjects($cacheKey, $entities, ['assignment', 'association']);
        return $entities;
    }
    
    protected function processAssignmentRow($row, $locale = null)
    {
        static $swFilter;
        if (!isset($locale)) {
            if (isset($this->locale)) {
                $locale = $this->locale;
            } else {
                $locale = $this->getLocale();
            }
        }
        $id = $this->filterDbId($row['AssignmentId']);
        $startDate = $this->filterDbDate($row['StartDate']);
        $endDate = $this->filterDbDate($row['EndDate']);
        $isActive = $this::areWeWithinDateRange($startDate, $endDate);
        $roleTitle = $row['RoleTitle'];
        $formattedRoleTitle = $this->translator->translate($roleTitle, self::TRANSLATOR_DOMAIN, $locale);
        
        $associationId = $this->filterDbId($row['AssociationId']);
        if (!isset($swFilter)) {
            $swFilter = new ToSchoenstattLinkIdentifier('association');
        }
        $associationIdentifier = $swFilter->filter($associationId);
        
        $object = [
            'assignmentId'          => $id,
            'roleId'                => $this->filterDbId($row['RoleId']),
            'roleTitle'             => $roleTitle,
            'associationId'         => $associationId,
            'startDate'             => $startDate,
            'endDate'               => $endDate,
            'isMainRole'            => $this->filterDbBool($row['IsMainRole']),
            'isMainContact'         => $this->filterDbBool($row['IsMainContact']),
            'isSinglePosition'      => $this->filterDbBool($row['IsSinglePosition']),
            'shouldAlwaysBeFilled'  => $this->filterDbBool($row['ShouldAlwaysBeFilled']),
            'roleSort'              => $this->filterDbInt($row['Sort']),
            'roleIsActive'          => $this->filterDbBool($row['IsActive']),
            'personId'              => $this->filterDbId($row['PersonId']),
            'createdOn'             => $this->filterDbDate($row['CreatedOn']),
            'createdBy'             => $this->filterDbId($row['CreatedBy']),
            'updatedOn'             => $this->filterDbDate($row['UpdatedOn']),
            'updatedBy'             => $this->filterDbId($row['UpdatedBy']),
            
            'associationIdentifier' => $associationIdentifier,
            'formattedRoleTitle'    => $formattedRoleTitle,
            'isActive'              => $isActive,
            'association'           => null,
            'person'                => null,
        ];
        return $object;
    }

    /**
     *
     * @param int $id
     * @return mixed[]
     */
    public function getAssignment($id)
    {
        $assignments = $this->getAssignments();

        if (!isset($assignments[$id]) || !($assignment = $assignments[$id])) {
            return null;
        }

        return $assignment;
    }

    /**
     * Criteria keys are: search(text), country(text),
     *     roleTitle(string), associationKind(string),
     *     onlyMainRoles(bool=false, allows associations without a main role to be returned),
     *     includeInactive(bool=false, when false, won't return any inactive associations or assignments),
     *     onlyAssignments(bool=false),
     *     showPeopleWithoutActiveAssignment(bool=false)
     * The 'search' query key will search (case-insensitive) the following fields:
     *
     * @param array $query

     * @todo we still need to include tag, country searches
     * @todo make sure that we normalize (remove accented things from
     *   fields search for with the 'search' criterion
     */
    public function searchEntities($query, $options = [])
    {
        /*
         * How do I do this?
         * * I normally assume everyone is inocent until I find them guilty of not complying with
         *   the search criteria
         * * I should probably do a calculation at the beginning of acceptingOnlyPersons,
         *   acceptingOnlyAssociations
         *
         * * acceptingMerePersons: !associationKind && !associationCountry && !roleTitle
         * * acceptingMereAssociations: !roleTitle
         */
        $locale = $this->getLocale();
        $onlyMainRoles = isset($query['onlyMainRoles']) && is_bool($query['onlyMainRoles'])
            ? $query['onlyMainRoles'] : false;
        $includeInactive = isset($query['includeInactive']) && is_bool($query['includeInactive'])
            ? $query['includeInactive'] : false;
        $onlyAssignments = (isset($query['onlyAssignments']) && is_bool($query['onlyAssignments'])
                ? $query['onlyAssignments'] : false) || $onlyMainRoles;
        $showPeopleWithoutActiveAssignment = isset($query['showPeopleWithoutActiveAssignment'])
            && is_bool($query['showPeopleWithoutActiveAssignment'])
            ? $query['showPeopleWithoutActiveAssignment'] : false;

        $bypassRequiredParams = isset($options['bypassRequiredParams'])
            ? (bool)$options['bypassRequiredParams'] : false;

        $oneOfRequiredParams = ['search', 'associationKind', 'associationCountry',
            'personName', 'roleTitle'
        ];
        //get rid of unnecesary parameters
        $realParamCount = 0;
        foreach ($query as $key => $value) {
            if (!isset($value) || $value === '') {
                unset($query[$key]);
            } elseif (in_array($key, $oneOfRequiredParams)) {
                $realParamCount++;
            }
        }
        if ($realParamCount == 0 && !$bypassRequiredParams) {
            return null;
        }

        $filter = new ToAscii();
        if (isset($query['search'])) {
            $query['search'] = $filter->filter($query['search']);
        }

        //roleTitle param should be an array
        if (isset($query['roleTitle']) && is_string($query['roleTitle'])) {
            $query['roleTitle'] = [$query['roleTitle']];
        } elseif (isset($query['roleTitle']) && !is_array($query['roleTitle'])) {
            throw new \InvalidArgumentException('Role title query param should be a string or an array.');
        }

        $searchSearchField = isset($query['search']) && isset($query['search']) && is_string($query['search']);
        $searchAssociationKind = isset($query['associationKind']) && is_string($query['associationKind']);
        $searchAssociationCountry = isset($query['associationCountry']) && is_string($query['associationCountry']);
        $searchRoleTitle = isset($query['roleTitle']) && is_array($query['roleTitle']);
        $searchPersonName = isset($query['personName']) && isset($query['personName'])
            && is_string($query['personName']);

        $acceptingMerePersons = !$onlyAssignments && !$searchAssociationCountry && !$searchAssociationKind
            && !$searchRoleTitle;

        $acceptingMereAssociations = !$onlyAssignments && !$searchPersonName && !$searchRoleTitle;

//         var_dump($query);

        $assignments = $this->getAssignmentPersonAssociations();
        if (!isset($assignments)) {
            return [];
        }
/*
 * Returning assignments: This is easy since it's the most specific. If any criterion doesn't
 *  match, we'll reject the assignment and see if we can match the row as a person or association.
 *
 * Returning associations: We happen to know that assignments come in order by assignmentId. When we
 *  find an association that didn't match as an assignment (and wasn't already entered as another
 *  assignment), we'll cache it until the association changes. At that point we know we can shift it
 *  onto the return array.
 *
 * Returning person: I don't know that this is necessary. I can't think of a use case right now.
 *
 * Note: The concept of returning an association or a person without an assignment only comes into play
 *  when we use a criterion that can only apply to a person/association (like onlyMainRoles)
 */
        /**
         * The list of final results
         * @var array $results
         */
        $results = [];
        /**
         * A list of personIds already added to the final results
         * @var array $personsReturned
         */
        $personsReturned = [];
        /**
         * A list of associationIds already added to the final results
         * @var array $associationsReturned
         */
        $associationsReturned = [];
        /**
         * A list of association candidates to check at the end of the main loop
         * and add them to the search results if they haven't already been checked
         * off in the associationsReturned list
         * @var array $maybeAssociations
         */
        $maybeAssociations = [];
        /**
         * Analogous to maybeAssociations
         * @var array $maybePersons
         */
        $maybePersons = [];
        /**
         * A flag, reset every loop, which signals that an assignment criterion has failed
         * @var string $hasAssignmentFailed
         */
        $hasAssignmentFailed = false;
        /**
         * Similar to $hasAssignmentFailed
         * @var string $hasPersonFailed
         */
        $hasPersonFailed = false;
        /**
         * Similar to $hasAssignmentFailed
         * @var string $hasAssociationFailed
         */
        $hasAssociationFailed = false;

        foreach ($assignments as $assignment) {
            $hasAssignmentFailed = false;
            $hasPersonFailed = false;
            $hasAssociationFailed = false;
            //This a special type of test for when a criterion is enough to oust a mere person/association,
            //but not a full assignment
            $hasMerePersonFailed = false;
            $hasMereAssociationFailed = false;

            $isFullAssignment = isset($assignment['assignmentId']);
            $isMerePerson = !isset($assignment['assignmentId']) && isset($assignment['person']);
            $isMereAssociation = !isset($assignment['assignmentId']) && isset($assignment['association']);

            //0. Categorical criteria: just continue

            //0.1 search field criteria
            if ($searchSearchField &&
                (!isset($assignment['association']) ||
                    (false === stripos($assignment['association']['name'], $query['search']) &&
                    false === stripos($assignment['association']['nameByLocale'][$locale], $query['search']))) &&
                (!isset($assignment['roleTitle']) || false === stripos($assignment['roleTitle'], $query['search'])) &&
                (!isset($assignment['formattedRoleTitle'])
                    || false === stripos($assignment['formattedRoleTitle'], $query['search'])) &&
                (!isset($assignment['person'])
                    || (false === stripos($assignment['person']['searchName'], $query['search'])))
            ) {
                continue;
            }

            //1. AssignmentCriteria

            //1.1 isActive
            if ($isFullAssignment && !$includeInactive && !$assignment['isActive']
            ) {
                $hasAssignmentFailed = true;
            }

            //1.2 roleTitle
            if ($searchRoleTitle &&
                !in_array($assignment['roleTitle'], $query['roleTitle'])
            ) {
                $hasAssignmentFailed = true;
            }

            //1.3 mainRole
            if (($onlyMainRoles && !$assignment['isMainRole'])) {
                $hasAssignmentFailed = true;
            }

            //if we're only looking for full assignments, and it's failed, continue
            if ($onlyAssignments && $hasAssignmentFailed) {
                continue;
            }

            //2. AssociationCriteria

            //2.1 isActive
            if (!$isMerePerson && !$includeInactive && isset($assignment['association'])
                && !empty($assignment['association']) &&
                !$assignment['association']['isActive']
            ) {
                $hasAssociationFailed = true;
            }
            //2.2 association kind
            if ($searchAssociationKind &&
                $query['associationKind'] != $assignment['association']['kind']
            ) {
                $hasAssociationFailed = true;
            }
            //2.3 association country
            if ($searchAssociationCountry &&
                $query['associationCountry'] != $assignment['association']['country']
            ) {
                $hasAssociationFailed = true;
            }
            //2.4 search field criteria
            if (!$isMerePerson && $searchSearchField &&
                false === stripos($assignment['association']['name'], $query['search']) &&
                false === stripos($assignment['association']['nameByLocale'][$locale], $query['search'])
            ) {
                $hasMereAssociationFailed = true;
            }

            //3. Person criteria

            //3.1 hasActiveAssignment
            if (!$isMereAssociation && !$showPeopleWithoutActiveAssignment &&
                !$assignment['person']['hasActiveAssignment']
            ) {
                $hasPersonFailed = true;
            }

            //3.1 Person name
            if (!$isMereAssociation && $searchPersonName &&
                false === stripos($assignment['person']['searchName'], $query['personName'])
            ) {
                $hasPersonFailed = true;
            }

            //3.2 search field criteria
            if (!$isMereAssociation && $searchSearchField &&
                false === stripos($assignment['person']['searchName'], $query['search'])
            ) {
                $hasMerePersonFailed = true;
            }

            //save info about what we've returned
            if (!$hasAssignmentFailed && !$hasAssociationFailed && !$hasPersonFailed) {
                if (isset($assignment['personId'])) {
                    $personsReturned[] = $assignment['personId'];
                }
                if (isset($assignment['associationId'])) {
                    $associationsReturned[] = $assignment['associationId'];
                }
                $results[] = $assignment;
            } else {
                if ($acceptingMereAssociations && $hasAssignmentFailed &&
                    !$isMerePerson && !$hasAssociationFailed && !$hasMereAssociationFailed
                ) {
                    $association = $this->getAssignmentPrototype();
                    $association['associationId'] = $assignment['associationId'];
                    $association['association'] = $assignment['association'];
                    $association['associationSort'] = $assignment['associationSort'];
                    $maybeAssociations[] = $association;
                }
                if ($acceptingMerePersons && $hasAssignmentFailed &&
                    !$isMereAssociation && !$hasPersonFailed && !$hasMerePersonFailed
                ) {
                    $person = $this->getAssignmentPrototype();
                    $person['personId'] = $assignment['personId'];
                    $person['person'] = $assignment['person'];
                    $person['personSort'] = $assignment['personSort'];
                    $maybePersons[] = $person;
                }
            }
        }

        //check which maybeAssociations to add
        foreach ($maybeAssociations as $assignment) {
            if (!in_array($assignment['associationId'], $associationsReturned)) {
                $results[] = $assignment;
                $associationsReturned[] = $assignment['associationId'];
            }
        }
        //check which maybePersons to add
        foreach ($maybeAssociations as $assignment) {
            if (!in_array($assignment['personId'], $personsReturned)) {
                $results[] = $assignment;
                $personsReturned[] = $assignment['personId'];
            }
        }

        return $results;
    }

    /**
     * Get the list of main roles of national movements.
     * A list of assignments are returned, but keyed by the associationId.
     * This provides compatibility with the assignments-table-partial, while giving the
     * ability to print the list of all active national movements.
     * @return mixed[]
     */
    public function getNationalMovementsLeaders()
    {
        $nationalLeadersResults = $this->searchEntities([
            'associationKind' => 'sch-national-movement',
            'onlyMainRoles' => true,
        ]);

        $nationalLeaders = [];
        foreach ($nationalLeadersResults as $assignment) {
            $nationalLeaders[$assignment['associationId']] = $assignment;
        }
        return $nationalLeaders;
    }

    /**
     * Get the list of main roles of shrines.
     * A list of assignments are returned, but keyed by the associationId.
     * This provides compatibility with the assignments-table-partial, while giving the
     * ability to print the list of all active national movements.
     * @return mixed[]
     */
    public function getShrines()
    {
        $cacheKey = 'shrines';
        if (null !== ($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }
        $objects = $this->queryObjects('association', ['kind' => 'sch-shrine']);
        $this->linkAssociations($objects);

        $locale = $this->getLocale();
        $sort = [];
        foreach ($objects as $k => $v) {
            $sort['countryRegion'][$k] = $v['countryRegion'];
            $sort['country'][$k] = $v['country'];
            $sort['nameByLocale'][$k] = $v['nameByLocale'][$locale];
        }
        array_multisort(
            $sort['countryRegion'],
            SORT_ASC,
            $sort['country'],
            SORT_ASC,
            $sort['nameByLocale'],
            SORT_ASC,
            $objects
        );
        
        $this->cacheEntityObjects($cacheKey, $objects, ['assignment', 'association']);
        return $objects;
    }

    /**
     * Get the list of main roles of shrines.
     * A list of assignments are returned, but keyed by the associationId.
     * This provides compatibility with the assignments-table-partial, while giving the
     * ability to print the list of all active national movements.
     * @return mixed[]
     */
    public function getWaysideShrines()
    {
        $objects = $this->queryObjects('association', ['kind' => 'sch-wayside-shrine']);
        $this->linkAssociations($objects);
        
        $locale = $this->getLocale();
        $sort = [];
        foreach ($objects as $k => $v) {
            $sort['countryRegion'][$k] = $v['countryRegion'];
            $sort['country'][$k] = $v['country'];
            $sort['nameByLocale'][$k] = $v['nameByLocale'][$locale];
        }
        array_multisort(
            $sort['countryRegion'],
            SORT_ASC,
            $sort['country'],
            SORT_ASC,
            $sort['nameByLocale'],
            SORT_ASC,
            $objects
            );
        
        return $objects;
    }
    
    /**
     * Criteria keys are: search(text), exMembers(bool=true), deceased(bool=true),
     *     category(array[string]), status(array[string])
     * @param array $query
     */
    public function searchPersons($query, $registerSearch = true, $bypassRequiredParams = false)
    {
        $oneOfRequiredParams = ['search', 'category', 'roleTitle', 'country'];
        //get rid of unnecessary parameters
        $realParamCount = 0;
        foreach ($query as $key => $value) {
            if (!isset($value) || $value === '') {
                unset($query[$key]);
            } elseif (in_array($key, $oneOfRequiredParams)) {
                $realParamCount++;
            }
        }
        if ($realParamCount == 0 && !$bypassRequiredParams) {
            return null;
        }
        if (isset($query['category'])) {
            if (is_string($query['category'])) {
                $query['category'] = array($query['category']);
            }
            if (!is_array($query['category'])) {
                unset($query['category']);
            }
        }
        if (isset($query['category'])) {
            if (is_string($query['category'])) {
                $query['category'] = array($query['category']);
            }
            if (!is_array($query['category'])) {
                unset($query['category']);
            }
        }
        //@todo why is this variable never again used?
//         $statusAcceptNull = true;
//         if (isset($query['status'])) {
//             if (is_string($query['status'])) {
//                 $query['status'] = array($query['status']);
//             }
//             if (!is_array($query['status'])) {
//                 unset($query['status']);
//             } else {
//                 $statusAcceptNull = in_array('none', $query['status']);
//             }
//         }
        $filter = new ToAscii();
        if (isset($query['search'])) {
            $query['search'] = $filter->filter($query['search']);
        }

        $persons = $this->getPersons();
        if (!isset($persons)) {
            return [];
        }
        $return = [];
        foreach ($persons as $personId => $person) {
            if (isset($query['search']) &&
               false === stripos($person['searchName'], $query['search'])) {
                continue;
            }
            if (isset($query['personName']) &&
                false === stripos($person['searchName'], $query['personName'])) {
                continue;
            }
            //@todo I'm not actually checking the value here I think?
            if (isset($query['deceased']) && false === $query['deceased'] && isset($person['deathDate'])) {
                continue;
            }
            if (isset($query['country']) && $query['country'] != $person['country']) {
                continue;
            }
            if (isset($query['category']) &&
                !in_array($person['category'], $query['category'])) {
                continue;
            }
            if (isset($query['dataSource']) &&
                isset($query['dataSourceId']) &&
                ($query['dataSource'] != $person['dataSource'] ||
                $query['dataSourceId'] != $person['dataSourceId'])) {
                continue;
            }
            $return[$personId] = $person;
        }

        return $return;
    }

    public function getAllPersonPhoneNumbers($includeInactive = false)
    {
        $persons = $this->getPersons();
        $phoneNumbers = [];
        foreach ($persons as $person) {
            if ($person['isActive'] || $includeInactive) {
                foreach ($person['phones'] as $phone) {
                    if ($phone['label'] != 'House') {
                        $phoneNumbers[] = [
                            'person' => $person,
                            'number' => $phone['number'],
                            'label' => $phone['label'],
                            'whatsApp' => isset($phone['whatsApp']) ? $phone['whatsApp'] : false,
                        ];
                    }
                }
            }
        }
        return $phoneNumbers;
    }

    /**
     * Preprocess data bound for the database on the person entities or its sub-entities
     * @param array $data
     * @return array
     */
    protected function preprocessPerson($data, $entityData, $action)
    {
        if (isset($data['automaticTitle']) && $data['automaticTitle'] === true) {
            $data['title'] = null;
        }
        return $data;
    }

    protected function postprocessPerson($data, $newEntityData, $action)
    {
        //after changing/inserting a person, check spouse info
        if (isset($data['spousePersonId'])) {
            $persons = $this->getUnlinkedPersons();
            $spousePersonId = $newEntityData['spousePersonId'];
            if (isset($persons[$spousePersonId])) {
                if ($persons[$spousePersonId]['spousePersonId'] != $newEntityData['personId']) {
                    $this->updateEntity('person', $spousePersonId, ['spousePersonId' => $newEntityData['personId']]);
                }
            }
        }
    }

    /**
     * Gets a list of all used countries in the database
     */
    public function getCountries()
    {
        if ($cache = $this->fetchCachedEntityObjects('used-countries')) {
            return $cache;
        }

        $sql = "SELECT g.Country
FROM ((SELECT DISTINCT Country FROM sch_associations) UNION (SELECT DISTINCT Country FROM sch_persons)) AS g
WHERE (NOT ISNULL(g.Country)) GROUP BY g.Country ORDER BY Country";

        $results = $this->fetchSome(null, $sql, null);
        $objects = [];
        foreach ($results as $row) {
            if (strlen($row['Country']) == 2) {
                $objects[] = $row['Country'];
            }
        }
        $this->cacheEntityObjects('used-countries', $objects, ['association', 'person']);
        return $objects;
    }

    /**
     * Gets a list of role titles including the aliases for each of
     * the titles he holds
     * @todo this function could be optimized by using a binary tree or something
     * @param int $personId
     */
    public function getPersonRoleTitles($personId)
    {
        $assignments = $this->getAssignments();
        $return = [];
        foreach ($assignments as $assignment) {
            if ($assignment['personId'] == $personId) {
                if (!in_array($assignment['roleTitle'], $return)) {
                    $return[] = $assignment['roleTitle'];
                }
                $aliases = $this->getRoleTitleAliases($assignment['roleTitle']);
                if ($aliases) {
                    foreach ($aliases as $alias) {
                        if (!in_array($alias['alias'], $return)) {
                            $return[] = $alias['alias'];
                        }
                    }
                }
            }
        }
        return $return;
    }

    /**
     * Connect the corresponding roles and assignments to a given list of entities
     * @param string $entity
     * @param mixed[] $entities
     * @return number
     */
    protected function connectEntityRolesAndAssignments($entity, &$entities)
    {
        $assignments = $this->getUnlinkedAssignments();
        $roles = $this->getUnlinkedRoles();
        $persons = $this->getUnlinkedPersons();

        //set assignments
        foreach ($assignments as $assignmentId => $assignment) {
            if (isset($entities[$assignment['associationId']]) &&
                isset($persons[$assignment['personId']])
            ) {
                $assignment['person'] = $persons[$assignment['personId']];
                $entities[$assignment['associationId']]['assignments'][$assignmentId] = $assignment;
                if ($assignment['isMainRole'] && $assignment['isActive'] &&
                    $persons[$assignment['personId']]['isActive']
                ) {
                    $entities[$assignment['associationId']]['mainAssignment'] = $assignment;
                    $entities[$assignment['associationId']]['mainPerson'] = $persons[$assignment['personId']];
                }
                if ($assignment['isMainContact'] && $assignment['isActive'] &&
                    $persons[$assignment['personId']]['isActive']
                ) {
                    $entities[$assignment['associationId']]['mainContactAssignment'] = $assignment;
                    $entities[$assignment['associationId']]['mainContactPerson'] = $persons[$assignment['personId']];
                }
            }
        }

        //set roles
        foreach ($roles as $roleId => $role) {
            if (isset($entities[$role['associationId']])) {
                $entities[$role['associationId']]['roles'][$roleId] = $role;
                if ($role['isMainRole'] && $role['isActive'] &&
                    !isset($entities[$role['associationId']]['mainRole'])
                ) {
                    $entities[$role['associationId']]['mainRole'] = $role;
                }
                if ($role['isMainContact'] && $role['isActive'] &&
                    !isset($entities[$role['associationId']]['mainContactRole'])
                ) {
                    $entities[$role['associationId']]['mainContactRole'] = $role;
                }
            }
        }
        return 0;
    }

    /**
     * {@inheritDoc}
     * @see \SionModel\Problem\ProblemProviderInterface::getProblems()
     */
    public function getProblems($minimumSeverity = EntityProblem::SEVERITY_INFO)
    {
        return array_merge($this->getPersonProblems($minimumSeverity), $this->getAssociationProblems($minimumSeverity));
    }

    public function getPersonProblems($minimumSeverity = EntityProblem::SEVERITY_INFO)
    {
        $persons = $this->getPersons();

        $problems = [];
        foreach ($persons as $person) {
            if (!isset($person['email'])) {
                $obj = clone $this->entityProblemPrototype;
                $obj->setProblem(self::PROBLEM_PERSON_NO_EMAIL)
                    ->setData($person);
                $problems[] = $obj;
            }
        }
        return $problems;
    }

    /**
     * {@inheritDoc}
     * @see \SionModel\Problem\ProblemProviderInterface::autoFixProblems()
     */
    public function autoFixProblems($simulate = true)
    {
        return [];
    }

    public function getAssociationProblems($minimumSeverity = EntityProblem::SEVERITY_INFO)
    {
        $associations = $this->getAssociations();
        $problems = [];
        foreach ($associations as $association) {
            if (!$association['isActive']) {
                continue;
            }
            $mainRoleCount = 0;
            foreach ($association['roles'] as $role) {
                if ($role['isActive'] && $role['isMainRole']) {
                    $mainRoleCount++;
                }
            }
            if ($mainRoleCount === 0 && $association['kind'] === 'sch-national-movement') {
                $obj = clone $this->entityProblemPrototype;
                $obj->setProblem(self::PROBLEM_ASSOCIATION_NO_MAIN_ROLE)
                    ->setData($association);
                $problems[] = $obj;
            }
            if ($mainRoleCount > 1) {
                $obj = clone $this->entityProblemPrototype;
                $obj->setProblem(self::PROBLEM_ASSOCIATION_MULTIPLE_MAIN_ROLE)
                    ->setData($association);
                $problems[] = $obj;
            }
        }
        return $problems;
    }

    public function getTranslationChangesCountPerMonth()
    {
//         $predicate = new Where();
        $gateway = $this->getTableGateway('trans_translations');
        $select = new Select('trans_translations');
        $select->columns(['TheMonth' => new Expression('MONTH(`modified_on`)'),
            'TheYear' => new Expression('YEAR(`modified_on`)'), 'Count' => new Expression('Count(*)')]);
        $select->group(['TheMonth', 'TheYear']);
//         $select->where($predicate->in('ChangedEntity', $tableEntities));
        $select->order('TheYear, TheMonth');
        $resultsChanges = $gateway->selectWith($select);
        $months = [];
        foreach ($resultsChanges as $row) {
            if (is_numeric($row['TheMonth']) && $row['TheMonth'] > 0  && $row['TheMonth'] <= 12 &&
                    is_numeric($row['TheYear']) && $row['TheYear'] >= 2015 && $row['TheYear'] <= 2050 &&
                    is_numeric($row['Count'])
                    ) {
                        $key = (string)($row['TheYear'] * 100 + $row['TheMonth']);
                        $months[$key] = $this->filterDbInt($row['Count']);
            }
        }
        return $months;
    }

    public function getResources()
    {
        $return = [];
        $persons = $this->getPersons();
        $associations = $this->getAssociations();
        foreach ($persons as $person) {
            $return[] = new GenericResource($person['resourceId']);
        }
        foreach ($associations as $association) {
            $return[] = new GenericResource($association['resourceId']);
        }
        return $return;
    }

    /**
     * @return \Zend\Permissions\Acl\Assertion\AssertionAggregate
     */
    public function getRules()
    {
        $persons = $this->getPersons();
        $associations = $this->getAssociations();


        //transform to object BjyAuthorize will understand
        $allow = [];
        foreach ($persons as $person) {
            $allow[$person['resourceId']] = ['sch_international_leader', 'sch_institute_member'];
        }

        foreach ($associations as $association) {
            $allow[$association['resourceId']] = ['sch_international_leader', 'sch_institute_member'];
        }

        return $this->formatRulesArray($allow);
    }

    /**
     * Takes an array in format [$resourceId => $roles(array)] and transforms
     * it to [$roles(array), $resourceId].
     * Everything is wrapped in an array and keyed by 'allow'.
     *
     * @todo finish development
     * @param array $allow
     */
    protected function formatRulesArray($allow)
    {
        $return = [];
        foreach ($allow as $resourceId => $roles) {
            $return[] = [$roles, $resourceId];
        }
//         var_dump(['allow' => $return]);
        return ['allow' => $return];
    }

    /**
     * @return TableGatewayInterface
     */
    public function getRoleTableGateway()
    {
        return new TableGateway('sch_roles', $this->adapter);
    }

    /**
     * Get the locale value
     * @return string
     */
    public function getLocale()
    {
        if (!isset($this->locale)) {
            $this->locale = \Locale::getDefault();
        }
        return $this->locale;
    }

    /**
     *
     * @param string $locale
     * @return self
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;
        return $this;
    }

    /**
     * Get the countriesInfo value
     * @return CountriesInfo
     */
    public function getCountriesInfo()
    {
        return $this->countriesInfo;
    }

    /**
     *
     * @param CountriesInfo $countriesInfo
     * @return self
     */
    public function setCountriesInfo($countriesInfo)
    {
        $this->countriesInfo = $countriesInfo;
        return $this;
    }
}
