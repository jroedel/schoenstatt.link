<?php
namespace Application\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Factory responsible of retrieving an array containing the Schoenstatt configuration
 *
 * @author Jeff Ro <webmaster@schoenstatt.link>
 */
class DbAdapterServiceFactory implements FactoryInterface
{
    /**
     * {@inheritDoc}
     *
     * @return array
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $config = $serviceLocator->get('Config');

        $dbParams = $config['db'];

        $env = getenv('APP_ENV') ?: 'production';
        if ($env != 'production') {
            $adapter = new \BjyProfiler\Db\Adapter\ProfilingAdapter($dbParams);

            if (php_sapi_name() == 'cli') {
                $logger = new \Zend\Log\Logger();
                // write queries profiling info to stdout in CLI mode
                $writer = new Zend\Log\Writer\Stream('php://output');
                $logger->addWriter($writer, \Zend\Log\Logger::DEBUG);
                $adapter->setProfiler(new \BjyProfiler\Db\Profiler\LoggingProfiler($logger));
            } else {
                $adapter->setProfiler(new \BjyProfiler\Db\Profiler\Profiler());
            }
            if (isset($dbParams['options']) && is_array($dbParams['options'])) {
                $options = $dbParams['options'];
            } else {
                $options = [];
            }
            $adapter->injectProfilingStatementPrototype($options);
        } else {
            $adapter = new \Zend\Db\Adapter\Adapter([
                'driver'    => 'pdo',
                'dsn'       => 'mysql:dbname='.$dbParams['database'].';host='.$dbParams['hostname'],
                'database'  => $dbParams['database'],
                'username'  => $dbParams['username'],
                'password'  => $dbParams['password'],
                'hostname'  => $dbParams['hostname'],
                'driver_options' => $dbParams['driver_options'],
            ]);
        }
        return $adapter;
    }
}
