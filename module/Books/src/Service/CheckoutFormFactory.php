<?php
namespace Books\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Books\Form\CheckoutForm;
use Books\Model\LibraryTable;

/**
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class CheckoutFormFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        /** @var LibraryTable $table **/
        $table = $container->get(LibraryTable::class);

        /**
         * @var \Zend\Mvc\Application $application
         */
        $application = $container->get('Application');
        $routeMatch = $application->getMvcEvent()->getRouteMatch();
        $libraryId = $routeMatch->getParam('library_id', null);
        $libraries = $table->getObjects('library');
        if (! key_exists($libraryId, $libraries)) {
            throw new \Exception('Library not found');
        }
        $libraryOptions = $libraries[$libraryId]['options'];

        $schConfig = $container->get('Schoenstatt\Config');
        if (! isset($schConfig['person_value_options_providers'])) {
            throw new \Exception('No person_value_options_providers set');
        }
        $providers = $schConfig['person_value_options_providers'];
        if (! isset($providers[$libraryOptions->checkoutPersonListKind]) ||
            ! isset($providers[$libraryOptions->checkoutPersonListKind]['target']) ||
            ! $container->has($providers[$libraryOptions->checkoutPersonListKind]['target'])
        ) {
            throw new \Exception('Improper checkout person list kind configuration');
        }
        $form = new CheckoutForm();

        $persons = $container->get($providers[$libraryOptions->checkoutPersonListKind]['target']);
        $form->get('personId')->setValueOptions($persons);

        return $form;
    }
}
