<?php
namespace DownloadManager\Service\ViewHelper;

use DownloadManager\View\Helper\CheckResourceToDownload;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class CheckResourceToDownloadFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $pluginManager = $services->get('ControllerPluginManager');
        $plugin = $pluginManager->get('checkResourceToDownload');
        return new CheckResourceToDownload(
            $plugin
        );
    }
}
