<?php
namespace Schoenstatt\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Http\Client;
use Zend\Json\Json;

/**
 * Factory responsible of retrieving an array containing the Schoenstatt configuration
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class FathersValueOptionsService implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return array
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $config = $serviceLocator->get('Schoenstatt\Config');
        if (!isset($config['patres_api_key'])) {
            throw new \Exception('No \'patres_api_key\' set to retrieve data from Patres Sion.');
        }
        $key = $config['patres_api_key'];
        $listUrl = $config['patres_api_person_list_uri'];
        $client = new Client();
        $client->setMethod('get');
        $client->setUri($listUrl);
        $client->setParameterGet(['key' => $key]);
        $response = $client->send();

        if (200 != $response->getStatusCode()) {
            throw new \Exception('Failed to retrieve list of fathers from Patres. Status code: '. $response->getStatusCode());
        }
        $data = Json::decode($response->getBody(), Json::TYPE_ARRAY);
        if (!isset($data['data'])) {
            throw new \Exception('Failed to retrieve list of fathers from Patres. No data returned');
        }
        $persons = $data['data'];
        return $persons;
    }
}
