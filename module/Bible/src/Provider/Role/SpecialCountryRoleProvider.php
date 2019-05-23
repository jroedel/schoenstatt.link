<?php
namespace Bible\Provider\Role;

use BjyAuthorize\Provider\Role\ProviderInterface;
use ZfSnapGeoip\Service\Geoip;
use Zend\Permissions\Acl\Role\GenericRole;

class SpecialCountryRoleProvider implements ProviderInterface
{
    protected $geoip;
    
    public function __construct(Geoip $geoip)
    {
        $this->geoip = $geoip;
    }
    
    /**
     * @return \Zend\Permissions\Acl\Role\RoleInterface[]
     */
    public function getRoles()
    {
        $ip = $_SERVER['REMOTE_ADDR'];
        $addressRecord = $this->geoip->lookup($ip);
        $countryCode = $addressRecord->getCountryCode();
        if ('CL' === $countryCode) {
            return [new GenericRole('bib_user')];
        }
        return [];
    }
}
