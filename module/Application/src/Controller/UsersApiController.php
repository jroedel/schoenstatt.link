<?php
namespace Application\Controller;

use RestApi\Controller\ApiController;
use Application\Authentication\Adapter\JsonPost;
use Carbon\Carbon;

class UsersApiController extends ApiController
{
    protected $adapter;
    
    public function __construct(JsonPost $adapter)
    {
        $this->adapter = $adapter;
    }
    
    public function loginAction()
    {
        /**
         * @var \Zend\Authentication\Result $auth
         */
        $auth = $this->adapter->authenticate();
        if (!$auth->isValid()) {
            if (\Zend\Authentication\Result::FAILURE_UNCATEGORIZED === $auth->getCode()) {
                $this->httpStatusCode = 400;
            } else {
                $this->httpStatusCode = 401;
            }
            $this->apiResponse['message'] = $auth->getMessages()[0];
            return $this->createResponse();
        }
        $expiration = Carbon::now()
            ->addMonths(6);
        $payload = [
            'id' => $auth->getIdentity()['id'],
            'expiration' => $expiration->format('Y-m-d\TH:i:s\Z'),
        ];
        $jwt = $this->generateJwtToken($payload);
        $this->httpStatusCode = 200;
        $this->apiResponse['token'] = $jwt;
        return $this->createResponse();
    }
}
