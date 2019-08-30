<?php
namespace Log\Service;

use Interop\Container\ContainerInterface;
use Zend\Log\Logger;
use Zend\Log\Writer\Noop;

/**
 * Logger Db factory.
 */
class LoggerDbFactory extends LoggerFactory
{
    /**
     * Create the logger Db service.
     *
     * @return Logger
     */
    public function __invoke(ContainerInterface $serviceLocator, $requestedName, array $options = null)
    {
        $config = $serviceLocator->get('Config');

        $writers = ['db' => $config['logger']['options']['writers']['db']];

        if (empty($writers['db']['options']['db'])) {
            $dbAdapter = $this->getDbAdapter($serviceLocator);
            if ($dbAdapter) {
                $writers['db']['options']['db'] = $dbAdapter;
            } else {
                trigger_error(
                    'Database logging disabled: wrong config.',
                    E_USER_WARNING
                );
                return (new Logger)->addWriter(new Noop);
            }
        }

        $config['logger']['options']['writers'] = $writers;
        if (!empty($config['logger']['options']['processors']['userid']['name'])) {
            $config['logger']['options']['processors']['userid']['name'] = $this->addUserIdProcessor($serviceLocator);
        }

        // Checks are managed via the constructor.
        return new Logger($config['logger']['options']);
    }
}
