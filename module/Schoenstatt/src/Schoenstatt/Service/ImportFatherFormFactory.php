<?php
namespace Schoenstatt\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Schoenstatt\Form\ImportFatherForm;

/**
 * Factory responsible of retrieving an array containing the Schoenstatt configuration
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class ImportFatherFormFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return array
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $persons = $serviceLocator->get('Schoenstatt\FathersValueOptions');
        $form = new ImportFatherForm();
        $form->get('personId')->setValueOptions($persons);
        return $form;
    }
}
