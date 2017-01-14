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

class SchoenstattTable extends SionTable implements ProblemProviderInterface
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

    public function __construct(AdapterInterface $dbAdapter, $serviceLocator, $actingUserId, $schoenstattConfig)
    {
        parent::__construct($dbAdapter, $serviceLocator, $actingUserId);
        $this->config = $schoenstattConfig;
    }

    /**
     * Gets a simple key => value array of the generation
     * @param bool $includeInactive
     */
    public function getPersonValueOptions($includeInactive = false, $onlyPriests = true)
    {
        $persons = $this->getPersons();
        $result = [];
        foreach ($persons as $per) {
            if ($includeInactive || $per['isActive']) { //put it in
                $result[$per['personId']] = $per['lastName'].', '.$per['firstName'];
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
    public function getAssociationValueOptions($translator = null, $includeInactive = false, $includeNonLifeLongMembership = true)
    {
        $sql = "SELECT `AssociationId`, `AssociationName`, `IsNameTranslateable` FROM `sch_associations`";
        $results = $this->fetchSome(null, $sql, null);
        $valueOptions = [];
        foreach ($results as $row) {
            if ($translator instanceof TranslatorInterface && $this->filterDbBool($row['IsNameTranslateable'])) {
                $valueOptions[$row['AssociationId']] = $translator->translate($row['AssociationName']);
            } else {
                $valueOptions[$row['AssociationId']] = $row['AssociationName'];
            }
        }
        asort($valueOptions);
        return $valueOptions;
    }

    /**
     * Gets a simple key => value array of the role titles
     * @param TranslatorInterface $translator
     * @return mixed[]
     */
    public function getRoleTitleValueOptions()
    {
        $sql = "SELECT DISTINCT `RoleTitle` FROM `sch_roles` WHERE (`IsActive` = 1)";
        $results = $this->fetchSome(null, $sql, null);
        $valueOptions = [];
        foreach ($results as $row) {
            $valueOptions[$row['RoleTitle']] = $row['RoleTitle'];
        }
        asort($valueOptions);
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
        if (!is_null($this->associationsCache)) {
            return $this->associationsCache;
        }
        $entities = $this->getUnlinkedAssociations();
        return $this->associationsCache = $entities;
    }

    protected function getUnlinkedAssociations()
    {

        $sql = "SELECT `AssociationId`, `AssociationName`, `Parent`, `Kind`,
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
            $sort['Kind'][$k] = $v['Kind'];
            $sort['AssociationName'][$k] = $v['AssociationName'];
        }
        # sort by event_type desc and then title asc
        array_multisort($sort['Kind'], SORT_ASC, $sort['AssociationName'], SORT_ASC, $results);


        $entities = [];
        foreach ($results as $row) {
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

            $entities[$id] = [
                'associationId'         => $id,
                'name'                  => $this->filterDbString($row['AssociationName']),
                'parent'                => $this->filterDbId($row['Parent']),
                'kind'                  => $this->filterDbString($row['Kind']),
                'country'               => $this->filterDbString($row['Country']),
                'foundationDate'        => $this->filterDbDate($row['FoundationDate']),
                'suppressionDate'       => $this->filterDbDate($row['SuppressionDate']),
                'isLifeCommunity'       => $this->filterDbBool($row['IsLifeCommunity']),
                'isNameTranslateable'   => $this->filterDbBool($row['IsNameTranslateable']),
                'isActive'              => $this->filterDbBool($row['IsActive']),
                'adminTags'             => $this->filterDbArray($row['AdminTags']),
                'heirarchyLevel'        => 1, //@todo find a way to do this

                'roles'                 => [],
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
                'post1Street1'              => $this->filterDbString($row['Post1Street1']),
                'post1Street2'              => $this->filterDbString($row['Post1Street2']),
                'post1CityState'            => $this->filterDbString($row['Post1CityState']),
                'post1Zip'                  => $this->filterDbString($row['Post1Zip']),
                'post1Country'              => $this->filterDbString($row['Post1Country']),
                'post2Street1'              => $this->filterDbString($row['Post2Street1']),
                'post2Street2'              => $this->filterDbString($row['Post2Street2']),
                'post2CityState'            => $this->filterDbString($row['Post2CityState']),
                'post2Zip'                  => $this->filterDbString($row['Post2Zip']),
                'post2Country'              => $this->filterDbString($row['Post2Country']),
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
     * Inserts new roles for a newly created entity
     * Returns the number of roles inserted
     * @param string $associationId
     * @param string $kind
     * @return int
     */
    public function createAssociatedRoles($associationId, $kind)
    {
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
                'isSinglePosition'          => isset($role['isSinglePosition']) ? $role['isSinglePosition'] : false,
                'shouldAlwaysBeFilled'      => isset($role['shouldAlwaysBeFilled']) ? $role['shouldAlwaysBeFilled'] : false,
                'sort'                      => isset($role['sort']) ? $role['sort'] : 100,
                'isActive'                  => true,
            ];

            $this->getRoleTableGateway()->insert($params);
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
ORDER BY `BirthDate`";
        $results = $this->fetchSome(null, $sqlPers, null);
        if (is_null($results) || 0 == count($results)) {
            return null;
        }

        //sort list beforehand to not mess up the array key
        $sort = array();
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
                $titles = [];
                foreach ($personTagConfig as $tag => $tagConfig) {
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
                'isLiving'                  => $isLiving,
                'isActive'                  => $isLiving, //@todo make a new `active` column
                'title'                     => $title, //this is a calculated field, not for updating
                'assignments'               => [],
                'updatedOn'                 => $this->filterDbDate($row['UpdatedOn']),
                'updatedBy'                 => $this->filterDbId($row['UpdatedBy']),
                'createdOn'                 => $this->filterDbDate($row['CreatedOn']),
                'createdBy'                 => $this->filterDbId($row['CreatedBy']),

                /**
                 * Personal fields
                */
                'lastName'                  => $lastName,
                'firstName'                 => $firstName,
                'fullName'                  => $firstName.' '.$lastName,
                'searchName'                => $firstName.' '.$lastName,
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
            if ($role['associationId'] && isset($associations[$role['associationId']])) {
                $entities[$key]['association'] = $associations[$role['associationId']];
            }
        }

        return $this->rolesCache = $entities;
    }

    protected function getUnlinkedRoles()
    {
        $sql = "SELECT `RoleId`, `RoleTitle`, `AssociationId`,
`IsMainRole`, `IsSinglePosition`, `ShouldAlwaysBeFilled`, `Sort`, `IsActive`, `UpdatedOn`,
`UpdatedBy`, `CreatedOn`, `CreatedBy` FROM `sch_roles` WHERE 1";

        $results = $this->fetchSome(null, $sql, null);
        $entities = [];
        foreach ($results as $row) {
            $id = $this->filterDbId($row['RoleId']);
            $entities[$id] = [
                'roleId'                    => $id,
                'roleTitle'                 => $this->filterDbString($row['RoleTitle']),
                'associationId'             => $this->filterDbId($row['AssociationId']),
                'association'               => null,
                'sort'                      => $this->filterDbInt($row['Sort']),
                'isMainRole'                => $this->filterDbBool($row['IsMainRole']),
                'isSinglePosition'          => $this->filterDbBool($row['IsSinglePosition']),
                'shouldAlwaysBeFilled'      => $this->filterDbBool($row['ShouldAlwaysBeFilled']),
                'isActive'                  => $this->filterDbBool($row['IsActive']),
                'createdOn'                 => $this->filterDbDate($row['CreatedOn']),
                'createdBy'                 => $this->filterDbId($row['CreatedBy']),
                'updatedOn'                 => $this->filterDbDate($row['UpdatedOn']),
                'updatedBy'                 => $this->filterDbId($row['UpdatedBy']),
            ];
        }
        return $entities;
    }
    /**
     *
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

    public function getDefaultAssociatedRoles($associationKind)
    {
        $kindsSpecifications = $this->config['association_kinds'];
        $defaultRoles = [];
        foreach ($kindsSpecifications as $kind => $spec) {
            if (isset($spec['default_roles'])) {
                $defaultRoles[$kind] = $spec['default_roles'];
            }
        }
        return $defaultRoles[$associationKind];
    }

    /**
     * @return mixed[]
     */
    public function getAssignmentPersonAssociations()
    {
        $assignments    = $this->getUnlinkedAssignments();
        $associations   = $this->getUnlinkedAssociations();
        $persons        = $this->getUnlinkedPersons();

        $entities = [];
        foreach ($assignments as $assignmentId => $assignment) {
            $entity = [
                'assignmentId'      => $assignmentId,
                'assignment'        => $assignment,
                'associationId'     => null,
                'association'       => null,
                'personId'          => null,
                'person'            => null,
            ];
            if  (isset($assignment['personId']) && isset($persons[$assignment['personId']])) {
                $entity['person'] = $persons[$assignment['personId']];
                $persons[$assignment['personId']]['found'] = true;
            }
            if  (isset($assignment['associationId']) && isset($associations[$assignment['associationId']])) {
                $entity['association'] = $associations[$assignment['associationId']];
                $associations[$assignment['associationId']]['found'] = true;
            }
            $entities[] = $entity;
        }

        foreach ($associations as $associationId => $association) {
            if (!isset($association['found']) && $association['isActive']) {
                $entities[] = [
                    'assignmentId'          => null,
                    'assignment'            => null,
                    'associationId'         => $associationId,
                    'association'           => $association,
                    'personId'              => null,
                    'person'                => null,
                ];
            }
        }

        foreach ($persons as $personId => $person) {
            if (!isset($person['found']) && $person['isActive']) {
                $entities[] = [
                    'assignmentId'          => null,
                    'assignment'            => null,
                    'personId'              => $personId,
                    'person'                => $person,
                    'associationId'         => null,
                    'association'           => null,
                ];
            }
        }
        return $entities;
    }

    protected function getUnlinkedAssignments()
    {
        $sql = "SELECT a.`AssignmentId`, a.`RoleId`, a.`PersonId`,
a.`StartDate`, a.`EndDate`, a.`CreatedOn`, a.`CreatedBy`, a.`UpdatedOn`, a.`UpdatedBy`,
r.`RoleTitle`, r.`AssociationId`, r.`IsMainRole`, r.`IsSinglePosition`, r.`Sort`, r.`IsActive`,
r.`ShouldAlwaysBeFilled`
FROM `sch_assignments` a
INNER JOIN `sch_roles` r ON a.`RoleId` = r.`RoleId` WHERE 1";

        $results = $this->fetchSome(null, $sql, null);
        $entities = [];
        foreach ($results as $row) {
            $id = $this->filterDbId($row['AssignmentId']);
            $entities[$id] = [
                'assignmentId'          => $id,
                'roleId'                => $this->filterDbId($row['RoleId']),
                'roleTitle'             => $this->filterDbString($row['RoleTitle']),
                'associationId'         => $this->filterDbId($row['AssociationId']),
                'association'           => null,
                'isMainRole'            => $this->filterDbBool($row['IsMainRole']),
                'isSinglePosition'      => $this->filterDbBool($row['IsSinglePosition']),
                'shouldAlwaysBeFilled'  => $this->filterDbBool($row['ShouldAlwaysBeFilled']),
                'sort'                  => $this->filterDbInt($row['Sort']),
                'isActive'              => $this->filterDbBool($row['IsActive']),
                'personId'              => $this->filterDbId($row['PersonId']),
                'person'                => null,
                'startDate'             => $this->filterDbDate($row['StartDate']),
                'endDate'               => $this->filterDbDate($row['EndDate']),
                'createdOn'             => $this->filterDbDate($row['CreatedOn']),
                'createdBy'             => $this->filterDbId($row['CreatedBy']),
                'updatedOn'             => $this->filterDbDate($row['UpdatedOn']),
                'updatedBy'             => $this->filterDbId($row['UpdatedBy']),
            ];
        }
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
     * @todo get this list more efficiently by making its own query
     */
    public function getCountries()
    {
        if (empty($this->usedCountries)) {
            $this->getPersons();
        }
        return $this->usedCountries;
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
        $return = array();
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
//             if (isset($query['roleTitle']) && !is_null($query['roleTitle']) &&
//                 !in_array($query['roleTitle'], $person['roleTitles']))
//             {
//                 continue;
//             }
            $return[$personId] = $person;
        }

        //add search to counter
        //@todo fix
//         if ($registerSearch && false) {
//             $date = new \DateTime(null, new \DateTimeZone('UTC'));
//             $params = array('search_ip' => $_SERVER['REMOTE_ADDR'],
//                 'search_user' => $this->actingUserId,
//                 'search_datetime' => $date->format('Y-m-d H:i:s'),
//                 'search_results' => count($return),
//                 'search_query' => isset($query['search']) ? $query['search'] : null,
//                 'search_filiation' => isset($query['filiation']) ? $query['filiation'] : null,
//                 'search_course' => isset($query['course']) ? $query['course'] : null,
//                 'search_generation' => isset($query['generation']) ? $query['generation'] : null,
//                 'search_house' => isset($query['house']) ? $query['house'] : null,
//                 'search_country' => isset($query['country']) ? $query['country'] : null,
//                 'search_territory' => isset($query['territory']) ? $query['territory'] : null);
//             $this->getSearchTableGateway()->insert($params);
//         }
        return $return;
    }

    /**
     *
     * @param int|string $id
     * @param string $simpleScope
     * @throws \InvalidArgumentException
     */
    public function getAssociatedRoles($id, $simpleScope)
    {
        if (!$id) {
            throw new \InvalidArgumentException('Invalid id provided.');
        }
        $in = $this->getAssociatedScopesForInStatement($simpleScope);
        $roles = $this->getRoles();
        $return = array();
        foreach ($roles as $role) {
            if ($role['scopeId'] == $id && in_array($role['scope'], $in)) {
                $return[] = $role;
            }
        }
        return $return;
    }

    public function getProblems($minimumSeverity = EntityProblem::SEVERITY_INFO)
    {
        return $this->getPersonProblems($minimumSeverity);
        return array_merge($this->getPersonProblems($minimumSeverity));//, $this->getCourseProblems($minimumSeverity));
    }

    public function getPersonProblems($minimumSeverity = EntityProblem::SEVERITY_INFO )
    {
        $persons = $this->getPersons();

        $problems = [];
        foreach ($persons as $personId => $person) {
            if (is_null($person['email']) &&
                ($person['condition'] == self::CONDITION_PRIEST ||
                    $person['condition'] == self::CONDITION_DEACON ||
                    $person['condition'] == self::CONDITION_STUDENT) &&
                $person['age'] < 70) {
                $obj = clone $this->entityProblemPrototype;
                $obj->setProblem(self::PROBLEM_PERSON_NO_EMAIL)
                    ->setData($person);
                $problems[] = $obj;
            }
        }
        return $problems;
    }

    /**
     * @return TableGatewayInterface
     */
    public function getRoleTableGateway()
    {
        return new TableGateway('sch_roles', $this->adapter);
    }
}
