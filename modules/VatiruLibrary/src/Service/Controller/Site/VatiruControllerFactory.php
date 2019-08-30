<?php
namespace VatiruLibrary\Service\Controller\Site;

use VatiruLibrary\Controller\Site\VatiruController;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class VatiruControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new VatiruController(
            $services->get('Omeka\AuthenticationService')
        );
    }
}
