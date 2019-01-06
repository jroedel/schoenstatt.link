<?php 
namespace Books\Controller;

use Cocur\Slugify\Slugify;
use RestApi\Controller\ApiController;
use Books\Model\DictionaryTable;
use Books\Form\DictionaryEntryForm;
use Books\Exception\DuplicateKeyException;

class DictionaryApiController extends ApiController
{
    const REPLACE_LIST_ITEM_ACTION_CREATE = 'create';
    const REPLACE_LIST_ITEM_ACTION_UPDATE = 'update';
    const REPLACE_LIST_ITEM_ACTION_INACTIVATE = 'inactivate';
    
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
        if (!isset($data['entries']) || !is_array($data['entries'])) {
            $this->httpStatusCode = 400;
            $this->apiResponse['message'] = 'Please send a JSON request body with a array \'entries\' property';
            return $this->createResponse();
        }
        $entries = $data['entries'];
        
        $isSimulation = isset($data['isSimulation']) && $data['isSimulation'];
        
        /**
         * If there are problems with any entries, this array will be sent to the user. Keyed by the orig slug
         * @var array $errors
         */
        $errors = [];
        /**
         * These will be the objects to update/insert. Keyed by the slug and locale concatenated
         * @var array $objects
         */
        $objects = [];
        /**
         * Used to check the links later. Keyed by the slug strings; values are the bool literal true.
         * @var array $slugs
         */
        $slugs = [];
        
        //first check against the input filter
        $inputFilter = $this->getInputFilter();
        $slugifier = new Slugify();
        foreach ($entries as $entry) {
            if (!is_array($entry)
                || !isset($entry['key'])
                || !isset($entry['slug'])
                || !isset($entry['locale'])
                || !isset($entry['entry'])
            ) {
                $this->httpStatusCode = 400;
                $this->apiResponse['message'] = 
                    "All entries must define the following properties: key, slug, locale, entry";
                return $this->createResponse();
            }
            if (!isset($entry['isActive'])) {
                $entry['isActive'] = "1";
            }
            $inputFilter->setData($entry);
            if ($inputFilter->isValid()) {
                $validData = $inputFilter->getValues();
                $slug = $slugifier->slugify($validData['key']);
                $compositeKey = $slug.$validData['locale'];
                $validData['slug'] = $slug;
                $validData['isActive'] = (bool)$validData['isActive'];
                //assume this row should be created in the db until otherwise shown
                $validData['itemAction'] = self::REPLACE_LIST_ITEM_ACTION_CREATE;
                if ($slug !== $entry['slug']) {
                    //slug problem
                    $errors[$entry['slug']] = [
                        "slug value does not correspond to the key given"
                    ];
                }
                if (isset($objects[$compositeKey])) {
                    //duplicate slug detected
                    $errors[$slug] = [
                        "duplicate slug value"
                    ];
                }
                $objects[$compositeKey] = $validData;
                $slugs[$slug] = true;
            } else {
                $errors[$entry['slug']] = $inputFilter->getMessages();
            }
        }
        
        if (!empty($errors)) {
            $this->httpStatusCode = 400;
            $this->apiResponse['invalidInputs'] = $errors;
            return $this->createResponse();
        }
        
        //make sure links checkout
        foreach ($objects as $entry) {
            foreach ($entry['links'] as $linkSlug) {
                if (!isset($slugs[$linkSlug])) {
                    //we found a bad link
                    $errors[$entry['slug']] = [
                        "Invalid links"
                    ];
                }
            }
        }
        if (!empty($errors)) {
            $this->httpStatusCode = 400;
            $this->apiResponse['invalidInputs'] = $errors;
            return $this->createResponse();
        }
        
        //figure out which of the input records should be inserted, updated and inactivated
        $table = $this->getDictionaryTable();
        $currentObjects = $table->getObjects('dictionary-entry');
        foreach ($currentObjects as $value) {
            $compositeKey = $value['slug'].$value['locale'];
            if (isset($objects[$compositeKey])) {
                $objects[$compositeKey]['entryId'] = $value['entryId'];
                $objects[$compositeKey]['itemAction'] = self::REPLACE_LIST_ITEM_ACTION_UPDATE;
            } elseif ($value['isActive']) {
                $objects[$compositeKey] = [
                    'entryId' => $value['entryId'],
                    'itemAction' => self::REPLACE_LIST_ITEM_ACTION_INACTIVATE,
                ];
            }
        }
        
        //do it
        if (!$isSimulation) {
            foreach ($objects as $compositeKey => $object) {
                switch ($object['itemAction']) {
                    case self::REPLACE_LIST_ITEM_ACTION_CREATE:
                        $result = $table->createEntity('dictionary-entry', $object);
                        $objects[$compositeKey]['dbResult'] = $result;
                        break;
                    case self::REPLACE_LIST_ITEM_ACTION_UPDATE:
                        if (!isset($object['entryId'])) {
                            throw new \Exception('Missing entryId for item to update. Weird.');
                        }
                        $result = $table->updateEntity('dictionary-entry', $object['entryId'], $object);
                        $objects[$compositeKey]['dbResult'] = $result;
                        break;
                    case self::REPLACE_LIST_ITEM_ACTION_INACTIVATE:
                        if (!isset($object['entryId'])) {
                            throw new \Exception('Missing entryId for item to update. Weird.');
                        }
                        $result = $table->updateEntity('dictionary-entry', $object['entryId'], ['isActive' => false]);
                        $objects[$compositeKey]['dbResult'] = $result;
                        break;
                    default: //who knows what happened here
                        throw new \Exception('We should never be here. Please report this error.');
                        break;
                }
            }
        }
        
        $this->httpStatusCode = 200;
        $this->apiResponse['results'] = array_values($objects);
        return $this->createResponse();
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
            if (isset($fields['submit'])) {
                unset($fields['submit']);
            }
            $this->inputFilter->get('isActive')->setFallbackValue(1);
//             var_dump($this->inputFilter->get('isActive'));
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
