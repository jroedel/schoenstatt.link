<?php
namespace Schoenstatt\Validator;

use Laminas\Validator\AbstractValidator;
use Laminas\Json\Json;

class EventsJson extends AbstractValidator
{
    const NOT_STRING = 'notString';
    const INVALID_JSON = 'invalidJson';
    const JSON_NOT_EVALUATED_AS_OBJECT = 'jsonNotEvaluatedAsObject';

    /**
     * Validation failure message template definitions
     *
     * @var array
     */
    protected $messageTemplates = [
        self::NOT_STRING => "Invalid type given. String expected",
        self::INVALID_JSON => "The input could not be parsed as valid JSON",
        self::JSON_NOT_EVALUATED_AS_OBJECT => "The JSON does not describe an object or an array",
    ];

    /**
     *
     * {@inheritDoc}
     * @see \Laminas\Validator\ValidatorInterface::isValid()
     */
    public function isValid($value)
    {
        if (! isset($value) || '' === $value) {
            return true;
        }
        if (! is_string($value)) {
            $this->error(self::NOT_STRING);
            return false;
        }
        try {
            $json = Json::decode($value, Json::TYPE_ARRAY);
        } catch (\Exception $e) {
            $this->error(self::INVALID_JSON);
            return false;
        }
        if (! is_array($json)) {
            $this->error(self::JSON_NOT_EVALUATED_AS_OBJECT);
            return false;
        }
        return true;
    }
}
