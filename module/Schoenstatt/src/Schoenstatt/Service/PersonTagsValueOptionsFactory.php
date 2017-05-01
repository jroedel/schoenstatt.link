<?php
namespace Schoenstatt\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Factory responsible of retrieving an array containing the Schoenstatt configuration
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class PersonTagsValueOptionsFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return array
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $config = $serviceLocator->get('Config')['schoenstatt'];

        $tagLabels = [];
        foreach ($config['person_tags'] as $key => $value) {
            if (is_array($value) && key_exists('label', $value)) {
                $tagLabels[$key] = $value['label'];
            }
        }
        return $tagLabels;
    }
}
