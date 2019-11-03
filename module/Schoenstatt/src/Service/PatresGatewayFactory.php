<?php
namespace Schoenstatt\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Schoenstatt\Form\PersonForm;
use Schoenstatt\Model\SchoenstattTable;

/**
 * Factory responsible of retrieving an array containing the Schoenstatt configuration
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class PatresGatewayFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        /** @var \Schoenstatt\Form\PersonForm $personForm */
        $personForm = $container->get(PersonForm::class);
        $config = $container->get('Schoenstatt\Config');
        $table = $container->get(SchoenstattTable::class);

        $patresGateway = new PatresGateway();

        $inputFilter = clone $personForm->getInputFilter();
        $inputFilter->remove('security');
        $patresGateway->setPersonInputFilter($inputFilter);
        $patresGateway->setSchoenstattConfig($config);
        $patresGateway->setSchoenstattTable($table);
        
        if ($container->has('JUser\Logger')) {
            $logger = $container->get('JUser\Logger');
            $table->setLogger($logger);
        }
        return $patresGateway;
    }
}
