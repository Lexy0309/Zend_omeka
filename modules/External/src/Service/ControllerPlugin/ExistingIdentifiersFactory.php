<?php
namespace External\Service\ControllerPlugin;

use External\Mvc\Controller\Plugin\ExistingIdentifiers;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ExistingIdentifiersFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        return new ExistingIdentifiers(
            $services->get('Omeka\Connection')
        );
    }
}

