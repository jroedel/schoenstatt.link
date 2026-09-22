<?php

declare(strict_types=1);

namespace Schoenstatt\Service;

use JTranslate\Model\CountriesInfo;
use JTranslate\Model\TranslationsTable;
use SionModel\Db\Connection;
use SionModel\I18n\TranslatesMessages;
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

        //the one translator, asked for directly. This reached it through the `translate`
        //view helper until laminas-view was removed; the helper only ever handed back what
        //the container had put into it.
        $translator = $container->get(TranslatesMessages::class);

        $problemPrototype = empty($sionModelConfig['problem_specifications'])
            ? null
            : $container->get(ProblemService::class)->getEntityProblemPrototype();

        $table = new SchoenstattTable(
            $container->get(Connection::class),
            $container->get(EntitiesService::class),
            $sionModelConfig,
            $container->get(ActingUserProviderInterface::class),
            $container->get(AssociationKindsService::class),
            $problemPrototype,
            $container->get('Config'),
            $container->get(CountriesInfo::class),
            $translator,
            $container->get(TranslationsTable::class)
        );
        SionTableWiring::apply($container, $table);

        return $table;
    }
}
