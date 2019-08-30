<?php
namespace VatiruLibrary\Service\ControllerPlugin;

use VatiruLibrary\Mvc\Controller\Plugin\VatiruApiVersion;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class VatiruApiVersionFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        /** @var \Omeka\Module\Manager $moduleManager */
        $moduleManager = $services->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule('VatiruLibrary');
        $version = $module->getIni('version');
        return new VatiruApiVersion($version);
    }
}
