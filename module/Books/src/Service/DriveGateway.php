<?php
namespace Books\Service;

use Symfony\Component\HttpClient\HttpClient;
use App\Json;
use SionModel\Cache\Storage as CacheStorage;
use Books\InputFilter\DriveFileFilter;

class DriveGateway
{
    protected const FILES_CACHE_KEY = 'drive-gateway-files-json';
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
     * @var CacheStorage $cache;
     */
    protected $cache;

    /**
     * The Drive files that belong to a publication, keyed by publication id, then file id
     * @throws \Exception
     * @return array
     */
    public function getPublicationFiles()
    {
        $files = $this->getFilesFromGoogleDrive();
        $publicationFiles = [];
        foreach ($files as $file) {
            if (isset($file['publicationId'])) {
                if (! array_key_exists($file['publicationId'], $publicationFiles)) {
                    $publicationFiles[$file['publicationId']] = [];
                }
                $publicationFiles[$file['publicationId']][$file['fileId']] = $file;
            }
        }
        return $publicationFiles;
    }

    /**
     * Gets info from server and validates data
     * @throws \Exception
     * @return mixed
     */
    protected function getFilesFromGoogleDrive()
    {
        $cache = $this->getCache();
        $success = null;
        $json = $cache->getItem(self::FILES_CACHE_KEY, $success);
        if (! $success) {
            $key = $this->getApiKey();
            $listUrl = $this->getFilesApiUrl();
            $response = HttpClient::create()->request('GET', $listUrl, ['query' => ['key' => $key]]);
            $status   = $response->getStatusCode();

            if (200 != $status) {
                throw new \Exception('Failed to retrieve list of files from Google Drive. Status code: ' . $status);
            }
            //`false`: the status is checked above, so the client must not throw its own
            //exception over it and replace that message.
            $json = $response->getContent(false);
            //best to cache the JSON instead of serializing afterwards, plus it gets re-validated
            $cache->setItem(self::FILES_CACHE_KEY, $json);
        }
        $data = Json::decodeToArray($json);
        if (! is_array($data)) {
            throw new \Exception('Failed to retrieve list of files from Google Drive. No data returned');
        }
        $result = [];
        $fileInputFilter = DriveFileFilter::engine();
        foreach ($data as $file) {
            $fileInputFilter->setData($file);
            if ($fileInputFilter->isValid()) {
                $purified = $fileInputFilter->getValues();
                $result[] = $purified;
            } //simply ignore invalid rows @todo log them somewhere
        }
        return $result;
    }

    /**
     * Get the cache value
     * @return CacheStorage
     */
    public function getCache()
    {
        if (! isset($this->cache)) {
            throw new \Exception('Something went wrong, no cache available');
        }
        return $this->cache;
    }

    /**
     * Set the cache value
     * @param CacheStorage $cache
     * @return self
     */
    public function setCache(CacheStorage $cache)
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
        if (! is_array($this->config)) {
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
        if (! isset($this->apiKey)) {
            $config = $this->getConfig();
            if (isset($config['books']) && isset($config['books'][self::CONFIG_KEY_API_KEY])) {
                $this->apiKey = $config['books'][self::CONFIG_KEY_API_KEY];
            }
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

    /**
     *
     * @throws \Exception
     * @return string
     */
    public function getFilesApiUrl()
    {
        if (! isset($this->filesApiUrl)) {
            $config = $this->getConfig();
            if (isset($config['books']) && isset($config['books'][self::CONFIG_KEY_FILES_API_URL])) {
                $this->filesApiUrl = $config['books'][self::CONFIG_KEY_FILES_API_URL];
            }
        }
        if (! is_string($this->filesApiUrl)) {
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
