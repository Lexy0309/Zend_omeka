<?php

namespace DownloadManager\Service\Controller\Admin;

use DownloadManager\Controller\Admin\StatsController;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class StatsControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new StatsController(
            $services->get('Omeka\EntityManager')
        );
    }
}
