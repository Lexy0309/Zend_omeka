<?php

namespace DownloadManager\Service\Controller\Site;

use DownloadManager\Controller\Site\DownloadController;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class DownloadControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $entityManager = $services->get('Omeka\EntityManager');
        $config = $services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        return new DownloadController(
            $entityManager,
            $basePath,
            $config
        );
    }
}
