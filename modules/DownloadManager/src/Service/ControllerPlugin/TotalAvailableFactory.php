<?php
namespace DownloadManager\Service\ControllerPlugin;

use DownloadManager\Mvc\Controller\Plugin\TotalAvailable;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class TotalAvailableFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        $api = $services->get('Omeka\ApiManager');
        $plugins = $services->get('ControllerPluginManager');
        return new TotalAvailable(
            $api,
            $plugins
        );
    }
}
