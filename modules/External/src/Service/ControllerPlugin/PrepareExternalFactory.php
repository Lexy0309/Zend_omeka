<?php
namespace External\Service\ControllerPlugin;

use External\Mvc\Controller\Plugin\PrepareExternal;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class PrepareExternalFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        return new PrepareExternal(
            $services->get('ControllerPluginManager')
        );
    }
}
