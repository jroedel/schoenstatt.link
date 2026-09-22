<?php
namespace Application\Service;

use Psr\Container\ContainerInterface;

/**
 * Factory responsible of retrieving an array containing the Schoenstatt configuration
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class DbAdapterServiceFactory
{

    /**
     * Create an object
     *
     * @param  ContainerInterface $container
     * @param  string             $requestedName
     * @param  null|array         $options
     * @return object
     * @throws \Exception if unable to resolve the service.
     * @throws \Exception if an exception is raised when
     *     creating a service.
     * @throws \Exception if any other error occurs
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $config = $container->get('Config');

        $dbParams = $this->credentials($config);

        //bjyoungblood/bjy-profiler (dev query profiling) was dropped 2026-08-02:
        //abandoned, and it required the vulnerable monolithic zendframework metapackage
        return new \Laminas\Db\Adapter\Adapter([
            'driver'    => 'pdo',
            'dsn'       => 'mysql:dbname=' . $dbParams['database'] . ';host=' . $dbParams['hostname'],
            'database'  => $dbParams['database'],
            'username'  => $dbParams['username'],
            'password'  => $dbParams['password'],
            'hostname'  => $dbParams['hostname'],
            'driver_options' => $dbParams['driver_options'],
        ]);
    }

    /**
     * The `db` block, or a refusal that says what is missing.
     *
     * This read used to be `$config['db']` and six unchecked keys off it, which on a
     * machine with no `config/autoload/local.php` — a CI runner, a fresh checkout before
     * `config.sh` — meant **seven "Undefined array key" warnings** and then an adapter
     * built out of nulls: an empty DSN that fails later, at connect time, with a PDO
     * message naming no cause.
     *
     * Worth stating why that mattered enough to change: `phpunit.xml.dist` sets
     * `failOnWarning`, so the seven warnings failed the whole integration job on
     * 2026-09-07 with every assertion passing and nothing to point at. The tests that
     * reach the adapter already wrap it in `catch (Throwable) → markTestSkipped`, so a
     * clean refusal is exactly what they want and the suite skips honestly.
     *
     * The keys are named rather than defaulted because every one of them is required to
     * connect, and a default would trade a clear failure here for a confusing one later.
     *
     * @param mixed $config the merged configuration
     * @return array<string, mixed>
     * @throws \App\Services\ServiceNotCreated
     */
    private function credentials($config): array
    {
        $dbParams = is_array($config) && isset($config['db']) && is_array($config['db'])
            ? $config['db']
            : null;

        if (null === $dbParams) {
            throw new \App\Services\ServiceNotCreated(
                'No `db` configuration: the database credentials live in'
                . ' config/autoload/local.php, which is gitignored and machine-specific.'
                . ' Run ./config.sh to create one from the .dist file.'
            );
        }

        $missing = [];
        foreach (['database', 'hostname', 'username', 'password', 'driver_options'] as $key) {
            if (! array_key_exists($key, $dbParams)) {
                $missing[] = $key;
            }
        }
        if ([] !== $missing) {
            throw new \App\Services\ServiceNotCreated(sprintf(
                'The `db` configuration in config/autoload/local.php is missing: %s.',
                implode(', ', $missing)
            ));
        }

        return $dbParams;
    }
}
