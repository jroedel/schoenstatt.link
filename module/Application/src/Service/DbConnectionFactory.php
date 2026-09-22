<?php

declare(strict_types=1);

namespace Application\Service;

use App\Services\ServiceNotCreated;
use Psr\Container\ContainerInterface;
use SionModel\Db\Connection;

use function array_key_exists;
use function implode;
use function is_array;
use function sprintf;

/**
 * The application's one database connection.
 *
 * The credentials live in `config/autoload/local.php`, which is gitignored and
 * machine-specific, so the `db` block is checked key by key rather than read and hoped for.
 * That is not defensive habit: on a machine without a `local.php` — a CI runner, a fresh
 * checkout before `config.sh` — the unchecked read produced seven "Undefined array key"
 * warnings and then a connection built out of nulls, which failed later at connect time with
 * a PDO message naming no cause. `phpunit.xml.dist` sets `failOnWarning`, so those warnings
 * failed the whole integration job on 2026-09-07 with every assertion passing and nothing to
 * point at. The tests that reach the connection already wrap it in
 * `catch (Throwable) → markTestSkipped`, so a clean refusal is what they want.
 */
final class DbConnectionFactory
{
    public function __invoke(ContainerInterface $container): Connection
    {
        return Connection::fromCredentials($this->credentials($container->get('Config')));
    }

    /**
     * @return array<string, mixed>
     * @throws ServiceNotCreated when the `db` block is absent or incomplete.
     */
    private function credentials(mixed $config): array
    {
        if (! is_array($config) || ! isset($config['db']) || ! is_array($config['db'])) {
            throw new ServiceNotCreated(
                'No `db` configuration: the database credentials live in'
                . ' config/autoload/local.php, which is gitignored and machine-specific.'
                . ' Run ./config.sh to create one from the .dist file.'
            );
        }

        $missing = [];
        foreach (['database', 'hostname', 'username', 'password', 'driver_options'] as $key) {
            if (! array_key_exists($key, $config['db'])) {
                $missing[] = $key;
            }
        }

        if ([] !== $missing) {
            throw new ServiceNotCreated(sprintf(
                'The `db` configuration in config/autoload/local.php is missing: %s.',
                implode(', ', $missing)
            ));
        }

        return $config['db'];
    }
}
