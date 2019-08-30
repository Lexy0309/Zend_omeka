<?php
namespace DownloadManager\Service\ViewHelper;

use DownloadManager\View\Helper\TotalAvailable;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class TotalAvailableFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $pluginManager = $services->get('ControllerPluginManager');
        $totalAvailable = $pluginManager->get('totalAvailable');
        return new TotalAvailable(
            $totalAvailable
        );
    }
}
