<?php

namespace DownloadManager\Service\Controller\Admin;

use DownloadManager\Controller\Admin\DownloadController;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class DownloadControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new DownloadController(
            $services->get('Omeka\EntityManager')
        );
    }
}
