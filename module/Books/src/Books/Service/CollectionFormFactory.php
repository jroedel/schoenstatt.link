<?php
namespace Books\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Books\Model\LibraryTable;
use Books\Form\CollectionForm;

/**
 * @author Jeff Roedel <webmaster@schoenstatt.link>
 */
class CollectionFormFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $form = new CollectionForm();
        $form->get('mainShowDisplay')->setValueOptions(LibraryTable::MAIN_SHOW_DISPLAY_VALUE_OPTIONS);
		return $form;
    }
}
