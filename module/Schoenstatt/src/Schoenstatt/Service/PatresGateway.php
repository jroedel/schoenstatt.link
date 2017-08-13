<?php
namespace Schoenstatt\Service;

use Schoenstatt\Model\SchoenstattTable;
use Schoenstatt\Form\PersonForm;
use Zend\InputFilter\InputFilterInterface;
use Zend\Http\Client;
use Zend\Json\Json;

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

    public function getPersonList($id)
    {

    }

    /**
     *
     * @param number $personId
     * @param array $options
     */
    public function importRemotePerson($personId, $options = [])
    {
        if (false === $personData = $this->getRemotePerson($personId)) {
            return false;
        }
        $personData['dataSource'] = 'patres-sion';
        $personData['dataSourceId'] = $personId;
        unset($personData['personId']); //to make sure that we don't try setting that as the primary key
        if (isset($options['isAuthor']) && is_bool($options['isAuthor'])) {
            $personData['isAuthor'] = $options['isAuthor'];
        }
        if (isset($options['isLibraryUser']) && is_bool($options['isLibraryUser'])) {
            $personData['isLibraryUser'] = $options['isLibraryUser'];
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
            }
        }

        $table = $this->getSchoenstattTable();
        if (0 !== count($currentPersonList = $table->searchPersons(['dataSource' => 'patres-sion', 'dataSourceId' => $personId], false, true)))
        {
            //@todo warn the user that existing data will be overwritten
            $currentPerson = current($currentPersonList);
            $currentPersonId = $currentPerson['personId'];
            $table->updateEntity('person', $currentPerson['personId'], $personData);
            return $currentPersonId;
        } else {
            $newId = $table->createEntity('person', $personData);
        }
        return $newId;
    }

    /**
     * Get a fresh InputFilter to test person data
     * @return \Zend\InputFilter\InputFilterInterface
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
     * @param unknown $personId
     * @throws \Exception
     */
    public function getRemotePerson($personId)
    {
        $key = $this->getApiKey();
        $getPersonUrl = $this->getPersonUri($personId);
        $client = new Client();
        $client->setMethod('get');
        $client->setUri($getPersonUrl);
        $client->setParameterGet(['key' => $key]);
        $response = $client->send();

        if (200 != $response->getStatusCode()) {
            throw new \Exception('Request for information on father \''.$personId.'\' failed. Status code: '.$response->getStatusCode());
        }
        $data = Json::decode($response->getBody(), Json::TYPE_ARRAY);
        if (!isset($data['data'])) {
            throw new \Exception('Request for information on father \''.$personId.'\' failed. No information returned.');
        }
        $person = $data['data'];
        $person['personTags'] = 'priest';
        $person['lifeCommunity'] = 1;
        //validate
        $inputFilter = $this->getPersonInputFilter();
        $inputFilter->setData($person);
        if ($inputFilter->isValid()) {
            $return = $inputFilter->getValues();
            return $return;
        } else {
            //@todo find a way to log this
        }
        return false;
    }

    /**
     * Get the schoenstattConfig value
     * @return mixed[]
     */
    public function getSchoenstattConfig()
    {
        if (!is_array($this->schoenstattConfig)) {
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
    * Get the apiKey value
    * @return string
    */
    public function getApiKey()
    {
        if (is_null($this->apiKey)) {
            $config = $this->getSchoenstattConfig();
            $this->apiKey = $config['patres_api_key'];
        }
        if (!is_string($this->apiKey)) {
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
        if (!is_numeric($personId)) {
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
        if (!$this->personUriFormat) {
            $config = $this->getSchoenstattConfig();
            $this->setPersonUriFormat($config['patres_api_get_person_uri']);
        }
        if (!$this->personUriFormat) {
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
        if (!$this->schoenstattTable instanceof SchoenstattTable) {
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
}
