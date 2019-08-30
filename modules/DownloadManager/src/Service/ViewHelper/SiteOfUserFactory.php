<?php
namespace DownloadManager\Service\ViewHelper;

use DownloadManager\View\Helper\SiteOfUser;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class SiteOfUserFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $pluginManager = $services->get('ControllerPluginManager');
        $plugin = $pluginManager->get('siteOfUser');
        return new SiteOfUser(
            $plugin
        );
    }
}
