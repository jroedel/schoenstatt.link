<?php

declare(strict_types=1);

namespace Books\Service;

use Books\Model\BorrowerTokenTable;
use Laminas\Db\Adapter\Adapter;
use Psr\Container\ContainerInterface;

/**
 * A factory class rather than a closure in module.config.php, and that is not style:
 * production runs with config caching on, the merged config is written out with
 * var_export, and a closure cannot survive that. It fatals at boot.
 */
class BorrowerTokenTableFactory
{
    public function __invoke(ContainerInterface $container): BorrowerTokenTable
    {
        return new BorrowerTokenTable($container->get(Adapter::class));
    }
}
