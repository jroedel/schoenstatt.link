<?php
namespace Books\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Books\Model\LibraryTable;
use Books\Form\CollectionForm;

/**
 * @author Jeff Roedel <webmaster@schoenstatt.link>
 */
class CollectionFormFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $form = new CollectionForm();
        $form->get('mainShowDisplay')->setValueOptions(LibraryTable::MAIN_SHOW_DISPLAY_VALUE_OPTIONS);
		return $form;
    }
}
