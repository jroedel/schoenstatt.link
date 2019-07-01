<?php
namespace Schoenstatt\Controller;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\View\Model\JsonModel;
use Schoenstatt\Model\SchoenstattTable;
use Schoenstatt\Validator\SchoenstattLinkIdentifier;

class AssociationsApiController extends AbstractRestfulController
{
    /**
     * @var SchoenstattTable $schoenstattTable
     */
    protected $schoenstattTable;

    /**
     * @var array $config
     */
    protected $config;

    public function __construct(SchoenstattTable $schoenstattTable, array $config)
    {
        $this->setIdentifierName('sw_id');
        $this->schoenstattTable = $schoenstattTable;
        $this->config = $config;
    }

    public function getList()
    {
//         $params = $this->params()->fromQuery();
//         if (!isset($params['key'])) {
//             return $this->sendFailedMessage('Please pass the API key as the \'key\' query parameter.');
//         }
//         if (!$this->authenticateApiKey($params['key'])) {
//             return $this->sendFailedMessage('Invalid API key.');
//         }
        $table = $this->schoenstattTable;
        $objects = $table->getAssociations();
        $locale = \Locale::getDefault();
        $md5s = null;
        $json = $table->getAssociationListSchemaV1($objects, $md5s, $locale);
        $md5 = md5(json_encode($md5s));
        return new JsonModel([
            'items'         => $json,
            'locale'        => $locale,
            'md5'           => $md5,
            'objectMd5s'    => $md5s,
        ], ['prettyPrint' => true]);
    }

    public function get($id)
    {
        if (!$id) {
            return $this->sendFailedMessage('Invalid association requested.');
        }
        $id = $this->processSiteWideIdentifier($id);
        if (false === $id) {
            return $this->sendFailedMessage('Invalid association requested.');
        }
//         $params = $this->params()->fromQuery();
//         if (!isset($params['key'])) {
//             return $this->sendFailedMessage('Please pass the API key as the \'key\' query parameter.');
//         }
//         if (!$this->authenticateApiKey($params['key'])) {
//             return $this->sendFailedMessage('Invalid API key.');
//         }
        $table = $this->schoenstattTable;
        $locale = \Locale::getDefault();
        $object = $table->getAssociation($id);
        $place = $table->getAssociationSchemaV1($object, $locale);
        if (!isset($place)) {
            return $this->sendFailedMessage('Invalid shrine requested.', 404);
        }
        $return = [
            'apiUrl'    => 'https://schoenstatt.link/api/v1/associations/'.$object['identifier'],
            'locale'    => \Locale::getDefault(),
            'md5'       => $object['schemaOrgJsonMd5V1ByLocale'][$locale],
            'object'    => $place->toArray(),
        ];
        $view = new JsonModel($return, ['prettyPrint' => true]);
        return $view;
    }

    public function findByKindAction()
    {
        $kind = $this->params()->fromQuery('kind');
        if (!isset($kind)) {
            return $this->sendFailedMessage('Please pass a `kind` parameter.');
        }
        $kinds = array_keys($this->config['schoenstatt']['association_kinds']);
        if (!in_array($kind, $kinds)) {
            return $this->sendFailedMessage('Please pass a valid `kind` parameter.');
        }
        $table = $this->schoenstattTable;
        $objects = $table->searchAssociations(['kind' => $kind]); //@todo factor out this function
        $locale = \Locale::getDefault();
        $md5s = null;
        $json = $table->getAssociationListSchemaV1($objects, $md5s, $locale);
        $md5 = md5(json_encode($md5s));
        return new JsonModel([
            'items'         => $json,
            'locale'        => \Locale::getDefault(),
            'md5'           => $md5,
            'objectMd5s'    => $md5s,
        ], ['prettyPrint' => true]);
    }
    
    public function findByKindMd5Action()
    {
        $view = $this->findByKindAction();
        if ($view instanceof JsonModel) {
            $view->setVariable('items', null);
        }
        return $view;
    }
    
    public function shrinesJsonAction()
    {
        $table = $this->schoenstattTable;
        $geoJson = $table->getShrineGeoJson();
        $serialized = $geoJson->jsonSerialize();
        return new JsonModel($serialized);
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

    protected function processSiteWideIdentifier($identifier)
    {
        static $validator;
        static $filter;
        if (!isset($validator)) {
            $validator = new SchoenstattLinkIdentifier(SchoenstattLinkIdentifier::ENTITY_ASSOCIATION);
        }
        if (!$validator->isValid($identifier)) {
            return false;
        }

        if (!isset($filter)) {
            $filter = new \Schoenstatt\Filter\SchoenstattLinkIdentifier(SchoenstattLinkIdentifier::ENTITY_ASSOCIATION);
        }
        return $filter->filter($identifier);
    }
}
