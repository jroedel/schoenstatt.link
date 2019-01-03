<?php 
namespace Books\Controller;

use Cocur\Slugify\Slugify;
use RestApi\Controller\ApiController;
use Books\Model\DictionaryTable;
use Books\Form\DictionaryEntryForm;
use Books\Exception\DuplicateKeyException;

class DictionaryApiController extends ApiController
{
    /** @var DictionaryTable $table */
    protected $table;
    
    protected $inputFilter;
    
    public function __construct(DictionaryTable $table)
    {
        $this->table = $table;
        $this->setIdentifierName('entry_id');
    }
    
    public function getList()
    {
        $table = $this->getDictionaryTable();
        $objects = $table->getObjects('dictionary-entry');
        $this->apiResponse['entries'] = $this->prepDictionaryEntries($objects);
        $this->httpStatusCode = 200;
        return $this->createResponse();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \Zend\Mvc\Controller\AbstractRestfulController::get()
     */
    public function get($id)
    {
        $table = $this->getDictionaryTable();
        $object = $table->getObject('dictionary-entry', $id);
        if (!isset($object)) {
            $this->apiResponse['message'] = 'Entry not found';
            $this->httpStatusCode = 201;
            return $this->createResponse();
        }

        $this->apiResponse['entry'] = $this->prepDictionaryEntry($object);
        $this->httpStatusCode = 200;
        return $this->createResponse();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \Zend\Mvc\Controller\AbstractRestfulController::update()
     */
    public function update($id, $data)
    {
        $inputFilter = $this->getInputFilter();
        $inputFilter->setData($data);
        if ($inputFilter->isValid()) {
            $updateData = $inputFilter->getValues();
            $table = $this->getDictionaryTable();
            $result = $table->updateEntity('dictionary-entry', $id, $updateData);
            $this->httpStatusCode = 200;
            $this->apiResponse = $result;
        } else {
            $this->httpStatusCode = 201;
            $invalidInputs = $inputFilter->getInvalidInput();
            $invalidMessages = [];
            foreach ($invalidInputs as $name => $input) {
                $invalidMessages[$name] = $input->getMessages();
            }
            $this->apiResponse['invalidFields'] = $invalidMessages;
        }
        return $this->createResponse();
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \Zend\Mvc\Controller\AbstractRestfulController::create()
     */
    public function create($data)
    {
        $inputFilter = $this->getInputFilter();
        $inputFilter->setData($data);
        if ($inputFilter->isValid()) {
            $updateData = $inputFilter->getValues();
            $table = $this->getDictionaryTable();
            try {
                $newId = $table->createEntity('dictionary-entry', $updateData);
            } catch (DuplicateKeyException $e) {
                $this->httpStatusCode = 400;
                $this->apiResponse['invalidFields'] = [
                    'key' => 'There is already a dictionary entry for the given key and locale'
                ];
                return $this->createResponse();
            }
            if (isset($newId) && is_numeric($newId) && $newId > 0) {
                return $this->get($newId);
            } else {
                $this->httpStatusCode = 500;
                $this->apiResponse['error'] = 'Unknown failure.';
            }
        } else {
            $this->httpStatusCode = 400;
            $invalidInputs = $inputFilter->getInvalidInput();
            $invalidMessages = [];
            foreach ($invalidInputs as $name => $input) {
                //@todo maybe we shouldn't string the array keys so that API users can better
                //identify the problem without indexing long strings, but we would have
                //to document each key in the API
                $invalidMessages[$name] = array_values($input->getMessages());
            }
            $this->apiResponse['invalidFields'] = $invalidMessages;
        }
        return $this->createResponse();
    }
    
    /**
     * This is for replacing the whole online dictionary.
     * {@inheritDoc}
     * @see \Zend\Mvc\Controller\AbstractRestfulController::replaceList()
     */
    public function replaceList($data)
    {
        
    }
    
    public function slugifyTermsAction()
    {
        $request = $this->getRequest();
        $data = $this->processBodyContent($request);
        if (!is_array($data) || !isset($data['terms']) || !is_array($data['terms'])) {
            $this->httpStatusCode = 201;
            $this->apiResponse['message'] = '`terms` property is undefined.';
            return $this->createResponse();
        }        
        $this->httpStatusCode = 200;
        $slugify = new Slugify();
        $slugs = [];
        
        foreach ($data['terms'] as $term) {
            if (!is_string($term) && !isset($slugs[$term])) {
                $slugs[$term] = null;
            }
            $slugs[$term] = $slugify->slugify($term);
        }
        $this->apiResponse['slugs'] = $slugs;
        return $this->createResponse();
    }
    
    /**
     * Massage ORM-returned objects for handing over the API
     * @param mixed $objects
     * @return mixed
     */
    protected function prepDictionaryEntries($objects)
    {
        $results = [];
        foreach ($objects as $object) {
            $results[] = $this->prepDictionaryEntry($object);
        }
        return $results;
    }
    
    protected function prepDictionaryEntry($object)
    {
        unset($object['createdOn']);
        unset($object['createdBy']);
        unset($object['updatedOn']);
        unset($object['updatedBy']);
        return $object;
    }
    
    /**
     * Retrieve an input filter to validate api-submitted dictionary entries
     * @return \Zend\InputFilter\InputFilterInterface
     */
    public function getInputFilter()
    {
        if (!isset($this->inputFilter)) {
            $form = new DictionaryEntryForm();
            $this->inputFilter = $form->getInputFilter();
            $fields = $this->inputFilter->getInputs();
            if (isset($fields['security'])) {
                unset($fields['security']);
            }
            $fieldsToValidate = array_keys($fields);
            $this->inputFilter->setValidationGroup($fieldsToValidate);
        }
        return $this->inputFilter;
    }
    
    /**
     * @return \Books\Model\DictionaryTable
     */
    public function getDictionaryTable()
    {
        return $this->table;
    }
}
