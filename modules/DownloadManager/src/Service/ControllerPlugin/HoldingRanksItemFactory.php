<?php
namespace DownloadManager\Service\ControllerPlugin;

use DownloadManager\Mvc\Controller\Plugin\HoldingRanksItem;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class HoldingRanksItemFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        $entityManager = $services->get('Omeka\EntityManager');
        $plugins = $services->get('ControllerPluginManager');
        return new HoldingRanksItem(
            $entityManager,
            $plugins
        );
    }
}
