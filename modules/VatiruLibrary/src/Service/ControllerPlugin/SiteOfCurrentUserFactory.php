<?php
namespace VatiruLibrary\Service\ControllerPlugin;

use VatiruLibrary\Mvc\Controller\Plugin\SiteOfCurrentUser;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class SiteOfCurrentUserFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        return new SiteOfCurrentUser(
            $services->get('Omeka\AuthenticationService'),
            $services->get('Omeka\EntityManager'),
            $services->get('Omeka\Settings')
        );
    }
}
