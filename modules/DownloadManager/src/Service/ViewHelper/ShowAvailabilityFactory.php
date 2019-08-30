<?php
namespace DownloadManager\Service\ViewHelper;

use DownloadManager\View\Helper\ShowAvailability;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ShowAvailabilityFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $plugins = $services->get('ControllerPluginManager');
        return new ShowAvailability(
            $plugins
        );
    }
}
