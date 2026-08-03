<?php

// Absorbed from multidots/zf3-rest-api (MIT license) after the upstream repo
// was deleted from GitHub; JWT calls updated for firebase/php-jwt 7.x.

namespace RestApi\Controller;

use Laminas\Mvc\Controller\AbstractRestfulController;
use Laminas\View\Model\JsonModel;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Laminas\EventManager\EventManagerInterface;

class ApiController extends AbstractRestfulController
{

    /**
     * @var int $httpStatusCode Define Api Response code.
     */
    public $httpStatusCode = 200;

    /**
     * @var array $apiResponse Define response for api
     */
    public $apiResponse;

    /**
     *
     * @var string $token
     */
    public $token;

    /**
     *
     * @var object|array $tokenPayload
     */
    public $tokenPayload;

    /**
     * set Event Manager to check Authorization
     * @param \Laminas\EventManager\EventManagerInterface $events
     */
    public function setEventManager(EventManagerInterface $events)
    {
        parent::setEventManager($events);
        $events->attach('dispatch', array($this, 'checkAuthorization'), 10);
    }

    /**
     * This Function call from eventmanager to check authntication and token validation
     * @param \Laminas\Mvc\MvcEvent $event
     * 
     */
    public function checkAuthorization($event)
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        $isAuthorizationRequired = $event->getRouteMatch()->getParam('isAuthorizationRequired');
        $config = $event->getApplication()->getServiceManager()->get('Config');
        $event->setParam('config', $config);
        if (isset($config['ApiRequest'])) {
            $responseStatusKey = $config['ApiRequest']['responseFormat']['statusKey'];
            if (!$isAuthorizationRequired) {
                return;
            }
            $jwtToken = $this->findJwtToken($request);
            if ($jwtToken) {
                $this->token = $jwtToken;
                $this->decodeJwtToken();
                if (is_object($this->tokenPayload)) {
                    return;
                }
                $response->setStatusCode(400);
                $jsonModelArr = [$responseStatusKey => $config['ApiRequest']['responseFormat']['statusNokText'], $config['ApiRequest']['responseFormat']['resultKey'] => [$config['ApiRequest']['responseFormat']['errorKey'] => $this->tokenPayload]];
            } else {
                $response->setStatusCode(401);
                $jsonModelArr = [$responseStatusKey => $config['ApiRequest']['responseFormat']['statusNokText'], $config['ApiRequest']['responseFormat']['resultKey'] => [$config['ApiRequest']['responseFormat']['errorKey'] => $config['ApiRequest']['responseFormat']['authenticationRequireText']]];
            }
        } else {
            $response->setStatusCode(400);
            $jsonModelArr = ['status' => 'NOK', 'result' => ['error' => 'Require copy this file vender\multidots\zf3-rest-api\config\restapi.global.php and paste to root config\autoload\restapi.global.php']];
        }

