<?php
namespace GuestUser\Service\Controller\Site;

use GuestUser\Controller\Site\GuestUserController;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class GuestUserControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new GuestUserController(
            $services->get('Omeka\AuthenticationService'),
            $services->get('Omeka\EntityManager'),
            $services->get('Config')
        );
    }
}
