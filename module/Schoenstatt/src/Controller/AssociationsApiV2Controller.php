<?php
namespace Schoenstatt\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use Schoenstatt\Model\SchoenstattTable;
use Schoenstatt\Validator\SchoenstattLinkIdentifier;
use Laminas\Http\Header\Pragma;
use Laminas\Mvc\MvcEvent;
use Laminas\Http\Header\Expires;

class AssociationsApiV2Controller extends AbstractRestfulController
{
    /**
     * @var SchoenstattTable $schoenstattTable
     */
    protected $schoenstattTable;

    /**
     * @var array $config
     */
    protected $config;

    protected $makeCacheable = false;

    const BASE_URL = 'https://schoenstatt.link/api/v2';

    public function __construct(SchoenstattTable $schoenstattTable, array $config)
    {
        $this->setIdentifierName('sw_id');
        $this->schoenstattTable = $schoenstattTable;
        $this->config = $config;
    }

    public function getList()
    {
        $table = $this->schoenstattTable;
        $objects = $table->getAssociations();
        $locale = \Locale::getDefault();
        $md5s = null;
        $json = $table->getAssociationListSchemaV2($objects, $md5s, $locale);
        $md5 = md5(json_encode($md5s));

        return new JsonModel([
            'items'         => $json,
            'locale'        => $locale,
            'md5'           => $md5,
            'objectMd5s'    => $md5s,
        ]);//, ['prettyPrint' => true]);
    }

    public function get($id)
    {
        if (! $id) {
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
        $place = $table->getAssociationSchemaV2($object, $locale);
        if (! isset($place)) {
            return $this->sendFailedMessage('Invalid shrine requested.', 404);
        }
        $return = [
            'apiUrl'    => self::BASE_URL . '/associations/' . $object['identifier'],
            'locale'    => \Locale::getDefault(),
            'md5'       => $object['schemaOrgJsonMd5V1ByLocale'][$locale],
            'object'    => $place->toArray(),
        ];
        $view = new JsonModel($return);//, ['prettyPrint' => true]);
        return $view;
    }

    public function onDispatch(MvcEvent $e)
    {
        $this->makeCacheable();
        return parent::onDispatch($e);
    }

    public function makeCacheable()
    {
        /** @var \Laminas\Http\PhpEnvironment\Response $response */
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        if (false !== $oldHeaders = $headers->get('Cache-Control')) {
            foreach ($oldHeaders as $oldHeader) {
                $headers->removeHeader($oldHeader);
            }
        }
        if (false !== $oldHeaders = $headers->get('Pragma')) {
            foreach ($oldHeaders as $oldHeader) {
                $headers->removeHeader($oldHeader);
            }
        }
        $headers->addHeaderLine('Cache-Control: public, max-age=1800');
        $headers->addHeader(new Pragma());
        $expires = new Expires();
        $expires->setDate($expires->date()->add(date_interval_create_from_date_string('30 minutes')));
        $headers->addHeader($expires);
    }

    public function findByKindAction()
    {
        $kind = $this->params()->fromQuery('kind');
        if (! isset($kind)) {
            return $this->sendFailedMessage('Please pass a `kind` parameter.');
        }
        $kinds = array_keys($this->config['schoenstatt']['association_kinds']);
        if (! in_array($kind, $kinds)) {
            return $this->sendFailedMessage('Please pass a valid `kind` parameter.');
        }
        $table = $this->schoenstattTable;
        $objects = $table->searchAssociations(['kind' => $kind]); //@todo factor out this function
        $locale = \Locale::getDefault();
        $md5s = null;
        $json = $table->getAssociationListSchemaV2($objects, $md5s, $locale);
        $md5 = md5(json_encode($md5s));
        $this->makeCacheable = true;
        return new JsonModel([
            'items'         => $json,
            'locale'        => \Locale::getDefault(),
            'md5'           => $md5,
            'objectMd5s'    => $md5s,
        ]);//, ['prettyPrint' => true]);
    }

    public function findByKindMd5Action()
    {
        $view = $this->findByKindAction();
        if ($view instanceof JsonModel) {
            $view->setVariable('items', null);
        }
        return $view;
    }

    /**
     * @deprecated 2026-08-07 The shrine GeoJSON feed is on its way out; it is still
     *      served and still correct, but nothing new should be built on it. The response
     *      carries `Deprecation: true` (IETF draft) so existing callers find out without
     *      reading this file. No `Sunset` date, because no removal date has been decided
     *      — see docs/BACKLOG.md.
     *
     *      The header is set here as well as in App\Controller\ShrinesGeoJsonController
     *      because production still serves this action: SYMFONY_KERNEL is unset there, so
     *      a deprecation announced only on the ported route would reach nobody.
     */
    public function shrinesJsonAction()
    {
        $table = $this->schoenstattTable;
        $geoJson = $table->getShrineGeoJson();
        $serialized = $geoJson->jsonSerialize();
        $this->getResponse()->getHeaders()->addHeaderLine('Deprecation', 'true');
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
        if (! isset($config['api_keys']) || ! is_array($config['api_keys'])) {
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
        if (! isset($validator)) {
            $validator = new SchoenstattLinkIdentifier(SchoenstattLinkIdentifier::ENTITY_ASSOCIATION);
        }
        if (! $validator->isValid($identifier)) {
            return false;
        }

        if (! isset($filter)) {
            $filter = new \Schoenstatt\Filter\SchoenstattLinkIdentifier(SchoenstattLinkIdentifier::ENTITY_ASSOCIATION);
        }
        return $filter->filter($identifier);
    }
}
