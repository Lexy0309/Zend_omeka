<?php
namespace DownloadManager\Service\ViewHelper;

use DownloadManager\View\Helper\TotalExemplars;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class TotalExemplarsFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $pluginManager = $services->get('ControllerPluginManager');
        $totalExemplars = $pluginManager->get('totalExemplars');
        return new TotalExemplars(
            $totalExemplars
        );
    }
}
