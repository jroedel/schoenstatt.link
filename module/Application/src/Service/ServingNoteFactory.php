<?php

namespace Application\Service;

use Application\View\Helper\ServingNote;
use Laminas\Mvc\Application;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

/**
 * Builds the footer's serving-note helper.
 *
 * It takes the MvcEvent off the Application, which is safe *here* and would not be in
 * a controller factory: a view helper is constructed when a template first calls it,
 * i.e. after routing and dispatch, so the event already carries its route match. The
 * eager-controller trap in docs/BACKLOG.md is the same mistake made one layer up.
 */
class ServingNoteFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @inheritdoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        /** @var Application $application */
        $application = $container->get('Application');

        return new ServingNote($application->getMvcEvent());
    }
}
