<?php
namespace Application\Controller;

use RestApi\Controller\ApiController;
use Application\Authentication\Adapter\JsonPost;
use Carbon\Carbon;
use Zend\Math\Rand;

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
        if (! $auth->isValid()) {
            if (\Zend\Authentication\Result::FAILURE_UNCATEGORIZED === $auth->getCode()) {
                $this->httpStatusCode = 400;
            } else {
                $this->httpStatusCode = 401;
            }
            $this->apiResponse['message'] = $auth->getMessages()[0];
            return $this->createResponse();
        }
        $jwtId = Rand::getString(10);
        $expiration = Carbon::now()
            ->addMonths(6);
        $payload = [
            'sub' => $auth->getIdentity()['id'],
            'exp' => $expiration->format('U'), //'Y-m-d\TH:i:s\Z'),
            'jti' => $jwtId,
        ];
        $jwt = $this->generateJwtToken($payload);
        $this->httpStatusCode = 200;
        $this->apiResponse['token'] = $jwt;
        return $this->createResponse();
    }
}
