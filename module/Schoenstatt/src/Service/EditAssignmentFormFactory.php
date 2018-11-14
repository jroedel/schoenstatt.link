<?php
namespace Schoenstatt\Service;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;
use Schoenstatt\Form\AssignmentForm;

/**
 * Factory responsible of prepping the AssignmentForm
 *
 * @author Jeff Roedel <webmaster@schoenstatt.link>
 */
class EditAssignmentFormFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        /** @var AssignmentForm $form */
        $form = $container->get(AssignmentForm::class);

        $form->prepareforEdit();
        return $form;
    }
}
