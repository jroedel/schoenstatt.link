<?php

declare(strict_types=1);

namespace Books\Service;

use App\Http\SymfonyRoutes;
use Books\Model\DictionaryTable;
use Laminas\Db\Adapter\Adapter;
use Psr\Container\ContainerInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use SionModel\Service\ActingUserProviderInterface;
use SionModel\Service\EntitiesService;
use SionModel\Service\SionTableWiring;

use function is_array;
use function is_string;

/**
 * Builds {@see DictionaryTable}.
 *
 * The old comment here explained that this passed three arguments rather than four because
 * "the table reaches config through the container it is already given". It is not given one
 * any more: SionTable takes its four required collaborators explicitly, and the router this
 * table used to fetch for itself is now argument five.
 */
class DictionaryTableFactory
{
    /**
     * @param string $requestedName
     * @param array<string, mixed>|null $options
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null): DictionaryTable
    {
        $table = new DictionaryTable(
            $container->get(Adapter::class),
            $container->get(EntitiesService::class),
            $container->get('SionModel\Config'),
            $container->get(ActingUserProviderInterface::class),
            new UrlGenerator(SymfonyRoutes::collection(), new RequestContext()),
            self::canonicalBaseUrl($container)
        );
        SionTableWiring::apply($container, $table);

        return $table;
    }

    /**
     * `sion_model.canonical_base_url` — the scheme and host the schema.org projection
     * publishes. Empty means "do not claim one", and getDictionaryUrl() then omits the
     * `inDefinedTermSet` rather than emitting a relative URL into JSON-LD.
     */
    private static function canonicalBaseUrl(ContainerInterface $container): string
    {
        /** @var mixed $config */
        $config = $container->get('SionModel\Config');
        $base   = is_array($config) ? ($config['canonical_base_url'] ?? null) : null;

        return is_string($base) ? $base : '';
    }
}
