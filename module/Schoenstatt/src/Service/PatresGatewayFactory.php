<?php
namespace Schoenstatt\Service;

use Psr\Container\ContainerInterface;
use Schoenstatt\Form\PersonForm;
use SionModel\Form\Validation\FormSpecification;
use Schoenstatt\Model\SchoenstattTable;

/**
 * Factory responsible of retrieving an array containing the Schoenstatt configuration
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class PatresGatewayFactory
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        /** @var \Schoenstatt\Form\PersonForm $personForm */
        $personForm = $container->get(PersonForm::class);
        $config = $container->get('Schoenstatt\Config');
        $table = $container->get(SchoenstattTable::class);

        $patresGateway = new PatresGateway();

        //The specification, not a built filter, and `security` dropped by unsetting its
        //key: an import has no session, so leaving the CSRF rule in place would be a fatal
        //rather than a validation failure. Same seam, and for the same reason, as
        //App\Schoenstatt\Association\AssociationValidator.
        $personSpec = FormSpecification::of($personForm);
        unset($personSpec['security']);
        $patresGateway->setPersonInputFilterSpecification($personSpec);
        $patresGateway->setSchoenstattConfig($config);
        $patresGateway->setSchoenstattTable($table);

        if ($container->has('JUser\\Logger')) {
            $logger = $container->get('JUser\\Logger');
            $table->setLogger($logger);
        }
        return $patresGateway;
    }
}
