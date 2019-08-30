<?php
namespace DownloadManager\Service\ViewHelper;

use DownloadManager\View\Helper\CheckRightToDownload;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class CheckRightToDownloadFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $pluginManager = $services->get('ControllerPluginManager');
        $checkRightToDownload = $pluginManager->get('checkRightToDownload');
        return new CheckRightToDownload(
            $checkRightToDownload
        );
    }
}
