<?php
namespace Books\Service;

use Schoenstatt\Model\SchoenstattTable;
use Zend\InputFilter\InputFilterInterface;
use Zend\Http\Client;
use Zend\Json\Json;
use Zend\Cache\Storage\StorageInterface;
use Books\InputFilter\DriveFileFilter;

class DriveGateway
{
    protected const FILES_CACHE_KEY = 'drive-gateway-files';
    protected const CONFIG_KEY_API_KEY = 'files_api_key';
    protected const CONFIG_KEY_FILES_API_URL = 'files_api_url';
    /**
    * @var mixed[] $config
    */
    protected $config;

    /**
     * @var string $apiKey
     */
    protected $apiKey;

    /**
     * @var string $filesApiUrl
     */
    protected $filesApiUrl;
    
    /**
     * @var StorageInterface $cache;
     */
    protected $cache;
    
    /**
     * Get associative array of persons from Patres database $personId => $name
     * @throws \Exception
     * @return array
     */
    public function getPublicationFiles()
    {
        $files = $this->getFilesFromGoogleDrive();
        $publicationFiles = [];
        foreach ($files as $file) {
            if (isset($file['publicationId'])) {
                if (!array_key_exists($file['publicationId'], $publicationFiles)) {
                    $publicationFiles[$file['publicationId']] = [];
                }
                $publicationFiles[$file['publicationId']][$file['fileId']] = $file;
            }
        }
        return $publicationFiles;
    }
    
    /**
     * Given a patres personId, a person from the SchoenstattTable is returned. Person is imported if requested
     * @param number $patresPersonId
     * @param bool $importPersonIfNotFound
     * @param array $options May specify changes to be made to the person record in SchoenstattTable
     */
    public function getSchoenstattPersonFromPatresPersonId($patresPersonId, $importPersonIfNotFound = false,  $options = [])
    {
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
        $this->complementSchoenstattTablePersonDataKeysWithOptions($personData, $options);

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
     * Gets info from server and validates data
     * @throws \Exception
     * @return mixed|void|array|stdClass|boolean|NULL|string|number|mixed[]|void[]|boolean[]|NULL[]|string[]|number[]|array[]|stdClass[]
     */
    protected function getFilesFromGoogleDrive()
    {
        $cache = $this->getCache();
        $result = $cache->getItem(self::FILES_CACHE_KEY, $success);
        if (! $success) {
            $key = $this->getApiKey();
            $listUrl = $this->getFilesApiUrl();
            $client = new Client();
            $client->setMethod('get');
            $client->setUri($listUrl);
            $client->setParameterGet(['key' => $key]);
            $response = $client->send();
            
            if (200 != $response->getStatusCode()) {
                throw new \Exception('Failed to retrieve list of files from Google Drive. Status code: '. $response->getStatusCode());
            }
            $data = Json::decode($response->getBody(), Json::TYPE_ARRAY);
            if (!is_array($data)) {
                throw new \Exception('Failed to retrieve list of files from Google Drive. No data returned');
            }
            $result = [];
            $fileInputFilter = new DriveFileFilter();
            foreach ($data as $file) {
                $fileInputFilter->setData($file);
                if ($fileInputFilter->isValid()) {
                    $purified = $fileInputFilter->getValues();
                    $result[] = $purified;
                } //simply ignore invalid rows @todo log them somewhere
            }
            $cache->setItem(self::FILES_CACHE_KEY, $result);
        }
        return $result;
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
     * Get the cache value
     * @return StorageInterface
     */
    public function getCache()
    {
        if (!isset($this->cache)) {
            throw new \Exception('Something went wrong, no cache available');
        }
        return $this->cache;
    }
    
    /**
     * Set the cache value
     * @param StorageInterface $cache
     * @return self
     */
    public function setCache(StorageInterface $cache)
    {
        $this->cache = $cache;
        return $this;
    }

    /**
     * Get the schoenstattConfig value
     * @return mixed[]
     */
    public function getConfig()
    {
        if (!is_array($this->config)) {
            throw new \Exception('No config set');
        }
        return $this->config;
    }

    /**
     *
     * @param mixed[] $schoenstattConfig
     * @return self
     */
    public function setConfig($config)
    {
        $this->config = $config;
        return $this;
    }

    /**
    * Get the apiKey value
    * @return string
    */
    public function getApiKey()
    {
        if (!isset($this->apiKey)) {
            $config = $this->getConfig();
            if (isset($config['books']) && isset($config['books'][self::CONFIG_KEY_API_KEY])) {
                $this->apiKey = $config['books'][self::CONFIG_KEY_API_KEY];
            }
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

    /**
     * 
     * @throws \Exception
     * @return string
     */
    public function getFilesApiUrl()
    {
        if (!isset($this->filesApiUrl)) {
            $config = $this->getConfig();
            if (isset($config['books']) && isset($config['books'][self::CONFIG_KEY_FILES_API_URL])) {
                $this->filesApiUrl = $config['books'][self::CONFIG_KEY_FILES_API_URL];
            }
        }
        if (!is_string($this->filesApiUrl)) {
            throw new \Exception('No API key available');
        }
        return $this->filesApiUrl;
    }

    /**
     * 
     * @param string $filesApiUrl
     * @return string
     */
    public function setFilesApiUrl($filesApiUrl)
    {
        $this->filesApiUrl = $filesApiUrl;
        return $this->filesApiUrl;
    }
}
