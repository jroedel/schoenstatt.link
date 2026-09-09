<?php
namespace Schoenstatt\Service;

use Schoenstatt\Model\SchoenstattTable;
use Laminas\InputFilter\InputFilterInterface;
use Symfony\Component\HttpClient\HttpClient;
use App\Json;
use Psr\Log\LoggerInterface;

class PatresGateway
{

    /**
    * @var mixed[] $schoenstattConfig
    */
    protected $schoenstattConfig;

    /**
     * @var string $personUriFormat
     */
    protected $personUriFormat;

    /**
     * @var string $apiKey
     */
    protected $apiKey;

    /**
    * @var SchoenstattTable $schoenstattTable
    */
    protected $schoenstattTable;

    /**
     * @var InputFilterInterface $personInputFilter
     */
    protected $personInputFilter;

    /**
     * @var string $personListUri
     */
    protected $personListUri;

    /**
     * Validation messages from the last getRemotePerson() call, keyed by field.
     *
     * @var array<string, mixed> $lastRemotePersonMessages
     */
    protected $lastRemotePersonMessages = [];

    /**
     *
     * @var LoggerInterface $logger
     */
    protected $logger;

    /**
     * Given a patres personId, a person from the SchoenstattTable is returned. Person is imported if requested
     * @param number $patresPersonId
     * @param bool $importPersonIfNotFound
     * @param array $options May specify changes to be made to the person record in SchoenstattTable
     */
    public function getSchoenstattPersonFromPatresPersonId(
        $patresPersonId,
        $importPersonIfNotFound = false,
        $options = []
    ) {
        $table = $this->getSchoenstattTable();
        if (false === $personData = $this->getPersonInSchoenstattTable($patresPersonId)) {
            if ($importPersonIfNotFound) {
                if (false === $newId = $this->importRemotePerson($patresPersonId, $options)) {
                    return false;
                }
                $personData = $this->getSchoenstattTable()->getPerson($newId);
            } else {
                return false;
            }
        } else { //check if we need to make changes based on $options
            if ($this->complementSchoenstattTablePersonDataKeysWithOptions($personData, $options)) {
                $personId = $personData['personId'];
                $table = $this->getSchoenstattTable();
                $table->updateEntity('person', $personId, $personData);
                $personData = $table->getPerson($personId);
            }
        }
        return $personData;
    }

    /**
     * Get associative array of persons from Patres database $personId => $name
     * @throws \Exception
     * @return array
     */
    public function getPersonList()
    {
        $cacheKey = 'patres-gateway-person-list';
        if (isset($this->schoenstattTable)
            && null !== ($cache = $this->schoenstattTable->fetchCachedEntityObjects($cacheKey))
        ) {
            return $cache;
        }
        $key = $this->getApiKey();
        $listUrl = $this->getPersonListUri();
        try {
            $response = HttpClient::create()->request('GET', $listUrl, ['query' => ['key' => $key]]);
            //Symfony's client is lazy — nothing is sent until the response is read — so the
            //status is asked for here, inside the catch that logs a failed request.
            $status   = $response->getStatusCode();
        } catch (\Throwable $e) {
            $logger = $this->getLogger();
            if (isset($logger)) {
                $logger->error("Error requesting the person list from schoenstatt-fathers.link. Reason: "
                    . $e->getMessage());
            }
            throw $e;
        }

        if (200 != $status) {
            throw new \Exception('Failed to retrieve list of fathers from Patres. Status code: '
                . $status);
        }
        //`false`: the status is checked above, and letting the client throw on a non-2xx
        //would replace that explicit message with a transport exception.
        $data = Json::decodeToArray($response->getContent(false));
        if (! isset($data['data'])) {
            throw new \Exception('Failed to retrieve list of fathers from Patres. No data returned');
        }
        $persons = $data['data'];
        if (isset($this->schoenstattTable)) {
            $this->schoenstattTable->cacheEntityObjects($cacheKey, $persons, []);
        }
        return $persons;
    }

