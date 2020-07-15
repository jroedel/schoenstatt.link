<?php
namespace Bible\Provider\Rule;

use BjyAuthorize\Provider\Rule\ProviderInterface;
use ZfSnapGeoip\Service\Geoip;

class SpecialCountryRuleProvider implements ProviderInterface
{
    protected $geoip;

    protected $specialCountries;

    public function __construct(Geoip $geoip, array $config)
    {
        $this->geoip = $geoip;
        $this->specialCountries = $config['bible']['special_countries'];
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
        if (in_array($countryCode, $this->specialCountries)) {
            return [ 'allow' => [
                [['guest', 'bib_user'], 'route/dh/page'],
                [['guest', 'bib_user'], 'route/dh/number'],
                [['guest', 'bib_user'], 'route/bible'],
                [['guest', 'bib_user'], 'route/bible/text'],
                [['guest', 'bib_user'], 'route/bible/search'],
                [['guest', 'bib_user'], 'route/bible/greek'],
            ]];
        }
        return [];
    }
}
