<?php
namespace GuestUser\Service\ControllerPlugin;

use GuestUser\Mvc\Controller\Plugin\CreateGuestUserToken;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class CreateGuestUserTokenFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        return new CreateGuestUserToken(
            $services->get('Omeka\EntityManager')
        );
    }
}