    /**
     *
     * @param number $personId
     * @param array $options
     */
    /**
     * Copy a person from the Patres database into sch_persons.
     *
     * `$overwroteExisting` is set to true when an already-imported row was found and
     * its fields were replaced with the remote ones, false when a new row was created.
     * That distinction used to be invisible — the branch below carried
     * `//@todo warn the user that existing data will be overwritten` and the caller
     * reported both outcomes as "Person successfully imported." A re-import is a
     * legitimate way to refresh a record, so this reports rather than refuses; the
     * point is that whoever triggered it is told which of the two happened.
     *
     * Worth knowing before treating the overwrite as a hazard: it is not reachable
     * from the checkout form. getSchoenstattPersonFromPatresPersonId() only calls this
     * after getPersonInSchoenstattTable() missed, and that method runs the *identical*
     * searchPersons() query as the check below — so a miss there is a miss here, and
     * the create branch is the one that runs. The overwrite belongs to
     * AdminController::importFatherAction(), which is sch_administrator only.
     *
     * @param number $personId
     * @param array $options
     * @param bool $overwroteExisting set by reference; see above
     */
    public function importRemotePerson($personId, $options = [], &$overwroteExisting = false)
    {
        $overwroteExisting = false;
        if (false === $personData = $this->getRemotePerson($personId)) {
            return false;
        }
        $personData['dataSource'] = 'patres-sion';
        $personData['dataSourceId'] = $personId;
        unset($personData['personId']); //to make sure that we don't try setting that as the primary key
        $this->complementSchoenstattTablePersonDataKeysWithOptions($personData, $options);

        $table = $this->getSchoenstattTable();
        if (0 !== count($currentPersonList = $table->searchPersons(
            ['dataSource' => 'patres-sion', 'dataSourceId' => $personId],
            false,
            true
        ))
        ) {
            $currentPerson = current($currentPersonList);
            $currentPersonId = $currentPerson['personId'];
            $table->updateEntity('person', $currentPerson['personId'], $personData);
            $overwroteExisting = true;
            return $currentPersonId;
        } else {
            $newId = $table->createEntity('person', $personData);
        }
        return $newId;
    }

    /**
     * Get a fresh InputFilter to test person data
     * @return \Laminas\InputFilter\InputFilterInterface
     */
    public function getPersonInputFilter()
    {
        if (is_null($this->personInputFilter)) {
            throw new \Exception('No person input filter set');
        }
        return clone $this->personInputFilter;
    }

    /**
    *
    * @param InputFilterInterface $personInputFilter
    * @return self
    */
    public function setPersonInputFilter($personInputFilter)
    {
        $this->personInputFilter = $personInputFilter;
        return $this;
    }

    /**
     * Retrieve and validate person info from Patres database
     * @param int $personId
     * @throws \Exception
     */
    public function getRemotePerson($personId)
    {
        $key = $this->getApiKey();
        $getPersonUrl = $this->getPersonUri($personId);
        $response = HttpClient::create()->request('GET', $getPersonUrl, ['query' => ['key' => $key]]);
        $status   = $response->getStatusCode();

        if (200 != $status) {
            throw new \Exception('Request for information on father \'' . $personId . '\' failed. Status code: '
                . $status);
        }
        //`false`: the status is checked above, so the client must not throw over it and
        //replace that message with a transport exception.
        $data = Json::decodeToArray($response->getContent(false));
        if (! isset($data['data'])) {
            throw new \Exception('Request for information on father \'' . $personId
                . '\' failed. No information returned.');
        }
        $person = $data['data'];
        $this->lastRemotePersonMessages = [];
        if (isset($person['bishopDate'])) {
            $person['personTags'] = 'bishop';
        } elseif (isset($person['priestDate'])) {
            $person['personTags'] = 'priest';
        } elseif (isset($person['priestDate'])) {
            $person['personTags'] = 'deacon';
        }
        $person['lifeCommunity'] = 1;
        //validate
        $inputFilter = $this->getPersonInputFilter();
        $inputFilter->setData($person);
        if ($inputFilter->isValid()) {
            $return = $inputFilter->getValues();
            return $return;
        }

        // Was `//@todo find a way to log this` until 2026-08-17, and the silence cost
        // more than a log line usually does: this is the ONLY way importRemotePerson()
        // returns false, and AdminController reported that as "Person already exists in
        // the database." So the one thing an administrator was ever told about a failed
        // import was both wrong and unactionable. Keep the messages for the caller as
        // well as logging them — the person doing the import is the person who can ask
        // Patres to correct the record.
        $this->lastRemotePersonMessages = $inputFilter->getMessages();
        $logger = $this->getLogger();
        if (isset($logger)) {
            $logger->error(sprintf(
                'Person %s from Patres failed validation and was not imported. Invalid fields: %s',
                $personId,
                implode(', ', array_keys($this->lastRemotePersonMessages))
            ));
        }
        return false;
    }

    /**
     * Why the last getRemotePerson() call rejected the record, as an InputFilter
     * message array keyed by field name. Empty when the last call succeeded or was
     * never made.
     *
     * @return array<string, mixed>
     */
    public function getLastRemotePersonMessages(): array
    {
        return $this->lastRemotePersonMessages;
    }

    /**
     * Get the person data from the SchoenstattTable, returns false if not found
     * @param int $personId
     * @return boolean|mixed
     */
    public function getPersonInSchoenstattTable($personId)
    {
        if (0 === count($currentPersonList = $this->getSchoenstattTable()->searchPersons(
            ['dataSource' => 'patres-sion', 'dataSourceId' => $personId],
            false,
            true
        ))
        ) {
            return false;
        }
        $currentPerson = current($currentPersonList);
        return $currentPerson;
    }