        $response->getHeaders()->addHeaderLine('Content-Type', 'application/json');
        $view = new JsonModel($jsonModelArr);
        $response->setContent($view->serialize());
        return $response;
    }

    /**
     * Check Request object have Authorization token or not 
     * @param \Laminas\Stdlib\RequestInterface $request
     * @return string
     */
    public function findJwtToken($request)
    {
        $jwtToken = $request->getHeaders("Authorization") ? $request->getHeaders("Authorization")->getFieldValue() : '';
        if ($jwtToken) {
            $jwtToken = trim(trim($jwtToken, "Bearer"), " ");
            return $jwtToken;
        }
        if ($request->isGet()) {
            $jwtToken = $request->getQuery('token');
        }
        if ($request->isPost()) {
            $jwtToken = $request->getPost('token');
        }
        // ?token[]=x arrives as an array and would reach JWT::decode()'s string
        // parameter, raising an uncaught TypeError: a 500 — and, since exception
        // reporting landed, an exception email — that any anonymous caller can
        // trigger at will. Treat it as no token at all.
        return is_string($jwtToken) ? $jwtToken : '';
    }

    /**
     * contain user information for createing JWT Token
     */
    protected function generateJwtToken($payload)
    {
        if (!is_array($payload) && !is_object($payload)) {
            $this->token = false;
            return false;
        }
        $this->tokenPayload = $payload;
        $config = $this->getEvent()->getParam('config', false);
        $cypherKey = $config['ApiRequest']['jwtAuth']['cypherKey'];
        $tokenAlgorithm = $config['ApiRequest']['jwtAuth']['tokenAlgorithm'];
        $this->assertUsableCypherKey($cypherKey, $tokenAlgorithm);
        $this->token = JWT::encode($this->tokenPayload, $cypherKey, $tokenAlgorithm);
        return $this->token;
    }

    /**
     * Fail loudly on an unusable ApiRequest.jwtAuth.cypherKey.
     *
     * php-jwt 7 rejects HMAC keys shorter than the digest size (32 bytes for
     * HS256) on both signing and verification, so a too-short key silently
     * breaks every authenticated request. That is our misconfiguration, and it
     * cannot be left to the library to report: php-jwt raises DomainException
     * for a short key *and* for a caller's malformed token ("Malformed UTF-8
     * characters"), so the two are indistinguishable at the catch site — and
     * decodeJwtToken() hands the exception message back to the caller as a 400.
     * Checking our own key up front keeps client errors 400 and server errors
     * 500 (with an exception email).
     *
     * The message deliberately reports lengths only, never key material.
     *
     * @param mixed $cypherKey
     * @param mixed $tokenAlgorithm
     * @throws \RuntimeException
     */
    private function assertUsableCypherKey($cypherKey, $tokenAlgorithm): void
    {
        if (! is_string($cypherKey) || '' === $cypherKey) {
            throw new \RuntimeException(
                'ApiRequest.jwtAuth.cypherKey is missing or empty; the API cannot sign or verify tokens.'
            );
        }
        if (! is_string($tokenAlgorithm) || 0 !== strncmp($tokenAlgorithm, 'HS', 2)) {
            //only the HMAC family keys on a shared secret; RS*/ES* take a PEM
            return;
        }
        $minimumBytes = intdiv((int) substr($tokenAlgorithm, 2), 8);
        if (strlen($cypherKey) < $minimumBytes) {
            throw new \RuntimeException(sprintf(
                'ApiRequest.jwtAuth.cypherKey is %d bytes; %s requires at least %d.',
                strlen($cypherKey),
                $tokenAlgorithm,
                $minimumBytes
            ));
        }
    }

    /**
     * contain encoded token for user.
     */
    protected function decodeJwtToken()
    {
        if (!$this->token) {
            $this->tokenPayload = false;
        }
        $config = $this->getEvent()->getParam('config', false);
        $cypherKey = $config['ApiRequest']['jwtAuth']['cypherKey'];
        $tokenAlgorithm = $config['ApiRequest']['jwtAuth']['tokenAlgorithm'];
        $this->assertUsableCypherKey($cypherKey, $tokenAlgorithm);
        try {
            $decodeToken = JWT::decode($this->token, new Key($cypherKey, $tokenAlgorithm));
            $this->tokenPayload = $decodeToken;
        } catch (\Exception $e) {
            $this->tokenPayload = $e->getMessage();
        }
    }

    /**
     * Create Response for api Assign require data for response and check is valid response or give error
     * @return \Laminas\View\Model\JsonModel
     */
    public function createResponse()
    {
        $config = $this->getEvent()->getParam('config', false);
        $event = $this->getEvent();
        $response = $event->getResponse();

        if (is_array($this->apiResponse)) {
            $response->setStatusCode($this->httpStatusCode);
        } else {
            $this->httpStatusCode = 500;
            $response->setStatusCode($this->httpStatusCode);
            $errorKey = $config['ApiRequest']['responseFormat']['errorKey'];
            $defaultErrorText = $config['ApiRequest']['responseFormat']['defaultErrorText'];
            $this->apiResponse = [
                $errorKey => $defaultErrorText
            ];
        }
        $statusKey = $config['ApiRequest']['responseFormat']['statusKey'];
        if ($this->httpStatusCode == 200) {
            $sendResponse[$statusKey] = $config['ApiRequest']['responseFormat']['statusOkText'];
        } else {
            $sendResponse[$statusKey] = $config['ApiRequest']['responseFormat']['statusNokText'];
        }
        $sendResponse[$config['ApiRequest']['responseFormat']['resultKey']] = $this->apiResponse;
        return new JsonModel($sendResponse);
    }
}
