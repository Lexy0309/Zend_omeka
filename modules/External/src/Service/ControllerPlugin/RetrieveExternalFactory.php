<?php
namespace External\Service\ControllerPlugin;

use External\Mvc\Controller\Plugin\RetrieveExternal;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class RetrieveExternalFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        $httpClient = $services->get('Omeka\HttpClient');
        $plugins = $services->get('ControllerPluginManager');
        return new RetrieveExternal(
            $httpClient,
            $plugins
        );
    }
}
