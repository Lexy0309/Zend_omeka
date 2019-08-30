<?php
namespace External\Service\ControllerPlugin;

use External\Mvc\Controller\Plugin\ImportThumbnail;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ImportThumbnailFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        return new ImportThumbnail(
            $services->get('Omeka\File\Downloader'),
            $services->get('Omeka\File\Validator')
        );
    }
}

