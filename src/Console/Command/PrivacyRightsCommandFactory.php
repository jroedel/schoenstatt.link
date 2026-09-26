<?php

declare(strict_types=1);

namespace App\Console\Command;

use App\Privacy\Erasure;
use App\Privacy\PersonalData;
use Psr\Container\ContainerInterface;
use SionModel\Db\Connection;

use function is_array;
use function is_string;

/**
 * Builds `privacy:export` and `privacy:erase`; the connection arrives in a closure for the
 * reason {@see SendBookNoticesCommandFactory} gives.
 */
final class PrivacyRightsCommandFactory
{
    /**
     * @param string $requestedName
     */
    public function __invoke(ContainerInterface $container, $requestedName): PrivacyExportCommand|PrivacyEraseCommand
    {
        if (PrivacyExportCommand::class === $requestedName) {
            return new PrivacyExportCommand(
                static fn (): PersonalData => new PersonalData($container->get(Connection::class))
            );
        }

        return new PrivacyEraseCommand(
            static fn (): Erasure => new Erasure($container->get(Connection::class), self::project($container))
        );
    }

    private static function project(ContainerInterface $container): string
    {
        $config = $container->get('config');
        $project = is_array($config) && is_array($config['jtranslate'] ?? null)
            ? ($config['jtranslate']['project_name'] ?? null)
            : null;
        if (! is_string($project) || '' === $project) {
            throw new \RuntimeException('jtranslate.project_name is not configured');
        }

        return $project;
    }
}
