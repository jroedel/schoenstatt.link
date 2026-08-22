<?php

declare(strict_types=1);

namespace Schoenstatt\Service;

use JTranslate\Model\CountriesInfo;
use JTranslate\Model\TranslationsTable;
use Laminas\Db\Adapter\Adapter;
use Psr\Container\ContainerInterface;
use Schoenstatt\Model\SchoenstattTable;
use SionModel\Service\ActingUserProviderInterface;
use SionModel\Service\EntitiesService;
use SionModel\Service\ProblemService;
use SionModel\Service\SionTableWiring;

/**
 * Builds {@see SchoenstattTable}.
 *
 * Two things this table used to do for itself and no longer can, because it is not handed a
 * container: resolve `AssociationKindsService`, and take an `EntityProblem` prototype off
 * the parent, which populated one for every table on behalf of the two that clone it.
 */
class SchoenstattTableFactory
{
    /**
     * @param string $requestedName
     * @param array<string, mixed>|null $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): SchoenstattTable
    {
        $sionModelConfig = $container->get('SionModel\Config');

        $plugins             = $container->get('ViewHelperManager');
        $translateViewHelper = $plugins->get('translate');

        $problemPrototype = empty($sionModelConfig['problem_specifications'])
            ? null
            : $container->get(ProblemService::class)->getEntityProblemPrototype();

        $table = new SchoenstattTable(
            $container->get(Adapter::class),
            $container->get(EntitiesService::class),
            $sionModelConfig,
            $container->get(ActingUserProviderInterface::class),
            $container->get(AssociationKindsService::class),
            $problemPrototype,
            $container->get('Config'),
            $container->get(CountriesInfo::class),
            $translateViewHelper->getTranslator(),
            $container->get(TranslationsTable::class)
        );
        SionTableWiring::apply($container, $table);

        return $table;
    }
}