    /**
     * Update a person in the SchoenstattTable
     * @param number $personId
     * @param array $options
     * @return boolean|boolean|number
     */
    public function updateSchoenstattTablePerson($personId, $options = [])
    {
        if (! $personData = $this->getPersonInSchoenstattTable($personId)) {
            return false;
        }
        $this->complementSchoenstattTablePersonDataKeysWithOptions($personData, $options);
        $table = $this->getSchoenstattTable();
        return $table->updateEntity('person', $personId, $personData);
    }

    /**
     * Complements person data without persisting changes. Returns true if data has been complemented
     * @param mixed[] $personData
     * @param array $options
     * @return boolean
     */
    protected function complementSchoenstattTablePersonDataKeysWithOptions(&$personData, $options)
    {
        $return = false;
        if (isset($options['isAuthor']) && is_bool($options['isAuthor'])) {
            $personData['isAuthor'] = $options['isAuthor'];
            $return = true;
        }
        if (isset($options['isBorrower']) && is_bool($options['isBorrower'])) {
            $personData['isBorrower'] = $options['isBorrower'];
            $return = true;
        }
        if (isset($options['adminTags'])) {
            if (is_string($options['adminTags'])) {
                $options['adminTags'] = [$options['adminTags']];
            }
            if (is_array($options['adminTags'])) {
                if (isset($personData['adminTags']) && is_array($personData['adminTags'])) {
                    $personData['adminTags'] = array_merge($personData['adminTags'], $options['adminTags']);
                } else {
                    $personData['adminTags'] = $options['adminTags'];
                }
                $return = true;
            }
        }
        return $return;
    }

    /**
     * Get the schoenstattConfig value
     * @return mixed[]
     */
    public function getSchoenstattConfig()
    {
        if (! is_array($this->schoenstattConfig)) {
            throw new \Exception('No config set');
        }
        return $this->schoenstattConfig;
    }

    /**
     *
     * @param mixed[] $schoenstattConfig
     * @return self
     */
    public function setSchoenstattConfig($schoenstattConfig)
    {
        $this->schoenstattConfig = $schoenstattConfig;
        return $this;
    }

    /**
    * Get the personListUri value
    * @return string
    */
    public function getPersonListUri()
    {
        if (is_null($this->personListUri)) {
            $config = $this->getSchoenstattConfig();
            $this->personListUri = $config['patres_api_person_list_uri'];
        }
        if (! is_string($this->personListUri)) {
            throw new \Exception('No person list URI available');
        }
        return $this->personListUri;
    }

    /**
    *
    * @param string $personListUri
    * @return self
    */
    public function setPersonListUri($personListUri)
    {
        $this->personListUri = $personListUri;
        return $this;
    }

    /**
    * Get the apiKey value
    * @return string
    */
    public function getApiKey()
    {
        if (is_null($this->apiKey)) {
            $config = $this->getSchoenstattConfig();
            $this->apiKey = $config['patres_api_key'];
        }
        if (! is_string($this->apiKey)) {
            throw new \Exception('No API key available');
        }
        return $this->apiKey;
    }

    /**
    *
    * @param string $apiKey
    * @return self
    */
    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
        return $this;
    }

    public function getPersonUri($personId)
    {
        if (! is_numeric($personId)) {
            throw new \InvalidArgumentException('personId should be numeric');
        }
        $uriFormat = $this->getPersonUriFormat();
        return sprintf($uriFormat, $personId);
    }

    /**
    * Get the personUriFormat value
    * @return string
    */
    public function getPersonUriFormat()
    {
        if (! $this->personUriFormat) {
            $config = $this->getSchoenstattConfig();
            $this->setPersonUriFormat($config['patres_api_get_person_uri']);
        }
        if (! $this->personUriFormat) {
            throw new \Exception('No person URI set');
        }
        return $this->personUriFormat;
    }

    /**
    *
    * @param string $personUriFormat
    * @return self
    */
    public function setPersonUriFormat($personUriFormat)
    {
        if (false === strpos($personUriFormat, '%s')) {
            throw new \InvalidArgumentException('personUriFormat contains no token');
        }
        $this->personUriFormat = $personUriFormat;
        return $this;
    }

    /**
     * Get the schoenstattTable value
     * @return SchoenstattTable
     */
    public function getSchoenstattTable()
    {
        if (! $this->schoenstattTable instanceof SchoenstattTable) {
            throw new \Exception('No schoenstatt table set');
        }
        return $this->schoenstattTable;
    }

    /**
     *
     * @param SchoenstattTable $schoenstattTable
     * @return self
     */
    public function setSchoenstattTable($schoenstattTable)
    {
        $this->schoenstattTable = $schoenstattTable;
        return $this;
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
        return $this;
    }

    public function getLogger()
    {
        return $this->logger;
    }
}
