<?php
namespace DownloadManager\Service\ViewHelper;

use DownloadManager\View\Helper\PrimaryOriginal;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class PrimaryOriginalFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $pluginManager = $services->get('ControllerPluginManager');
        $primaryOriginal = $pluginManager->get('primaryOriginal');
        return new PrimaryOriginal($primaryOriginal);
    }
}
