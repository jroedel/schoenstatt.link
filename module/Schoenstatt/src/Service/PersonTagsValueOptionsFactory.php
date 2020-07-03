<?php
namespace Schoenstatt\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

/**
 * Factory responsible of retrieving an array containing the Schoenstatt configuration
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class PersonTagsValueOptionsFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get('Config')['schoenstatt'];

        $tagLabels = [];
        foreach ($config['person_tags'] as $key => $value) {
            if (is_array($value) && key_exists('label', $value)) {
                $tagLabels[$key] = $value['label'];
            }
        }
        return $tagLabels;
    }
}
