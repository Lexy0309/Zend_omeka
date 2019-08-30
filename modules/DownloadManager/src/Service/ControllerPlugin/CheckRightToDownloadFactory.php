<?php
namespace DownloadManager\Service\ControllerPlugin;

use DownloadManager\Mvc\Controller\Plugin\CheckRightToDownload;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class CheckRightToDownloadFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        $acl = $services->get('Omeka\Acl');
        $settings = $services->get('Omeka\Settings');
        $api = $services->get('Omeka\ApiManager');
        $plugins = $services->get('ControllerPluginManager');
        return new CheckRightToDownload(
            $acl,
            $settings,
            $api,
            $plugins
        );
    }
}
