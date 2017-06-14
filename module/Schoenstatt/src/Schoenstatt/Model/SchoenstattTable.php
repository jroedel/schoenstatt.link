<?php
namespace Schoenstatt\Model;

use Zend\Filter\ToNull;
use Assetic\Exception\Exception;
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
use Zend\Permissions\Acl\Assertion\AssertionAggregate;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Expression;
use Schoenstatt\Service\AssociationKindsService;

class SchoenstattTable extends SionTable implements ProblemProviderInterface, PersonValueOptionsProviderInterface, ResourceProviderInterface
{
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
     * Schoenstatt config
     * @var mixed[]
     */
    protected $config;

    /**
     * Prototype to be cloned when specifying new problems
     * @var EntityProblem $entityProblemPrototype
     */
    protected $entityProblemPrototype;

    protected $personsCache;

    protected $mainRolesCache;

    protected $assignmentsCache;

    protected $associationsCache;

    protected $rolesCache;

    /**
     * @var TranslatorInterface $translator
     */
    protected $translator;

    protected $countryNameTranslations;

    /**
    * @var string $locale
    */
    protected $locale;

    /**
    * @var AssociationKind[] $associationKinds
    */
    protected $associationKinds;

    public function __construct(AdapterInterface $dbAdapter, $serviceLocator, $actingUserId, $schoenstattConfig)
    {
        parent::__construct($dbAdapter, $serviceLocator, $actingUserId);
        $this->config = $schoenstattConfig;
        $this->translator = $serviceLocator->get('translator');

        /** @var AssociationKindsService $kindsService */
        $kindsService = $serviceLocator->get('Schoenstatt\AssociationKindsService');
        $this->associationKinds = $kindsService->getAssociationKinds();

        if (!$this->countryNameTranslations = $this->fetchCachedEntityObjects('country-name-translations')) {
            if ($serviceLocator->has('CountriesInfo')) {
                /** @var \JTranslate\Model\CountriesInfo $countriesInfo */
                $countriesInfo = $serviceLocator->get('CountriesInfo');
                $this->countryNameTranslations = $countriesInfo->getCountryNameTranslations();
                $this->cacheEntityObjects('country-name-translations', $this->countryNameTranslations);
            }
        }
    }

