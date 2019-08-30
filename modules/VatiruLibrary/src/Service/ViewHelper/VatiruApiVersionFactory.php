<?php
namespace VatiruLibrary\Service\ViewHelper;

use VatiruLibrary\View\Helper\VatiruApiVersion;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class VatiruApiVersionFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $pluginManager = $services->get('ControllerPluginManager');
        $vatiruApiVersion = $pluginManager->get('vatiruApiVersion');
        return new VatiruApiVersion($vatiruApiVersion());
    }
}
