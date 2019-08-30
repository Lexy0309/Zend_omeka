<?php
namespace DownloadManager\Service\ControllerPlugin;

use DownloadManager\Mvc\Controller\Plugin\TotalExemplars;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class TotalExemplarsFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        $settings = $services->get('Omeka\Settings');
        $publicVisibility = $settings->get('downloadmanager_public_visibility');
        return new TotalExemplars(
            $publicVisibility
        );
    }
}
