<?php
namespace Books\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Books\Form\LibraryForm;
use Schoenstatt\Model\SchoenstattTable;
use Books\Model\LibraryTable;

/**
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class LibraryFormFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        /** @var SchoenstattTable $schoenstattTable **/
        $schoenstattTable = $container->get(SchoenstattTable::class);

        $persons = $schoenstattTable->getPersonValueOptions();
        $config = $container->get('Books\Config');

        $schConfig = $container->get('Schoenstatt\Config');
        $personValueOptionsOptions = [];
        foreach ($schConfig['person_value_options_providers'] as $key => $options) {
            if (! isset($options['label'])) {
                throw new \Exception('person_value_options_providers must have a \'label\' key set');
            }
            $personValueOptionsOptions[$key] = $options['label'];
        }

        $form = new LibraryForm();

        /** @var \Laminas\Router\RouteMatch $routeMatch */
        $routeMatch = $container->get('Application')->getMvcEvent()->getRouteMatch();
        $libraryId = $routeMatch->getParam('library_id');
        if (isset($libraryId)) {
            /** @var LibraryTable $table */
            $table = $container->get(LibraryTable::class);
            $collections = $table->getCollectionValueOptions($libraryId);
            $form->get('mainCollectionId')->setValueOptions($collections);
        }

        $form->get('filiationId')->setValueOptions($config['library_filiation_options']);
        $form->get('contactPersonId')->setValueOptions($persons);
        $form->get('mainShowDisplay')->setValueOptions(LibraryTable::MAIN_SHOW_DISPLAY_VALUE_OPTIONS);
        $form->get('defaultCheckoutPersonId')->setValueOptions($persons);
        $form->get('checkoutPersonListKind')->setValueOptions($personValueOptionsOptions);
        $form->get('checkoutBooksRole')->setValueOptions(LibraryTable::LIBRARY_GENERAL_ROLE_OPTIONS);
        $form->get('viewRole')->setValueOptions(LibraryTable::LIBRARY_GENERAL_ROLE_OPTIONS);
        return $form;
    }
}
