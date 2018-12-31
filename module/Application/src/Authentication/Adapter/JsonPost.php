<?php

namespace Application\Authentication\Adapter;

use Zend\Authentication\Result as AuthenticationResult;
use Zend\Crypt\Password\Bcrypt;
use Zend\Json\Json;
use Zend\Http\Header\HeaderInterface;
use ZfcUser\Authentication\Adapter\Db;

class JsonPost extends Db
{
    protected $request;
    
    /**
     * @param \Zend\EventManager\EventInterface $e
     * @return AuthenticationResult
     */
    public function authenticate(\Zend\EventManager\EventInterface $e = null)
    {
        $request = $this->getRequest();
        //we're only interested in json requests, otherwise
        $contentType = $request->getHeader('Content-Type');
        $isPost = 'POST' === $request->getMethod();
        if (!$isPost
            || !$contentType instanceof HeaderInterface 
            || 'application/json' !== $contentType->getFieldValue()
        ) {
            $e = new AuthenticationResult(
                AuthenticationResult::FAILURE_UNCATEGORIZED,
                null,
                ['Please send a POST request of Content-Type application/json.']
                );
            return $e;
        }
        $data = Json::decode($request->getContent(), Json::TYPE_ARRAY);
        if (!isset($data['identity']) || !isset($data['credential'])) {
            $e = new AuthenticationResult(
                AuthenticationResult::FAILURE, 
                null, 
                ['Please provide a valid json object with identity and credential properties.']
                );
            return $e;
        }
        $identity   = $data['identity'];
        $credential = $data['credential'];
        $credential = $this->preProcessCredential($credential);
        /** @var UserInterface|null $userObject */
        $userObject = null;

        // Cycle through the configured identity sources and test each
        $fields = $this->getOptions()->getAuthIdentityFields();
        while (!is_object($userObject) && count($fields) > 0) {
            $mode = array_shift($fields);
            switch ($mode) {
                case 'username':
                    $userObject = $this->getMapper()->findByUsername($identity);
                    break;
                case 'email':
                    $userObject = $this->getMapper()->findByEmail($identity);
                    break;
            }
        }

        if (!$userObject) {
            $e = new AuthenticationResult(
                AuthenticationResult::FAILURE_IDENTITY_NOT_FOUND,
                null,
                ['A record with the supplied identity could not be found.']
                );
            $this->setSatisfied(false);
            return $e;
        }

        if ($this->getOptions()->getEnableUserState()) {
            // Don't allow user to login if state is not in allowed list
            if (!in_array($userObject->getState(), $this->getOptions()->getAllowedLoginStates())) {
                
                $e = new AuthenticationResult(
                    AuthenticationResult::FAILURE_UNCATEGORIZED,
                    null,
                    ['A record with the supplied identity is not active.']
                    );
                $this->setSatisfied(false);
                return $e;
            }
        }

        $bcrypt = new Bcrypt();
        $bcrypt->setCost($this->getOptions()->getPasswordCost());
        if (!$bcrypt->verify($credential, $userObject->getPassword())) {
            // Password does not match
            $e = new AuthenticationResult(
                AuthenticationResult::FAILURE_CREDENTIAL_INVALID,
                null,
                ['Supplied credential is invalid.']
                );
            $this->setSatisfied(false);
            return $e;
        }

        // Success!
        // Update user's password hash if the cost parameter has changed
        $this->updateUserPasswordHash($userObject, $credential, $bcrypt);
        $this->setSatisfied(true);
        
        $e = new AuthenticationResult(
            AuthenticationResult::SUCCESS,
            ['id' => $userObject->getId(), 'username' => $userObject->getUsername()],
            ['Authentication successful.']
            );
        return $e;
    }
    
    public function getRequest()
    {
        if (!isset($this->request)) {
            $sm = $this->getServiceManager();
            $this->request = $sm->get('Request');
        }
        return $this->request;
    }
}
