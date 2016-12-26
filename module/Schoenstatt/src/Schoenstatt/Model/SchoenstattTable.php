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
     * Patres config
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
     * @var TableGateway $assignmentTableGateway
     */
    protected $assignmentTableGateway;

    /**
     *
     * @var TableGateway $assignmentTableGateway
     */
    protected $roleTableGateway;

    /**
     *
     * @var TableGateway $assignmentTableGateway
     */
    protected $filiationTableGateway;

    /**
     *
     * @var TableGateway $assignmentTableGateway
     */
    protected $houseTableGateway;

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
    /**
     *
     * @var TableGateway $personTableGateway
     */
    protected $personTableGateway;

    /**
     *
     * @var TableGateway $searchTableGateway
     */
    protected $searchTableGateway;

    protected $simpleRoleListCache;

    protected $rolesCache;

    protected $personsCache;

    protected $emailAddressesCache;

    protected $mailingsCache;

    protected $mainRolesCache;

    protected $assignmentsCache;

    protected $roleTitleAliasesCache;

    protected $usedCountries = [];

    /**
     * To fill the Select of the Edit Filiation form
     *
     */
    public function getTerritoryValueOptions($includeInactive = false)
    {
        $sql = "SELECT g.`GebID`, g.`GebName`, g.`Active`
FROM `a_data_gebiet` g
ORDER BY `GebName`";
        $resultsGeb = $this->fetchSome(null, $sql);
        $return = array();
        foreach ($resultsGeb as $geb) {
            if ($includeInactive || $this->filterDbBool($geb['Active'])) {
                $return[$geb['GebID']] = $geb['GebName'];
            }
        }
        return $return;
    }

    /**
     * Gets a simple key => value array of the generation
     * @param bool $includeInactive
     */
    public function getFiliationValueOptions($includeInactive = false, $onlyFormation = false)
    {
        $where = "";
        if ($onlyFormation) {
            $where = 'WHERE (f.GebID = 19)';
        }
        $sqlFiliations = "SELECT f.`FilID`, f.`Category`, f.`FilName`, f.`Active`, f.`Country`
FROM `a_data_filiale` f
$where
ORDER BY `Country`, `FilName`";

        $resultsFiliations = $this->fetchSome ( null, $sqlFiliations, null );
        $result = array();
        foreach ($resultsFiliations as $fil) {
            if ($includeInactive || ($fil['Active'] != 'false' && $fil['Active'] != '0' && !is_null($fil['Active']))) { //put it in
                $label = $fil['Country'].' '.$fil['FilName'];
                if ($fil['Category'] != 'Filiation') {
                    $label .= ' ('.$fil['Category'].')';
                }
                $result[$fil['FilID']] = $label;
            }
        }
        return $result;
    }

    /**
     * Gets a simple key => value array of the generation
     * @param bool $includeInactive
     */
    public function getHouseValueOptions($includeInactive = false)
    {
        $housesSql = "SELECT `HausID`, `HausName`, `HausLand`, `HausAktiv`
FROM `a_data_haus` h";

        $resultHouses = $this->fetchSome ( null, $housesSql, null );

        $sort = array();
        foreach($resultHouses as $k=>$v) {
            $sort['HausAktiv'][$k] = $v['HausAktiv'];
            $sort['HausLand'][$k] = $v['HausLand'];
            $sort['HausName'][$k] = $v['HausName'];
        }
        # sort by event_type desc and then title asc
        array_multisort($sort['HausAktiv'], SORT_DESC, $sort['HausLand'], SORT_ASC, $sort['HausName'], SORT_ASC, $resultHouses);

        $result = array();
        foreach ($resultHouses as $house) {
            if ($includeInactive || ($house['HausAktiv'] != '0')) {
                $result[$this->filterDbId($house['HausID'])] = $house['HausLand']. ' '.$house['HausName'];
            }
        }
        return $result;
    }

    /**
     * Gets a simple key => value array of the generation
     * @param bool $includeInactive
     */
    public function getMainHouseValueOptions($filiationId)
    {
        $houses = $this->getFiliationHouses($filiationId);
        if (!$houses || empty($houses)) {
            return null;
        }
        $result = array();
        foreach ($houses as $house) {
            $label = $house['country'].' '.$house['houseName'];
            if (!$house['active']) {
                $label .= ' (Inactive)';
            }
            $result[$house['houseId']] = $label;
        }
        return $result;
    }

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
     * @param bool $includeInactive
     */
    public function getGenerationValueOptions($includeInactive = false)
    {
        $sql = "SELECT `GenID`, `GenName` FROM `a_data_generation`";
        $resultsGenerations = $this->fetchSome(null, $sql, null);
        $result = array();
        foreach ($resultsGenerations as $gen) {
            $result[$gen['GenID']] = $gen['GenName'];
        }
        return $result;
    }


    /**
     * Gets a simple key => value array of the course
     */
    public function getCourseValueOptions()
    {
        $sql = "SELECT `KursID`, `KursName` FROM `a_data_kurs`";
        $resultCourses = $this->fetchSome(null, $sql, null);
        $result = array();
        foreach ($resultCourses as $course) {
            $result[$course['KursID']] = $course['KursName'];
        }
        return $result;
    }

    /**
     * Gets a simple key => value array of the role titles including ones from getRoleTitleAliases
     */
    public function getRoleTitleValueOptions()
    {
        $roleTitles = $this->getRoleTitles();
        $result = array();
        foreach ($roleTitles as $title) {
            $result[$title] = $title;
        }
        return $result;
    }

    /**
     * Get an associative array in format:
     * array(
     *     'realRoleTitle' => array('alias', 'alias2'),
     *     'realRoleTitle2' => array('alias', 'alias3'),
     * );
     */
    public function getRoleTitles()
    {
        $sql = "SELECT r.RoleTitle AS Title, 0 AS Popular
FROM `a_data_role` r
UNION
SELECT a.RoleTitleAlias, a.PopularAlias AS Popular
FROM `a_data_role` r
LEFT JOIN `a_data_role_alias` a ON r.RoleTitle = a.RoleTitle";
        $resultsRoleTitles = $this->fetchSome(null, $sql, null);
        //sort results
        $sort = array();
        foreach($resultsRoleTitles as $k=>$v) {
            $sort['Title'][$k] = $v['Title'];
            $sort['Popular'][$k] = $v['Popular'];
        }
        # sort by event_type desc and then title asc
        array_multisort($sort['Popular'], SORT_DESC, $sort['Title'], SORT_ASC, $resultsRoleTitles);

        //weed out duplicates
        $return = array();
        foreach ($resultsRoleTitles as $row) {
            if ($row['Title'] && !key_exists($row['Title'], $return)) {
               $return[] = $row['Title'];
            }
        }
        return $return;
    }

    public function getAllRoleTitleAliases()
    {
        if ($this->roleTitleAliasesCache) {
            return $this->roleTitleAliasesCache;
        }
        $sql = "SELECT a.RoleTitle, a.RoleTitleAlias, a.PopularAlias AS Popular
FROM `a_data_role_alias` a";
        $resultsRoleTitles = $this->fetchSome(null, $sql, null);

        $return = array();
        foreach ($resultsRoleTitles as $row) {
            $record = array(
                'alias' => $row['RoleTitleAlias'],
                'popular' => $this->filterDbBool($row['Popular']),
            );
            if (key_exists($row['RoleTitle'], $return)) {
                $return[$row['RoleTitle']][] = $record;
            } else {
                $return[$row['RoleTitle']] = array($record);
            }
        }
        $this->roleTitleAliasesCache = $return;
        return $return;
    }

    /**
     * Returns a list of the aliases of a given roleTitle,
     * if none exist, null is returned
     * @param string $roleTitle
     * @return null|array
     */
    public function getRoleTitleAliases($roleTitle)
    {
        $aliases = $this->getAllRoleTitleAliases();
        if (isset($aliases[$roleTitle]) && is_array($aliases[$roleTitle])) {
            return $aliases[$roleTitle];
        }
        return null;
    }

    /**
     * Get an associative array of properties of the course
     * @param string|int $id
     */
    public function getSimpleCourse($id)
    {
        $sqlCourse = "SELECT k.`KursID`, k.`KursName`, k.`KursAbk`,
k.`KursNoviziatBeginn`, k.`KursWeihetag`, k.`KursNote`, k.`GenID`, k.`NoviceMaster`,
`NovitiateFiliation`, `ScholasticateFiliation`, k.`FirstTertianshipMaster`,
k.`SecondTertianshipMaster`, k.`AdminNotes`, k.`IdealEnglish`, k.`IdealSpanish`,
k.`IdealGerman`, k.`IdealPortuguese`, k.`IdealFrench`, k.`ScholasticateRector1`,
k.`ScholasticateRector2`, k.`ScholasticateRector3`, k.`Wall`, k.`WallUpdatedOn`,
k.`WallUpdatedBy`, k.`NotesUpdatedOn`, k.`NotesUpdatedBy`, k.`AdminNotesUpdatedOn`,
k.`AdminNotesUpdatedBy`, k.`UpdatedOn`, g.`GenName`, k.`IdealConsecrationDate`,
nm.PersID AS NMPersID, nm.PersName AS NMPersName, nm.PersVorname AS NMPersVorname, nm.PersTod AS NMPersTod, nm.PersUrsprLand AS NMPersUrsprLand,
sr1.PersID AS SR1PersID, sr1.PersName AS SR1PersName, sr1.PersVorname AS SR1PersVorname, sr1.PersTod AS SR1PersTod, sr1.PersUrsprLand AS SR1PersUrsprLand,
sr2.PersID AS NMPersID, sr2.PersName AS SR2PersName, sr2.PersVorname AS SR2PersVorname, sr2.PersTod AS SR2PersTod, sr2.PersUrsprLand AS SR2PersUrsprLand,
sr3.PersID AS SR3PersID, sr3.PersName AS SR3PersName, sr3.PersVorname AS SR3PersVorname, sr3.PersTod AS SR3PersTod, sr3.PersUrsprLand AS SR3PersUrsprLand,
ft.PersID AS FTPersID, ft.PersName AS FTPersName, ft.PersVorname AS FTPersVorname, ft.PersTod AS FTPersTod, ft.PersUrsprLand AS FTPersUrsprLand,
st.PersID AS STPersID, st.PersName AS STPersName, st.PersVorname AS STPersVorname, st.PersTod AS STPersTod, st.PersUrsprLand AS STPersUrsprLand,
sz.PersID AS STPersID, sz.PersName AS SZPersName, sz.PersVorname AS SZPersVorname, sz.PersTod AS SZPersTod, sz.PersUrsprLand AS SZPersUrsprLand,
nf.`FilName` AS NovitiateFilName, nf.`Country` AS NovitiateFilCountry,
sf.`FilName` AS Scholasticate1FilName, sf.`Country` AS Scholasticate1FilCountry,
k.`ScholasticateFiliation2`, k.`FirstTertianshipFiliation`, k.`SecondTertianshipFiliation`, k.`SionzeitFiliation`,
k.`FirstTertianshipStartDate`, k.`FirstTertianshipEndDate`, k.`SecondTertianshipStartDate`, k.`SecondTertianshipEndDate`,
k.`SionzeitCoordinator`, k.`SionzeitStartDate`, k.`SionzeitEndDate`,
sf2.`FilName` AS Scholasticate2FilName, sf2.`Country` AS Scholasticate2FilCountry,
ftf.`FilName` AS FirstTertianshipFilName, ftf.`Country` AS FirstTertianshipFilCountry,
stf.`FilName` AS SecondTertianshipFilName, stf.`Country` AS SecondTertianshipFilCountry,
szf.`FilName` AS SionzeitFilName, szf.`Country` AS SionzeitFilCountry
FROM `a_data_kurs` k
LEFT JOIN `a_data_generation` g ON k.`GenID` = g.`GenID`
LEFT JOIN `a_data_person` nm ON k.`NoviceMaster` = nm.`PersID`
LEFT JOIN `a_data_person` sr1 ON k.`ScholasticateRector1` = sr1.`PersID`
LEFT JOIN `a_data_person` sr2 ON k.`ScholasticateRector2` = sr2.`PersID`
LEFT JOIN `a_data_person` sr3 ON k.`ScholasticateRector3` = sr3.`PersID`
LEFT JOIN `a_data_person` ft ON k.`FirstTertianshipMaster` = ft.`PersID`
LEFT JOIN `a_data_person` st ON k.`SecondTertianshipMaster` = st.`PersID`
LEFT JOIN `a_data_person` sz ON k.`SionzeitCoordinator` = sz.`PersID`
LEFT JOIN `a_data_filiale` nf ON nf.`FilID` = k.`NovitiateFiliation`
LEFT JOIN `a_data_filiale` sf ON sf.`FilID` = k.`ScholasticateFiliation`
LEFT JOIN `a_data_filiale` sf2 ON sf2.`FilID` = k.`ScholasticateFiliation2`
LEFT JOIN `a_data_filiale` ftf ON ftf.`FilID` = k.`FirstTertianshipFiliation`
LEFT JOIN `a_data_filiale` stf ON stf.`FilID` = k.`SecondTertianshipFiliation`
LEFT JOIN `a_data_filiale` szf ON szf.`FilID` = k.`SionzeitFiliation`
WHERE (k.`KursID` = ?)";
        $params = array($id);
        $resultsCourse = $this->fetchSome(null, $sqlCourse, $params);
        if (count($resultsCourse) != 1) { //we either didn't find it or got 2, weird
            throw new \Exception("Course not found.");
        }
        $scholasticateRectors = array();
        if ($this->filterDbId($resultsCourse[0]['ScholasticateRector1'])) {
            $scholasticateRectors[] = array(
                'personId'     => $this->filterDbId($resultsCourse[0]['ScholasticateRector1']),
                'firstName'    => $resultsCourse[0]['SR1PersVorname'],
                'lastName'     => $resultsCourse[0]['SR1PersName'],
                'deathDate'    => $this->filterDbDate($resultsCourse[0]['SR1PersTod']),
                'country'      => $resultsCourse[0]['SR1PersUrsprLand'],
            );
        }
        if ($this->filterDbId($resultsCourse[0]['ScholasticateRector2'])) {
            $scholasticateRectors[] = array(
                'personId'     => $this->filterDbId($resultsCourse[0]['ScholasticateRector2']),
                'firstName'    => $resultsCourse[0]['SR2PersVorname'],
                'lastName'     => $resultsCourse[0]['SR2PersName'],
                'deathDate'    => $this->filterDbDate($resultsCourse[0]['SR2PersTod']),
                'country'      => $resultsCourse[0]['SR2PersUrsprLand'],
            );
        }
        if ($this->filterDbId($resultsCourse[0]['ScholasticateRector3'])) {
            $scholasticateRectors[] = array(
                'personId'     => $this->filterDbId($resultsCourse[0]['ScholasticateRector3']),
                'firstName'    => $resultsCourse[0]['SR3PersVorname'],
                'lastName'     => $resultsCourse[0]['SR3PersName'],
                'deathDate'    => $this->filterDbDate($resultsCourse[0]['SR3PersTod']),
                'country'      => $resultsCourse[0]['SR3PersUrsprLand'],
            );
        }
        $scholasticateFiliations = array();
        if ($this->filterDbId($resultsCourse[0]['ScholasticateFiliation'])) {
            $scholasticateFiliations[] = array(
                'filiationId'      => $this->filterDbId($resultsCourse[0]['ScholasticateFiliation']),
                'filiationName'    => $resultsCourse[0]['Scholasticate1FilName'],
                'country'          => $resultsCourse[0]['Scholasticate1FilCountry'],
            );
        }
        if ($this->filterDbId($resultsCourse[0]['ScholasticateFiliation2'])) {
            $scholasticateFiliations[] = array(
                'filiationId'      => $this->filterDbId($resultsCourse[0]['ScholasticateFiliation2']),
                'filiationName'    => $resultsCourse[0]['Scholasticate2FilName'],
                'country'          => $resultsCourse[0]['Scholasticate2FilCountry'],
            );
        }

        $enIdeal = $this->filterDbString($resultsCourse[0]['IdealEnglish']);
        $esIdeal = $this->filterDbString($resultsCourse[0]['IdealSpanish']);
        $ptIdeal = $this->filterDbString($resultsCourse[0]['IdealPortuguese']);
        $frIdeal = $this->filterDbString($resultsCourse[0]['IdealFrench']);
        $deIdeal = $this->filterDbString($resultsCourse[0]['IdealGerman']);
        $idealLanguages = [];
        if (!is_null($enIdeal)) $idealLanguages['en'] = 'enIdeal';
        if (!is_null($esIdeal)) $idealLanguages['es'] = 'esIdeal';
        if (!is_null($deIdeal)) $idealLanguages['de'] = 'deIdeal';
        if (!is_null($ptIdeal)) $idealLanguages['pt'] = 'ptIdeal';
        if (!is_null($frIdeal)) $idealLanguages['fr'] = 'frIdeal';
        //manipulate column names
        $result = array(
            'courseId'                         => $this->filterDbId($resultsCourse[0]['KursID']),
            'courseName'                       => $this->filterDbString($resultsCourse[0]['KursName']),
            'generationId'                     => $this->filterDbId($resultsCourse[0]['GenID']),
            'generationName'                   => $this->filterDbString($resultsCourse[0]['GenName']),
            'celebrationDate'                  => $this->filterDbDate($resultsCourse[0]['KursWeihetag']),
            'idealConsecrationDate'            => $this->filterDbDate($resultsCourse[0]['IdealConsecrationDate']),
            'novitiateStartDate'               => $this->filterDbDate($resultsCourse[0]['KursNoviziatBeginn']),
            'noviceMaster'                     => $this->filterDbId($resultsCourse[0]['NoviceMaster']),
            'noviceMasterFirstName'            => $this->filterDbString($resultsCourse[0]['NMPersVorname']),
            'noviceMasterLastName'             => $this->filterDbString($resultsCourse[0]['NMPersName']),
            'noviceMasterDeathDate'            => $this->filterDbDate($resultsCourse[0]['NMPersTod']),
            'noviceMasterCountry'              => $this->filterDbString($resultsCourse[0]['NMPersUrsprLand']),
            'novitiateFiliation'               => $this->filterDbId($resultsCourse[0]['NovitiateFiliation']),
            'novitiateFiliationName'           => $this->filterDbString($resultsCourse[0]['NovitiateFilName']),
            'novitiateFiliationCountry'        => $this->filterDbString($resultsCourse[0]['NovitiateFilCountry']),
            'scholasticateRectors'             => $scholasticateRectors,
            'scholasticateRector1'             => $this->filterDbId($resultsCourse[0]['ScholasticateRector1']),
            'scholasticateRector1FirstName'    => $resultsCourse[0]['SR1PersVorname'],
            'scholasticateRector1LastName'     => $resultsCourse[0]['SR1PersName'],
            'scholasticateRector1DeathDate'    => $this->filterDbDate($resultsCourse[0]['SR1PersTod']),
            'scholasticateRector1Country'      => $resultsCourse[0]['SR1PersUrsprLand'],
            'scholasticateRector2'             => $this->filterDbId($resultsCourse[0]['ScholasticateRector2']),
            'scholasticateRector2FirstName'    => $resultsCourse[0]['SR2PersVorname'],
            'scholasticateRector2LastName'     => $resultsCourse[0]['SR2PersName'],
            'scholasticateRector2DeathDate'    => $this->filterDbDate($resultsCourse[0]['SR2PersTod']),
            'scholasticateRector2Country'      => $resultsCourse[0]['SR2PersUrsprLand'],
            'scholasticateRector3'             => $this->filterDbId($resultsCourse[0]['ScholasticateRector3']),
            'scholasticateRector3FirstName'    => $resultsCourse[0]['SR3PersVorname'],
            'scholasticateRector3LastName'     => $resultsCourse[0]['SR3PersName'],
            'scholasticateRector3DeathDate'    => $this->filterDbDate($resultsCourse[0]['SR3PersTod']),
            'scholasticateRector3Country'      => $resultsCourse[0]['SR3PersUrsprLand'],
            'scholasticateFiliations'          => $scholasticateFiliations,
            'scholasticateFiliation1'          => $this->filterDbId($resultsCourse[0]['ScholasticateFiliation']),
            'scholasticateFiliation1Name'      => $resultsCourse[0]['Scholasticate1FilName'],
            'scholasticateFiliation1Country'   => $resultsCourse[0]['Scholasticate1FilCountry'],
            'scholasticateFiliation2'          => $this->filterDbId($resultsCourse[0]['ScholasticateFiliation2']),
            'scholasticateFiliation2Name'      => $resultsCourse[0]['Scholasticate2FilName'],
            'scholasticateFiliation2Country'   => $resultsCourse[0]['Scholasticate2FilCountry'],
            'firstTertianshipFiliation'        => $this->filterDbId($resultsCourse[0]['FirstTertianshipFiliation']),
            'firstTertianshipFiliationName'    => $resultsCourse[0]['FirstTertianshipFilName'],
            'firstTertianshipFiliationCountry' => $resultsCourse[0]['FirstTertianshipFilCountry'],
            'firstTertianshipStartDate'        => $this->filterDbDate($resultsCourse[0]['FirstTertianshipStartDate']),
            'firstTertianshipEndDate'          => $this->filterDbDate($resultsCourse[0]['FirstTertianshipEndDate']),
            'firstTertianshipMaster'           => $this->filterDbId($resultsCourse[0]['FirstTertianshipMaster']),
            'firstTertianshipMasterFirstName'  => $resultsCourse[0]['FTPersVorname'],
            'firstTertianshipMasterLastName'   => $resultsCourse[0]['FTPersName'],
            'firstTertianshipMasterDeathDate'  => $this->filterDbDate($resultsCourse[0]['FTPersTod']),
            'firstTertianshipMasterCountry'    => $resultsCourse[0]['FTPersUrsprLand'],
            'secondTertianshipFiliation'       => $this->filterDbId($resultsCourse[0]['SecondTertianshipFiliation']),
            'secondTertianshipFiliationName'   => $resultsCourse[0]['SecondTertianshipFilName'],
            'secondTertianshipFiliationCountry'=> $resultsCourse[0]['SecondTertianshipFilCountry'],
            'secondTertianshipStartDate'       => $this->filterDbDate($resultsCourse[0]['SecondTertianshipStartDate']),
            'secondTertianshipEndDate'         => $this->filterDbDate($resultsCourse[0]['SecondTertianshipEndDate']),
            'secondTertianshipMaster'          => $this->filterDbId($resultsCourse[0]['SecondTertianshipMaster']),
            'secondTertianshipMasterFirstName' => $resultsCourse[0]['STPersVorname'],
            'secondTertianshipMasterLastName'  => $resultsCourse[0]['STPersName'],
            'secondTertianshipMasterDeathDate' => $this->filterDbDate($resultsCourse[0]['STPersTod']),
            'secondTertianshipMasterCountry'   => $resultsCourse[0]['STPersUrsprLand'],
            'sionzeitStartDate'                => $this->filterDbDate($resultsCourse[0]['SionzeitStartDate']),
            'sionzeitEndDate'                  => $this->filterDbDate($resultsCourse[0]['SionzeitEndDate']),
            'sionzeitCoordinator'              => $this->filterDbId($resultsCourse[0]['SionzeitCoordinator']),
            'sionzeitCoordinatorFirstName'     => $resultsCourse[0]['SZPersVorname'],
            'sionzeitCoordinatorLastName'      => $resultsCourse[0]['SZPersName'],
            'sionzeitCoordinatorDeathDate'     => $this->filterDbDate($resultsCourse[0]['SZPersTod']),
            'sionzeitCoordinatorCountry'       => $resultsCourse[0]['SZPersUrsprLand'],
            'sionzeitFiliation'                => $this->filterDbId($resultsCourse[0]['SionzeitFiliation']),
            'sionzeitFiliationName'            => $resultsCourse[0]['SionzeitFilName'],
            'sionzeitFiliationCountry'         => $resultsCourse[0]['SionzeitFilCountry'],
            'adminNotes'                       => $this->filterDbString($resultsCourse[0]['AdminNotes']),
            'adminNotesUpdatedOn'              => $this->filterDbDate($resultsCourse[0]['AdminNotesUpdatedOn']),
            'adminNotesUpdatedBy'              => $this->filterDbId($resultsCourse[0]['AdminNotesUpdatedBy']),
            'enIdeal'                          => $enIdeal,
            'esIdeal'                          => $esIdeal,
            'ptIdeal'                          => $ptIdeal,
            'frIdeal'                          => $frIdeal,
            'deIdeal'                          => $deIdeal,
            'idealLanguages'                   => $idealLanguages,
            'wall'                             => $this->filterDbString($resultsCourse[0]['Wall']),
            'wallUpdatedOn'                    => $this->filterDbDate($resultsCourse[0]['WallUpdatedOn']),
            'wallUpdatedBy'                    => $this->filterDbId($resultsCourse[0]['WallUpdatedBy']),
            'publicNotes'                      => $this->filterDbString($resultsCourse[0]['KursNote']),
            'publicNotesUpdatedOn'             => $this->filterDbDate($resultsCourse[0]['NotesUpdatedOn']),
            'publicNotesUpdatedBy'             => $this->filterDbId($resultsCourse[0]['NotesUpdatedBy']),
            'updatedOn'                        => $this->filterDbDate($resultsCourse[0]['UpdatedOn']),
            'associatedRoles'                  => $this->getAssociatedRoles($id, 'Course')
        );
        return $result;
    }

    /**
     *
     * @param int|string $id
     */
    public function getCourse($id)
    {
        //get course data (with generation)
        $course = $this->getSimpleCourse($id);

        //get course roles
        $sqlRoles = "SELECT r.`RoleId`, r.`RoleTitle`,r.`Scope`,r.`ScopeId`,
r.`SinglePosition`,r.`Sort`, a.`StartDate`, a.`EndDate`, a.`AssignmentId`,
p.`PersID`, p.`PersVorname`, p.`PersName`, p.`PersTod`, p.`PersAus`, p.`PersUrsprLand`
FROM `a_data_role` r
LEFT JOIN `a_data_role_assignment` a ON a.RoleId = r.RoleId
LEFT JOIN `a_data_person` p ON a.`PersID` = p.`PersID`
WHERE (r.`Scope` = 'Course') AND (r.`ScopeId` = ?)";
        $params = [$id];
        $resultsRoles = $this->fetchSome(null, $sqlRoles, $params);
        $roles = [];
        foreach ($resultsRoles as $role) {
            $timeZone = new \DateTimeZone('UTC');
            $now = new \DateTime(null, $timeZone);
            $startDate = $this->filterDbDate($role['StartDate']);
            $endDate = $this->filterDbDate($role['EndDate']);
            $active = ($startDate < $now && (is_null($endDate) || $endDate > $now)) || (is_null($startDate) && (is_null($endDate) || $endDate > $now));

            array_push($roles, array(
                'roleId' => $this->filterDbId($role['RoleId']),
                'roleTitle' => $role['RoleTitle'],
                'scope' => $role['Scope'],
                'isSinglePosition' => $this->filterDbBool($role['SinglePosition']),
                'sort' => $role['Sort'],
                'assignmentId' => $this->filterDbId($role['AssignmentId']),
                'assignmentPersonId' => $this->filterDbId($role['PersID']),
                'assignmentStartDate' => $startDate,
                'assignmentEndDate' => $endDate,
                'assignmentFirstName' => $role['PersVorname'],
                'assignmentLastName' => $role['PersName'],
                'assignmentCountry' => $role['PersUrsprLand'],
                'assignmentDeathDate' => $this->filterDbDate($role['PersTod']),
                'assignmentDeceased' => $role['PersTod'] != '0000-00-00',
                'active' => $active
            ));
        }
        if (!empty($roles)) {//sort list
            $sort = [];
            foreach($roles as $k=>$v) {
                $sort['active'][$k] = $v['active'];
                $sort['sort'][$k] = $v['sort'];
                $sort['assignmentStartDate'][$k] = $v['assignmentStartDate'];
            }
            # sort by event_type desc and then title asc
            array_multisort($sort['active'], SORT_DESC, $sort['sort'], SORT_ASC, $sort['assignmentStartDate'], SORT_DESC, $roles);
        }
        $course['roles'] = $roles;

        //get course persons
        $query = [
            'deceased'     => true,
            'course'       => $id,
            'exMembers'    => true,
        ];
        $persons = $this->searchPersons($query);

        $course['persons'] = $persons;

        return $course;
    }

    public function getCourses()
    {
        $sqlCourses = "SELECT k.`KursID`, k.`KursName`, k.`KursAbk`, k.`KursNoviziatBeginn`, k.`KursWeihetag`, k.`KursNote`, k.`GenID`, g.GenName,
k.NoviceMaster, k.NovitiateFiliation, k.ScholasticateFiliation, k.FirstTertianshipMaster, k.SecondTertianshipMaster, k.AdminNotes,
(SELECT MAX(maxp.PersGeburtstag) FROM `a_data_person` maxp WHERE (
    maxp.PersKursID = k.KursID AND maxp.PersGeburtstag <> '0000-00-00' AND
    NOT ISNULL(maxp.PersGeburtstag) AND
    (maxp.`PersAus` = '0000-00-00' OR ISNULL(maxp.`PersAus`)) AND
    (maxp.`PersTod` = '0000-00-00' OR ISNULL(maxp.`PersTod`))
)) AS YoungestMember,
(SELECT MIN(minp.PersGeburtstag) FROM `a_data_person` minp WHERE (
    minp.PersKursID = k.KursID AND
    minp.`PersGeburtstag` <> '0000-00-00' AND
    NOT ISNULL(minp.PersGeburtstag) AND
    (minp.`PersAus` = '0000-00-00' OR ISNULL(minp.`PersAus`)) AND
    (minp.`PersTod` = '0000-00-00' OR ISNULL(minp.`PersTod`))
)) AS OldestMember,
lp.PersID AS LeaderPersID, lp.PersName AS LeaderPersName, lp.PersVorname AS LeaderPersVorname, lp.PersTod AS LeaderPersTod, lp.PersUrsprLand AS LeaderPersUrsprLand,
a.`AssignmentId` AS LeaderAssignmentId, r.`RoleId` AS LeaderRoleId,
gp.PersID AS GenerationPersID, gp.PersName AS GenerationPersName, gp.PersVorname AS GenerationPersVorname, gp.PersTod AS GenerationPersTod, gp.PersUrsprLand AS GenerationPersUrsprLand,
ga.`AssignmentId` AS GenerationAssignmentId, gr.`RoleId` AS GenerationRoleId,
IF(ISNULL(g.`GenID`),999,g.`GenID`) AS GenSort
FROM `a_data_kurs` k
LEFT JOIN `a_data_generation` g ON g.`GenID` = k.`GenID`
LEFT JOIN `a_data_role` r ON r.`Scope` = 'Course' AND k.`KursID` = r.`ScopeId`
LEFT JOIN `a_data_role_assignment` a ON a.`RoleId` = r.`RoleId` AND (ISNULL(a.EndDate) OR a.EndDate > CURDATE())
LEFT JOIN `a_data_person` lp ON a.`PersID` = lp.PersID
LEFT JOIN `a_data_role` gr ON gr.`Scope` = 'Generation' AND k.`GenID` = gr.`ScopeId`
LEFT JOIN `a_data_role_assignment` ga ON ga.`RoleId` = gr.`RoleId` AND (ISNULL(ga.EndDate) OR ga.EndDate > CURDATE())
LEFT JOIN `a_data_person` gp ON ga.`PersID` = gp.PersID
WHERE (k.`KursID` <> 999)
ORDER BY GenSort, k.`KursID`";
        $sqlCourseCountries = "SELECT PersKursID AS KursID, PersUrsprLand AS Land
FROM  `a_data_person` p
WHERE (p.`PersAus` = '0000-00-00' OR ISNULL(p.`PersAus`))
GROUP BY PersKursID, PersUrsprLand";
        $resultsCourses = $this->fetchSome(null, $sqlCourses, null);
        $resultsCountries = $this->fetchSome(null, $sqlCourseCountries, null);
        $countryLinks = array();
        foreach ($resultsCountries as $row) {
            if (!$row['Land']) {
                continue;
            }
            if (!isset($countryLinks[$row['KursID']])) {
                $countryLinks[$row['KursID']] = [$row['Land']];
            } else {
                $countryLinks[$row['KursID']][] = $row['Land'];
            }
        }

        $today = new \DateTime();
        $courses = [];
        $generations = [];
        foreach ($resultsCourses as $row) {
            $courseId = $this->filterDbId($row['KursID']);
            $generationId = $this->filterDbId($row['GenID']);
            $ageDiff = null;
            if (isset($row['YoungestMember']) && isset($row['OldestMember']) &&
                $row['YoungestMember'] != '0000-00-00' && !is_null($row['YoungestMember']) &&
                $row['OldestMember'] != '0000-00-00' && !is_null($row['OldestMember']))
            {
                try {
                    $y = new \DateTime($row['YoungestMember']);
                    $o = new \DateTime($row['OldestMember']);
                    $ageDiff = $today->diff($y)->y . '-'.$today->diff($o)->y;
                    if ($ageDiff === '0-0') {
                        $ageDiff = null;
                    }
                }
                catch (\Exception $e) {}
            }

            //calculate if the generation has died out completely
            if (!is_null($generationId)) {
                if (isset($generations[$generationId])) {
                    $generations[$generationId]['isAlive'] = $generations[$generationId]['isAlive'] || !is_null($ageDiff);
                    $generations[$generationId]['courses'][] = $courseId;
                } else {
                    $generations[$generationId] = [
                        'isAlive' => (bool)!is_null($ageDiff),
                        'courses' => [$courseId],
                    ];
                }
            }

            $courses[$courseId] = [
                'courseId'                 => $courseId,
                'courseName'               => $this->filterDbString($row['KursName']),
                'abbreviation'             => $row['KursAbk'], //@todo DEPRECATED
                'novitiateBeginDate'       => $this->filterDbDate($row['KursNoviziatBeginn']),
                'celebrationDate'          => $this->filterDbDate($row['KursWeihetag']),
                'adminNotes'               => $this->filterDbString($row['AdminNotes']),
                'publicNotes'              => $this->filterDbString($row['KursNote']),
                'generationId'             => $generationId,
                'generationName'           => $this->filterDbString($row['GenName']),
                'generationHasLivingMember'=> !is_null($generationId), //if not, we'll go back and set this to false
                'ageRange'                 => $ageDiff,
                'hasLivingMember'          => !is_null($ageDiff),
                'leaderRoleId'             => $this->filterDbId($row['LeaderRoleId']),
                'leaderAssignmentId'       => $this->filterDbId($row['LeaderAssignmentId']),
                'leaderId'                 => $this->filterDbId($row['LeaderPersID']),
                'leaderLastName'           => $this->filterDbString($row['LeaderPersName']),
                'leaderFirstName'          => $this->filterDbString($row['LeaderPersVorname']),
                'leaderDeathDate'          => $this->filterDbDate($row['LeaderPersTod']),
                'leaderCountry'            => $this->filterDbString($row['LeaderPersUrsprLand']),
                'generationRepRoleId'      => $this->filterDbId($row['GenerationRoleId']),
                'generationRepAssignmentId'=> $this->filterDbId($row['GenerationAssignmentId']),
                'generationRepId'          => $this->filterDbId($row['GenerationPersID']),
                'generationRepLastName'    => $this->filterDbString($row['GenerationPersName']),
                'generationRepFirstName'   => $this->filterDbString($row['GenerationPersVorname']),
                'generationRepDeathDate'   => $this->filterDbDate($row['GenerationPersTod']),
                'generationRepCountry'     => $this->filterDbString($row['GenerationPersUrsprLand']),
            ];
            if (isset($countryLinks[$courseId])) {
                $courses[$courseId]['countries'] = $countryLinks[$courseId];
            }
        }

        //check for dead generations
        foreach ($generations as $generation) {
            if (!$generation['isAlive']) {
                foreach ($generation['courses'] as $deadGenerationCourse) {
                    $courses[$deadGenerationCourse]['generationHasLivingMember'] = false;
                }
            }
        }

        return $courses;
    }

    /**
     * @return mixed[]
     */
    public function getGenerations()
    {

        if (!is_null($this->generationsCache)) {
            return $this->generationsCache;
        }

        $sql = "SELECT `GenID`, `GenName`, `GenAbk`,
`GenBeauftragter`, `GenNote`, `AdminNotes`, `CelebrationDate`,
`FoundingDate`, `IdealEnglish`, `IdealSpanish`, `IdealGerman`,
`IdealPortuguese`, `IdealFrench`, `Sort`,
`PublicNotesUpdatedOn`, `PublicNotesUpdatedBy`,
`AdminNotesUpdatedOn`, `AdminNotesUpdatedBy`,
`CreatedOn`, `CreatedBy`, `UpdatedOn`, `UpdatedBy`
FROM `a_data_generation` WHERE 1";

        $results = $this->fetchSome(null, $sql, null);
        $entities = [];
        foreach ($results as $row) {
            $id = $this->filterDbId($row['GenID']);
            $enIdeal = $this->filterDbString($row['IdealEnglish']);
            $esIdeal = $this->filterDbString($row['IdealSpanish']);
            $ptIdeal = $this->filterDbString($row['IdealPortuguese']);
            $frIdeal = $this->filterDbString($row['IdealFrench']);
            $deIdeal = $this->filterDbString($row['IdealGerman']);
            $idealLanguages = [];
            if (!is_null($enIdeal)) $idealLanguages['en'] = 'enIdeal';
            if (!is_null($esIdeal)) $idealLanguages['es'] = 'esIdeal';
            if (!is_null($deIdeal)) $idealLanguages['de'] = 'deIdeal';
            if (!is_null($ptIdeal)) $idealLanguages['pt'] = 'ptIdeal';
            if (!is_null($frIdeal)) $idealLanguages['fr'] = 'frIdeal';
            $entities[$id] = [
                'generationId'          => $id,
                'courses'               => [],
                'generationName'        => $this->filterDbString($row['GenName']),
                'celebrationDate'       => $this->filterDbDate($row['CelebrationDate']),
                'foundingDate'          => $this->filterDbDate($row['FoundingDate']),
                'enIdeal'               => $enIdeal,
                'esIdeal'               => $esIdeal,
                'ptIdeal'               => $ptIdeal,
                'frIdeal'               => $frIdeal,
                'deIdeal'               => $deIdeal,
                'idealLanguages'        => $idealLanguages,
                'sort'                  => $this->filterDbInt($row['Sort']),
                'publicNotes'           => $this->filterDbString($row['GenNote']),
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

        $courses = $this->getCourses();
        foreach ($courses as $course) {
            if (isset($entities[$course['generationId']])) {
                $entities[$course['generationId']]['courses'][$course['courseId']] = $course;
            }
        }

        return $this->generationsCache = $entities;
    }

    /**
     *
     * @param int $id
     * @return mixed[]
     */
    public function getSimpleGeneration($id)
    {
        $generations = $this->getGenerations();

        if (!isset($generations[$id]) || !($generation = $generations[$id])) {
            return null;
        }

        return $generation;
    }

    /**
     *
     * @param int $id
     * @return mixed[]
     */
    public function getGeneration($id)
    {
        $generations = $this->getGenerations();

        if (!isset($generations[$id]) || !($generation = $generations[$id])) {
            return null;
        }

        foreach ($generation['courses'] as $courseId => $course) {
            $generation['courses'][$courseId] = $this->getCourse($courseId);
        }


        //get course roles
        $sqlRoles = "SELECT r.`RoleId`, r.`RoleTitle`,r.`Scope`,r.`ScopeId`,
r.`SinglePosition`,r.`Sort`, a.`StartDate`, a.`EndDate`, a.`AssignmentId`,
p.`PersID`, p.`PersVorname`, p.`PersName`, p.`PersTod`, p.`PersAus`, p.`PersUrsprLand`
FROM `a_data_role` r
LEFT JOIN `a_data_role_assignment` a ON a.RoleId = r.RoleId
LEFT JOIN `a_data_person` p ON a.`PersID` = p.`PersID`
WHERE (r.`Scope` = 'Generation') AND (r.`ScopeId` = ?)";
        $params = [$id];
        $resultsRoles = $this->fetchSome(null, $sqlRoles, $params);
        $roles = array();
        foreach ($resultsRoles as $role) {
            $timeZone = new \DateTimeZone('UTC');
            $now = new \DateTime(null, $timeZone);
            $startDate = $this->filterDbDate($role['StartDate']);
            $endDate = $this->filterDbDate($role['EndDate']);
            $active = ($startDate < $now && (is_null($endDate) || $endDate > $now)) || (is_null($startDate) && (is_null($endDate) || $endDate > $now));

            array_push($roles, array(
                'roleId' => $this->filterDbId($role['RoleId']),
                'roleTitle' => $role['RoleTitle'],
                'scope' => $role['Scope'],
                'isSinglePosition' => $this->filterDbBool($role['SinglePosition']),
                'sort' => $role['Sort'],
                'assignmentId' => $this->filterDbId($role['AssignmentId']),
                'assignmentPersonId' => $this->filterDbId($role['PersID']),
                'assignmentStartDate' => $startDate,
                'assignmentEndDate' => $endDate,
                'assignmentFirstName' => $role['PersVorname'],
                'assignmentLastName' => $role['PersName'],
                'assignmentCountry' => $role['PersUrsprLand'],
                'assignmentDeathDate' => $this->filterDbDate($role['PersTod']),
                'assignmentDeceased' => $role['PersTod'] != '0000-00-00',
                'active' => $active
            ));
        }
        if (!empty($roles)) {//sort list
            $sort = array();
            foreach($roles as $k=>$v) {
                $sort['active'][$k] = $v['active'];
                $sort['sort'][$k] = $v['sort'];
                $sort['assignmentStartDate'][$k] = $v['assignmentStartDate'];
            }
            # sort by event_type desc and then title asc
            array_multisort($sort['active'], SORT_DESC, $sort['sort'], SORT_ASC, $sort['assignmentStartDate'], SORT_DESC, $roles);
        }
        $generation['roles'] = $roles;

        return $generation;
    }

    /**
     *
     * @param int $daysInAdvance
     * @param bool $includeDeceased
     * @param bool $includeLeftPersons
     * return array(
     *     'date' => ,
     *     'dateType' => ,
     *     'yearsAgo' => ,
     *     'isImportant' => ,
     *     'entity' => 'person',
     *     'entityId' => ,
     *     'entityName => ,
     *     'entityCountry' => ,
     *     'entityObject' => array(...),s
     * );
     */
    public function getCourseDates($daysInAdvance, $includeDeceased = false, $includeLeftPersons = false)
    {
        $courses = $this->getCourses();

        $sometimeToday = new \DateTime(null, new \DateTimeZone('UTC'));
        $sometimeYesterday = new \DateTime(null, new \DateTimeZone('UTC'));
        $sometimeYesterday->modify('-1 day'); //today is passed midnight, so subtract a day to make sure we dont cut off today's dates.
        $xDaysInAdvance = $sometimeToday->add(new \DateInterval(sprintf("P%sD", $daysInAdvance)));
        $yearsFrom1900 = (int)$sometimeToday->format('Y') - 1900;
        $return = array();
        foreach ($courses as $course) {

            //exclude ex-members
//             if (!$course['active']) {
//                 continue;
//             }

            //death day
            if (is_object($course['celebrationDate'])) {
                $interval = $sometimeToday->diff($course['celebrationDate']);
                $yearsAgo = $interval->y;

                $date = $course['celebrationDate']->add(new \DateInterval(sprintf('P%sY', $yearsAgo)));
                if ($date < $sometimeYesterday) {
                    $date->add(new \DateInterval('P1Y'));
                    $yearsAgo++;
                }
                if ($date < $xDaysInAdvance) {
                    $isImportant = false;
                    $return[] = array(
                        'date'          => $date,
                        'dateType'      => 'courseCelebrationDate',
                        'yearsAgo'      => $yearsAgo,
                        'isImportant'   => $isImportant,
                        'entity'        => 'course',
                        'entityId'      => $course['courseId'],
                        'entityName'    => $course['courseName'],
                        'entityCountry' => null,
                        'entityObject'  => $course,
                    );
                }
                continue;
            }
        }
        return $return;
    }

    public function getSuggestionCount()
    {
        $sql = "SELECT COUNT(*) AS SuggestionCount FROM `a_data_suggestion`
WHERE (`Status` = 'In review')";
        $resultsSuggestion = $this->fetchSome(null, $sql, null);
        if (count($resultsSuggestion) == 0) {
            return 0;
        }
        return $this->filterDbInt($resultsSuggestion[0]['SuggestionCount']);
    }

    public function getSuggestion($suggestionId)
    {
        if (is_null($suggestionId) || !is_numeric($suggestionId)) {
            throw new \InvalidArgumentException('Argument must be a valid suggestion id.');
        }
        $suggestionSql = "SELECT s.`SuggestionID`, s.`SuggestionEntity`, s.`SuggestionIDValue`,
s.`IpAddress`, s.`Status`, s.`SuggestionNotes`, s.`SuggestionOn`, s.`SuggestionBy`,
s.`SuggestionByPersonId`, s.`SuggestionResponse`, s.`ResponseEmailAddress`, s.`IsEmailSent`, s.`ReviewedOn`,
s.`ReviewedBy`, COUNT(sc.SuggestionColumnID) AS SuggestionCount
FROM `a_data_suggestion` s
LEFT JOIN `a_data_suggestion_columns` sc ON s.SuggestionID = sc.SuggestionID
GROUP BY s.`SuggestionID`, s.`SuggestionEntity`, s.`SuggestionIDValue`,
s.`IpAddress`, s.`Status`, s.`SuggestionNotes`, s.`SuggestionOn`, s.`SuggestionBy`,
s.`SuggestionByPersonId`, s.`SuggestionResponse`, s.`ResponseEmailAddress`, s.`IsEmailSent`, s.`ReviewedOn`,
s.`ReviewedBy`
HAVING (s.`SuggestionID` = ?)
ORDER BY ReviewedOn DESC";
        $params = [$suggestionId];
        $resultsSuggestion = $this->fetchSome(null, $suggestionSql, $params);
        if (!isset($resultsSuggestion[0])) {
            return null;
        }
        if (!is_null($data = $this->processSuggestionRowData($resultsSuggestion[0]))) {
            $result = $data;
        } else {
            return null;
        }
        return $result;
    }

    public function getSuggestions()
    {
        $suggestionSql = "SELECT s.`SuggestionID`, s.`SuggestionEntity`, s.`SuggestionIDValue`,
s.`IpAddress`, s.`Status`, s.`SuggestionNotes`, s.`SuggestionOn`, s.`SuggestionBy`,
s.`SuggestionByPersonId`, s.`SuggestionResponse`, s.`ResponseEmailAddress`, s.`IsEmailSent`, s.`ReviewedOn`,
s.`ReviewedBy`, COUNT(sc.SuggestionColumnID) AS SuggestionCount
FROM `a_data_suggestion` s
LEFT JOIN `a_data_suggestion_columns` sc ON s.SuggestionID = sc.SuggestionID
GROUP BY s.`SuggestionID`, s.`SuggestionEntity`, s.`SuggestionIDValue`,
s.`IpAddress`, s.`Status`, s.`SuggestionNotes`, s.`SuggestionOn`, s.`SuggestionBy`,
s.`SuggestionByPersonId`, s.`SuggestionResponse`, s.`ResponseEmailAddress`, s.`IsEmailSent`, s.`ReviewedOn`,
s.`ReviewedBy`
ORDER BY ReviewedOn DESC";
        $resultsSuggestion = $this->fetchSome(null, $suggestionSql, null);
        $results = [];
        foreach ($resultsSuggestion as $row) {
            if (!is_null($data = $this->processSuggestionRowData($row))) {
                $results[$data['suggestionId']] = $data;
            }
        }

        //Sort with the newest suggestions first
        $sort = [];
        foreach($results as $k=>$v) {
            $sort['suggestionOn'][$k] = $v['suggestionOn'];
        }
        array_multisort($sort['suggestionOn'], SORT_DESC, $results);

        return $results;
    }

    /**
     * Takes the column output from a single row of a suggestion query and processes it
     * @param string[] $row
     */
    protected function processSuggestionRowData($row)
    {
        if ($row['Status'] == $this::SUGGESTION_ERROR) {
            return null;
        }
        $entity = $row['SuggestionEntity'];
        $id =  $this->filterDbId($row['SuggestionIDValue']);
        if (!isset($this->entities[$entity]) || !isset($this->entities[$entity]['update_reference_data_function'])
            || !isset($this->entities[$entity]['name_column']))
        {
            return null;
        }
        $entityFunction = $this->entities[$entity]['update_reference_data_function'];
        $entityData = $this->$entityFunction($id);
        $entityName = "";
        if ($entityData) {
            $entityNameColumn = $this->entities[$entity]['name_column'];
            $entityName = $entityData[$entityNameColumn];
        }
        $suggestionCount = $this->filterDbInt($row['SuggestionCount']);
        if ($row['SuggestionResponse']) {
            $suggestionCount++;
        }
        $route = isset($this->entities[$entity]['moderate_route']) ?
        $this->entities[$entity]['moderate_route'] : null;
        $routeKey = isset($this->entities[$entity]['moderate_route_entity_key']) ?
        $this->entities[$entity]['moderate_route_entity_key'] : null;

        //see if we have everything we need to form a url to a show route
        if (isset($this->entities[$entity]['show_route']) &&
            isset($this->entities[$entity]['show_route_key']) &&
            isset($this->entities[$entity]['show_route_key_reference']) &&
            isset($entityData[$showRouteKeyReference = $this->entities[$entity]['show_route_key_reference']]))
        {
            $showRoute = $this->entities[$entity]['show_route'];
            $showRouteKey = $this->entities[$entity]['show_route_key'];
            $showRouteValue = $entityData[$showRouteKeyReference];
            $showRouteAvailable = true;
        } else {
            $showRoute = null;
            $showRouteKey = null;
            $showRouteValue = null;
            $showRouteAvailable = false;
        }

        $personId = $this->filterDbId($row['SuggestionByPersonId']);
        $person = !is_null($personId) ? $this->getPerson($personId) : null;

        $reviewedBy = $this->filterDbId($row['ReviewedBy']);
        $reviewedByPerson = null;
        if (!is_null($reviewedBy)) {
            $reviewedByUser = $this->getUserTable()->getUser($reviewedBy);
            if (!is_null($reviewedByUser) && !is_null($reviewedByPersonId = $reviewedByUser['personId'])) {
                $reviewedByPerson = $this->getPerson($reviewedByPersonId);
            }
        }

        return [
            'suggestionId'             => $this->filterDbId($row['SuggestionID']),
            'suggestionEntity'         => $this->filterDbString($row['SuggestionEntity']),
            'suggestionEntityId'       => $id,
            'suggestionEntityName'     => $entityName,
            'suggestionStatus'         => $row['Status'],
            'suggestionNotes'          => $this->filterDbString($row['SuggestionNotes']),
            'suggestionIpAddress'      => $this->filterDbString($row['IpAddress']),
            'suggestionOn'             => $this->filterDbDate($row['SuggestionOn']),
            'suggestionBy'             => $this->filterDbId($row['SuggestionBy']),
            'suggestionByUser'         => $this->userTable->getUser($row['SuggestionBy']),
            'suggestionByPersonId'     => $personId,
            'suggestionByPerson'       => $person,
            'suggestionColumnsCount'   => $suggestionCount,
            'suggestionResponse'       => $this->filterDbString($row['SuggestionResponse']),
            'suggestionEmailAddress'   => $this->filterEmailString($row['ResponseEmailAddress']),
            'suggestionIsEmailSent'    => $this->filterDbBool($row['IsEmailSent']),
            'suggestionReviewedOn'     => $this->filterDbDate($row['ReviewedOn']),
            'suggestionReviewedBy'     => $reviewedBy,
            'suggestionReviewedByPerson'=> $reviewedByPerson,
            'moderationRoute'          => $route,
            'moderationRouteKey'       => $routeKey,
            'showRouteAvailable'       => $showRouteAvailable,
            'showRoute'                => $showRoute,
            'showRouteKey'             => $showRouteKey,
            'showRouteKeyValue'        => $showRouteValue,
            'hasDedicatedModerateForm' => !is_null($route) && !is_null($routeKey),
        ];
    }

    public function getLastSuggestion()
    {
        $suggestionSql = "SELECT s.`SuggestionID`, s.`SuggestionEntity`, s.`SuggestionIDValue`,
s.`IpAddress`, s.`Status`, s.`SuggestionNotes`, s.`SuggestionOn`, s.`SuggestionBy`,
s.`SuggestionByPersonId`, s.`SuggestionResponse`, s.`ResponseEmailAddress`, s.`IsEmailSent`, s.`ReviewedOn`,
s.`ReviewedBy`, COUNT(sc.SuggestionColumnID) AS SuggestionCount
FROM `a_data_suggestion` s
LEFT JOIN `a_data_suggestion_columns` sc ON s.SuggestionID = sc.SuggestionID
GROUP BY s.`SuggestionID`, s.`SuggestionEntity`, s.`SuggestionIDValue`,
s.`IpAddress`, s.`Status`, s.`SuggestionNotes`, s.`SuggestionOn`, s.`SuggestionBy`,
s.`SuggestionByPersonId`, s.`SuggestionResponse`, s.`ResponseEmailAddress`, s.`IsEmailSent`, s.`ReviewedOn`,
s.`ReviewedBy`
ORDER BY SuggestionID DESC";
        $resultsSuggestion = $this->fetchSome(null, $suggestionSql, null);
        return $this->processSuggestionRowData($resultsSuggestion[0]);
    }

    public function getSuggestionData($id, &$oldData)
    {
        if (!is_numeric($id)) {
            throw new \InvalidArgumentException('Invalid id provided.');
        }
        if (!is_array($oldData)) {
            $oldData = array();
        }
        $suggestionSql = "SELECT s.`SuggestionID`, s.`SuggestionEntity`, s.`SuggestionIDValue`,
s.`IpAddress`, s.`Status`, s.`SuggestionNotes`, s.`SuggestionOn`, s.`SuggestionBy`, `SuggestionByPersonId`,
s.`SuggestionResponse`, s.`ResponseEmailAddress`, s.`IsEmailSent`, s.`ReviewedOn`,
s.`ReviewedBy`, sc.SuggestionColumnID, sc.SuggestionColumn,  sc.OldValue, sc.OldText,
sc.NewText, sc.NewValue, sc.IsText
FROM `a_data_suggestion` s
LEFT JOIN `a_data_suggestion_columns` sc ON s.SuggestionID = sc.SuggestionID
WHERE (s.SuggestionID = ?)";
        $params = array($id);
        $resultsSuggestion = $this->fetchSome(null, $suggestionSql, $params);
        if (!$resultsSuggestion) {
            throw new \Exception("Suggestion not found.");
        }
        //get the information for the entity retrieved
        $entity = $resultsSuggestion[0]['SuggestionEntity'];
        $entityId = $resultsSuggestion[0]['SuggestionIDValue'];
        $entityFunction = $this->entities[$entity]['update_reference_data_function'];
        //@todo Check to make sure function exists
        $entityData = $this->$entityFunction($entityId);
        if (!$entityData) {
            $entityData = [];
        }

        $personId = $this->filterDbId($resultsSuggestion[0]['SuggestionByPersonId']);
        $person = !is_null($personId) ? $this->getPerson($personId) : null;

        //manipulate column names
        $data = array(
            'suggestionId'             => $this->filterDbId($resultsSuggestion[0]['SuggestionID']),
            'suggestionEntity'         => $resultsSuggestion[0]['SuggestionEntity'],
            'suggestionEntityId'       => $this->filterDbId($resultsSuggestion[0]['SuggestionIDValue']),
            'suggestionStatus'         => $resultsSuggestion[0]['Status'],
            'suggestionNotes'          => $this->filterDbString($resultsSuggestion[0]['SuggestionNotes']),
            'suggestionIpAddress'      => $this->filterDbString($resultsSuggestion[0]['IpAddress']),
            'suggestionOn'             => $this->filterDbDate($resultsSuggestion[0]['SuggestionOn']),
            'suggestionBy'             => $this->filterDbId($resultsSuggestion[0]['SuggestionBy']),
            'suggestionByUser'         => $this->userTable->getUser($resultsSuggestion[0]['SuggestionBy']),
            'suggestionByPersonId'     => $personId,
            'suggestionByPerson'       => $person,
            'suggestionColumnsCount'   => count($resultsSuggestion),
            'suggestionColumns'        => [],
            'suggestionResponse'       => $resultsSuggestion[0]['SuggestionResponse'],
            'suggestionEmailAddress'   => $this->filterEmailString($resultsSuggestion[0]['ResponseEmailAddress']),
            'suggestionIsEmailSent'    => $this->filterDbBool($resultsSuggestion[0]['IsEmailSent']),
            'suggestionReviewedOn'     => $this->filterDbDate($resultsSuggestion[0]['ReviewedOn']),
            'suggestionReviewedBy'     => $this->filterDbId($resultsSuggestion[0]['ReviewedBy']),
        );
        if (isset($this->entities[$data['suggestionEntity']]['date_columns'])) {
            $isDate = $this->entities[$data['suggestionEntity']]['date_columns'];
        } else {
            $isDate = [];
        }
        foreach ($resultsSuggestion as $row) {
            //@todo is it possible for suggestionColumns to be empty or null?
            $data['suggestionColumns'][] = $row['SuggestionColumn'];
            if ($this->filterDbBool($row['IsText'])) {
                $data[$row['SuggestionColumn']] = $row['NewText'];
                $oldData[$row['SuggestionColumn']] = $row['OldText'];
            } elseif (in_array($row['SuggestionColumn'], $isDate)) {
                $data[$row['SuggestionColumn']] = $this->filterDbDate($row['NewValue']);
//                 $oldData[$row['SuggestionColumn']] = $this->filterDbDate($row['OldValue']);  leave old values as string so they are displayed in the help blocks correctly
                $oldData[$row['SuggestionColumn']] = $row['OldValue'];
            } else {
                $data[$row['SuggestionColumn']] = $row['NewValue'];
                $oldData[$row['SuggestionColumn']] = $row['OldValue'];
            }
        }
        $data = array_merge($entityData, $data);
//         var_dump($data);
        return $data;
    }

    public function suggestEntity($entity, $id, $data)
    {
        if (!is_numeric($id)) {
            throw new \InvalidArgumentException('Invalid id provided.');
        }
        $updateCols = null;
        $entityData = null;
        $textColumns = null;
        if (isset($this->entities[$entity]) && isset($this->entities[$entity]['has_dedicated_suggest_form']) &&
            $this->entities[$entity]['has_dedicated_suggest_form']) {
            $entityFunction = $this->entities[$entity]['update_reference_data_function'];
            //@todo Check to make sure function exists
            $entityData = $this->$entityFunction($id);
            if (!$entityData) {
                throw new \InvalidArgumentException('No entity provided.');
            }
            $updateCols = $this->entities[$entity]['update_columns'];
            if (key_exists('text_columns', $this->entities[$entity])) {
               $textColumns = $this->entities[$entity]['text_columns'];
            }
        }
        return $this->suggestHelper($entity, $id, $data, $updateCols, $entityData, $textColumns);
    }

    protected function suggestHelper($entity, $id, $data, $updateCols, $referenceEntity, $textColumns = null)
    {
        if (null===$textColumns) {
            $textColumns = array();
        }
        if (!is_array($textColumns)) {
            throw new \Exception('Invalid text columns array passed');
        }
        $now = (new \DateTime(null, new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $changes = [];
        if ($referenceEntity) {
            foreach ($referenceEntity as $col => $value) {
                if (!key_exists($col, $updateCols) || !key_exists($col, $data) || $value == $data[$col]) {
                    continue;
                }
                if ($data[$col] instanceof \DateTime) {
                    $data[$col] = $data[$col]->format('Y-m-d H:i:s');
                }
                if (is_array($data[$col])) {
                    $data[$col] = $this->formatDbArray($data[$col]);
                }
                if ($value instanceof \DateTime) {
                    $value = $value->format('Y-m-d H:i:s');
                }
                if (is_array($value)) {
                    $value = $this->formatDbArray($value);
                }
                if (in_array($col, $textColumns)) {
                    $changes[] = [
                        'IsText' => true,
                        'NewText' => $data[$col],
                        'OldText' => $value,
                        'SuggestionColumn' => $col,
                    ];
                } else {
                    $changes[] = [
                        'IsText' => false,
                        'NewValue' => $data[$col],
                        'OldValue' => $value,
                        'SuggestionColumn' => $col,
                    ];
                }
            }
        }

        if (count($changes) > 0 || (isset($data['suggestionNotes']) && '' !== $data['suggestionNotes'])) {
            $suggestion = [
                'SuggestionEntity' => $entity,
                'SuggestionIDValue' => $id,
                'IpAddress' => $_SERVER['REMOTE_ADDR'],
                'Status' => $this::SUGGESTION_INREVIEW,
                'SuggestionNotes' => isset($data['suggestionNotes']) && $data['suggestionNotes'] ? $data['suggestionNotes'] : null,
                'SuggestionOn' => $now,
                'SuggestionBy' => $this->actingUserId,
                'SuggestionByPersonId' => isset($data['suggestionByPersonId']) ? $data['suggestionByPersonId'] : null,
                'ResponseEmailAddress' => isset($data['suggestionByEmail']) ? $data['suggestionByEmail'] : null, //@todo look up user's email address
            ];
            $suggestionResult = $this->getSuggestionTableGateway()->insert($suggestion);
            $suggestionId = $this->getSuggestionTableGateway()->getLastInsertValue();
            try {
                foreach ($changes as $change) {
                    $change['SuggestionID'] = $suggestionId;
                    $this->getSuggestionColumnTableGateway()->insert($change);
                }
            } catch (\Exception $e) {
                $suggestionUpdate = [
                    'Status' => $this::SUGGESTION_ERROR,
                    'SuggestionNotes' => isset($data['suggestionNotes']) && $data['suggestionNotes'] ?
                       $data['suggestionNotes'].'//'.$e->getMessage() : $e->getMessage(),
                ];
                $suggestionResult = $this->getSuggestionTableGateway()->update($suggestionUpdate, ['SuggestionID' => $suggestionId]);
                throw $e; //let this exception be re-caught by the controller
            }
            return $suggestionResult;
        }
        return true;
    }

    public function updateSuggestion($data)
    {
        if (!isset($data['suggestionId']) || !is_numeric($data['suggestionId'])) {
            throw new \InvalidArgumentException('Invalid suggestion passed.');
        }
        if (isset($data['deny'])) {
            $data['status'] = 'Denied';
        } else {
            $data['status'] = 'Accepted';
        }
        //@todo send email from somewhere,  Idk if this is the right place, maybe in the controller?
//         $this->sendEmail($data['ResponseEmailAddress']);
        $timeZone = new \DateTimeZone('UTC');
        $now = new \DateTime(null, $timeZone);
        $updateVals = array(
            'Status' => $data['status'] ? $data['status'] : 'In review', //@todo we should be sure there will be a status
            'ReviewedOn' => $this->formatDbDate($now),
            'ReviewedBy' => $this->actingUserId,
            'SuggestionResponse' => isset($data['suggestionResponse']) ? $data['suggestionResponse'] : null,
//             'IsEmailSent' => true
        );
        return $this->getSuggestionTableGateway()->update($updateVals, array('SuggestionID' => $data['suggestionId']));
    }

    /**
     * Get all records from the email addresses table
     * @return mixed[][]
     */
    public function getEmailAddresses()
    {
        if (!is_null($this->emailAddressesCache)) {
            return $this->emailAddressesCache;
        }

        $sql = "SELECT `EmailAddress`, `PersonId`,
`VerificationToken`, `VerifiedOn`, `VerifiedFromIpAddress`,
`VerificationTokenExpiresOn`, `VerificationTokenGenerationNotes`,
`UnsubscribedOn`, `UnsubscibedFromIpAddress`, `UpdatedOn`,
`UpdatedBy`, `CreatedOn`, `CreatedBy`
FROM `a_data_email_address` WHERE 1";

        $results = $this->fetchSome(null, $sql, null);
        $entities = [];
        foreach ($results as $row) {
            $email = $this->filterDbString($row['EmailAddress']);
            $entities[$email] = [
                'emailAddress'                      => $email,
                'personId'                          => $this->filterDbId($row['PersonId']),
                'verificationToken'                 => $this->filterDbString($row['VerificationToken']),
                'verifiedOn'                        => $this->filterDbDate($row['VerifiedOn']),
                'verifiedFromIpAddress'             => $this->filterDbString($row['VerifiedFromIpAddress']),
                'verificationTokenExpiresOn'        => $this->filterDbDate($row['VerificationTokenExpiresOn']),
                'verificationTokenGenerationNotes'  => $this->filterDbString($row['VerificationTokenGenerationNotes']),
                'unsubscribedOn'                    => $this->filterDbDate($row['UnsubscribedOn']),
                'unsubscibedFromIpAddress'          => $this->filterDbString($row['UnsubscibedFromIpAddress']),
                'updatedOn'                         => $this->filterDbDate($row['UpdatedOn']),
                'updatedBy'                         => $this->filterDbId($row['UpdatedBy']),
                'createdOn'                         => $this->filterDbDate($row['CreatedOn']),
                'createdBy'                         => $this->filterDbId($row['CreatedBy']),
            ];
        }
        return $this->emailAddressesCache = $entities;
    }

    public function getEmailAddress($email)
    {
        $emailAddresses = $this->getEmailAddresses();

        if (!isset($emailAddresses[$email]) || !($emailAddress = $emailAddresses[$email])) {
            return null;
        }
        return $emailAddress;
    }

    /**
     * @todo !!!
     * @return mixed[][]
     */
    public function getAdministratorsContactList()
    {
        $env = getenv('APP_ENV') ?: 'production';
        if ($env == 'development') {
            return [
                [
                    'name' => 'Jeff',
                    'email' => 'webmaster@schoenstatt.link',
                    'locale' => 'es_ES',
                ],
            ];
        } else {
            return [
                [
                    'name' => 'P. Francisco',
                    'email' => 'webmaster@schoenstatt.link',
                    'locale' => 'es_ES',
                ],
            ];
        }
    }

    /**
     * Get all records from the mailings table
     * @return mixed[][]
     */
    public function getMailings()
    {
        if (!is_null($this->mailingsCache)) {
            return $this->mailingsCache;
        }

        $sql = "SELECT `MailingId`, `EmailAddress`, `MailingOn`,
`MailingBy`, `Subject`, `Body`, `Sender`, `MailingText`, `MailingTags`,
`TrackingToken`, `OpenedFromIpAddress`, `OpenedFromHeaders`, `OpenedOn`,
`EmailTemplate`, `Status`, `QueueUntil`, `ErrorMessage`, `StackTrace`
FROM `a_data_mailing` WHERE 1";

        $results = $this->fetchSome(null, $sql, null);
        $entities = [];
        foreach ($results as $row) {
            $id = $this->filterDbId($row['MailingId']);
            $email = $this->filterDbString($row['EmailAddress']);
            $entities[$id] = [
                'mailingId'             => $id,
                'emailAddress'          => $this->getEmailAddress($email),
                'mailingOn'             => $this->filterDbDate($row['MailingOn']),
                'mailingBy'             => $this->filterDbId($row['MailingBy']),
                'subject'               => $this->filterDbString($row['Subject']),
                'body'                  => $this->filterDbString($row['Body']),
                'sender'                => $this->filterDbString($row['Sender']),
                'text'                  => $this->filterDbString($row['MailingText']),
                'tags'                  => $this->filterDbArray($row['MailingTags']),
                'trackingToken'         => $this->filterDbString($row['TrackingToken']),
                'openedFromIpAddress'   => $this->filterDbString($row['OpenedFromIpAddress']),
                'openedFromHeaders'     => $row['OpenedFromHeaders'], //@todo process as JSON
                'openedOn'              => $this->filterDbDate($row['OpenedOn']),
                'emailTemplate'         => $this->filterDbString($row['EmailTemplate']),
                'emailLocale'           => $this->filterDbString($row['EmailLocale']),
                'status'                => $this->filterDbString($row['Status']),
                'attempt'               => $this->filterDbInt($row['Attempt']),
                'maxAttempts'           => $this->filterDbInt($row['MaxAttempts']),
                'queueUntil'            => $this->filterDbDate($row['QueueUntil']),
                'errorMessage'          => $this->filterDbString($row['ErrorMessage']),
                'stackTrace'            => $this->filterDbString($row['StackTrace']),
            ];
        }
        return $this->mailingsCache = $entities;
    }

    public function getMailing($id)
    {
        $mailings = $this->getMailings();

        if (!isset($mailings[$id]) || !($mailing = $mailings[$id])) {
            return null;
        }
        return $mailing;
    }

    public function getFiliations()
    {
        $inFiliation = $this->getAssociatedScopesForInStatement('Filiation', true);
        $sqlFiliations = "SELECT f.`FilID`, f.`FilName`, f.`Category`, f.`PublicNotes`, f.`AdminNotes`, f.GebID, g.GebName, g.Sort, g.`Active` AS GebActive, f.`Active` AS FilActive,
f.`Country`, f.`CanonicalErection`, f.`CanonicalSuppression`, rp.PersID AS RectorID, rp.PersName AS RectorName, rp.PersVorname AS RectorVorname, rp.PersTod AS RectorTod,
a.AssignmentId, rp.PersUrsprLand AS RectorUrsprLand, (SELECT COUNT(*) FROM `a_data_person` p WHERE (f.FilID = p.PersFilialeID) AND (p.PersAus = '0000-00-00' OR ISNULL(p.PersAus)) AND (p.PersTod = '0000-00-00' OR ISNULL(p.PersTod) )) AS Count,
IF(g.MainFiliation=f.FilID, 1, 0) AS MainFiliation
FROM `a_data_filiale` f
LEFT JOIN a_data_gebiet g ON g.GebID = f.GebID
LEFT JOIN `a_data_role` r ON r.`Scope` IN $inFiliation AND f.`FilID` = r.`ScopeId` AND r.`MainRole` = TRUE
LEFT JOIN `a_data_role_assignment` a ON a.`RoleId` = r.`RoleId` AND (ISNULL(a.EndDate) OR a.EndDate > CURDATE())
LEFT JOIN `a_data_person` rp ON a.`PersID` = rp.PersID
ORDER BY GebActive DESC, Sort, GebName, FilActive DESC, MainFiliation DESC, `Country`, `FilName`, rp.PersName";

        $resultsFiliations = $this->fetchSome(null, $sqlFiliations, null);
        $result = array();
        foreach ($resultsFiliations as $fil) {
            array_push($result, array(
                'filiationId'          => $this->filterDbId($fil['FilID']),
                'filiationName'        => $fil['FilName'],
                'type'                 => $fil['Category'],
                'country'              => $fil['Country'],
                'publicNotes'          => $fil['PublicNotes'],
                'adminNotes'           => $fil['AdminNotes'],
                'canonicalErection'    => $this->filterDbDate($fil['CanonicalErection']),
                'canonicalSuppression' => $this->filterDbDate($fil['CanonicalSuppression']),
                'country'              => $fil['Country'],
                'active'               => $this->filterDbBool($fil['FilActive']),
                'mainFiliation'        => $this->filterDbBool($fil['MainFiliation']),
                'memberCount'          => $this->filterDbInt($fil['Count']),
                'territoryId'          => $this->filterDbId($fil['GebID']),
                'territoryName'        => $fil['GebName'],
                'territoryActive'      => $this->filterDbBool($fil['GebActive']),
                'rectorId'             => $this->filterDbId($fil['RectorID']),
                'rectorAssignmentId'   => $this->filterDbId($fil['AssignmentId']),
                'rectorLastName'       => $fil['RectorName'],
                'rectorFirstName'      => $fil['RectorVorname'],
                'rectorDeathDate'      => $this->filterDbDate($fil['RectorTod']),
                'rectorCountry'        => $fil['RectorUrsprLand']
            ));
        }
        return $result;
    }

    /**
     *
     *
     * @param string|int $id
     */
    public function getFiliation($id)
    {
        $result = $this->getSimpleFiliation($id);

        $inFiliation = $this->getAssociatedScopesForInStatement('Filiation', true);
        $rolesSql = "SELECT r.`RoleId`, r.`RoleTitle`, r.`Scope`, r.`SinglePosition`, r.`Sort`, ra.`AssignmentId`, ra.`PersID`, ra.`StartDate`, ra.`EndDate`,
p.`PersVorname`, p.`PersName`, p.PersTod, p.PersUrsprLand, p.`PersEmail`, p.`PersHandy`
FROM `a_data_role` r
LEFT JOIN `a_data_role_assignment` ra ON r.`RoleId` = ra.`RoleId`
LEFT JOIN `a_data_person` p ON ra.`PersID` = p.`PersID`
WHERE (r.`Scope` IN $inFiliation) AND (r.`ScopeId` = ?)";
        $rolesParams = array($result['filiationId']);
        $resultsRoles = $this->fetchSome(null, $rolesSql, $rolesParams);
        $roles = array();
        $timeZone = new \DateTimeZone('UTC');
        $now = new \DateTime(null, $timeZone);
        foreach ($resultsRoles as $role) {
            $startDate = $this->filterDbDate($role['StartDate']);
            $endDate = $this->filterDbDate($role['EndDate']);
            $active = ($startDate < $now && (is_null($endDate) || $endDate > $now)) || (is_null($startDate) && (is_null($endDate) || $endDate > $now));
            $deathDate = $this->filterDbDate($role['PersTod']);
            array_push($roles, array(
                'roleId'               => $this->filterDbId($role['RoleId']),
                'roleTitle'            => $role['RoleTitle'],
                'scope'                => $role['Scope'],
                'isSinglePosition'     => $this->filterDbBool($role['SinglePosition']),
                'sort'                 => $this->filterDbInt($role['Sort']),
                'assignmentId'         => $this->filterDbId($role['AssignmentId']),
                'assignmentPersonId'   => $this->filterDbId($role['PersID']),
                'assignmentStartDate'  => $startDate,
                'assignmentEndDate'    => $endDate,
                'assignmentFirstName'  => $role['PersVorname'],
                'assignmentLastName'   => $role['PersName'],
                'assignmentCell'       => $role['PersHandy'],
                'assignmentEmail'      => $role['PersEmail'],
                'assignmentCountry'    => $role['PersUrsprLand'],
                'assignmentDeathDate'  => $deathDate,
                'assignmentDeceased'   => !is_null($deathDate),
                'active'               => $active,
            ));
        }
        //sort list
        $sort = array();
        foreach($roles as $k=>$v) {
            $sort['active'][$k] = $v['active'];
            $sort['sort'][$k] = $v['sort'];
        }
        # sort by event_type desc and then title asc
        array_multisort($sort['active'], SORT_DESC, $sort['sort'], SORT_ASC, $roles);
        $result['assignments'] = $roles;

        $result['houses'] = $this->getFiliationHouses($result['filiationId']);
//         var_dump($result);
        return $result;
    }

    public function getSimpleFiliation($id)
    {
        $filiationSql = "SELECT f.`FilID`, f.`FilName`, f.`Category`, f.`PublicNotes`, f.`Active`, f.`Country`,
f.`CanonicalErection`, f.`CanonicalSuppression`, f.`GebID`, g.`GebName`, f.`AdminNotes`, f.`MainHouse`
FROM `a_data_filiale` f
LEFT JOIN `a_data_gebiet` g ON f.`GebID` = g.`GebID`
WHERE f.`FilID` = ?
ORDER BY `FilID` ASC";
        $params = array($id);
        $resultsFiliation = $this->fetchSome(null, $filiationSql, $params);
        if (count($resultsFiliation) != 1) { //we either didn't find it or got 2, weird
            throw new \Exception("Filiation not found.");
        }
        //manipulate column names
        $result = array(
            'filiationId'          => $this->filterDbId($resultsFiliation[0]['FilID']),
            'filiationName'        => $resultsFiliation[0]['FilName'],
            'type'                 => $resultsFiliation[0]['Category'],
            'country'              => $resultsFiliation[0]['Country'],
            'mainHouse'            => $this->filterDbId($resultsFiliation[0]['MainHouse']),
            'publicNotes'          => $resultsFiliation[0]['PublicNotes'],
            'adminNotes'           => $resultsFiliation[0]['AdminNotes'],
            'active'               => $this->filterDbBool($resultsFiliation[0]['Active']),
            'canonicalErection'    => $this->filterDbDate($resultsFiliation[0]['CanonicalErection']),
            'canonicalSuppression' => $this->filterDbDate($resultsFiliation[0]['CanonicalSuppression']),
            'territoryId'          => $this->filterDbId($resultsFiliation[0]['GebID']),
            'territoryName'        => $resultsFiliation[0]['GebName']
        );
        $result['associatedRoles'] = $this->getAssociatedRoles($id, 'Filiation');
        return $result;
    }

    /**
     *
     * @param int|string $id
     * @param string $startDate
     * @param string $endDate
     * @todo factor out into a SionModel
     */
    public function updateFiliation($id, $data) {
        if (!is_numeric($id)) {
            throw new \InvalidArgumentException('Invalid filiation id provided.');
        }
        $filiation = $this->getFiliation($id);
        if (!$filiation) {
            throw new \InvalidArgumentException('No filiation provided.');
        }
        $tableName = 'a_data_filiale';
        $tableKey = 'FilID';
        $tableGateway = $this->getFiliationTableGateway();
        $updateCols = array(
            'filiationId'          => 'FilID',
            'filiationName'        => 'FilName',
            'type'                 => 'Category',
            'country'              => 'Country',
            'mainHouse'            => 'MainHouse',
            'publicNotes'          => 'PublicNotes',
            'adminNotes'           => 'AdminNotes',
            'active'               => 'Active',
            'canonicalErection'    => 'CanonicalErection',
            'canonicalSuppression' => 'CanonicalSuppression',
            'territoryId'          => 'GebID'
        );

        return $this->updateHelper($id, $data, $tableName, $tableKey, $tableGateway, $updateCols, $filiation);
    }

    /**
     *
     * @param string[][] $data
     */
    public function createFiliation($data) {
        $tableName     = 'a_data_filiale';
        $tableGateway  = $this->getFiliationTableGateway();
        $scope         = $data['type'];
        $requiredCols  = array(
            'filiationName',
            'type',
            'country',
            'territoryId',
        );
        $updateCols = array(
            'filiationId'          => 'FilID',
            'filiationName'        => 'FilName',
            'type'                 => 'Category',
            'country'              => 'Country',
            'mainHouse'            => 'MainHouse',
            'active'               => 'Active',
            'canonicalErection'    => 'CanonicalErection',
            'canonicalSuppression' => 'CanonicalSuppression',
            'territoryId'          => 'GebID',
            'adminNotes'               => 'AdminNotes',
            'adminNotesUpdatedOn'      => 'AdminNotesUpdatedOn',
            'adminNotesUpdatedBy'      => 'AdminNotesUpdatedBy',
            'publicNotes'              => 'PublicNotes',
            'publicNotesUpdatedOn'     => 'PublicNotesUpdatedOn',
            'publicNotesUpdatedBy'     => 'PublicNotesUpdatedBy',
            'updatedOn'                => 'UpdatedOn',
            'updatedBy'                => 'UpdatedBy',
            'createdOn'                => 'CreatedOn',
            'createdBy'                => 'CreatedBy'
        );
        return $this->createHelper($data, $requiredCols, $updateCols, $tableName, $tableGateway, $scope);
    }

    public function getFiliationHouses($filiationId)
    {
        $housesSql = "SELECT `HausID`, `HausName`, `HausEmail1`,
`HausWeb`, `HausStrasse`, `HausStrasse2`, `HausOrt`, `HausPLZ`,
`HausPostSameAsAddress`, `HausPostStrasse`, `HausPostStrasse2`,
`HausPostOrt`, `HausPostPLZ`, `HausLand`, `HausTel`, `HausTelLabel`,
`HausTel2`, `HausTel2Label`, `HausTel3`, `HausTel3Label`, `HausFax`,
`HausFaxLabel`, `HausFax2`, `HausFax2Label`, `HausTelsUpdatedOn`,
`HausTelsUpdatedBy`, `HausDioezese`, `HausDioezeseUrl`, `HausAktiv`,
`HausFilID`, `WifiPassword`, `WifiPasswordUpdatedOn`, `WifiPasswordUpdatedBy`,
h.`PublicNotes`, h.`PublicNotesUpdatedOn`, h.`PublicNotesUpdatedBy`,
h.`AdminNotes`, h.`AdminNotesUpdatedOn`, h.`AdminNotesUpdatedBy`,
h.`UpdatedOn`, h.`UpdatedBy`, h.`CreatedOn`, h.`CreatedBy`, f.`MainHouse`
FROM `a_data_haus` h
LEFT JOIN `a_data_filiale` f ON f.FilID = h.`HausFilID`
WHERE (h.`HausFilID` = ?)";
        $housesParams = [$filiationId];
        $housesResults = $this->fetchSome(null, $housesSql, $housesParams);

        //compile list of houseIds
        $houseIds = [];
        foreach ($housesResults as $house) {
            $houseIds[] = $house['HausID'];
        }

        //get list of all people
        $query = [
            'deceased'     => false,
            'exMembers'    => false,
        ];
        $allPersons = $this->searchPersons($query, false, true);

        //slim down the list of person to just people in the filiation and/or house
        $resultsFathers = [];
        if ($allPersons) {
            foreach ($allPersons as $person) {
                if ($person['filiationId'] == $filiationId || in_array($person['houseId'], $houseIds)) {
                    if ($person['filiationId'] != $filiationId) {
                        if (isset($person['labels']) && is_array($person['labels'])) {
                            $person['labels'][] = 'Other filiation';
                        } else {
                            $person['labels'] = ['Other filiation'];
                        }
                    }
                    array_push($resultsFathers, $person);
                }
            }
        } else {
            $resultsFathers = $allPersons;
        }

        $checkedFathers = []; //this array keeps track of which filiation people have been put into houses
        $houses = [];
        $generalPlacePattern = isset($this->config['post_place_line_format']) ?
            $this->config['post_place_line_format'] : $this::DEFAULT_PLACE_FORMAT;
        $countryPlacePatterns = isset($this->config['post_place_line_format_by_country']) ?
            $this->config['post_place_line_format_by_country'] : [];
        foreach ($housesResults as $house) {
            $street1          = $this->filterDbString($house['HausStrasse']);
            $street2          = $this->filterDbString($house['HausStrasse2']);
            $cityState        = $this->filterDbString($house['HausOrt']);
            $zip              = $this->filterDbString($house['HausPLZ']);
            $postSameAsAddress= $this->filterDbBool($house['HausPostSameAsAddress']);
            $postStreet1      = $this->filterDbString($house['HausPostStrasse']);
            $postStreet2      = $this->filterDbString($house['HausPostStrasse2']);
            $postCityState    = $this->filterDbString($house['HausPostOrt']);
            $postZip          = $this->filterDbString($house['HausPostPLZ']);
            $country          = strtoupper($this->filterDbString($house['HausLand']));

            if (isset($countryPlacePatterns[$country])) {
                $placePattern = $countryPlacePatterns[$country];
            } else {
                $placePattern = $generalPlacePattern;
            }
            $place = str_replace(':zip', $zip, $placePattern);
            $place = trim(str_replace(':cityState', $cityState, $place));
            $postPlace = str_replace(':zip', $postZip, $placePattern);
            $postPlace = trim(str_replace(':cityState', $postCityState, $postPlace));

            $persons = [];
            if ($resultsFathers) {
                foreach ($resultsFathers as $person) {
                    if ($person['houseId'] == $house['HausID']) {
                        array_push($persons, $person);
                        $checkedFathers[] = $person['personId'];
                    }
                }
            }

            $phones = [];
            $phone1           = $this->filterDbString($house['HausTel']);
            $phone1Label      = $this->filterDbString($house['HausTelLabel']);
            $phone2           = $this->filterDbString($house['HausTel2']);
            $phone2Label      = $this->filterDbString($house['HausTel2Label']);
            $phone3           = $this->filterDbString($house['HausTel3']);
            $phone3Label      = $this->filterDbString($house['HausTel3Label']);
            $fax1             = $this->filterDbString($house['HausFax']);
            $fax1Label        = $this->filterDbString($house['HausFaxLabel']);
            $fax2             = $this->filterDbString($house['HausFax2']);
            $fax2Label        = $this->filterDbString($house['HausFax2Label']);

            if (!is_null($phone1)) {
                $phones[] = array(
                    'label'    => $phone1Label ? $phone1Label : null,
                    'number'   => $phone1,
                );
            }
            if (!is_null($phone2)) {
                $phones[] = array(
                    'label'    => $phone2Label ? $phone2Label : null,
                    'number'   => $phone2,
                );
            }
            if (!is_null($phone3)) {
                $phones[] = array(
                    'label'    => $phone3Label ? $phone3Label : null,
                    'number'   => $phone3,
                );
            }
            $faxes = [];
            if (!is_null($fax1)) {
                $faxes[] = array(
                    'label'    => $fax1Label ? $fax1Label : null,
                    'number'   => $fax1,
                );
            }
            if (!is_null($fax2)) {
                $faxes[] = array(
                    'label'    => $fax2Label ? $fax2Label : null,
                    'number'   => $fax2,
                );
            }
            array_push($houses, array(
                'houseId'          => $this->filterDbId($house['HausID']),
                'houseName'        => $this->filterDbString($house['HausName']),
                'filiationId'      => $this->filterDbId($house['HausFilID']),
                'mainHouse'        => $house['MainHouse'] === $house['HausID'],
                'email'            => $this->filterEmailString($house['HausEmail1']),
                'website'          => $this->filterDbString($house['HausWeb']),
                'phone1'           => $phone1,
                'phone1Label'      => $phone1Label,
                'phone2'           => $phone2,
                'phone2Label'      => $phone2Label,
                'phone3'           => $phone3,
                'phone3Label'      => $phone3Label,
                'phones'           => $phones,
                'fax1'             => $fax1,
                'fax1Label'        => $fax1Label,
                'fax2'             => $fax2,
                'fax2Label'        => $fax2Label,
                'faxes'            => $faxes,
                'diocese'          => $this->filterDbString($house['HausDioezese']),
                'active'           => $this->filterDbBool($house['HausAktiv']),
                'street1'          => $street1,
                'street2'          => $street2,
                'cityState'        => $cityState,
                'zip'              => $zip,
                'placeLine'        => $place,
                'postSameAsAddress'=> $postSameAsAddress,
                'postStreet1'      => $postStreet1,
                'postStreet2'      => $postStreet2,
                'postCityState'    => $postCityState,
                'postZip'          => $postZip,
                'postPlaceLine'    => $postPlace,
                'country'          => $country,
                'placePattern'     => $placePattern,
                'persons'          => $persons
            ));
        }

        //sort list
        if (!empty($houses)) {
            $sort = [];
            foreach($houses as $k=>$v) {
                $sort['mainHouse'][$k] = $v['mainHouse'];
                $sort['active'][$k] = $v['active'];
                $sort['country'][$k] = $v['country'];
                $sort['houseName'][$k] = $v['houseName'];
            }
            # sort by event_type desc and then title asc
            array_multisort($sort['mainHouse'], SORT_DESC, $sort['active'], SORT_DESC,
               $sort['country'], SORT_ASC, $sort['houseName'], SORT_ASC, $houses);
        }

        //check if there were any people from the filiation who aren't in the houses
        $stragglers = array();
        if ($resultsFathers) {
            foreach ($resultsFathers as $person) {
                if ( !in_array($person['personId'], $checkedFathers)) {
                    array_push($stragglers, $person);
                }
            }
        }
        if (!empty($stragglers)) { //create a phantom house for the other people
            array_push($houses, array(
                'houseId'          => null,
                'houseName'        => 'People not in the filiation houses',
                'filiationId'      => $filiationId,
                'email'            => null,
                'website'          => null,
                'phone1'           => null,
                'phone1Label'      => null,
                'phone2'           => null,
                'phone2Label'      => null,
                'phone3'           => null,
                'phone3Label'      => null,
                'phones'           => null,
                'fax1'             => null,
                'fax1Label'        => null,
                'fax2'             => null,
                'fax2Label'        => null,
                'faxes'            => null,
                'diocese'          => null,
                'active'           => true,
                'street1'          => null,
                'street2'          => null,
                'cityState'        => null,
                'zip'              => null,
                'placeLine'        => null,
                'postSameAsAddress'=> null,
                'postStreet1'      => null,
                'postStreet2'      => null,
                'postCityState'    => null,
                'postZip'          => null,
                'postPlaceLine'    => null,
                'country'          => 'ZZ',
                'placePattern'     => null,
                'persons'          => $stragglers
            ));
        }
        return $houses;
    }
    /**
     * no validation of id
     * @todo report errors
     * @param int|string $id
     */
    public function deleteFiliation($id)
    {
        $result = $this->getFiliationTableGateway()->delete(array('FilID' => $id));
        return $result;
    }

    public function getSimpleHouse($id)
    {
        $houseSql = "SELECT `HausID`, `HausName`, `HausEmail1`, `HausEmail2`,
`HausWeb`, `HausStrasse`, `HausStrasse2`,
`HausOrt`, `HausPLZ`, `HausPostSameAsAddress`,
`HausPostStrasse`, `HausPostStrasse2`, `HausPostOrt`, `HausPostPLZ`,
`HausLand`, `HausTel`, `HausTelLabel`,
`HausTel2`, `HausTel2Label`, `HausTel3`, `HausTel3Label`,
`HausFax`, `HausFaxLabel`, `HausFax2`, `HausFax2Label`, `HausTelsUpdatedOn`,
`HausTelsUpdatedBy`,  `HausDioezese`, `HausDioezeseUrl`,
`HausAktiv`, `HausFilID`, `WifiPassword`, `WifiPasswordUpdatedOn`,
`WifiPasswordUpdatedBy`, `PublicNotes`, `PublicNotesUpdatedOn`,
`PublicNotesUpdatedBy`, `AdminNotes`, `AdminNotesUpdatedOn`,
`AdminNotesUpdatedBy`, `UpdatedOn`, `UpdatedBy`, `CreatedOn`,
`CreatedBy`
FROM `a_data_haus`
WHERE (HausID = ?)";
        $params = array($id);
        $resultsHouse = $this->fetchSome(null, $houseSql, $params);
        if (count($resultsHouse) != 1) { //we either didn't find it or got 2, weird
            throw new \Exception("House not found.");
        }
        //manipulate column names
        $result = array(
            'houseId'                   => $resultsHouse[0]['HausID'],
            'houseName'                 => $resultsHouse[0]['HausName'],
            'filiationId'               => $resultsHouse[0]['HausFilID'],
            'email'                     => $resultsHouse[0]['HausEmail1'],
            'website'                   => $resultsHouse[0]['HausWeb'],
            'phone1'                    => $resultsHouse[0]['HausTel'],
            'phone1Label'               => $resultsHouse[0]['HausTelLabel'],
            'phone2'                    => $resultsHouse[0]['HausTel2'],
            'phone2Label'               => $resultsHouse[0]['HausTel2Label'],
            'phone3'                    => $resultsHouse[0]['HausTel3'],
            'phone3Label'               => $resultsHouse[0]['HausTel3Label'],
            'fax1'                      => $resultsHouse[0]['HausFax'],
            'fax1Label'                 => $resultsHouse[0]['HausFaxLabel'],
            'fax2'                      => $resultsHouse[0]['HausFax2'],
            'fax2Label'                 => $resultsHouse[0]['HausFax2Label'],
            'phonesUpdatedOn'           => $resultsHouse[0]['HausTelsUpdatedOn'],
            'phonesUpdatedBy'           => $resultsHouse[0]['HausTelsUpdatedBy'],
            'diocese'                   => $resultsHouse[0]['HausDioezese'],
            'dioceseUrl'                => $resultsHouse[0]['HausDioezeseUrl'],
            'wifiPassword'              => $resultsHouse[0]['WifiPassword'],
            'wifiPasswordUpdatedOn'     => $resultsHouse[0]['WifiPasswordUpdatedOn'],
            'wifiPasswordUpdatedBy'     => $resultsHouse[0]['WifiPasswordUpdatedBy'],
            'active'                    => $resultsHouse[0]['HausAktiv'],
            'street1'                   => $resultsHouse[0]['HausStrasse'],
            'street2'                   => $resultsHouse[0]['HausStrasse2'],
            'cityState'                 => $resultsHouse[0]['HausOrt'],
            'zip'                       => $resultsHouse[0]['HausPLZ'],
            'postSameAsAddress'         => $resultsHouse[0]['HausPostSameAsAddress'],
            'postStreet1'               => $resultsHouse[0]['HausPostStrasse'],
            'postStreet2'               => $resultsHouse[0]['HausPostStrasse2'],
            'postCityState'             => $resultsHouse[0]['HausPostOrt'],
            'postZip'                   => $resultsHouse[0]['HausPostPLZ'],
            'country'                   => $resultsHouse[0]['HausLand'],
            'publicNotes'               => $resultsHouse[0]['PublicNotes'],
            'publicNotesUpdatedOn'      => $resultsHouse[0]['PublicNotesUpdatedOn'],
            'publicNotesUpdatedBy'      => $resultsHouse[0]['PublicNotesUpdatedBy'],
            'adminNotes'                => $resultsHouse[0]['AdminNotes'],
            'adminNotesUpdatedOn'       => $resultsHouse[0]['AdminNotesUpdatedOn'],
            'adminNotesUpdatedBy'       => $resultsHouse[0]['AdminNotesUpdatedBy'],
            'updatedOn'                 => $resultsHouse[0]['UpdatedOn'],
            'updatedBy'                 => $resultsHouse[0]['UpdatedBy']
        );
        return $result;
    }

    /**
     *
     * @param int|string $id
     * @param string[][] $data
     */
    public function updateHouse($id, $data) {
        $tableName     = 'a_data_haus';
        $tableKey      = 'HausID';
        $tableGateway  = $this->getHouseTableGateway();

        if (!is_numeric($id)) {
            throw new \InvalidArgumentException('Invalid house id provided.');
        }
        $house = $this->getSimpleHouse($id);
        if (!$house) {
            throw new \InvalidArgumentException('No house provided.');
        }
        $manyToOneUpdateColumns = array(
            'phone1'                    => 'phones',
            'phone1Label'               => 'phones',
            'phone2'                    => 'phones',
            'phone2Label'               => 'phones',
            'phone3'                    => 'phones',
            'phone3Label'               => 'phones',
            'fax1'                      => 'phones',
            'fax1Label'                 => 'phones',
            'fax2'                      => 'phones',
            'fax2Label'                 => 'phones',
        );
        $updateCols = array(
            'houseId'                   => 'HausID',
            'houseName'                 => 'HausName',
            'filiationId'               => 'HausFilID',
            'email'                     => 'HausEmail1',
            'website'                   => 'HausWeb',
            'phone1'                    => 'HausTel',
            'phone1Label'               => 'HausTelLabel',
            'phone2'                    => 'HausTel2',
            'phone2Label'               => 'HausTel2Label',
            'phone3'                    => 'HausTel3',
            'phone3Label'               => 'HausTel3Label',
            'fax1'                      => 'HausFax',
            'fax1Label'                 => 'HausFaxLabel',
            'fax2'                      => 'HausFax2',
            'fax2Label'                 => 'HausFax2Label',
            'phonesUpdatedOn'           => 'HausTelsUpdatedOn',
            'phonesUpdatedBy'           => 'HausTelsUpdatedBy',
            'diocese'                   => 'HausDioezese',
            'dioceseUrl'                => 'HausDioezeseUrl',
            'wifiPassword'              => 'WifiPassword',
            'wifiPasswordUpdatedOn'     => 'WifiPasswordUpdatedOn',
            'wifiPasswordUpdatedBy'     => 'WifiPasswordUpdatedBy',
            'active'                    => 'HausAktiv',
            'street1'                   => 'HausStrasse',
            'street2'                   => 'HausStrasse2',
            'cityState'                 => 'HausOrt',
            'zip'                       => 'HausPLZ',
            'postSameAsAddress'         => 'HausPostSameAsAddress',
            'postStreet1'               => 'HausPostStrasse',
            'postStreet2'               => 'HausPostStrasse2',
            'postCityState'             => 'HausPostOrt',
            'postZip'                   => 'HausPostPLZ',
            'country'                   => 'HausLand',
            'publicNotes'               => 'PublicNotes',
            'publicNotesUpdatedOn'      => 'PublicNotesUpdatedOn',
            'publicNotesUpdatedBy'      => 'PublicNotesUpdatedBy',
            'adminNotes'                => 'AdminNotes',
            'adminNotesUpdatedOn'       => 'AdminNotesUpdatedOn',
            'adminNotesUpdatedBy'       => 'AdminNotesUpdatedBy',
            'updatedOn'                 => 'UpdatedOn',
            'updatedBy'                 => 'UpdatedBy'
        );
        return $this->updateHelper($id, $data, $tableName, $tableKey, $tableGateway, $updateCols, $house, $manyToOneUpdateColumns);
    }

    /**
     *
     * @param string[][] $data
     */
    public function createHouse($data) {
        $tableName     = 'a_data_haus';
        $tableGateway  = $this->getHouseTableGateway();
        $scope         = 'House';
        $requiredCols  = array(
            'houseName',
            'filiationId',
            'country',
        );
        $manyToOneUpdateColumns = array(
            'phone1'                    => 'phones',
            'phone1Label'               => 'phones',
            'phone2'                    => 'phones',
            'phone2Label'               => 'phones',
            'phone3'                    => 'phones',
            'phone3Label'               => 'phones',
            'fax1'                      => 'phones',
            'fax1Label'                 => 'phones',
            'fax2'                      => 'phones',
            'fax2Label'                 => 'phones',
        );
        $updateCols = array(
            'houseId'                   => 'HausID',
            'houseName'                 => 'HausName',
            'filiationId'               => 'HausFilID',
            'email'                     => 'HausEmail1',
            'website'                   => 'HausWeb',
            'phone1'                    => 'HausTel',
            'phone1Label'               => 'HausTelLabel',
            'phone2'                    => 'HausTel2',
            'phone2Label'               => 'HausTel2Label',
            'phone3'                    => 'HausTel3',
            'phone3Label'               => 'HausTel3Label',
            'fax1'                      => 'HausFax',
            'fax1Label'                 => 'HausFaxLabel',
            'fax2'                      => 'HausFax2',
            'fax2Label'                 => 'HausFax2Label',
            'phonesUpdatedOn'           => 'HausTelsUpdatedOn',
            'phonesUpdatedBy'           => 'HausTelsUpdatedBy',
            'diocese'                   => 'HausDioezese',
            'dioceseUrl'                => 'HausDioezeseUrl',
            'wifiPassword'              => 'WifiPassword',
            'wifiPasswordUpdatedOn'     => 'WifiPasswordUpdatedOn',
            'wifiPasswordUpdatedBy'     => 'WifiPasswordUpdatedBy',
            'active'                    => 'HausAktiv',
            'street1'                   => 'HausStrasse',
            'street2'                   => 'HausStrasse2',
            'cityState'                 => 'HausOrt',
            'zip'                       => 'HausPLZ',
            'postSameAsAddress'         => 'HausPostSameAsAddress',
            'postStreet1'               => 'HausPostStrasse',
            'postStreet2'               => 'HausPostStrasse2',
            'postCityState'             => 'HausPostOrt',
            'postZip'                   => 'HausPostPLZ',
            'country'                   => 'HausLand',
            'publicNotes'               => 'PublicNotes',
            'publicNotesUpdatedOn'      => 'PublicNotesUpdatedOn',
            'publicNotesUpdatedBy'      => 'PublicNotesUpdatedBy',
            'adminNotes'                => 'AdminNotes',
            'adminNotesUpdatedOn'       => 'AdminNotesUpdatedOn',
            'adminNotesUpdatedBy'       => 'AdminNotesUpdatedBy',
            'updatedOn'                 => 'UpdatedOn',
            'updatedBy'                 => 'UpdatedBy'
        );
        return $this->createHelper($data, $requiredCols, $updateCols, $tableName, $tableGateway, $scope, $manyToOneUpdateColumns);
    }

    /**
     * Get a simple list of persons to fill a autocomplete list
     * @param bool $showDeceased
     * @param bool $showExMembers
     * @return array
     */
    public function getSimplePersonList($showDeceased = false, $showExMembers = false)
    {
        $where = '';
        if (!$showDeceased && !$showExMembers) {
            $where = "WHERE (p.`PersAus` = '0000-00-00' OR ISNULL(p.`PersAus`)) AND (p.PersTod = '0000-00-00' OR ISNULL(p.PersTod))";
        } elseif (!$showDeceased || !$showExMembers) {
            if (!$showDeceased) {
               $where = "WHERE (p.PersTod = '0000-00-00' OR ISNULL(p.PersTod))";
            }
            if (!$showExMembers) {
               $where = "WHERE (p.`PersAus` = '0000-00-00' OR ISNULL(p.`PersAus`))";
            }
        }
        $sqlPersons = "SELECT p.`PersID`,p.`PersVorname`, p.`PersName`, p.`PersTod`, p.`PersAus`, p.`PersUrsprLand`
FROM `a_data_person` p
$where
ORDER BY p.`PersVorname`";
        $resultsPersons = $this->fetchSome ( null, $sqlPersons, null );
        $return = array();
        foreach ($resultsPersons as $row) {
            //$return[$row['PersID']] = array(
            $return[] = array(
                'personId'     => $row['PersID'],
                'label'        => $row['PersVorname'].' '.$row['PersName'],
//                 'firstName'    => $row['PersVorname'],
//                 'lastName'     => $row['PersName'],
//                 'deathDate'    => $row['PersTod'],
//                 'leaveDate'    => $row['PersAus'],
//                 'country'      => $row['Country'],
            );
        }
        return !empty($return) ? $return : null;
    }

    public function getPerson($id)
    {
        $persons = $this->getPersons();
        if (!isset($persons[$id]) || !($person = $persons[$id])) {
            return null;
        }
        return $person;
    }

    /**
     *
     * @param int $daysInAdvance
     * @param bool $includeDeceased
     * @param bool $includeLeftPersons
     * return array(
     *     'date' => ,
     *     'dateType' => ,
     *     'yearsAgo' => ,
     *     'isImportant' => ,
     *     'entity' => 'person',
     *     'entityId' => ,
     *     'entityName => ,
     *     'entityCountry' => ,
     *     'entityObject' => array(...),s
     * );
     */
    public function getPersonDates($daysInAdvance, $includeDeceased = false, $includeLeftPersons = false)
    {
        $persons = $this->getPersons();
        $sometimeToday = new \DateTime(null, new \DateTimeZone('UTC'));
        $sometimeYesterday = new \DateTime(null, new \DateTimeZone('UTC'));
        $sometimeYesterday->modify('-1 day'); //today is passed midnight, so subtract a day to make sure we dont cut off today's dates.
        $xDaysInAdvance = $sometimeToday->add(new \DateInterval(sprintf("P%sD", $daysInAdvance)));
        $yearsFrom1900 = (int)$sometimeToday->format('Y') - 1900;
        $return = array();
        foreach ($persons as $person) {
            //exclude ex-members
            if ($person['category'] == $this::CATEGORY_EXMEMBER && !$includeLeftPersons) {
                continue;
            }

            //death day
            if (is_object($person['deathDate'])) {

                $yearsAgo = null;
                if (is_object($person['deathDate'])) {
                    $interval = $sometimeToday->diff($person['deathDate']);
                    $yearsAgo = $interval->y;
                }
                $date = $person['deathDate']->add(new \DateInterval(sprintf('P%sY', $yearsAgo)));
                if ($date < $sometimeYesterday) {
                    $date->add(new \DateInterval('P1Y'));
                    $yearsAgo++;
                }
                if ($date < $xDaysInAdvance) {
                    $isImportant = (0 == $yearsAgo % 25) || (0 == $yearsAgo % 10);
                    $return[] = array(
                       'date'          => $date,
                       'dateType'      => 'deathDate',
                       'yearsAgo'      => $yearsAgo,
                       'isImportant'   => $isImportant,
                       'entity'        => 'person',
                       'entityId'      => $person['personId'],
                       'entityName'    => $person['fullFriendlyName'],
                       'entityCountry' => $person['country'],
                       'entityObject'  => $person,
                    );
                }
                if (!$includeDeceased) {
                   continue;
                }
            }

            //priestly anniversary
            if (is_object($person['priestDate'])) {
                $yearsAgo = $person['priestYears'];
                $date = $person['priestDate']->add(new \DateInterval(sprintf('P%sY', $yearsAgo)));
                if ($date < $sometimeYesterday) {
                    $date->add(new \DateInterval('P1Y'));
                    $yearsAgo++;
                }
                if ($date < $xDaysInAdvance) {
                    $isImportant = (0 == $yearsAgo % 25) || (0 == $yearsAgo % 10);
                    $return[] = array(
                       'date'          => $date,
                       'dateType'      => 'priestDate',
                       'yearsAgo'      => $yearsAgo,
                       'isImportant'   => $isImportant,
                       'entity'        => 'person',
                       'entityId'      => $person['personId'],
                       'entityName'    => $person['fullFriendlyName'],
                       'entityCountry' => $person['country'],
                       'entityObject'  => $person,
                    );
                }
            }
            //birthday
            if (is_object($person['birthDate'])) {
                $yearsAgo = $person['age'];
                $date = $person['birthDate']->add(new \DateInterval(sprintf('P%sY', $yearsAgo)));
                if ($date < $sometimeYesterday) {
                    $date->add(new \DateInterval('P1Y'));
                    $yearsAgo++;
                }
                if ($date < $xDaysInAdvance) {
                    $isImportant = (0 == $yearsAgo % 10) || $yearsAgo == 75;
                    $return[] = array(
                       'date'          => $date,
                       'dateType'      => 'birthDate',
                       'yearsAgo'      => $yearsAgo,
                       'isImportant'   => $isImportant,
                       'entity'        => 'person',
                       'entityId'      => $person['personId'],
                       'entityName'    => $person['fullFriendlyName'],
                       'entityCountry' => $person['country'],
                       'entityObject'  => $person,
                    );
                }
            }

            //nameday
            if (is_object($person['nameDay'])) { //they should all be of the year 1900
                $date = $person['nameDay']->add(new \DateInterval(sprintf('P%sY', $yearsFrom1900)));
                if ($date < $sometimeYesterday) {
                    $date->add(new \DateInterval('P1Y'));
                    $yearsAgo++;
                }
                if ($date < $xDaysInAdvance) {
                    $isImportant = false;
                    $return[] = array(
                       'date'          => $date,
                       'dateType'      => 'nameDay',
                       'yearsAgo'      => null,
                       'isImportant'   => $isImportant,
                       'entity'        => 'person',
                       'entityId'      => $person['personId'],
                       'entityName'    => $person['fullFriendlyName'],
                       'entityCountry' => $person['country'],
                       'entityObject'  => $person,
                    );
                }
            }
        }
        return $return;
    }

    public function existsPerson($id)
    {
        try {
            $result = $this->getPersonTableGateway()->select(array('PersID' => $id));
        }
        catch (\Exception $e) {
            return false;
        }
        return 1 == $result->count();
    }

    public function createFirstLivingSituations($simulate = true)
    {
        $persons = $this->getPersons();
        $tz = new \DateTimeZone('UTC');
        $now = new \DateTime(null, $tz);
        $startDate = \DateTime::createFromFormat('Y-m-d', $now->format('Y').'-01-01', $tz);
        $i = 0;

        foreach ($persons as $person) {
            if (!empty($person['livingSituations']) || !is_null($person['leaveDate']) ||
                !is_null($person['deathDate']) || is_null($person['oldResponsibleTerritoryId']) ||
                is_null($person['oldFiliationId']) || is_null($person['oldHouseId'])) {
                $i++;
                continue;
            }
            $id = $person['personId'];
            if ($person['glSpez'] == 'Assoz') { //PersGISpez
                $status = $this::STATUS_ASSOCIATED;
//                         var_dump($person);
            } elseif ($person['glSpez'] == 'Exii') {
                $status = $this::STATUS_EXTERN_EXEMPT;
//                         var_dump($person);
            } else {
                switch ($person['persStatus']) { //PersGruppePers
                    case 'gs':
                        $status = $this::STATUS_EXTERN_EXEMPT;
//                         var_dump($person);
                        break;
                    case 'ex':
                        $status = $this::STATUS_EXTERN;
//                         var_dump($person);
                        break;
                    default:
                        $status = $this::STATUS_INTERN;
                        break;
                }
            }
            $data[$person['personId']] = [
                'personId' => $person['personId'],
                'responsibleTerritoryId' => $person['oldResponsibleTerritoryId'],
                'filiationId' => $person['oldFiliationId'],
                'houseId' => $person['oldHouseId'],
                'status' => $status,
                'startDate' => $startDate,
                'adminNotes' => 'Living situation automatically generated.',
            ];
            if (!$simulate) {
                $this->createEntity('living_situation', $data[$person['personId']]);
            }
            $persons[$person['personId']]['status'] = $status;
            $persons[$person['personId']]['startDate'] = $data[$person['personId']]['startDate'];
            $persons[$person['personId']]['adminNotes'] = $data[$person['personId']]['adminNotes'];
        }
        return $persons;
    }

    public function fixPersonCountries($simulate = false)
    {
        $countryMap = array(
            '14' => 'AT',
            '2' => 'AU',
            '28' => 'BY',
            '3' => 'BR',
            '26' => 'CU',
            '18' => 'CH',
            '11' => 'CO',
            '24' => 'CD',
            '21' => 'CZ',
            '5' => 'DE',
            '19' => 'ES',
            '6' => 'EC',
            '23' => 'FR',
            '7' => 'GB',
            '30' => 'HU',
            '10' => 'IT',
            '9' => 'IN',
            '31' => 'KE',
            '12' => 'MX',
            '13' => 'NG',
            '17' => 'PT',
            '16' => 'PL',
            '25' => 'PR',
            '15' => 'PY',
            '1' => 'AR',
            '8' => 'CL',
            '29' => 'PH',
            '4' => 'BI',
            '20' => 'ZA',
            '22' => 'US',
            '32' => 'UY',
            '27' => 'VE',
            '33' => 'CD',
            '34' => 'GM',
            '35' => 'SL',
            '36' => 'GH',
            '37' => 'TZ',
            '38' => 'GN',
        );
        $sql = "SELECT PersID, PersUrsprLandID, PersUrsprLand, PersVorname, PersName, PersTod
FROM a_data_person
WHERE (PersUrsprLand IS NULL || PersUrsprLand = '') AND PersUrsprLandID IS NOT NULL  AND PersUrsprLandID <> 0";
        $results = $this->fetchSome(null, $sql, null);
        $changes = array();
        foreach ($results as $row) {
//             var_dump($row);
            if (key_exists($row['PersUrsprLandID'], $countryMap)) {
                $updateVals = array('PersUrsprLand' => $countryMap[$row['PersUrsprLandID']]);
                $changes[] = array(
                    'table'    => 'a_data_person',
                    'column'   => 'country',
                    'id'       => $row['PersID'],
                    'oldValue' => '',
                    'newValue' => $countryMap[$row['PersUrsprLandID']],

                    'personFirstName' => $row['PersVorname'],
                    'personLastName' => $row['PersName'],
                    'personDeathDate' => $row['PersTod'],
                );
                if (!$simulate) {
                    $updateResult = $this->getPersonTableGateway()->update($updateVals,
                       array('PersID' => $row['PersID']));
                }
            }
        }
        if (!$simulate) {
           $this->reportChange($changes);
        }
        return $changes;
    }

    /**
     * Get a parsed array of all the persons in the database
     * Results are cached
     * Default order is leaveDate, deathDate, country, lastName
     * @return array
     */
    public function getPersons()
    {
        if ($this->personsCache) {
            return $this->personsCache;
        }

        $sqlPers = "SELECT `PersonId`, `LastName`, `FirstName`,
`LastNameWithoutAccents`, `FirstNameWithoutAccents`, `ReligiousStatus`, `Title`,
`TitleAutomatic`, `Country`, `BirthDate`, `NameDay`, `DeaconDate`, `PriestDate`,
`BishopDate`, `DeathDate`, `LeaveDate`, `PublicNotes`, `PublicNotesUpdatedOn`,
`PublicNotesUpdatedBy`, `PersonalInfoUpdatedOn`, `PersonalInfoUpdatedBy`, `AdminTags`,
`BirthCity`, `Nationalities`, `AdminNotes`, `AdminNotesUpdatedOn`,
`AdminNotesUpdatedBy`, `PrivateInfoUpdatedOn`, `PrivateInfoUpdatedBy`,
`Email`, `Email2`, `EmailsUpdatedOn`, `EmailsUpdatedBy`, `CellPhone`,
`CellPhoneHasWhatsApp`, `Phone1`, `Phone1Label`, `Phone2`, `Phone2Label`, `Phone3`,
`Phone3Label`, `PhonesUpdatedOn`, `PhonesUpdatedBy`, `Url1`, `Url1Label`, `Url2`,
`Url2Label`, `Url3`, `Url3Label`, `FacebookUrl`, `SkypeUser`, `TwitterUser`,
`InstagramUser`, `SlackUser`, `ContactNotes`, `ContactInfoUpdatedOn`,
`ContactInfoUpdatedBy`, `UpdatedOn`, `UpdatedBy`, `CreatedOn`, `CreatedBy`
FROM `sch_persons` WHERE 1
ORDER BY `BirthDate`";
        $resultsFathers = $this->fetchSome(null, $sqlPers, null);
        if (is_null($resultsFathers) || 0 == count($resultsFathers)) {
            return null;
        }

        //sort list beforehand to not mess up the array key
        $sort = array();
        foreach($resultsFathers as $k=>$v) {
            $sort['LeaveDate'][$k] = $v['LeaveDate'];
            $sort['DeathDate'][$k] = $v['DeathDate'];
            $sort['Country'][$k] = $v['Country'];
            $sort['LastName'][$k] = $v['LastName'];
        }
        # sort by event_type desc and then title asc
        array_multisort($sort['LeaveDate'], SORT_ASC, $sort['DeathDate'], SORT_ASC,
           $sort['Country'], SORT_ASC, $sort['LastName'], SORT_ASC, $resultsFathers);

        $return = [];
        $filter = new ToNull();
        $tz = new \DateTimeZone('UTC');
        $today = new \DateTime(null, $tz);
        foreach ($resultsFathers as $row) {
            $id = $this->filterDbId($row['PersonId']);
            $leaveDate = $this->filterDbDate($row['LeaveDate']);
            $deathDate = $this->filterDbDate($row['DeathDate']);
            $deaconDate = $this->filterDbDate($row['DeaconDate']);
            $priestDate = $this->filterDbDate($row['PriestDate']);
            $bishopDate = $this->filterDbDate($row['BishopDate']);
            $birthDate = $this->filterDbDate($row['PersGeburtstag']);
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
            $priestYears = null;
            if (is_object($priestDate)) {
                $interval = $today->diff($priestDate);
                $priestYears = $interval->y;
            }
            $nationalities = $this->filterDbArray($row['Nationalities']);
            foreach ($nationalities as $key => $value) {
                if (2 !== strlen($value)) {
                    unset($nationalities[$key]);
                }
            }

            //process URLs
            //@todo check URL against Google Safe Browsing
            $unprocessedUrls = [
                ['url' => $row['Url1'], 'label' => $this->filterDbString($row['Url1Label'])],
                ['url' => $row['Url2'], 'label' => $this->filterDbString($row['Url2Label'])],
                ['url' => $row['Url3'], 'label' => $this->filterDbString($row['Url3Label'])],
            ];
            $urls = [];

            foreach ($unprocessedUrls as $urlRow) {
                if (!is_null($urlRow['url'])) {
                    $url = new Http($urlRow['url']);
                    if ($url->isValid()) {
                        if (!is_null($urlRow['label']) && 0 !== strlen($urlRow['label'])) {
                            $label = $urlRow['label'];
                        } else {
                            $label = $url->getHost();
                        }
                        $urls[] = ['url' => $url->toString(), 'label' => $label];
                    }
                }
            }

            $today = new \DateTime(null, $tz);
            $isLiving = is_null($deathDate);
            $isMember = !(!is_null($leaveDate) && $leaveDate < $today);
            $category = null;
            $condition = null;
            $title = null;
            if (!$isMember) {
                $category = $this::CATEGORY_EXMEMBER;
                if (!is_null($bishopDate) && $bishopDate < $today) {
                    $condition = $this::CONDITION_EXMEMBER_BISHOP;
                } elseif (!is_null($priestDate) && $priestDate < $today) {
                    $condition = $this::CONDITION_EXMEMBER_PRIEST;
                } elseif (!is_null($deaconDate) && $deaconDate < $today) {
                    $condition = $this::CONDITION_EXMEMBER_DEACON;
                } elseif (!is_null($deathDate)) {
                    $condition = $this::CONDITION_DECEASED_EXMEMBER;
                } else {
                    $condition = $this::CONDITION_EXMEMBER;
                }
            } elseif (!$isLiving) {
                $category = $this::CATEGORY_DECEASED;
                if (!is_null($bishopDate) && $bishopDate < $today) {
                    $title = $this::TITLE_BISHOP;
                    $condition = $this::CONDITION_DECEASED_BISHOP;
                } elseif (!is_null($priestDate) && $priestDate < $today) {
                    $title = $this::TITLE_PRIEST;
                    $condition = $this::CONDITION_DECEASED_PRIEST;
                } elseif (!is_null($deaconDate) && $deaconDate < $today) {
                    $title = $this::TITLE_DEACON;
                    $condition = $this::CONDITION_DECEASED_DEACON;
                } else {
                    $condition = $this::CONDITION_DECEASED;
                }
            } elseif (!is_null($bishopDate) && $bishopDate < $today) {
                $category = $this::CATEGORY_BISHOP;
                $title = $this::TITLE_BISHOP;
                $condition = $this::CONDITION_BISHOP;
            } elseif (!is_null($priestDate) && $priestDate < $today) {
                $category = $this::CATEGORY_PRIEST;
                $title = $this::TITLE_PRIEST;
                $condition = $this::CONDITION_PRIEST;
            } elseif (!is_null($deaconDate) && $deaconDate < $today) {
                $category = $this::CATEGORY_DEACON;
                $title = $this::TITLE_DEACON;
                $condition = $this::CONDITION_DEACON;
            } else {
                $category = $this::CATEGORY_OTHER;
                $condition = $this::CONDITION_OTHER;
            }

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

            $return[$id] = [
                'personId'                  => $id,
                'isLiving'                  => $isLiving,
                'isMember'                  => $isMember,
                'isActive'                  => $isLiving && $isMember,
                'communityMembershipDate'   => $communityMembershipDate,
                'title'                     => $title, //this is a calculated field, not for updating
                'assignments'               => [],
                'roleTitles'                => [],
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
//                 'searchName'                => $row['SearchName'],
                'firstNameWithoutAccents'   => $this->filterDbString($row['FirstNameWithoutAccents']),
                'lastNameWithoutAccents'    => $this->filterDbString($row['LastNameWithoutAccents']),
                'fullFriendlyName'          => $fullName,
                'automaticTitle'            => $automaticTitle,
                'country'                   => $this->filterDbString($row['Country']),
                'category'                  => $category,
                'religiousStatus'           => $this->filterDbString($row['ReligiousStatus']),
                'condition'                 => $condition,
                'manualTitle'               => $manualTitle,
                'primaryLocale'             => 'en_US', //@todo add this column

                'deathDate'                 => $deathDate,
                'leaveDate'                 => $leaveDate,
                'deaconDate'                => $deaconDate,
                'priestDate'                => $priestDate,
                'bishopDate'                => $bishopDate,
                'priestYears'               => $priestYears,
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
                'contactNotes'              => $this->filterDbString($row['ContactNotes']),
                'contactNotesUpdatedOn'     => $this->filterDbDate($row['ContactNotesUpdatedOn']),
                'contactNotesUpdatedBy'     => $this->filterDbId($row['ContactNotesUpdatedBy']),

                'contactInfoUpdatedOn'      => $this->filterDbDate($row['ContactInfoUpdatedOn']),
                'contactInfoUpdatedBy'      => $this->filterDbDate($row['ContactInfoUpdatedBy']),

/**
 * Private info
 */
                'birthCity'                 => $this->filterDbString($row['BirthCity']),
                'nationalities'             => $nationalities,

                'adminTags'                 => $this->filterDbArray(strtolower($row['AdminTags'])),
                'adminNotes'                => $this->filterDbString($row['AdminNotes']),
                'adminNotesUpdatedOn'       => $this->filterDbDate($row['AdminNotesUpdatedOn']),
                'adminNotesUpdatedBy'       => $this->filterDbId($row['AdminNotesUpdatedBy']),

                'privateInfoUpdateOn'      => $this->filterDbDate($row['PrivateInfoUpdatedOn']),
                'privateInfoUpdateBy'      => $this->filterDbDate($row['PrivateInfoUpdatedBy']),
            ];
            //collect countries
            if ($row['PersUrsprLand'] && !in_array($row['PersUrsprLand'], $this->usedCountries)) {
                $this->usedCountries[] = $row['PersUrsprLand'];
            }
        }

//         $assignments = $this->getAssignments();
//         $aliases = $this->getAllRoleTitleAliases();
//         foreach ($assignments as $assignment) {
//             if (isset($return[$assignment['personId']])) {
//                 $return[$assignment['personId']]['assignments'][] = $assignment;
//             }
//             //add the role titles
//             if ($assignment['active']) {
//                 //check if the person in question already has this title
//                 if (!in_array($assignment['roleTitle'], $return[$assignment['personId']]['roleTitles'])) {
//                     $return[$assignment['personId']]['roleTitles'][] = $assignment['roleTitle'];
//                 }
//                 //lookup the alias
//                 if (isset($aliases[$assignment['roleTitle']])) {
//                     foreach ($aliases[$assignment['roleTitle']] as $alias) {
//                         if (!in_array($alias['alias'], $return[$assignment['personId']]['roleTitles'])) {
//                             $return[$assignment['personId']]['roleTitles'][] = $alias['alias'];
//                         }
//                     }
//                 }
//             }
//         }

        return $this->personsCache = $return;
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

    /**
     *
     * @param \DateTime $startDate
     * @param \DateTime $endDate
     * @return string|NULL
     */
    public static function getYearRange($startDate, $endDate)
    {
        if ((!is_null($startDate) && !$startDate instanceof \DateTime) ||
            (!is_null($endDate) && !$endDate instanceof \DateTime))
        {
            throw new \InvalidArgumentException('Date parameters must be either DateTime instances or null.');
        }

        $text = '';
        if ((!is_null($startDate) && $startDate instanceof \DateTime) ||
            (!is_null($endDate) && $startDate instanceof \DateTime))
        {
            if (!is_null($startDate) xor !is_null($endDate)) { //only one is set
                if (!is_null($startDate)) {
                    $text .=' '. $startDate->format('Y');
                } else {
                    $text .=' '. $endDate->format('Y');
                }
            } else {
                $startYear = (int)$startDate->format('Y');
                $endYear = (int)$endDate->format('Y');
                if ($startYear == $endYear) {
                    $text .=' '. $startYear;
                } else {
                    $text .=' '. $startYear.'-'.$endYear;
                }
            }
            return $text;
        } else {
            return null;
        }
    }

    /**
     * Returns a Living Situation array entity
     * @param int $id
     * @throws \Exception
     * @return NULL|mixed[]
     */
    public function getLivingSituation($id)
    {
        if (is_null($id) || $id < 1 ) {
            throw new \Exception('Invalid id.');
        }
        $allSituations = $this->getLivingSituations();
        if (!isset($allSituations[$id])) {
            return null;
        }
        return $allSituations[$id];
    }

    /**
     * Queries the database and returns a keyed-array of living situation array entities
     * Results are cached for the session
     * @return mixed[][]
     */
    public function getLivingSituations()
    {
        if (!is_null($this->livingSituationsCache)) {
            return $this->livingSituationsCache;
        }
        $sql = "SELECT l.`LivingSituationId`, l.`PersonId`, l.`FiliationId`, l.`HouseId`,
l.`SituationStatus`, l.`ResponsibleTerritoryId`, l.`PublicNotes`,
l.`PublicNotesUpdatedOn`, l.`PublicNotesUpdatedBy`,
l.`AdminNotes`, l.`AdminNotesUpdatedOn`, l.`AdminNotesUpdatedBy`,
l.`StartDate`, l.`EndDate`, l.`UpdatedOn`, l.`UpdatedBy`, l.`CreatedOn`, l.`CreatedBy`,
f.FilName,  f.Country AS FilCountry, f.MainHouse, f.Active AS FilActive,
h.HausLand, h.HausName, h.HausAktiv AS HausActive, h.HausFilID, h.HausTel,
g.Category AS GenCategory, g.GebName, g.Active AS GebActive
FROM `a_data_person_living` l
LEFT JOIN `a_data_filiale` f ON f.FilID = l.FiliationId
LEFT JOIN `a_data_haus` h ON h.HausID = l.HouseId
LEFT JOIN a_data_gebiet g ON g.`GebID` = l.ResponsibleTerritoryId
ORDER BY l.StartDate DESC";

        $rows = $this->fetchSome ( null, $sql, null );

        $tz = new \DateTimeZone('UTC');
        $now = new \DateTime(null, $tz);
        $livingSituations = [];
        foreach ($rows as $row) {
            $id =  $this->filterDbId($row['LivingSituationId']);
            $personId =  $this->filterDbId($row['PersonId']);
            $startDate = $this->filterDbDate($row['StartDate']);
            $endDate = $this->filterDbDate($row['EndDate']);
            $active = ($startDate < $now && (is_null($endDate) || $endDate > $now)) || (is_null($startDate) && (is_null($endDate) || $endDate > $now));
            $livingSituations[$id] = [
                'livingSituationId'     => $id,
                'personId'              => $personId,
//                 'personFullName'        => $persons[$personId]['fullName'],
                'filiationId'           => $this->filterDbId($row['FiliationId']),
                'filiationName'         => $this->filterDbString($row['FilName']),
                'filiationCountry'      => $this->filterDbString($row['FilCountry']),
                'filiationActive'       => $this->filterDbBool($row['FilActive']),
                'houseId'               => $this->filterDbId($row['HouseId']),
                'houseName'             => $this->filterDbString($row['HausName']),
                'houseFiliationId'      => $this->filterDbId($row['HausFilID']),
                'houseCountry'          => $this->filterDbString($row['HausLand']),
                'houseIsMain'           => $row['MainHouse'] == $row['HouseId'],
                'housePhone'            => $this->filterDbString($row['HausTel']),
                'houseActive'           => $this->filterDbBool($row['HausActive']),
                'responsibleTerritoryId'=> $this->filterDbId($row['ResponsibleTerritoryId']),
                'responsibleTerritoryName'=> $this->filterDbString($row['GebName']),
                'responsibleTerritoryActive'=> $this->filterDbBool($row['GebActive']),
                'status'                => $this->filterDbString($row['SituationStatus']),
                'startDate'             => $startDate,
                'endDate'               => $endDate,
                'active'                => $active,
                'publicNotes'           => $this->filterDbString($row['PublicNotes']),
                'publicNotesUpdatedOn'  => $this->filterDbDate($row['PublicNotesUpdatedOn']),
                'publicNotesUpdatedBy'  => $this->filterDbId($row['PublicNotesUpdatedBy']),
                'adminNotes'            => $this->filterDbString($row['AdminNotes']),
                'adminNotesUpdatedOn'   => $this->filterDbDate($row['AdminNotesUpdatedOn']),
                'adminNotesUpdatedBy'   => $this->filterDbId($row['AdminNotesUpdatedBy']),
                'updatedOn'             => $this->filterDbDate($row['UpdatedOn']),
                'updatedBy'             => $this->filterDbId($row['UpdatedBy']),
                'createdOn'             => $this->filterDbDate($row['CreatedOn']),
                'createdBy'             => $this->filterDbId($row['CreatedBy']),
            ];
        }

        $this->livingSituationsCache = $livingSituations;
        return $livingSituations;
    }

    /**
     *
     * @param int|string $id
     */
    public function existsLivingSituation($id)
    {
        try {
            $gateway = new TableGateway('a_data_person_living', $this->adapter);
            $result = $gateway->select(array('LivingSituationId' => $id));
        }
        catch (\Exception $e) {
            return false;
        }
        return 1 == $result->count();
    }

    /**
     * no validation of id
     * @todo report errors
     * @param int|string $id
     */
    public function deleteLivingSituation($id)
    {
        $gateway = new TableGateway('a_data_person_living', $this->adapter);
        $result = $gateway->delete(array('LivingSituationId' => $id));
        $changeVals = array(array(
            'table'    => 'a_data_person_living',
            'column'   => 'entryDeleted',
            'id'       => $id
        ));
        $this->reportChange($changeVals);
        return $result;
    }

    public function registerVisit($entity, $entityId)
    {
        if ($entity !== $this::ENTITY_COURSE &&
            $entity !== $this::ENTITY_TERRITORY &&
            $entity !== $this::ENTITY_FILIATION &&
            $entity !== $this::ENTITY_PERSON &&
            $entity !== $this::ENTITY_GENERATION )
        {
            throw new \InvalidArgumentException('Invalid entity submitted for visit registration');
        }

        if (!is_numeric($entityId)) {
            throw new \InvalidArgumentException('Invalid entity id submitted for visit registration');
        }

        $date = new \DateTime(null, new \DateTimeZone('UTC'));
        $params = [
            'Entity' => $entity,
            'EntityId' => $entityId,
            'UserId' => $this->actingUserId,
            'IpAddress' => $_SERVER['REMOTE_ADDR'],
            'VisitedAt' => $date->format('Y-m-d H:i:s'),
        ];
        $this->getVisitTableGateway()->insert($params);
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
        $return = array();
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
            if (isset($query['exMembers']) && !is_null($query['exMembers']) && !$query['exMembers'] && !is_null($person['leaveDate'])) {
                continue;
            }
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

            //check if status isn't in the list (but only worry when it's not null)
            if (isset($query['status']) && !is_null($query['status']) &&
                !is_null($person['status']) && !in_array($person['status'], $query['status']))
            {
                continue;
            }
            //now check the corner case of a null status
            if (is_null($person['status']) && !$statusAcceptNull) {
                continue;
            }

            if (isset($query['status']) && !is_null($query['status']) &&
                !in_array($person['status'], $query['status']) && !is_null($person['status']))
            {
                continue;
            }

            if (isset($query['roleTitle']) && !is_null($query['roleTitle']) &&
                !in_array($query['roleTitle'], $person['roleTitles']))
            {
                continue;
            }
            $return[$personId] = $person;
        }

        //add search to counter
        //@todo fix
        if ($registerSearch && false) {
            $date = new \DateTime(null, new \DateTimeZone('UTC'));
            $params = array('search_ip' => $_SERVER['REMOTE_ADDR'],
                'search_user' => $this->actingUserId,
                'search_datetime' => $date->format('Y-m-d H:i:s'),
                'search_results' => count($return),
                'search_query' => isset($query['search']) ? $query['search'] : null,
                'search_filiation' => isset($query['filiation']) ? $query['filiation'] : null,
                'search_course' => isset($query['course']) ? $query['course'] : null,
                'search_generation' => isset($query['generation']) ? $query['generation'] : null,
                'search_house' => isset($query['house']) ? $query['house'] : null,
                'search_country' => isset($query['country']) ? $query['country'] : null,
                'search_territory' => isset($query['territory']) ? $query['territory'] : null);
            $this->getSearchTableGateway()->insert($params);
        }
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
     * @param int|string $roleId
     */
    public function getRole($roleId)
    {
        $sqlRole = "SELECT `RoleId`, `RoleTitle`, `Scope`, `ScopeId`, `SinglePosition`, `Sort`
FROM `a_data_role` r
WHERE (r.`RoleId` = ?)";
        $params = array($roleId);

        try {
           $resultsRole = $this->fetchSome ( null, $sqlRole, $params );
        } catch (\Exception $e) {
            return null;
        }
        if (!$resultsRole || !$resultsRole [0]) {
            return null;
        }
        $simpleRoleList = $this->getSimpleRoleList();

        return array(
            'roleId'           => $this->filterDbId($resultsRole[0]['RoleId']),
            'title'            => $resultsRole[0]['RoleTitle'],
            'scope'            => $resultsRole[0]['Scope'],
            'scopeId'          => $this->filterDbId($resultsRole[0]['ScopeId']),
            'scopeName'        => $simpleRoleList[$resultsRole[0]['ScopeId'].$resultsRole[0]['Scope']]['label'],
            'isSinglePosition' => $this->filterDbBool($resultsRole[0]['SinglePosition']),
            'sort'             => $this->filterDbInt($resultsRole[0]['Sort']),
        );
    }

    /**
     *
     * @param int|string $id
     */
    public function getAssignment($id)
    {
        $inFiliation = $this->getAssociatedScopesForInStatement('Filiation', true);
        $inTerritory = $this->getAssociatedScopesForInStatement('Territory', true);
        $sqlAssignment = "SELECT a.*, r.*, k.KursName, f.FilName, gen.GenName, g.GebName,
rp.PersName, rp.PersVorname, rp.PersTod, rp.PersUrsprLand
FROM `a_data_role_assignment` a
LEFT JOIN `a_data_person` rp ON a.PersID = rp.PersID
LEFT JOIN `a_data_role` r ON a.RoleId = r.RoleId
LEFT JOIN `a_data_kurs` k ON r.ScopeId = k.KursID AND r.Scope = 'Course'
LEFT JOIN `a_data_filiale` f ON r.ScopeId = f.FilID AND r.Scope IN $inFiliation
LEFT JOIN `a_data_generation` gen ON r.ScopeId = gen.GenID AND r.Scope = 'Generation'
LEFT JOIN `a_data_gebiet` g ON r.ScopeId = g.GebID AND (r.Scope IN $inTerritory)
WHERE (a.AssignmentId = ?)";
        $params = [$id];
        $resultsAssignment = $this->fetchSome ( null, $sqlAssignment, $params );
        if ($resultsAssignment && $resultsAssignment [0]) {
            $assignment = $resultsAssignment [0];
            if (in_array($assignment['Scope'], $this->getAssociatedScopesForInStatement('Territory'))) {
                $scopeName = $assignment['GebName'];
            } else if (in_array($assignment['Scope'], $this->getAssociatedScopesForInStatement('Course'))) {
                $scopeName = $assignment['KursName'];
            } else if (in_array($assignment['Scope'], $this->getAssociatedScopesForInStatement('Generation'))) {
                $scopeName = $assignment['GenName'];
            } else if (in_array($assignment['Scope'], $this->getAssociatedScopesForInStatement('Filiation'))) {
                $scopeName = $assignment['FilName'];
            }
            $timeZone = new \DateTimeZone('UTC');
            $now = new \DateTime(null, $timeZone);
            $startDate = $this->filterDbDate($assignment['StartDate']);
            $endDate = $this->filterDbDate($assignment['EndDate']);
            $active = ($startDate < $now && (is_null($endDate) || $endDate > $now)) || (is_null($startDate) && (is_null($endDate) || $endDate > $now));
            $name = $assignment['RoleTitle'] .'-'.$scopeName;
            $yearRange = $this::getYearRange($startDate, $endDate);
            if (!is_null($yearRange)) {
                $name.= ' '.$yearRange;
            }
            return [
                'assignmentId' => $this->filterDbId($assignment['AssignmentId']),
                'assignmentName' => $name,
                'roleId' => $this->filterDbId($assignment['RoleId']),
                'roleTitle' => $assignment['RoleTitle'],
                'scope' => $assignment['Scope'],
                'scopeName' => $scopeName,
                'isSinglePosition' => $this->filterDbBool($assignment['SinglePosition']),
                'sort' => $this->filterDbInt($assignment['Sort']),
                'personId' => $this->filterDbId($assignment['PersID']),
                'startDate' => $startDate,
                'endDate' => $endDate,
                'assignmentFirstName' => $assignment['PersVorname'],
                'assignmentLastName' => $assignment['PersName'],
//                 'assignmentCell' => $assignment['PersHandy'],
//                 'assignmentEmail' => $assignment['PersEmail'],
                'assignmentCountry' => $assignment['PersUrsprLand'],
                'assignmentDeathDate' => $this->filterDbDate($assignment['PersTod']),
                'assignmentDeceased' => is_null($assignment['PersTod']) || $assignment['PersTod'] != '0000-00-00',
                'active' => $active,
            ];
        } else {
            return null;
        }
    }

    /**
     *
     * @param int|string $id
     */
    public function existsAssignment($id)
    {
        try {
            $result = $this->getAssignmentTableGateway()->select(array('AssignmentId' => $id));
        }
        catch (\Exception $e) {
            return false;
        }
        return 1 == $result->count();
    }

    /**
     *
     * data should be validated ahead of time
     *
     * @param array $data
     * @return int
     * @todo make sure status is properly outputted 1 for success, 0 or other for error
     */
    public function createAssignment($data) {
        $tableName     = 'a_data_role_assignment';
        $tableGateway  = $this->getAssignmentTableGateway();
        $scope         = null;
        $requiredCols  = array(
            'roleId',
            'personId'
        );
        $updateCols = array(
            'assignmentId' => 'AssignmentId',
            'roleId'       => 'RoleId',
            'personId'     => 'PersID',
            'startDate'    => 'StartDate',
            'endDate'      => 'EndDate',
            'updatedOn'    => 'UpdatedOn',
            'updatedBy'    => 'UpdatedBy',
            'createdOn'    => 'CreatedOn',
            'createdBy'    => 'CreatedBy',
        );
        return $this->createHelper($data, $requiredCols, $updateCols, $tableName, $tableGateway, $scope);
    }

    /**
     *
     * @param int|string $id
     * @param string $startDate
     * @param string $endDate
     * @todo factor out into a SionModel
     */
    public function updateAssignment($id, $data)
    {
        if (!is_numeric($id)) {
            throw new \InvalidArgumentException('Invalid assignment id provided.');
        }
        $assignment = $this->getAssignment($id);
        if (!$assignment) {
            throw new \InvalidArgumentException('No assignment provided.');
        }
        $tableName = 'a_data_role_assignment';
        $tableKey = 'AssignmentId';
        $tableGateway = $this->getAssignmentTableGateway();
        $updateCols = array(
            'assignmentId' => 'AssignmentId',
            'roleId'       => 'RoleId',
            'personId'     => 'PersID',
            'startDate'    => 'StartDate',
            'endDate'      => 'EndDate',
            'updatedOn'    => 'UpdatedOn',
            'updatedBy'    => 'UpdatedBy',
            'createdOn'    => 'CreatedOn',
            'createdBy'    => 'CreatedBy',
        );

        return $this->updateHelper($id, $data, $tableName, $tableKey, $tableGateway, $updateCols, $assignment);
    }

    /**
     * no validation of id
     * @todo report errors
     * @param int|string $id
     */
    public function deleteAssignment($id)
    {
        $result = $this->getAssignmentTableGateway()->delete(array('AssignmentId' => $id));
        $changeVals = array(array(
            'table'    => 'a_data_role_assignment',
            'column'   => 'entryDeleted',
            'id'       => $id
        ));
        $this->reportChange($changeVals);
        return $result;
    }

    public function getRoles()
    {
        if ($this->rolesCache) {
            return $this->rolesCache;
        }
        $sqlRoles = "SELECT r.`RoleId`, r.`RoleTitle`,r.`Scope`,r.`ScopeId`,
r.`SinglePosition`, r.`MainRole`, r.`Sort`, r.`Active`
FROM a_data_role r
ORDER BY r.`Scope`, r.`ScopeId`, r.`MainRole` DESC, r.`Sort`";
        $resultsRoles = $this->fetchSome ( null, $sqlRoles, null );
        $roles = array();
        foreach ($resultsRoles as $row) {
            $id = $this->filterDbId($row['RoleId']);
            $roles[$id] = array(
                'roleId'           => $id,
                'roleTitle'        => $row['RoleTitle'],
                'scope'            => $row['Scope'],
                'scopeId'          => $this->filterDbId($row['ScopeId']),
                'isSinglePosition' => $this->filterDbBool($row['SinglePosition']),
                'isMainRole'       => $this->filterDbBool($row['MainRole']),
                'isActive'         => $this->filterDbBool($row['Active']),
                'sort'             => $this->filterDbInt($row['Sort']),
            );
        }
        $this->rolesCache = $roles;
        return $roles;
    }

    public function getAssignments()
    {
        if ($this->assignmentsCache) {
            return $this->assignmentsCache;
        }
        $inFiliation = $this->getAssociatedScopesForInStatement('Filiation', true);
        $inTerritory = $this->getAssociatedScopesForInStatement('Territory', true);
        $sqlAssignments = "SELECT a.`AssignmentId`, a.`RoleId`, a.`PersID`,
a.`StartDate`, a.`EndDate`, a.`CreatedOn`, a.`CreatedBy`, a.`UpdatedOn`, a.`UpdatedBy`, r.RoleId AS RoleCheck,
r.RoleTitle, r.ScopeId, r.Scope, r.SinglePosition, r.Sort, k.KursName, f.FilName, gen.GenName, g.GebName,
rp.PersName, rp.PersVorname, rp.PersTod, rp.PersUrsprLand
FROM `a_data_role_assignment` a
LEFT JOIN `a_data_person` rp ON a.PersID = rp.PersID
LEFT JOIN `a_data_role` r ON a.RoleId = r.RoleId
LEFT JOIN `a_data_kurs` k ON r.ScopeId = k.KursID AND r.Scope = 'Course'
LEFT JOIN `a_data_filiale` f ON r.ScopeId = f.FilID AND r.Scope IN $inFiliation
LEFT JOIN `a_data_generation` gen ON r.ScopeId = gen.GenID AND r.Scope = 'Generation'
LEFT JOIN `a_data_gebiet` g ON r.ScopeId = g.GebID AND (r.Scope IN $inTerritory)";

        $resultsAssignments = $this->fetchSome ( null, $sqlAssignments, null );
        $assignments = array();
        $timeZone = new \DateTimeZone('UTC');
        $now = new \DateTime(null, $timeZone);
        foreach ($resultsAssignments as $row) {
            //check for invalid rows
            if (!$row['RoleCheck'] || !isset($this->scopeMap[$row['Scope']])) {
//                 var_dump($row);
                continue;
            }

            $id = $this->filterDbId($row['AssignmentId']);
            if (in_array($row['Scope'], $this->getAssociatedScopesForInStatement('Territory'))) {
                $scopeName = $row['GebName'];
            } else if (in_array($row['Scope'], $this->getAssociatedScopesForInStatement('Course'))) {
                $scopeName = $row['KursName'];
            } else if (in_array($row['Scope'], $this->getAssociatedScopesForInStatement('Generation'))) {
                $scopeName = $row['GenName'];
            } else if (in_array($row['Scope'], $this->getAssociatedScopesForInStatement('Filiation'))) {
                $scopeName = $row['FilName'];
            }
            $startDate = $this->filterDbDate($row['StartDate']);
            $endDate = $this->filterDbDate($row['EndDate']);
            $active = ($startDate < $now && (is_null($endDate) || $endDate > $now)) || (is_null($startDate) && (is_null($endDate) || $endDate > $now));

            $assignments[$id] = array(
                'assignmentId'     => $id,
                'active'           => $active,
                'roleId'           => $this->filterDbId($row['RoleId']),
                'personId'         => $this->filterDbId($row['PersID']),
                'startDate'        => $this->filterDbDate($row['StartDate']),
                'endDate'          => $this->filterDbDate($row['EndDate']),
                'createdOn'        => $this->filterDbDate($row['CreatedOn']),
                'createdBy'        => $this->filterDbId($row['CreatedBy']),
                'updatedOn'        => $this->filterDbDate($row['UpdatedOn']),
                'updatedBy'        => $this->filterDbId($row['UpdatedBy']),
                'scopeId'          => $this->filterDbId($row['ScopeId']),
                'roleTitle'        => $row['RoleTitle'],
                'scope'            => $row['Scope'],
                'baseScope'        => $this->scopeMap[$row['Scope']],
                'scopeName'        => $scopeName,
                'isSinglePosition' => $this->filterDbBool($row['SinglePosition']),
                'sort'             => $this->filterDbInt($row['Sort']),
//                 'assignmentFirstName' => $row['PersVorname'],
//                 'assignmentLastName' => $row['PersName'],
//                 'assignmentCell' => $assignment['PersHandy'],
//                 'assignmentEmail' => $assignment['PersEmail'],
//                 'assignmentCountry' => $row['PersUrsprLand'],
//                 'assignmentDeathDate' => $this->filterDbDate($row['PersTod']),
//                 'assignmentDeceased' => is_null($row['PersTod']) || $row['PersTod'] != '0000-00-00',
            );
        }
        $this->assignments = $assignments;
        return $assignments;
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

    /**
     * @return TableGateway
     */
    public function getCourseTableGateway()
    {
        if (null == $this->courseTableGateway) {
            $this->courseTableGateway = new TableGateway('a_data_kurs', $this->adapter);
        }
        return $this->courseTableGateway;
    }

    /**
     *
     * @param TableGateway $gateway
     * @return self
     */
    public function setCourseTableGateway($gateway)
    {
        $this->courseTableGateway = $gateway;
        return $this;
    }

    /**
     * @return TableGateway
     */
    public function getHouseTableGateway()
    {
        if (null == $this->houseTableGateway) {
            $this->houseTableGateway = new TableGateway('a_data_haus', $this->adapter);
        }
        return $this->houseTableGateway;
    }

    /**
     *
     * @param TableGateway $gateway
     * @return self
     */
    public function setHouseTableGateway($gateway)
    {
        $this->houseTableGateway = $gateway;
        return $this;
    }

    /**
     * @return TableGateway
     */
    public function getFiliationTableGateway()
    {
        if (null == $this->filiationTableGateway) {
            $this->filiationTableGateway = new TableGateway('a_data_filiale', $this->adapter);
        }
        return $this->filiationTableGateway;
    }

    /**
     *
     * @param TableGateway $gateway
     * @return self
     */
    public function setFiliationTableGateway($gateway)
    {
        $this->filiationTableGateway = $gateway;
        return $this;
    }

    /**
     * @return TableGateway
     */
    public function getAssignmentTableGateway()
    {
        if (null == $this->assignmentTableGateway) {
            $this->assignmentTableGateway = new TableGateway('a_data_role_assignment', $this->adapter);
        }
        return $this->assignmentTableGateway;
    }

    /**
     *
     * @param TableGateway $gateway
     * @return self
     */
    public function setAssignmentTableGateway($gateway)
    {
        $this->assignmentTableGateway = $gateway;
        return $this;
    }

    /**
     * @return TableGateway
     */
    public function getRoleTableGateway()
    {
        if (null == $this->assignmentTableGateway) {
            $this->roleTableGateway = new TableGateway('a_data_role', $this->adapter);
        }
        return $this->roleTableGateway;
    }

    /**
     *
     * @param TableGateway $gateway
     * @return self
     */
    public function setRoleTableGateway($gateway)
    {
        $this->roleTableGateway = $gateway;
        return $this;
    }

    /**
     * @return TableGateway
     */
    public function getPersonTableGateway()
    {
        if (null == $this->personTableGateway) {
            $this->personTableGateway = new TableGateway('a_data_person', $this->adapter);
        }
        return $this->personTableGateway;
    }

    /**
     *
     * @param TableGateway $gateway
     * @return self
     */
    public function setPersonTableGateway($gateway)
    {
        $this->personTableGateway = $gateway;
        return $this;
    }

    /**
     * @return TableGateway
     */
    public function getSuggestionTableGateway()
    {
        if (null == $this->suggestionTableGateway) {
            $this->suggestionTableGateway = new TableGateway('a_data_suggestion', $this->adapter);
        }
        return $this->suggestionTableGateway;
    }

    /**
     *
     * @param TableGateway $gateway
     * @return self
     */
    public function setSuggestionTableGateway($gateway)
    {
        $this->suggestionTableGateway = $gateway;
        return $this;
    }
    /**
     * @return TableGateway
     */
    public function getSuggestionColumnTableGateway()
    {
        if (null == $this->suggestionColumnTableGateway) {
            $this->suggestionColumnTableGateway = new TableGateway('a_data_suggestion_columns', $this->adapter);
        }
        return $this->suggestionColumnTableGateway;
    }

    /**
     *
     * @param TableGateway $gateway
     * @return self
     */
    public function setSuggestionColumnTableGateway($gateway)
    {
        $this->suggestionColumnTableGateway = $gateway;
        return $this;
    }
    /**
     * @return TableGateway
     */
    public function getSearchTableGateway()
    {
        if (null == $this->searchTableGateway) {
            $this->searchTableGateway = new TableGateway('a_data_searches', $this->adapter);
        }
        return $this->searchTableGateway;
    }

    /**
     *
     * @param TableGateway $gateway
     * @return self
     */
    public function setSearchTableGateway($gateway)
    {
        $this->searchTableGateway = $gateway;
        return $this;
    }

    /**
     * @return TableGateway
     */
    public function getVisitTableGateway()
    {
        if (null == $this->visitTableGateway) {
            $this->visitTableGateway = new TableGateway('a_data_visit', $this->adapter);
        }
        return $this->visitTableGateway;
    }

    /**
     *
     * @param TableGateway $gateway
     * @return self
     */
    public function setVisitTableGateway($gateway)
    {
        $this->visitTableGateway = $gateway;
        return $this;
    }
}
