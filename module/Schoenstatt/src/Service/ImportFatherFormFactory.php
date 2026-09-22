<?php
namespace Schoenstatt\Service;

use Psr\Container\ContainerInterface;
use Schoenstatt\Form\ImportFatherForm;

/**
 * Factory responsible of retrieving an array containing the Schoenstatt configuration
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class ImportFatherFormFactory
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $persons = $container->get('Schoenstatt\FathersValueOptions');
        $form = new ImportFatherForm();
        $form->get('personId')->setValueOptions($persons);
        return $form;
    }
}
