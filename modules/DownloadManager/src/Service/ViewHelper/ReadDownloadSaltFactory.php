<?php
namespace DownloadManager\Service\ViewHelper;

use DownloadManager\View\Helper\ReadDownloadSalt;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ReadDownloadSaltFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $pluginManager = $services->get('ControllerPluginManager');
        $getCurrentDownload = $pluginManager->get('getCurrentDownload');
        return new ReadDownloadSalt(
            $getCurrentDownload
        );
    }
}
