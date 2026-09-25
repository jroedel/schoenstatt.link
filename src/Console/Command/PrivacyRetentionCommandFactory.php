<?php

declare(strict_types=1);

namespace App\Console\Command;

use App\Privacy\ContactRetention;
use Books\Model\BorrowerTokenTable;
use Psr\Container\ContainerInterface;
use SionModel\Db\Connection;

/**
 * Builds `privacy:retention`. The connection arrives in a closure for the reason
 * {@see SendBookNoticesCommandFactory} gives: every registered command is built to list it.
 */
final class PrivacyRetentionCommandFactory
{
    public function __invoke(ContainerInterface $container): PrivacyRetentionCommand
    {
        return new PrivacyRetentionCommand(
            static fn (): ContactRetention => new ContactRetention($container->get(Connection::class)),
            static fn (): BorrowerTokenTable => $container->get(BorrowerTokenTable::class)
        );
    }
}
