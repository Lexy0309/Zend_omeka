<?php
namespace DownloadManager\Service\ControllerPlugin;

use DownloadManager\Mvc\Controller\Plugin\HoldingRank;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class HoldingRankFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        $connection = $services->get('Omeka\Connection');
        $plugins = $services->get('ControllerPluginManager');
        return new HoldingRank(
            $connection,
            $plugins
        );
    }
}
