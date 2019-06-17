<?php
namespace Schoenstatt\Validator;

use Zend\Validator\AbstractValidator;
use ScottConnerly\TimeZone\TimeZoneSelect;

class TimeZone extends AbstractValidator
{
    const NOT_STRING = 'notString';
    const INVALID_TIME_ZONE = 'invalidTimeZone';
    
    /**
     * Validation failure message template definitions
     *
     * @var array
     */
    protected $messageTemplates = [
        self::NOT_STRING => "Invalid type given. String expected",
        self::INVALID_TIME_ZONE => "The input is not a valid time zone",
    ];
    
    /**
     * 
     * {@inheritDoc}
     * @see \Zend\Validator\ValidatorInterface::isValid()
     */
    public function isValid($value)
    {
        static $timeZones;
        if (!isset($value) || '' === $value) {
            return true;
        }
        if (!is_string($value)) {
            $this->error(self::NOT_STRING);
            return false;
        }
        if (!isset($timeZones)) {
            $timeZones = self::getTimeZoneValueOptions();
        }
        $result = in_array($value, $timeZones, TRUE);
        //@todo there appears to be some problem with false negatives here, don't use in production
        if (!$result) {
            $this->error(self::INVALID_TIME_ZONE);
        }
        return $result;
    }
    
    public static function getTimeZoneValueOptions(string $country = null)
    {
        $tzs = TimeZoneSelect::get_time_zones();
        if (isset($country) && 'GB-SCT' !== $country) {
            $countryList = \DateTimeZone::listIdentifiers(\DateTimeZone::PER_COUNTRY, $country);
        }
        $onlyCountry = isset($country) && is_array($countryList) && !empty($countryList);
        $result = [];
        foreach ($tzs as $value) {
            if (!$onlyCountry || in_array($value['identifier'], $countryList)) {
                $result[$value['identifier']] = $value['alias'];
            }
        }
        return $result;
    }
}
