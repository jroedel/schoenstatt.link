<?php
namespace Schoenstatt\Validator;

use SionModel\Validator\AbstractValidator;
use App\Json;
use Spatie\OpeningHours\OpeningHours;
use Spatie\OpeningHours\Exceptions\MaximumLimitExceeded;

class OpeningHoursSpecificationJson extends AbstractValidator
{
    const NOT_STRING = 'notString';
    const INVALID_JSON = 'invalidJson';
    const JSON_NOT_EVALUATED_AS_OBJECT = 'jsonNotEvaluatedAsObject';
    const ERROR_CREATING_OPENING_HOURS = 'errorCreatingOpeningHours';
    const NO_OPENING_WITHIN_ONE_MONTH = 'noOpeningWithinOneMonth';

    /**
     * Validation failure message template definitions
     *
     * @var array
     */
    protected $messageTemplates = [
        self::NOT_STRING => "Invalid type given. String expected",
        self::INVALID_JSON => "The input could not be parsed as valid JSON",
        self::JSON_NOT_EVALUATED_AS_OBJECT => "The JSON does not describe an object",
        self::ERROR_CREATING_OPENING_HOURS => "The input could not be parsed by OpeningHours",
        self::NO_OPENING_WITHIN_ONE_MONTH => "The input did not result in an opening within the next month",
    ];

    /**
     *
     * {@inheritDoc}
     * @see \SionModel\Validator\ValidatorInterface::isValid()
     */
    public function isValid($value, $context = null)
    {
        if (! isset($value) || '' === $value) {
            return true;
        }
        if (! is_string($value)) {
            $this->error(self::NOT_STRING);
            return false;
        }
        try {
            $json = Json::decodeToArray($value);
        } catch (\Exception $e) {
            $this->error(self::INVALID_JSON);
            return false;
        }
        if (! is_array($json)) {
            $this->error(self::JSON_NOT_EVALUATED_AS_OBJECT);
            return false;
        }
        try {
            $openingHours = OpeningHours::create($json);
            $now = new \DateTime();
            $openingHours->setDayLimit(31);
            $openingHours->nextOpen($now); // will throw exception if it doesn't find one in 31 days
        } catch (MaximumLimitExceeded $e) {
            $this->error(self::NO_OPENING_WITHIN_ONE_MONTH);
            return false;
        } catch (\Exception $e) {
            $this->error(self::ERROR_CREATING_OPENING_HOURS);
            return false;
        }
        return true;
    }
}
