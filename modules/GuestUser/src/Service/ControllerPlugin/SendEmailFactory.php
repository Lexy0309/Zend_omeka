<?php
namespace GuestUser\Service\ControllerPlugin;

use GuestUser\Mvc\Controller\Plugin\SendEmail;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class SendEmailFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        return new SendEmail(
            $services->get('Omeka\Mailer'),
            $services->get('Omeka\Logger')
        );
    }
}
