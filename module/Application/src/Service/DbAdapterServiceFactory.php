<?php
namespace Application\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

/**
 * Factory responsible of retrieving an array containing the Schoenstatt configuration
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class DbAdapterServiceFactory implements FactoryInterface
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

        $dbParams = $config['db'];

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
}
