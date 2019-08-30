<?php
namespace DownloadManager\Service\ViewHelper;

use DownloadManager\View\Helper\ReadDownloadHash;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ReadDownloadHashFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $pluginManager = $services->get('ControllerPluginManager');
        $getCurrentDownload = $pluginManager->get('getCurrentDownload');
        return new ReadDownloadHash(
            $getCurrentDownload
        );
    }
}
