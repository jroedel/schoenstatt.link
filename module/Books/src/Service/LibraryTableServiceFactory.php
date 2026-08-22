<?php

declare(strict_types=1);

namespace Books\Service;

use Books\Model\LibraryTable;
use Laminas\Db\Adapter\Adapter;
use Psr\Container\ContainerInterface;
use SionModel\Service\ActingUserProviderInterface;
use SionModel\Service\EntitiesService;
use SionModel\Service\ProblemService;
use SionModel\Service\SionTableWiring;

/**
 * Builds {@see LibraryTable}.
 *
 * One of the two tables in this application that clone an `EntityProblem` prototype, so one
 * of the two whose factory now asks `ProblemService` for it. SionTable used to do this for
 * every table, which is why it needed a `! $this instanceof ProblemTable` cycle guard.
 */
class LibraryTableServiceFactory
{
    /**
     * @param string $requestedName
     * @param array<string, mixed>|null $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): LibraryTable
    {
        $sionModelConfig = $container->get('SionModel\Config');

        //Null unless the application declares problem specifications — the same condition
        //SionTable's constructor applied before asking for the service.
        $problemPrototype = empty($sionModelConfig['problem_specifications'])
            ? null
            : $container->get(ProblemService::class)->getEntityProblemPrototype();

        $table = new LibraryTable(
            $container->get(Adapter::class),
            $container->get(EntitiesService::class),
            $sionModelConfig,
            $container->get(ActingUserProviderInterface::class),
            $problemPrototype,
            $container->get('Config')
        );
        SionTableWiring::apply($container, $table);

        return $table;
    }
}
