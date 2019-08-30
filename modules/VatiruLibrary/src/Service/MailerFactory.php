<?php
namespace VatiruLibrary\Service;

use VatiruLibrary\Stdlib\Mailer;
use Omeka\Service\Exception;
use Zend\Mail\Transport\Factory as TransportFactory;
use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class MailerFactory implements FactoryInterface
{
    /**
     * Create the mailer service.
     *
     * @return Mailer
     */
    public function __invoke(ContainerInterface $serviceLocator, $requestedName, array $options = null)
    {
        $config = $serviceLocator->get('Config');
        $viewHelpers = $serviceLocator->get('ViewHelperManager');
        $entityManager = $serviceLocator->get('Omeka\EntityManager');
        if (!isset($config['mail']['transport'])) {
            throw new Exception\ConfigException('Missing mail transport configuration');
        }
        $transport = TransportFactory::create($config['mail']['transport']);
        $settings = $serviceLocator->get('Omeka\Settings');
        $defaultOptions = [];
        if (isset($config['mail']['default_message_options'])) {
            $defaultOptions = $config['mail']['default_message_options'];
        }
        if (!isset($defaultOptions['administrator_email'])) {
            $defaultOptions['from'] = $settings->get('administrator_email');
        }

        $defaultOptions['message']['user_activation'] = $settings->get('vatiru_message_user_activation');

        return new Mailer($transport, $viewHelpers, $entityManager, $defaultOptions);
    }
}
