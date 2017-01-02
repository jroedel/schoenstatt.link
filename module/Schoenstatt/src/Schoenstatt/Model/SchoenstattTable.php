<?php
namespace Schoenstatt\Model;

use Zend\Db\TableGateway\TableGateway;
use Zend\Filter\ToNull;
use Assetic\Exception\Exception;
use SionModel\Filter\ToAscii;
use SionModel\Db\Model\SionTable;
use Zend\Uri\Http;
use SionModel\Problem\EntityProblem;
use Patres\Problem\PersonProblem;
use SionModel\Problem\ProblemProviderInterface;
use Zend\I18n\Translator\TranslatorInterface;

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

    const CONDITION_EXMEMBER_BISHOP = 'ex-member bishop';
    const CONDITION_EXMEMBER_PRIEST = 'ex-member priest';
    const CONDITION_EXMEMBER_DEACON = 'ex-member deacon';
    const CONDITION_DECEASED_EXMEMBER = 'deceased ex-member';
    const CONDITION_EXMEMBER = 'ex-member';
    const CONDITION_DECEASED_BISHOP = 'deceased bishop';
    const CONDITION_DECEASED_PRIEST = 'deceased priest';
    const CONDITION_DECEASED_DEACON = 'deceased deacon';
    const CONDITION_DECEASED = 'deceased'; //only a theoretical possibility
    const CONDITION_BISHOP = 'bishop';
    const CONDITION_PRIEST = 'priest';
    const CONDITION_DEACON = 'deacon';
    const CONDITION_OTHER = 'other';

    const TITLE_BISHOP = 'Most Rev.';
    const TITLE_PRIEST = 'Fr.';
    const TITLE_MONSIGNOR = 'Msgr.';
    const TITLE_DEACON = 'Dn.';
    const TITLE_BROTHER = 'Br.';
    const TITLE_SISTER = 'Sr.';
    const TITLE_DOCTOR = 'Dr.';
    const TITLE_PROFESSOR = 'Prof.';

    const ENTITY_PERSON = 'person';
    const ENTITY_COURSE = 'course';
    const ENTITY_GENERATION = 'generation';
    const ENTITY_FILIATION = 'filiation';
    const ENTITY_TERRITORY = 'territory';
    const ENTITY_HOUSE = 'house';
    const ENTITY_ASSIGNMENT = 'assignment';
    const ENTITY_LIVING_SITUATION = 'living_situation';

    const MAILING_STATUS_QUEUED = 'queued';
    const MAILING_STATUS_ERROR = 'error';
    const MAILING_STATUS_ERROR_1 = 'error-1';
    const MAILING_STATUS_ERROR_2 = 'error-2';
    const MAILING_STATUS_ERROR_3 = 'error-3';
    const MAILING_STATUS_SENT = 'sent';

    const MAILING_STATUS_LABELS = [
        self::MAILING_STATUS_QUEUED => 'Queued',
        self::MAILING_STATUS_ERROR => 'Error',
        self::MAILING_STATUS_ERROR_1 => 'Error 1st attempt',
        self::MAILING_STATUS_ERROR_2 => 'Error 2nd attempt',
        self::MAILING_STATUS_ERROR_3 => 'Error 3rd attempt',
        self::MAILING_STATUS_SENT => 'Sent',
    ];

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

    /**
     *
     * @var TableGateway $suggestionColumnTableGateway
     */
    protected $suggestionColumnTableGateway;
    /**
     *
     * @var TableGateway $suggestionTableGateway
     */
    protected $suggestionTableGateway;
    /**
     *
     * @var TableGateway $suggestionTableGateway
     */
    protected $visitTableGateway;

    protected $personsCache;

    protected $emailAddressesCache;

    protected $mailingsCache;

    protected $mainRolesCache;

    protected $assignmentsCache;

    protected $roleTitleAliasesCache;

    protected $usedCountries = [];

    protected $associationsCache;

    protected $rolesCache;

    /**
     * Gets a simple key => value array of the generation
     * @param bool $includeInactive
     */
    public function getPersonValueOptions($includeInactive = false, $onlyPriests = true)
    {
        $persons = $this->getPersons();
        $result = [''=>''];
        foreach ($persons as $per) {
            if ($includeInactive || $per['isActive']) { //put it in
                $result[$per['personId']] = $per['friendlyLastName'].', '.$per['friendlyFirstName'];
            }
        }
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
        ksort($valueOptions);
        return $valueOptions;
    }

    public function getRolesPlusOtherPeopleAndAssociations()
    {

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
`LastNameWithoutAccents`, `FirstNameWithoutAccents`, `ReligiousStatus`, `LifeCommunity`,
`Title`, `TitleAutomatic`, `Country`, `BirthDate`, `NameDay`, `DeathDate`,
`PublicNotes`, `PublicNotesUpdatedOn`, `PublicNotesUpdatedBy`,
`PersonalInfoUpdatedOn`, `PersonalInfoUpdatedBy`, `AdminTags`, `Nationalities`,
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
            $sort['Country'][$k] = $v['Country'];
            $sort['LastName'][$k] = $v['LastName'];
        }
        # sort by event_type desc and then title asc
        array_multisort($sort['Country'], SORT_ASC, $sort['LastName'], SORT_ASC, $results);

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
            $nationalities = $this->filterDbArray($row['Nationalities']);
            foreach ($nationalities as $key => $value) {
                if (2 !== strlen($value)) {
                    unset($nationalities[$key]);
                }
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
            $title = null;

            $automaticTitle = $this->filterDbBool($row['TitleAutomatic']);
            $manualTitle = $this->filterDbString($row['Title']);
            if (!$automaticTitle) {
                $title = $manualTitle;
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
                'automaticTitle'            => $automaticTitle,
                'country'                   => $this->filterDbString($row['Country']),
                //                 'category'                  => $category,
                'religiousStatus'           => $this->filterDbString($row['ReligiousStatus']),
                'lifeCommunity'             => $this->filterDbId($row['LifeCommunity']),
                //                 'condition'                 => $condition,
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
            //                 'birthCity'                 => $this->filterDbString($row['BirthCity']),
                'nationalities'             => $nationalities,
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

        return $this->rolesCache = $entities;
    }

    protected function getUnlinkedRoles()
    {

        $sql = "SELECT `RoleId`, `RoleTitle`, `AssociationId`,
`IsMainRole`, `IsSinglePosition`, `Sort`, `IsActive`, `UpdatedOn`,
`UpdatedBy`, `CreatedOn`, `CreatedBy` FROM `sch_roles` WHERE 1";

        $results = $this->fetchSome(null, $sql, null);
        $entities = [];
        foreach ($results as $row) {
            $id = $this->filterDbId($row['RoleId']);
            $entities[$id] = [
                'roleId'                    => $id,
                'roleTitle'                 => $this->filterDbString($row['RoleTitle']),
                'associationId'             => $this->filterDbId($row['AssociationId']),
                'isMainRole'                => $this->filterDbBool($row['IsMainRole']),
                'isSinglePosition'          => $this->filterDbBool($row['IsSinglePosition']),
                'sort'                      => $this->filterDbInt($row['Sort']),
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

    /**
     * @return mixed[]
     */
    public function getAssignments($includeLoosePersons = false)
    {

        if (!is_null($this->assignmentsCache)) {
            return $this->assignmentsCache;
        }
        $entities = $this->getUnlinkedAssignments();
        $persons = $this->getUnlinkedPersons();
        $associations = $this->getUnlinkedAssociations();
        foreach ($entities as $entityId => $entity) {
            if  (isset($persons[$entity['personId']])) {
                $entity['person'] = $persons[$entity['personId']];
                $persons[$entity['personId']]['found'] = true;
            }
            if  (isset($associations[$entity['associationId']])) {
                $entity['association'] = $associations[$entity['associationId']];
                $associations[$entity['associationId']]['found'] = true;
            }
        }

        if ($includeLoosePersons) {
            foreach ($persons as $personId => $person) {
                if (!isset($person['found']) && $person['isActive']) {
                    $entities[] = [
                        'assignmentId'          => null,
                        'roleId'                => null,
                        'roleTitle'             => null,
                        'associationId'         => null,
                        'association'           => null,
                        'isMainRole'            => null,
                        'isSinglePosition'      => null,
                        'sort'                  => null,
                        'isActive'              => null,
                        'personId'              => $personId,
                        'person'                => $person,
                        'startDate'             => null,
                        'endDate'               => null,
                        'createdOn'             => null,
                        'createdBy'             => null,
                        'updatedOn'             => null,
                        'updatedBy'             => null,
                    ];
                }
            }
            foreach ($associations as $associationId => $association) {
                if (!isset($association['found']) && $association['isActive']) {
                    $entities[] = [
                        'assignmentId'          => null,
                        'roleId'                => null,
                        'roleTitle'             => null,
                        'associationId'         => $associationId,
                        'association'           => $association,
                        'isMainRole'            => null,
                        'isSinglePosition'      => null,
                        'sort'                  => null,
                        'isActive'              => null,
                        'personId'              => null,
                        'person'                => null,
                        'startDate'             => null,
                        'endDate'               => null,
                        'createdOn'             => null,
                        'createdBy'             => null,
                        'updatedOn'             => null,
                        'updatedBy'             => null,
                    ];
                }
            }
        }

        return $this->assignmentsCache = $entities;
    }

    protected function getUnlinkedAssignments()
    {
        $sql = "SELECT a.`AssignmentId`, a.`RoleId`, a.`Personid`,
a.`StartDate`, a.`EndDate`, a.`CreatedOn`, a.`CreatedBy`, a.`UpdatedOn`, a.`UpdatedBy`,
r.`RoleTitle`, r.`AssociationId`, r.`IsMainRole`, r.`IsSinglePosition`, r.`Sort`, r.`IsActive`
FROM `sch_assignments` a
INNER JOIN `sch_roles` r ON a.`RoleId` = r.`RoleId` WHERE 1";

        $results = $this->fetchSome(null, $sql, null);
        $entities = [];
        foreach ($results as $row) {
            $id = $this->filterDbId($row['AssignmentId']);
            $entities[$id] = [
                'assignmentId'          => $id,
                'roleId'                => $this->filterDbString($row['RoleId']),
                'roleTitle'             => $this->filterDbString($row['RoleTitle']),
                'associationId'         => $this->filterDbId($row['AssociationId']),
                'association'           => null,
                'isMainRole'            => $this->filterDbBool($row['IsMainRole']),
                'isSinglePosition'      => $this->filterDbBool($row['IsSinglePosition']),
                'sort'                  => $this->filterDbInt($row['Sort']),
                'isActive'              => $this->filterDbBool($row['IsActive']),
                'personId'              => $this->filterDbDate($row['PersonId']),
                'person'                => null,
                'startDate'             => $this->filterDbInt($row['StartDate']),
                'endDate'               => $this->filterDbInt($row['EndDate']),
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

    /**
     * @return string[]
     */
    public function getPersonAdminTags()
    {
        $sql = "SELECT DISTINCT AdminTags FROM `a_data_person`";
        $resultsTags = $this->fetchSome(null, $sql, null);

        $return = array();
        foreach ($resultsTags as $row) {
            if (is_null($row['AdminTags']) || $row['AdminTags'] == '') {
                continue;
            }
            $values = explode("|", strtolower($row['AdminTags']));
            foreach ($values as $value) {
                $value = trim($value);
                if (!in_array($value, $return)) {
                    $return[$value] =  $value;
                }
            }
        }
        return $return;
    }

    /**
     * Generates a name for the living situation entity
     * @param int $id
     * @return string
     */
    public function getLivingSituationName($id)
    {
        $situation = $this->getLivingSituation($id);

        $person = $this->getPerson($situation['personId']);
        $text = $person['fullFriendlyName'] . ' - ' . $person['filiationName'];
        //tack a year on to the end of our situation name
        $yearRange = $this::getYearRange($situation['startDate'], $situation['endDate']);
        if (!is_null($yearRange)) {
            $text.= $yearRange;
        }
        return $text;
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
        if (isset($data['nationalities'])) {
            $data['nationality1'] = isset($data['nationalities'][0]) ? $data['nationalities'][0] : null;
            $data['nationality2'] = isset($data['nationalities'][1]) ? $data['nationalities'][1] : null;
            $data['nationality3'] = isset($data['nationalities'][2]) ? $data['nationalities'][2] : null;
        }
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
                $query['dataSource'] != $person['dataSource'] &&
                $query['dataSourceId'] != $person['dataSourceId'])
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

    public function getRolesList($showAll, $showHistory)
    {
        $in = $this->getAssociatedScopesForInStatement('Territory', true);
        $sqlTerritoryAssignments = "SELECT a.`AssignmentId`,a.`RoleId`, a.`PersID`, a.`StartDate`,
a.`EndDate`, r.RoleTitle, g.GebID, g.GebName, p.PersName, p.PersVorname, p.PersTod, p.PersUrsprLand
FROM `a_data_role_assignment` a
LEFT JOIN a_data_role r ON a.`RoleId` = r.RoleId
LEFT JOIN a_data_gebiet g ON r.ScopeId = g.GebID AND (r.Scope IN $in)
LEFT JOIN a_data_person p ON a.`PersID` = p.PersID
WHERE (r.Scope IN $in)
ORDER BY g.`Sort`, r.`Sort`";
        $resultsTerritoryAssignments = $this->fetchSome ( null, $sqlTerritoryAssignments, null );
        $territoryAssignments = array();
        foreach ($resultsTerritoryAssignments as $row) {
            $territoryAssignments[] = array(
                'assignmentId'     => $this->filterDbId($row['AssignmentId']),
                'roleId'           => $this->filterDbId($row['RoleId']),
                'startDate'        => $this->filterDbDate($row['StartDate']),
                'endDate'          => $this->filterDbDate($row['EndDate']),
                'roleTitle'        => $row['RoleTitle'],
                'territoryId'      => $this->filterDbId($row['GebID']),
                'territoryName'    => $row['GebName'],
                'personId'         => $this->filterDbId($row['PersID']),
                'personLastName'   => $row['PersName'],
                'personFirstName'  => $row['PersVorname'],
                'personDeathDate'  => $this->filterDbDate($row['PersTod']),
                'personCountry'    => $row['PersUrsprLand'],
            );
        }

        if ($showAll) {
            $dateWhere = "";
            $generationDateWhere = "";
            if (!$showHistory) {
                $dateWhere = "AND ((ISNULL(a.StartDate) AND (a.EndDate > CURDATE() OR ISNULL(a.EndDate))) OR (a.StartDate <= CURDATE() AND (ISNULL(a.EndDate) OR a.EndDate > CURDATE()))) ";
                $generationDateWhere = "AND ((ISNULL(ga.StartDate) AND (ISNULL(ga.EndDate) OR ga.EndDate > CURDATE()) ) OR (ga.StartDate <= CURDATE() AND (ISNULL(ga.EndDate) OR ga.EndDate > CURDATE())))";
            }
            $in = $this->getAssociatedScopesForInStatement('Filiation', true);
            $sqlFiliationAssignments = "SELECT a.`AssignmentId`, a.`PersID`, a.`StartDate`, a.`EndDate`, r.RoleId,
r.RoleTitle, f.FilID, f.FilName, f.Country, g.GebID, g.GebName, p.PersName, p.PersVorname, p.PersTod, p.PersUrsprLand
FROM a_data_filiale f
LEFT JOIN a_data_role r ON r.Scope IN $in AND r.ScopeId = f.FilID
LEFT JOIN `a_data_role_assignment` a ON a.`RoleId` = r.RoleId $dateWhere
LEFT JOIN a_data_gebiet g ON f.GebID = g.GebID
LEFT JOIN a_data_person p ON a.`PersID` = p.PersID
WHERE (f.Active = 1 OR f.Active IS NULL)
ORDER BY g.`Sort`, f.FilName, r.`Sort`";
            $resultsFiliationAssignments = $this->fetchSome ( null, $sqlFiliationAssignments, null );

            $filiationAssignments = array();
            foreach ($resultsFiliationAssignments as $row) {
                $filiationAssignments[] = array(
                    'assignmentId'     => $this->filterDbId($row['AssignmentId']),
                    'roleId'           => $this->filterDbId($row['RoleId']),
                    'startDate'        => $this->filterDbDate($row['StartDate']),
                    'endDate'          => $this->filterDbDate($row['EndDate']),
                    'roleTitle'        => $row['RoleTitle'],
                    'territoryId'      => $this->filterDbId($row['GebID']),
                    'territoryName'    => $row['GebName'],
                    'filiationId'      => $this->filterDbId($row['FilID']),
                    'filiationName'    => $row['FilName'],
                    'country'          => $row['Country'],
                    'personId'         => $this->filterDbId($row['PersID']),
                    'personLastName'   => $row['PersName'],
                    'personFirstName'  => $row['PersVorname'],
                    'personDeathDate'  => $this->filterDbDate($row['PersTod']),
                    'personCountry'    => $row['PersUrsprLand'],
                );
            }

            $sqlCourseAssignments = "SELECT k.`KursID`, k.`KursName`, k.`GenID`, g.`GenName`,
r.RoleId as LeaderRoleId, lp.PersID AS LeaderPersID, lp.PersName AS LeaderPersName, lp.PersVorname AS LeaderPersVorname,
lp.PersTod AS LeaderPersTod, lp.PersUrsprLand AS LeaderPersUrsprLand,
a.`AssignmentId` AS LeaderAssignmentID, a.StartDate AS LeaderStartDate, a.EndDate AS LeaderEndDate,
gr.RoleId as GenerationRoleId, ga.AssignmentId AS GenerationAssignmentID, ga.StartDate AS GenerationStartDate, ga.EndDate AS GenerationEndDate,
gp.PersID AS GenerationPersID, gp.PersName AS GenerationPersName, gp.PersVorname AS GenerationPersVorname,
gp.PersTod AS GenerationPersTod, gp.PersUrsprLand AS GenerationPersUrsprLand, IF(ISNULL(g.`GenID`),999,g.`GenID`) AS GenSort
FROM `a_data_kurs` k
LEFT JOIN `a_data_generation` g ON k.`GenID` = g.`GenID`
LEFT JOIN `a_data_role` r ON r.`Scope` = 'Course' AND r.`RoleTitle` = 'Course Leader' AND k.`KursID` = r.`ScopeId`
LEFT JOIN `a_data_role_assignment` a ON a.`RoleId` = r.`RoleId` $dateWhere
LEFT JOIN `a_data_person` lp ON a.`PersID` = lp.PersID
LEFT JOIN `a_data_role` gr ON gr.`Scope` = 'Generation' AND gr.`RoleTitle` = 'Generation Representative' AND k.`GenID` = gr.`ScopeId`
LEFT JOIN `a_data_role_assignment` ga ON ga.`RoleId` = gr.`RoleId` $generationDateWhere
LEFT JOIN `a_data_person` gp ON ga.`PersID` = gp.PersID
WHERE (k.`KursID` <> 999)
ORDER BY GenSort, k.`KursID`";
            $resultsCourseAssignments = $this->fetchSome ( null, $sqlCourseAssignments, null );

            $courseAssignments = array();
            foreach ($resultsCourseAssignments as $row) {
                $courseAssignments[] = array(
                    'courseId'                 => $this->filterDbId($row['KursID']),
                    'courseName'               => $row['KursName'],
                    'generationId'             => $this->filterDbId($row['GenID']),
                    'generationName'           => $row['GenName'],
                    'generationSort'           => $this->filterDbInt($row['GenSort']),
                    'leaderId'                 => $this->filterDbId($row['LeaderPersID']),
                    'leaderRoleId'             => $this->filterDbId($row['LeaderRoleId']),
                    'leaderAssignmentId'       => $this->filterDbId($row['LeaderAssignmentID']),
                    'leaderStartDate'          => $this->filterDbDate($row['GenerationStartDate']),
                    'leaderEndDate'            => $this->filterDbDate($row['GenerationEndDate']),
                    'leaderLastName'           => $row['LeaderPersName'],
                    'leaderFirstName'          => $row['LeaderPersVorname'],
                    'leaderDeathDate'          => $this->filterDbDate($row['LeaderPersTod']),
                    'leaderCountry'            => $row['LeaderPersUrsprLand'],
                    'generationRepId'          => $this->filterDbId($row['GenerationPersID']),
                    'generationRepRoleId'      => $this->filterDbId($row['GenerationRoleId']),
                    'generationRepAssignmentID'=> $this->filterDbId($row['GenerationAssignmentID']),
                    'generationRepStartDate'   => $this->filterDbDate($row['GenerationStartDate']),
                    'generationRepEndDate'     => $this->filterDbDate($row['GenerationEndDate']),
                    'generationRepLastName'    => $row['GenerationPersName'],
                    'generationRepFirstName'   => $row['GenerationPersVorname'],
                    'generationRepDeathDate'   => $this->filterDbDate($row['GenerationPersTod']),
                    'generationRepCountry'     => $row['GenerationPersUrsprLand'],
                );
            }
        } else {
            $filiationAssignments = null;
            $courseAssignments = null;
        }
        return array(
            'territoryAssignments' => $territoryAssignments,
            'filiationAssignments' => $filiationAssignments,
            'courseAssignments' => $courseAssignments
        );
    }

    /**
     * Get a simple list of roles in the database for use to fill form dropdowns
     *
     * @return array
     */
    public function getSimpleRoleList()
    {
        if ($this->simpleRoleListCache) {
            return $this->simpleRoleListCache;
        }
        $inFiliation = $this->getAssociatedScopesForInStatement('Filiation', true);
        $inTerritory = $this->getAssociatedScopesForInStatement('Territory', true);
        $sqlRoles = "SELECT r.`RoleId`,r.`RoleTitle`,r.`Scope`,r.`ScopeId`,r.`SinglePosition`,r.`Sort`, t.GebName AS ScopeName
FROM `a_data_role` r
INNER JOIN `a_data_gebiet` t ON (r.`Scope` IN $inTerritory) AND r.`ScopeId` = t.GebID
UNION
SELECT r.`RoleId`,r.`RoleTitle`,r.`Scope`,r.`ScopeId`,r.`SinglePosition`,r.`Sort`, k.KursName AS ScopeName
FROM `a_data_role` r
INNER JOIN `a_data_kurs` k ON r.`Scope` = 'Course' AND r.`ScopeId` = k.KursID
UNION
SELECT r.`RoleId`,r.`RoleTitle`,r.`Scope`,r.`ScopeId`,r.`SinglePosition`,r.`Sort`, gen.GenName AS ScopeName
FROM `a_data_role` r
INNER JOIN `a_data_generation` gen ON r.`Scope` = 'Generation' AND r.`ScopeId` = gen.GenID
UNION
SELECT r.`RoleId`,r.`RoleTitle`,r.`Scope`,r.`ScopeId`,r.`SinglePosition`,r.`Sort`, fil.FilName AS ScopeName
FROM `a_data_role` r
INNER JOIN `a_data_filiale` fil ON r.`Scope` IN $inFiliation AND r.`ScopeId` = fil.FilID
ORDER BY Sort, Scope, ScopeId";
        $resultsRoles = $this->fetchSome ( null, $sqlRoles, null );
        // process results
        $roles = array ();
        foreach ( $resultsRoles as $role ) {
            if (key_exists ( $role ['ScopeId'] . $role ['Scope'], $roles )) {
                array_push ( $roles [$role ['ScopeId'] . $role ['Scope']] ["roleTitles"], array (
                    "roleTitle" => $role ['RoleTitle'],
                    "roleId" => $role ['RoleId']
                ) );
            } else {
                $roles [$role ['ScopeId'] . $role ['Scope']] = array (
                    "scopeId" => $role ['ScopeId'],
                    "label" => $role ['ScopeName'],
                    "category" => $role ['Scope'],
                    "sort" => $role ['Sort'],
                    "roleTitles" => array (
                        array (
                            "roleTitle" => $role ['RoleTitle'],
                            "roleId" => $role ['RoleId']
                        )
                    )
                );
            }
        }
        $this->simpleRoleListCache = $roles;
        return $roles;
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

    public function getMainRoles()
    {
        if ($this->mainRolesCache) {
            return $this->mainRolesCache;
        }
        $roles = $this->getRoles();
        $return = array();
        foreach ($roles as $role) {
            if ($role['isMainRole']) {
                $return[$role['scope'].$role['scopeId']] = $role;
            }
        }
        $this->mainRolesCache = $return;
        return $return;
    }

    /**
     * Get the main role associated with the entity whose id is passed
     * @param int $id
     * @param string $simpleScope
     */
    public function getMainRole($id, $simpleScope)
    {
        if (!id) {
            throw new \InvalidArgumentException('Invalid id provided.');
        }
        $roles = $this->getMainRoles();
        $in = $this->getAssociatedScopesForInStatement($simpleScope);
        foreach ($in as $scope) {
            if (isset($roles[$scope.$id])) {
                return $roles[$scope.$id];
            }
        }
        return null;
    }

    /**
     * Inserts new roles for a newly created entity
     * Returns the number of roles inserted
     * @param string $scope
     * @param string|int $scopeId
     * @return int
     */
    public function createAssociatedRoles($scope, $scopeId)
    {
        if (!$scopeId || $scopeId == 0 || $scopeId == '') {
            throw new \Exception('Invalid scope id argument passed.');
        }
        static $scopeRoles = array(
            'Course' => array(
                array('RoleTitle' => 'Course Leader', 'SinglePosition' => true, 'Sort' => 127, 'MainRole' => true),
            ),
            'Generation' => array(
                array('RoleTitle' => 'Generation Representative', 'SinglePosition' => true, 'Sort' => 127, 'MainRole' => true),
            ),
            'Filiation' => array(
                array('RoleTitle' => 'Rector', 'SinglePosition' => true, 'Sort' => 127, 'MainRole' => true),
            ),
            'Novitiate' => array(
                array('RoleTitle' => 'Novice Master', 'SinglePosition' => true, 'Sort' => 100, 'MainRole' => true),
                array('RoleTitle' => 'Sozius', 'SinglePosition' => false, 'Sort' => 200, 'MainRole' => false),
            ),
            'Scholasticate' => array(
                array('RoleTitle' => 'Rector', 'SinglePosition' => true, 'Sort' => 100, 'MainRole' => true),
                array('RoleTitle' => 'Formator', 'SinglePosition' => false, 'Sort' => 200, 'MainRole' => false),
            ),
            'Province' => array(
                array('RoleTitle' => 'Provincial Superior', 'SinglePosition' => true, 'Sort' => 20, 'MainRole' => true),
                array('RoleTitle' => 'Provincial First Councelor', 'SinglePosition' => true, 'Sort' => 21, 'MainRole' => false),
                array('RoleTitle' => 'Provincial Councelor', 'SinglePosition' => false, 'Sort' => 25, 'MainRole' => false),
                array('RoleTitle' => 'Provincial Treasurer', 'SinglePosition' => true, 'Sort' => 28, 'MainRole' => false),
            ),
            'Region' => array(
                array('RoleTitle' => 'Regional Superior', 'SinglePosition' => true, 'Sort' => 30, 'MainRole' => true),
                array('RoleTitle' => 'Regional First Councelor', 'SinglePosition' => true, 'Sort' => 31, 'MainRole' => false),
                array('RoleTitle' => 'Regional Councelor', 'SinglePosition' => false, 'Sort' => 35, 'MainRole' => false),
                array('RoleTitle' => 'Regional Treasurer', 'SinglePosition' => true, 'Sort' => 38, 'MainRole' => false),
            ),
            'Delegation' => array(
                array('RoleTitle' => 'Delegate Superior', 'SinglePosition' => true, 'Sort' => 40, 'MainRole' => true),
                array('RoleTitle' => 'Delegation First Councelor', 'SinglePosition' => true, 'Sort' => 41, 'MainRole' => false),
                array('RoleTitle' => 'Delegation Councelor', 'SinglePosition' => false, 'Sort' => 45, 'MainRole' => false),
                array('RoleTitle' => 'Delegation Treasurer', 'SinglePosition' => true, 'Sort' => 48, 'MainRole' => false),
            ),
        );
        if (!key_exists($scope, $scopeRoles)) {
            return 0;
        }
        $i=0;
        foreach ($scopeRoles[$scope] as $role) {
            $params = $role;
            $params['ScopeId'] = $scopeId;
            $params['Scope'] = $scope;
            $this->getRoleTableGateway()->insert($params);
            $i++;
        }
        return $i;
    }

    public function getAssociatedScopesForInStatement($entityType, $preformatForSql = false)
    {
        $types = array(
            'Filiation' => array('Filiation', 'Quasifiliation', 'Novitiate', 'Scholasticate'),
            'Territory' => array('Community', 'Formation', 'Territory', 'Province', 'Delegation', 'Region'),
            'Course' => array('Course'),
            'Generation' => array('Generation'),
        );
        if (!isset($types[$entityType])) {
            throw new \InvalidArgumentException('Invalid entity type passed.');
        }
        if ($preformatForSql) {
            return "('".implode("', '", $types[$entityType])."')";
        }
        return $types[$entityType];
    }

    public function getChanges()
    {
        $sql = "SELECT ChangeID, ChangedTable, ChangedColumn, ChangedIDValue, NewValue, OldValue,
UpdateDateTime, IpAddress, user_id
FROM a_data_changes
ORDER BY UpdateDateTime DESC
LIMIT 200";
        $resultsChanges = $this->fetchSome ( null, $sql, null );

        $tz = new \DateTimeZone('UTC');
        $results = array();
        foreach ($resultsChanges as $row) {
            $user = $row['user_id'] ? $this->getUserTable()->getUser($row['user_id']) : null;
            $thisRow = array(
                'changeId' => $row['ChangeID'],
                'table' => $row['ChangedTable'],
                'column' => $row['ChangedColumn'],
                'newValue' => $row['NewValue'],
                'oldValue' => $row['OldValue'],
                'updateDateTime' => new \DateTime($row['UpdateDateTime'], $tz),
                'ipAddress' => $row['IpAddress'],
                'user' => $user,
            );
            $matchedEntity = true; //assume we'll find a known entity
            switch ($row['ChangedTable']) {
                case 'a_data_filiale':
                    $thisRow['scope'] = 'filiation';
                    $thisRow['object'] = $this->getSimpleFiliation($row['ChangedIDValue']);
                    //@todo what if its been deleted
                    break;
                case 'a_data_haus':
                    $thisRow['scope'] = 'house';
                    $thisRow['object'] = $this->getSimpleHouse($row['ChangedIDValue']);
                    //@todo what if its been deleted
                    break;
                case 'a_data_role_assignment':
                    $assignment = $this->getAssignment($row['ChangedIDValue']);
                    $thisRow['scope'] = 'assignment';
                    if ($assignment) {
                       $thisRow['object'] = $assignment;
                    } else {
                        $thisRow['object'] = [
                            'assignmentId' => $row['ChangedIDValue'],
                            'assignmentName' => 'Assignment ID: ' .$row['ChangedIDValue'],
                            'noLink' => true,
                        ];
                    }
                    break;
                case 'a_data_person':
                    $thisRow['scope'] = 'person';
                    $person = $this->getPerson($row['ChangedIDValue']);
                    if ($person) {
                       $thisRow['object'] = $person;
                    } else {
                        $thisRow['object'] = [
                            'personId' => $row['ChangedIDValue'],
                            'personFullName' => 'Person ID: '.$row['ChangedIDValue'],
                            'noLink' => true,
                        ];
                    }
                    break;
                case 'a_data_kurs':
                    $thisRow['scope'] = 'course';
                    $course = $this->getSimpleCourse($row['ChangedIDValue']);
                    if ($course) {
                       $thisRow['object'] = $course;
                    } else {
                        $thisRow['object'] = [
                            'courseId' => $row['ChangedIDValue'],
                            'courseName' => 'Course ID: '.$row['ChangedIDValue'],
                            'noLink' => true,
                        ];
                    }
                    break;
                case 'a_data_person_living':
                    $thisRow['scope'] = 'living-situation';
                    $livingSituation = $this->getLivingSituation($row['ChangedIDValue']);
                    if ($livingSituation) {
                        $livingSituation['livingSituationName'] = $this->getLivingSituationName($row['ChangedIDValue']);
                       $thisRow['object'] = $livingSituation;
                    } else {
                        $thisRow['object'] = [
                            'livingSituationId' => $row['ChangedIDValue'],
                            'livingSituationName' => 'Living ID:'.$row['ChangedIDValue'],
                            'noLink' => true,
                        ];
                    }
                    break;
                default:
                    $matchedEntity = false;
                    $thisRow['id'] = $row['ChangedIDValue'];
                    continue;
            }
            if ($matchedEntity) {
                $results[] = $thisRow;
            }
        }
        return $results;
    }

    public function getProblems($minimumSeverity = EntityProblem::SEVERITY_INFO)
    {
        return $this->getPersonProblems($minimumSeverity);
        array_merge($this->getPersonProblems($minimumSeverity), $this->getCourseProblems($minimumSeverity));
    }

    public function getCourseProblems($minimumSeverity = EntityProblem::SEVERITY_INFO )
    {
        $courses = $this->getCourses();

        $problems = [];
        foreach ($courses as $courseId => $course) {
            if ($course) {
                $problems[] = new PersonProblem(PersonProblem::PERSON_NO_EMAIL, $course);
            }
        }
        return $problems;
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
}
