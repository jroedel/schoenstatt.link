<?php
namespace Books\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Books\Model\LibraryTable;
use Books\Form\SearchForm;

/**
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class SearchFormFactory implements FactoryInterface
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

        $form = new SearchForm();
        $collections = $table->getCollectionValueOptions();
        $form->get('collectionId')->setValueOptions($collections);
        return $form;
    }
}
