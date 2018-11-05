<?php
namespace Schoenstatt\Controller;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Patres\Model\PatresTable;
use Schoenstatt\Model\SchoenstattTable;

class ShrinesApiController extends AbstractRestfulController
{
    /**
     * @var SchoenstattTable $patresTable
     */
    protected $schoenstattTable;

    /**
     * @var array $config
     */
    protected $config;

    public function __construct(SchoenstattTable $schoenstattTable, array $config)
    {
        $this->setIdentifierName('association_id');
        $this->schoenstattTable = $schoenstattTable;
        $this->config = $config;
    }

    public function getList()
    {
        $params = $this->params()->fromQuery();
//         if (!isset($params['key'])) {
//             return $this->sendFailedMessage('Please pass the API key as the \'key\' query parameter.');
//         }
//         if (!$this->authenticateApiKey($params['key'])) {
//             return $this->sendFailedMessage('Invalid API key.');
//         }
        $table = $this->schoenstattTable;
        $shrines = $table->getShrines();
        $md5 = null;
        $json = $table->getAssociationListSchema($shrines, $md5);
        return new JsonModel([
            'items' => $json,
            'md5' => $md5,
        ], ['prettyPrint' => true]);
    }

    public function get($id)
    {
        if (!$id) {
            return $this->sendFailedMessage('Invalid person requested.');
        }
//         $params = $this->params()->fromQuery();
//         if (!isset($params['key'])) {
//             return $this->sendFailedMessage('Please pass the API key as the \'key\' query parameter.');
//         }
//         if (!$this->authenticateApiKey($params['key'])) {
//             return $this->sendFailedMessage('Invalid API key.');
//         }
        $table = $this->schoenstattTable;

        $shrine = $table->getAssociation($id);
        $place = $table->getAssociationSchema($shrine);

        if (!isset($place)) {
            return $this->sendFailedMessage('Invalid shrine requested.', 404);
        }
        $view = new JsonModel($place->toArray(), ['prettyPrint' => true]);
        return $view;
    }

    protected function sendFailedMessage($message, $statusCode = 401)
    {
        $response = $this->getResponse();
        $response->setStatusCode($statusCode);
        $response->sendHeaders();
        $response->setContent($message);
        return $response;
    }

    protected function authenticateApiKey($key)
    {
        $config = $this->config['patres'];
        if (!isset($config['api_keys']) || !is_array($config['api_keys'])) {
            throw new \Exception('No API keys set.');
        }
        return in_array($key, $config['api_keys']);
    }

    protected function jsonSerializeDateTimeObjects(array $data)
    {
        $result = $data;
        foreach ($data as $key => $value) {
            if ($value instanceof \DateTime) {
                $result[$key] = $value->format('Y-m-d\TH:i:s\Z');
            }
        }
        return $result;
    }
}
