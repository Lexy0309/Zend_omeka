<?php
namespace External\Service\ControllerPlugin;

use External\Mvc\Controller\Plugin\SearchExternal;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class SearchExternalFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        $httpClient = $services->get('Omeka\HttpClient');
        $plugins = $services->get('ControllerPluginManager');
        return new SearchExternal(
            $httpClient,
            $plugins
        );
    }
}
