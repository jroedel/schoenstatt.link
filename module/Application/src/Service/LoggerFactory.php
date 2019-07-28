<?php
/**
 * BjyAuthorize Module (https://github.com/bjyoungblood/BjyAuthorize)
 *
 * @link https://github.com/bjyoungblood/BjyAuthorize for the canonical source repository
 * @license http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Application\Service;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Log\Logger;
use Application\Log\Writer\SlackWebhook;

class LoggerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get('Config');
        $path = isset($config['schoenstatt']['log_path']) ? $config['schoenstatt']['log_path'] : 'data/logs/application.log';
        $writer = new \Zend\Log\Writer\Stream($path);
        $logger = new Logger();
        $logger->addWriter($writer);
        
        if (isset($config['slack_logger_url'])) {
            $webhookUrl = $config['slack_logger_url'];
            if (isset($config['slack_logger_minimum_severity'])) {
                $minimumSeverity = $config['slack_logger_minimum_severity'];
                $writer = new SlackWebhook($webhookUrl, $minimumSeverity);
            } else {
                $writer = new SlackWebhook($webhookUrl);
            }
            /*
             * @todo fix this writer. Unfortunately, it looks like there may not be a way to do
             * this without extending page load times. We maybe need to spin off a reporting
             * process or something. Maybe we should listen to the finish event to wrap up requests
             */ 
//             $logger->addWriter($writer);
        }
        return $logger;
    }

    /**
     * {@inheritDoc}
     *
     * @return \BjyAuthorize\View\UnauthorizedStrategy
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        return $this($serviceLocator, Logger::class);
    }
}