    /**
     * Gets a simple key => value array of the generation
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
     * @return unknown[]
     */
    public function getAssociationValueOptions($includeInactive = false, $includeNonLifeLongMembership = true)
    {
        $entities = $this->getUnlinkedAssociations();
        $valueOptions = [];
        foreach ($entities as $entityId => $object) {
            if (!$includeNonLifeLongMembership && !$this->filterDbBool($object['isLifeCommunity'])) {
                continue;
            }
            if (!is_null($object['formattedName'])) {
                $valueOptions[$entityId] = $object['formattedName'];
            } else {
                $valueOptions[$entityId] = $object['name'];
            }
        }
        asort($valueOptions);
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
        foreach ($roles as $roleId => $role) {
            if ($includeInactive || $role['isActive']) {
                if (key_exists($role['associationId'], $valueOptions)) {
                    if (!key_exists($role['roleTitle'], $valueOptions[$role['associationId']])) {
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
     * @return mixed[]
     */
    public function getAssociations()
    {
        $cacheKey = 'associations-'.$this->getLocale();
        if (!is_null($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }
        $entities = $this->getUnlinkedAssociations();

        foreach ($entities as $entityId => $entity) {
            if (!is_null($entity['parentId']) && key_exists($entity['parentId'], $entities)) {
                $entities[$entityId]['parent'] = &$entities[$entity['parentId']];
                $entities[$entity['parentId']]['childAssociations'][$entityId] = &$entities[$entityId];
            }
        }

        $this->connectEntityRolesAndAssignments('association', $entities);

        $this->cacheEntityObjects($cacheKey, $entities, ['association', 'person', 'role', 'assignment']);
        return $entities;
    }

    protected function getUnlinkedAssociations()
    {
        $cacheKey = 'unlinked-associations-'.$this->getLocale();
        if (!is_null($cache = $this->fetchCachedEntityObjects($cacheKey))) {
            return $cache;
        }

        $sql = "SELECT `AssociationId`, `AssociationName`, `Parent`, `Kind`, `OverrideNameFormat`,
`Country`, `FoundationDate`, `SuppressionDate`, `IsLifeCommunity`, `IsNameTranslateable`,
`IsActive`, `PublicNotes`, `PublicNotesUpdatedOn`, `PublicNotesUpdatedBy`, `AdminTags`,
`AdminNotes`, `AdminNotesUpdatedOn`, `AdminNotesUpdatedBy`, `Email`, `Email2`,
`EmailsUpdatedOn`, `EmailsUpdatedBy`, `Phone1`, `Phone1Label`, `Phone2`, `Phone2Label`,
`Phone3`, `Phone3Label`, `PhonesUpdatedOn`, `PhonesUpdatedBy`, `Url1`, `Url1Label`,
`Url2`, `Url2Label`, `Url3`, `Url3Label`, `FacebookUrl`, `TwitterUser`, `InstagramUser`,
`Post1Street1`, `Post1Street2`, `Post1CityState`, `Post1Zip`, `Post1Country`,
`Post2Street1`, `Post2Street2`, `Post2CityState`, `Post2Zip`, `Post2Country`,
`ContactNotes`, `ContactInfoUpdatedOn`, `ContactInfoUpdatedBy`, `UpdatedOn`,
`UpdatedBy`, `CreatedOn`, `CreatedBy` FROM `sch_associations` WHERE 1";

        $results = $this->fetchSome(null, $sql, null);
        $sort = [];
        foreach($results as $k=>$v) {
            if (!key_exists($v['Kind'], $this->associationKinds)) {
                unset($results[$k]);
                continue;
            }
            $kindSort = $this->associationKinds[$v['Kind']]->sort;
            $results[$k]['AssociationSort'] = $kindSort;
            $sort['KindSort'][$k] = $kindSort;
            $sort['AssociationName'][$k] = $v['AssociationName'];
        }
        # sort by event_type desc and then title asc
        array_multisort($sort['KindSort'], SORT_ASC, $sort['AssociationName'], SORT_ASC, $results);

        $isTranslatorReady = $this->translator instanceof TranslatorInterface;
        $areCountryTranslationsReady = !is_null($this->countryNameTranslations);
        $locale = $this->getLocale();
        $entities = [];
        foreach ($results as $row) {
            $kind = $this->filterDbString($row['Kind']);
            if (!key_exists($kind, $this->associationKinds)) {
                continue;
            }
            $associationKindSpec = $this->associationKinds[$kind];

            $id = $this->filterDbId($row['AssociationId']);
            //process URLs
            $unprocessedUrls = [
                ['url' => $row['Url1'], 'label' => $this->filterDbString($row['Url1Label'])],
                ['url' => $row['Url2'], 'label' => $this->filterDbString($row['Url2Label'])],
                ['url' => $row['Url3'], 'label' => $this->filterDbString($row['Url3Label'])],
            ];
            $urls = $this::processUrls($unprocessedUrls);

            $phones = [];
            if (!is_null($phone1 = $this->filterDbString($row['Phone1']))) {
                $phones[] = [
                    'number' => $phone1,
                    'label' => !is_null($phone1Label = $this->filterDbString($row['Phone1Label'])) ? $phone1Label : 'Other',
                ];
            }
            if (!is_null($phone2 = $this->filterDbString($row['Phone2']))) {
                $phones[] = [
                    'number' => $phone2,
                    'label' => !is_null($phone2Label = $this->filterDbString($row['Phone2Label'])) ? $phone2Label : 'Other',
                ];
            }
            if (!is_null($phone3 = $this->filterDbString($row['Phone3']))) {
                $phones[] = [
                    'number' => $phone3,
                    'label' => !is_null($phone3Label = $this->filterDbString($row['Phone3Label'])) ? $phone3Label : 'Other',
                ];
            }

            //abstract address elements
            $post1Street1   = $this->filterDbString($row['Post1Street1']);
            $post1Street2   = $this->filterDbString($row['Post1Street2']);
            $post1CityState = $this->filterDbString($row['Post1CityState']);
            $post1Zip       = $this->filterDbString($row['Post1Zip']);
            $post1Country   = $this->filterDbString($row['Post1Country']);
            $post2Street1   = $this->filterDbString($row['Post2Street1']);
            $post2Street2   = $this->filterDbString($row['Post2Street2']);
            $post2CityState = $this->filterDbString($row['Post2CityState']);
            $post2Zip       = $this->filterDbString($row['Post2Zip']);
            $post2Country   = $this->filterDbString($row['Post2Country']);

            $postAddresses = [];
            if (!is_null($post1Street1) || !is_null($post1Street2) || !is_null($post1CityState)) {
                $postAddresses[] = [
                    'street1'   => $post1Street1,
                    'street2'   => $post1Street2,
                    'cityState' => $post1CityState,
                    'zip'       => $post1Zip,
                    'country'   => $post1Country,
                ];
            }
            if (!is_null($post2Street1) || !is_null($post2Street2) || !is_null($post2CityState)) {
                $postAddresses[] = [
                    'street1'   => $post2Street1,
                    'street2'   => $post2Street1,
                    'cityState' => $post2CityState,
                    'zip'       => $post2Zip,
                    'country'   => $post2Country,
                ];
            }

            $name = $this->filterDbString($row['AssociationName']);
            $overrideNameFormat = $this->filterDbBool($row['OverrideNameFormat']);
            $isNameTranslateable = $this->filterDbBool($row['IsNameTranslateable']);
            $formattedName = null;
            if (!$overrideNameFormat && $associationKindSpec->hasNameFormat()
            ) {
                $token = $name;
                if ($associationKindSpec->shouldTranslateNameParameter) {
                    if ($areCountryTranslationsReady &&
                        key_exists($token, $this->countryNameTranslations) &&
                        key_exists($locale, $this->countryNameTranslations[$token]) &&
                        !is_null($this->countryNameTranslations[$token][$locale])
                    ) {
                        $token = $this->countryNameTranslations[$token][$locale];
                    } else if ($isTranslatorReady) {
                        $token = $this->translator->translate($token, 'Schoenstatt');
                    }
                }
                $formattedName = sprintf($associationKindSpec->translatedNameFormat, $token);
            } else { //no name format
                if ($isNameTranslateable && $isTranslatorReady) {
                    $formattedName = $this->translator->translate($name, 'Schoenstatt');
                } else {
                    $formattedName = $name;
                }
            }

            $entities[$id] = [
                'associationId'         => $id,
                'name'                  => $name,
                'overrideNameFormat'    => $overrideNameFormat,
                'formattedName'         => $formattedName,
                'parentId'              => $this->filterDbId($row['Parent']),
                'kind'                  => $kind,
                'country'               => $this->filterDbString($row['Country']),
                'foundationDate'        => $this->filterDbDate($row['FoundationDate']),
                'suppressionDate'       => $this->filterDbDate($row['SuppressionDate']),
                'isLifeCommunity'       => $this->filterDbBool($row['IsLifeCommunity']),
                'isNameTranslateable'   => $isNameTranslateable,
                'isActive'              => $this->filterDbBool($row['IsActive']),
                'adminTags'             => $this->filterDbArray($row['AdminTags']),

                'sort'                  => $associationKindSpec->sort,
                'isSubDiocesan'         => $associationKindSpec->isSubDiocesanAssociation,
                'resourceId'            => 'association_'.$id,
                'roles'                 => [],
            	'assignments'			=> [],
                'mainRole'              => null,
                'mainAssignment'        => null,
                'mainPerson'            => null,
                'mainContact'           => null,
                'mainContactAssignment' => null,
                'mainContactPerson'     => null,
                'childAssociations'     => [],
                'parent'                => null,
/**
 * Contact fields
*/
                'email'                     => $this->filterEmailString($row['Email']),
                'email2'                    => $this->filterEmailString($row['Email2']),
                'emailsUpdatedOn'           => $this->filterDbDate($row['EmailsUpdatedOn']),
                'emailsUpdatedBy'           => $this->filterDbId($row['EmailsUpdatedBy']),
                'phones'                    => $phones,
                'phone1'                    => $phone1,
                'phone1Label'               => $this->filterDbString($row['Phone1Label']),
                'phone2'                    => $phone2,
                'phone2Label'               => $this->filterDbString($row['Phone2Label']),
                'phone3'                    => $phone3,
                'phone3Label'               => $this->filterDbString($row['Phone3Label']),
                'phonesUpdatedOn'           => $this->filterDbDate($row['PhonesUpdatedOn']),
                'phonesUpdatedBy'           => $this->filterDbId($row['PhonesUpdatedBy']),
                'urls'                      => $urls,
                'url1'                      => $this->filterDbString($row['Url1']),
                'url1Label'                 => $this->filterDbString($row['Url1Label']),
                'url2'                      => $this->filterDbString($row['Url2']),
                'url2Label'                 => $this->filterDbString($row['Url2Label']),
                'url3'                      => $this->filterDbString($row['Url3']),
                'url3Label'                 => $this->filterDbString($row['Url3Label']),
                'facebookUrl'               => $this->filterDbString($row['FacebookUrl']),
                'twitterUser'               => $this->filterDbString($row['TwitterUser']),
                'instagramUser'             => $this->filterDbString($row['InstagramUser']),
                'postAddresses'             => $postAddresses,
                'post1Street1'              => $post1Street1,
                'post1Street2'              => $post1Street2,
                'post1CityState'            => $post1CityState,
                'post1Zip'                  => $post1Zip,
                'post1Country'              => $post1Country,
                'post2Street1'              => $post2Street1,
                'post2Street2'              => $post2Street2,
                'post2CityState'            => $post2CityState,
                'post2Zip'                  => $post2Zip,
                'post2Country'              => $post2Country,
                'contactNotes'              => $this->filterDbString($row['ContactNotes']),
//                 'contactNotesUpdatedOn'     => $this->filterDbDate($row['ContactNotesUpdatedOn']),
//                 'contactNotesUpdatedBy'     => $this->filterDbId($row['ContactNotesUpdatedBy']),

                'contactInfoUpdatedOn'      => $this->filterDbDate($row['ContactInfoUpdatedOn']),
                'contactInfoUpdatedBy'      => $this->filterDbDate($row['ContactInfoUpdatedBy']),

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
        $this->cacheEntityObjects($cacheKey, $entities, ['association']);
        return $entities;
    }
    /**
     *
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
     * Create association generation roles upon generation creation
     * @param mixed[] $data
     * @param mixed[] $newData
     * @param string $entityAction
     * @return number
     */
    protected function associationPostprocessor($data, $newData, $entityAction)
    {
        //if we're creating a diocesan movement, check if auto-creation of any league branches were requested
        if ($entityAction === SionTable::ENTITY_ACTION_CREATE)
        {
            if (!isset($newData['associationId']) || is_null($newData['associationId'])) {
                throw new \Exception('There was an unexpectedly no associationId on a new association');
            }
            $this->createAssociatedRoles($newData['associationId'], $newData['kind']);
            if ($newData['kind'] == 'sch-diocesan-movement') {
                foreach ($data as $key => $value) {
                    if (key_exists($key, self::ADD_LEAGUE_KINDS) && $value) {
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
        $associationConfig = $this->config['association_kinds'];
        $entities = $this->getAssociations();
        foreach ($entities as $entityId => $entity) {
            //don't include sub-diocesan associations
            if ($entity['country'] != $country) {
                unset($entities[$entityId]);
            } elseif (key_exists($entity['kind'], $associationConfig) &&
                key_exists('is_sub_diocesan_association', $associationConfig[$entity['kind']]) &&
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
                'shouldAlwaysBeFilled'      => isset($role['shouldAlwaysBeFilled']) ? $role['shouldAlwaysBeFilled'] : false,
                'sort'                      => isset($role['sort']) ? $role['sort'] : 100,
                'isActive'                  => true,
            ];

            $this->createEntity('role', $params);
            $i++;
        }
        return $i;
    }

    /**
     * Get all records from the mailings table
     * @return mixed[][]
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
        if ($this->personsCache) {
            return $this->personsCache;
        }
        $entities = $this->getUnlinkedPersons();

        return $this->personsCache = $entities;
    }

    protected function getUnlinkedPersons()
    {
        $sqlPers = "SELECT `PersonId`, `LastName`, `FirstName`,
`LastNameWithoutAccents`, `FirstNameWithoutAccents`, `PersonTags`, `LifeCommunity`,
`Title`, `TitleAutomatic`, `Country`, `BirthDate`, `NameDay`, `DeathDate`,
`PublicNotes`, `PublicNotesUpdatedOn`, `PublicNotesUpdatedBy`,
`PersonalInfoUpdatedOn`, `PersonalInfoUpdatedBy`, `AdminTags`,
`AdminNotes`, `AdminNotesUpdatedOn`, `AdminNotesUpdatedBy`, `Email`, `Email2`,
`EmailsUpdatedOn`, `EmailsUpdatedBy`, `CellPhone`, `CellPhoneHasWhatsApp`,
`Phone1`, `Phone1Label`, `Phone2`, `Phone2Label`, `Phone3`, `Phone3Label`,
`PhonesUpdatedOn`, `PhonesUpdatedBy`, `Url1`, `Url1Label`, `Url2`, `Url2Label`,
`Url3`, `Url3Label`, `FacebookUrl`, `SkypeUser`, `TwitterUser`, `InstagramUser`,
`SlackUser`, `PostStreet1`, `PostStreet2`, `PostCityState`, `PostZip`,
`PostCountry`, `ContactNotes`, `ContactInfoUpdatedOn`, `ContactInfoUpdatedBy`,
`DataSource`, `DataSourceId`, `DataSourceUpdatedOn`,
`UpdatedOn`, `UpdatedBy`, `CreatedOn`, `CreatedBy` FROM `sch_persons` WHERE 1
ORDER BY `LastName`, `FirstName`";
        $results = $this->fetchSome(null, $sqlPers, null);
        if (is_null($results) || 0 == count($results)) {
            return null;
        }

        //sort list beforehand to not mess up the array key
        $sort = [];
        foreach($results as $k=>$v) {
            $sort['LastName'][$k] = $v['LastName'];
        }
        # sort by event_type desc and then title asc
        array_multisort($sort['LastName'], SORT_ASC, $results);

        $entities = [];
        $filter = new ToNull();
        $tz = new \DateTimeZone('UTC');
        $today = new \DateTime(null, $tz);
        foreach ($results as $row) {
            $id = $this->filterDbId($row['PersonId']);
            $deathDate = $this->filterDbDate($row['DeathDate']);
            $birthDate = $this->filterDbDate($row['BirthDate']);
            $nameDay = null;
            if (!is_null($row['NameDay']) && $row['NameDay'] != '0000-00-00') {
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
                ['url' => $row['Url1'], 'label' => $this->filterDbString($row['Url1Label'])],
                ['url' => $row['Url2'], 'label' => $this->filterDbString($row['Url2Label'])],
                ['url' => $row['Url3'], 'label' => $this->filterDbString($row['Url3Label'])],
            ];
            $urls = $this::processUrls($unprocessedUrls);

            $today = new \DateTime(null, $tz);
            $isLiving = is_null($deathDate);
            //             $category = null;
            //             $condition = null;
            $personTags = $this->filterDbArray($row['PersonTags']);

            $title = null;
            $automaticTitle = $this->filterDbBool($row['TitleAutomatic']);
            $manualTitle = $this->filterDbString($row['Title']);
            if (!$automaticTitle) {
                $title = $manualTitle;
            } else {
                $personTagConfig = $this->config['person_tags'];
                //sort tag config according to sort order
                $sort = [];
                foreach($personTagConfig as $k=>$v) {
                    $sort['sort'][$k] = $v['sort'];
                }
                # sort by event_type desc and then title asc
                array_multisort($sort['sort'], SORT_ASC, $personTagConfig);

                $titles = [];
                foreach ($personTagConfig as $tag => $tagConfig) {
                    if (!isset($tagConfig['title'])) { //exclude tags without titles
                        continue;
                    }
                    if (in_array($tag, $personTags) ) {
                        $titles[] = $tagConfig['title'];
                    }
                }
                if (!empty($titles)) {
                    $title = implode(' ', $titles);
                }
            }

            $lastName = $this->filterDbString($row['LastName']);
            $firstName = $this->filterDbString($row['FirstName']);
            $fullName = $firstName . ' ' . $lastName;
            $sort = strtoupper(substr($lastName.$firstName, 0, 4));

            $phones = [];
            if (!is_null($cellPhone = $this->filterDbString($row['CellPhone']))) {
                $phones[] = [
                    'number' => $cellPhone,
                    'label' => 'Main cell phone',
                    'whatsApp' => $this->filterDbBool($row['CellPhoneHasWhatsApp']),
                ];
            }
            if (!is_null($phone1 = $this->filterDbString($row['Phone1']))) {
                $phones[] = [
                    'number' => $phone1,
                    'label' => !is_null($phone1Label = $this->filterDbString($row['Phone1Label'])) ? $phone1Label : 'Other',
                ];
            }
            if (!is_null($phone2 = $this->filterDbString($row['Phone2']))) {
                $phones[] = [
                    'number' => $phone2,
                    'label' => !is_null($phone2Label = $this->filterDbString($row['Phone2Label'])) ? $phone2Label : 'Other',
                ];
            }
            if (!is_null($phone3 = $this->filterDbString($row['Phone3']))) {
                $phones[] = [
                    'number' => $phone3,
                    'label' => !is_null($phone3Label = $this->filterDbString($row['Phone3Label'])) ? $phone3Label : 'Other',
                ];
            }

            $address = [
                'street1'               => $this->filterDbString($row['PostStreet1']),
                'street2'               => $this->filterDbString($row['PostStreet2']),
                'cityState'             => $this->filterDbString($row['PostCityState']),
                'zip'                   => $this->filterDbString($row['PostZip']),
                'country'               => $this->filterDbString($row['PostCountry']),
            ];

            $entities[$id] = [
                'personId'                  => $id,
                'updatedOn'                 => $this->filterDbDate($row['UpdatedOn']),
                'updatedBy'                 => $this->filterDbId($row['UpdatedBy']),
                'createdOn'                 => $this->filterDbDate($row['CreatedOn']),
                'createdBy'                 => $this->filterDbId($row['CreatedBy']),

                'isLiving'                  => $isLiving,
                'isActive'                  => $isLiving,
                'title'                     => $title, //this is a calculated field, not for updating
                'assignments'               => [],
                'aclRoles'                  => [],
                'resourceId'                => 'person_'.$id,
                'sort'                      => $sort,

                /**
                 * Personal fields
                */
                'lastName'                  => $lastName,
                'firstName'                 => $firstName,
                'fullName'                  => $firstName.' '.$lastName,
                'searchName'                => $firstName.' '.$lastName, //@todo remove accents
//                 'searchName'                => $row['SearchName'],
                'firstNameWithoutAccents'   => $this->filterDbString($row['FirstNameWithoutAccents']),
                'lastNameWithoutAccents'    => $this->filterDbString($row['LastNameWithoutAccents']),
                'fullFriendlyName'          => $fullName,
                'personTags'                => $personTags,
                'automaticTitle'            => $automaticTitle,
                'country'                   => $this->filterDbString($row['Country']),
                'lifeCommunity'             => $this->filterDbId($row['LifeCommunity']),
                'manualTitle'               => $manualTitle,
                'primaryLocale'             => 'en_US', //@todo add this column

                'deathDate'                 => $deathDate,
                'birthDate'                 => $birthDate,
                'age'                       => $age,
                'nameDay'                   => $nameDay,

                'publicNotes'               => $this->filterDbString($row['PublicNotes']),
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
                'phone1Label'               => $this->filterDbString($row['Phone1Label']),
                'phone2'                    => $phone2,
                'phone2Label'               => $this->filterDbString($row['Phone2Label']),
                'phone3'                    => $phone3,
                'phone3Label'               => $this->filterDbString($row['Phone3Label']),
                'phonesUpdatedOn'           => $this->filterDbDate($row['PhonesUpdatedOn']),
                'phonesUpdatedBy'           => $this->filterDbId($row['PhonesUpdatedBy']),
                'urls'                      => $urls,
                'url1'                      => $this->filterDbString($row['Url1']),
                'url1Label'                 => $this->filterDbString($row['Url1Label']),
                'url2'                      => $this->filterDbString($row['Url2']),
                'url2Label'                 => $this->filterDbString($row['Url2Label']),
                'url3'                      => $this->filterDbString($row['Url3']),
                'url3Label'                 => $this->filterDbString($row['Url3Label']),
                'facebookUrl'               => $this->filterDbString($row['FacebookUrl']),
                'skypeUser'                 => $this->filterDbString($row['SkypeUser']),
                'twitterUser'               => $this->filterDbString($row['TwitterUser']),
                'instagramUser'             => $this->filterDbString($row['InstagramUser']),
                'slackUser'                 => $this->filterDbString($row['SlackUser']),

                'address'                   => $address,

                'postStreet1'               => $address['street1'],
                'postStreet2'               => $address['street2'],
                'postCityState'             => $address['cityState'],
                'postZip'                   => $address['zip'],
                'postCountry'               => $address['country'],

                'contactNotes'              => $this->filterDbString($row['ContactNotes']),
                //                 'contactNotesUpdatedOn'     => $this->filterDbDate($row['ContactNotesUpdatedOn']),
            //                 'contactNotesUpdatedBy'     => $this->filterDbId($row['ContactNotesUpdatedBy']),

                'contactInfoUpdatedOn'      => $this->filterDbDate($row['ContactInfoUpdatedOn']),
                'contactInfoUpdatedBy'      => $this->filterDbDate($row['ContactInfoUpdatedBy']),

                /**
                 * Private info
                */
                'dataSource'                => $this->filterDbString($row['DataSource']),
                'dataSourceId'              => $this->filterDbId($row['DataSourceId']),
                'dataSourceUpdatedOn'       => $this->filterDbDate($row['DataSourceUpdatedOn']),
                'adminTags'                 => $this->filterDbArray(strtolower($row['AdminTags'])),
                'adminNotes'                => $this->filterDbString($row['AdminNotes']),
                'adminNotesUpdatedOn'       => $this->filterDbDate($row['AdminNotesUpdatedOn']),
                'adminNotesUpdatedBy'       => $this->filterDbId($row['AdminNotesUpdatedBy']),

            ];
        }
        return $entities;
    }

    /**
     * Get all roles linked to their corresponding associations
     * @return mixed[]
     */
    public function getRoles()
    {

        if (!is_null($this->rolesCache)) {
            return $this->rolesCache;
        }
        $entities = $this->getUnlinkedRoles();
        $associations = $this->getUnlinkedAssociations();
        foreach ($entities as $key => $role) {
            if ($role['associationId'] && key_exists($role['associationId'], $associations)) {
                $entities[$key]['association'] = $associations[$role['associationId']];
            } else {
                unset($entities[$key]);
            }
        }

        return $this->rolesCache = $entities;
    }

    /**
     * Get role entities unlinked from their Association relations.
     * Entities come presorted by associationId, isActive, isMainRole, sort
     * @return mixed[][]
     */
    protected function getUnlinkedRoles()
    {
        $sql = "SELECT `RoleId`, `RoleTitle`, `AssociationId`,
`IsMainRole`, `IsMainContact`, `IsSinglePosition`, `ShouldAlwaysBeFilled`, `Sort`, `IsActive`, `UpdatedOn`,
`UpdatedBy`, `CreatedOn`, `CreatedBy` FROM `sch_roles` WHERE 1
ORDER BY `AssociationId`, `IsActive` DESC, `IsMainRole` DESC, `Sort`";

        $results = $this->fetchSome(null, $sql, null);
        $isTranslatorReady = $this->translator instanceof TranslatorInterface;
        $entities = [];
        foreach ($results as $row) {
            $id = $this->filterDbId($row['RoleId']);
            $roleTitle = $this->filterDbString($row['RoleTitle']);
            if ($isTranslatorReady) {
                $formattedRoleTitle = $this->translator->translate($roleTitle, 'Schoenstatt');
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
        if (!key_exists($associationKind, $this->associationKinds)) {
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
        /*
         * New plan: We'll use getAssignments as a base, and then:
         * 1. reimplement sorting
         * 2. Include active associations who were left out
         * 3. Include active persons who where left out
         */
        $entities       = $this->getAssignments();
        $associations   = $this->getUnlinkedAssociations();
        $persons        = $this->getUnlinkedPersons();

        $associationKindSpecifications =
            isset($this->config['association_kinds']) ? $this->config['association_kinds'] : [];

        //first mark the "found" persons and associations in assignments
        foreach ($entities as $assignmentId => $assignment) {
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
        foreach($entities as $k=>$v) {
            $sort['associationSort'][$k] = $v['associationSort'];
            $sort['personSort'][$k] = $v['personSort'];
        }
        # sort by event_type desc and then title asc
        array_multisort($sort['associationSort'], SORT_ASC, $sort['personSort'], SORT_ASC, $entities);
        return $entities;
    }

    /**
     * Get a blank assignment array
     * @return mixed[]
     */
    private function getAssignmentPrototype()
    {
        static $prototype;
        if (is_null($prototype)) {
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
        if (!is_null($cache = $this->fetchCachedEntityObjects('assignments'))) {
            return $cache;
        }
        $entities       = $this->getUnlinkedAssignments();
        $persons        = $this->getUnlinkedPersons();
        $associations   = $this->getUnlinkedAssociations();

        foreach ($entities as $assignmentId => $assignment) {
            if (!key_exists($assignment['personId'], $persons) ||
                !key_exists($assignment['associationId'], $associations)
            ) {
                unset($entities[$assignmentId]);
            } else {
                $entities[$assignmentId]['person'] = $persons[$assignment['personId']];
                $entities[$assignmentId]['personSort'] = $persons[$assignment['personId']]['sort'];
                $entities[$assignmentId]['association'] = $associations[$assignment['associationId']];
                $entities[$assignmentId]['associationSort'] = $this->strPad($associations[$assignment['associationId']]['sort'], 4, '0', STR_PAD_LEFT).
                    $associations[$assignment['associationId']]['formattedName'];
            }
        }

        $this->cacheEntityObjects('assignments', $entities, ['assignment']);
        return $entities;
    }

    protected function getUnlinkedAssignments()
    {
        if (!is_null($cache = $this->fetchCachedEntityObjects('unlinked-assignments'))) {
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
        $isTranslatorReady = $this->translator instanceof TranslatorInterface;
        $entities = [];
        foreach ($results as $row) {
            $id = $this->filterDbId($row['AssignmentId']);
            $startDate = $this->filterDbDate($row['StartDate']);
            $endDate = $this->filterDbDate($row['EndDate']);
            $isActive = $this::areWeWithinDateRange($startDate, $endDate);
            $roleTitle = $this->filterDbString($row['RoleTitle']);
            if ($isTranslatorReady) {
                $formattedRoleTitle = $this->translator->translate($roleTitle, 'Schoenstatt');
            } else {
                $formattedRoleTitle = $roleTitle;
            }
            $entities[$id] = [
                'assignmentId'          => $id,
                'roleId'                => $this->filterDbId($row['RoleId']),
                'roleTitle'             => $this->filterDbString($row['RoleTitle']),
                'associationId'         => $this->filterDbId($row['AssociationId']),
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

                'formattedRoleTitle'    => $formattedRoleTitle,
                'isActive'              => $isActive,
                'association'           => null,
                'person'                => null,
            ];
        }
        $this->cacheEntityObjects('unlinked-assignments', $entities);
        return $entities;
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
     *     <<<<entitiesToReturn(array=['association', 'association'])>>>>> NOT SURE ABOUT THIS ONE
     * The 'search' query key will search (case-insensitive) the following fields:
     *
     * @param array $query
     */
    public function searchEntities($query, $options = [])
    {
        $onlyMainRoles = isset($query['onlyMainRoles']) && is_bool($query['onlyMainRoles']) ? $query['onlyMainRoles'] : false;
        $includeInactive = isset($query['includeInactive']) && is_bool($query['includeInactive']) ? $query['includeInactive'] : false;
        $onlyAssignments = isset($query['onlyAssignments']) && is_bool($query['onlyAssignments']) ? $query['onlyAssignments'] : false;

        $bypassRequiredParams = isset($options['bypassRequiredParams']) ? (bool)$options['bypassRequiredParams'] : false;

        $oneOfRequiredParams = ['search', 'associationKind', 'associationCountry',
            'personName', 'roleTitle'
        ];
        //get rid of unnecesary parameters
        $realParamCount = 0;
        foreach ($query as $key => $value) {
            if (is_null($value) || $value === '') {
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
        }

//         var_dump($query);

        $assignments = $this->getAssignmentPersonAssociations();
        if (is_null($assignments)) {
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
        $return = [];
        $personsReturned = [];
        $associationsReturned = [];
        $queuedAssociationId = null;
        $queuedAssociation = null;
        foreach ($assignments as $assignment) {
//first we process the queuedAssociations
            if (!is_null($queuedAssociationId) && $queuedAssociationId !== $assignment['associationId']) {
                $associationsReturned[] = $queuedAssociationId;
                $return[] = $queuedAssociation;
                $queuedAssociationId = null;
                $queuedAssociation = null;
            }

//first we apply the criteria that are cut and dry
            if (!$includeInactive && isset($assignment['association']) && !empty($assignment['association']) &&
                !$assignment['association']['isActive']
            ) {
                continue;
            }
            //@todo we still need to include tag, country searches
            if (isset($query['search']) && $query['search'] && !is_null($query['search']) &&
                    false === stripos($assignment['association']['name'], $query['search']) &&
                    false === stripos($assignment['association']['formattedName'], $query['search']) &&
                    false === stripos($assignment['roleTitle'], $query['search']) &&
                    false === stripos($assignment['formattedRoleTitle'], $query['search']) &&
                false === stripos($assignment['person']['searchName'], $query['search'])
            ) {
                continue;
            }
            if (isset($query['associationKind']) && !is_null($assignment['association']) &&
                $query['associationKind'] != $assignment['association']['kind']
            ) {
                continue;
            }
            if (isset($query['associationCountry']) && !is_null($assignment['association']) &&
                $query['associationCountry'] != $assignment['association']['country']
            ) {
                continue;
            }

            if (isset($query['roleTitle']) && is_array($query['roleTitle']) &&
                !in_array($assignment['roleTitle'], $query['roleTitle'])
            ) {
                continue;
            }
//check assignment part, if not, we'll strip it and see if we can find another match as a person/association
//so far there are two criteria that can cause this: onlyMainRoles, and includeInactive=false
            //get rid of non-main roles
            if (($onlyMainRoles && !$assignment['isMainRole']) || (!$includeInactive && !$assignment['isActive'])) {
                if (!in_array($assignment['associationId'], $associationsReturned) &&
                    $queuedAssociationId !== $assignment['associationId'] && !$onlyAssignments
                ) {
                    //this record is now disputed. Maybe we'll strip out the assignment and person data and return the association
                    $queuedAssociationId = $assignment['associationId']; //@todo what does this do if we just continue after?
                    $queuedAssociation = $this->getAssignmentPrototype();
                    $queuedAssociation['associationId'] = $assignment['associationId'];
                    $queuedAssociation['association'] = $assignment['association'];
                    $queuedAssociation['associationSort'] = $assignment['associationSort'];

                    //@todo I think we also need to queue the person
                }
                //@todo I think we should not continue here
                continue;
            }

//save info about what we've returned
            if (isset($assignment['person']) && !empty($assignment['person'])) {
                $personsReturned[] = $assignment['personId'];
            }
            if (isset($assignment['association']) && !empty($assignment['association'])) {
                $associationsReturned[] = $assignment['associationId'];
            }
            $queuedAssociationId = null;
            $queuedAssociation = null;
            $return[] = $assignment;
        }

        return $return;
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
            if (is_null($value) || $value === '') {
                unset($query[$key]);
            } elseif (in_array($key, $oneOfRequiredParams)) {
                $realParamCount++;
            }
        }
        if ($realParamCount == 0 && !$bypassRequiredParams) {
            return null;
        }
        if (isset($query['category']) && !is_null($query['category'])) {
            if (is_string($query['category'])) {
                $query['category'] = array($query['category']);
            }
            if (!is_array($query['category'])) {
                unset($query['category']);
            }
        }
        if (isset($query['category']) && !is_null($query['category'])) {
            if (is_string($query['category'])) {
                $query['category'] = array($query['category']);
            }
            if (!is_array($query['category'])) {
                unset($query['category']);
            }
        }
        $statusAcceptNull = true;
        if (isset($query['status']) && !is_null($query['status'])) {
            if (is_string($query['status'])) {
                $query['status'] = array($query['status']);
            }
            if (!is_array($query['status'])) {
                unset($query['status']);
            } else {
                $statusAcceptNull == in_array('none', $query['status']);
            }
        }
        $filter = new ToAscii();
        if (isset($query['search'])) {
            $query['search'] = $filter->filter($query['search']);
        }

        $persons = $this->getPersons();
        if (is_null($persons)) {
            return [];
        }
        $return = [];
        foreach ($persons as $personId => $person) {
            if (isset($query['search']) && $query['search'] && !is_null($query['search']) &&
               false === stripos($person['searchName'], $query['search']))
            {
                continue;
            }
            if (isset($query['personName']) && !is_null($query['personName']) &&
                false === stripos($person['searchName'], $query['personName'])) {
                continue;
            }
            //@todo I'm not actually checking the value here I think?
            if (isset($query['deceased']) && false === $query['deceased'] && !is_null($person['deathDate'])) {
                continue;
            }
            if (isset($query['country']) && !is_null($query['country']) && $query['country'] != $person['country']) {
                continue;
            }
            if (isset($query['category']) && !is_null($query['category']) &&
                !in_array($person['category'], $query['category']))
            {
                continue;
            }
            if (isset($query['dataSource']) && !is_null($query['dataSource']) &&
                isset($query['dataSourceId']) && !is_null($query['dataSourceId']) &&
                ($query['dataSource'] != $person['dataSource'] ||
                $query['dataSourceId'] != $person['dataSourceId']))
            {
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
        foreach ($persons as $personId => $person) {
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
    protected function preprocessPerson($data)
    {
        if (isset($data['automaticTitle']) && $data['automaticTitle'] === true) {
            $data['title'] = null;
        }
        return $data;
    }

    /**
     * Gets a list of commonly used countries
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
     * @todo this function could be optimized by using a binary tree or somethings
     * @param int $personId
     */
    public function getPersonRoleTitles($personId)
    {
        $assignments = $this->getAssignments();
        $return = [];
        foreach ($assignments as $assignmentId => $assignment) {
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

    protected function connectEntityRolesAndAssignments($entity, &$entities)
    {
        $assignments = $this->getUnlinkedAssignments();
        $roles = $this->getUnlinkedRoles();
        $persons = $this->getUnlinkedPersons();

        //set assignments
        foreach ($assignments as $assignmentId => $assignment) {
            if (key_exists($assignment['associationId'], $entities) &&
                key_exists($assignment['personId'], $persons)
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
            if (key_exists($role['associationId'], $entities)) {
                $entities[$role['associationId']]['roles'][$roleId] = $role;
                if ($role['isMainRole'] && $role['isActive'] &&
                    key_exists('mainRole', $entities[$role['associationId']])
                ) {
                    $entities[$role['associationId']]['mainRole'] = $role;
                }
                if ($role['isMainContact'] && $role['isActive'] &&
                    key_exists('mainContact', $entities[$role['associationId']])
                ) {
                    $entities[$role['associationId']]['mainContact'] = $role;
                }
            }
        }
        return 0;
    }

    public function getProblems($minimumSeverity = EntityProblem::SEVERITY_INFO)
    {
        return array_merge($this->getPersonProblems($minimumSeverity), $this->getAssociationProblems($minimumSeverity));
    }

    public function getPersonProblems($minimumSeverity = EntityProblem::SEVERITY_INFO )
    {
        $persons = $this->getPersons();

        $problems = [];
        foreach ($persons as $personId => $person) {
            if (is_null($person['email'])) {
                $obj = clone $this->entityProblemPrototype;
                $obj->setProblem(self::PROBLEM_PERSON_NO_EMAIL)
                    ->setData($person);
                $problems[] = $obj;
            }
        }
        return $problems;
    }

    public function autoFixProblems($simulate = true)
    {
        return [];
    }

    public function getAssociationProblems($minimumSeverity = EntityProblem::SEVERITY_INFO )
    {
        $associations = $this->getAssociations();
        $problems = [];
        foreach ($associations as $associationId => $association) {
            if (!$association['isActive']) {
                continue;
            }
            $mainRoleCount = 0;
            foreach ($association['roles'] as $role) {
                if ($role['isActive'] && $role['isMainRole']) {
                    $mainRoleCount++;
                }
            }
            if ($mainRoleCount === 0 && $association['kind'] == 'sch-national-movement') {
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
        $select->columns(['TheMonth' => new Expression('MONTH(`modified_on`)'), 'TheYear' => new Expression('YEAR(`modified_on`)'), 'Count' => new Expression('Count(*)')]);
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
        foreach ($persons as $personId => $person) {
            $return[] = new GenericResource($person['resourceId']);
        }
        foreach ($associations as $associationId => $association) {
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
        foreach ($result as $key => $rule)
        {
            $allow[$person['resourceId']] = ['sch_international_leader', 'sch_institute_member'];
        }

        foreach ($associations as $associationId => $association) {
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
        var_dump(['allow' => $return]);
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
        if (is_null($this->locale)) {
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

}
