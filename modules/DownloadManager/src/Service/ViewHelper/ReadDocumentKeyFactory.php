<?php
namespace DownloadManager\Service\ViewHelper;

use DownloadManager\View\Helper\ReadDocumentKey;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ReadDocumentKeyFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $pluginManager = $services->get('ControllerPluginManager');
        $getCurrentDownload = $pluginManager->get('getCurrentDownload');
        $createDocumentKey = $pluginManager->get('createDocumentKey');
        return new ReadDocumentKey(
            $getCurrentDownload,
            $createDocumentKey
        );
    }
}
