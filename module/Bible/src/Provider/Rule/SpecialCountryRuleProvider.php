<?php
namespace Bible\Provider\Rule;

use BjyAuthorize\Provider\Rule\ProviderInterface;
use ZfSnapGeoip\Service\Geoip;

class SpecialCountryRuleProvider implements ProviderInterface
{
    protected $geoip;
    
    public function __construct(Geoip $geoip)
    {
        $this->geoip = $geoip;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \BjyAuthorize\Provider\Rule\ProviderInterface::getRules()
     * Format of the allow key is [['role1', 'role2'], 'resourceId', 'permission']
     */
    public function getRules()
    {
        $ip = $_SERVER['REMOTE_ADDR'];
        $addressRecord = $this->geoip->lookup($ip);
        $countryCode = $addressRecord->getCountryCode();
        if ('CL' === $countryCode) {
            return [ 'allow' => [
                [['guest', 'bib_user'], 'route/dh/page'],
                [['guest', 'bib_user'], 'route/dh/number'],
            ]];
        }
        return [];
    }
}
