<?php
namespace DownloadManager\Service\ViewHelper;

use DownloadManager\View\Helper\ReadUserKey;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ReadUserKeyFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $pluginManager = $services->get('ControllerPluginManager');
        $createUserKey = $pluginManager->get('createUserKey');
        return new ReadUserKey(
            $createUserKey
        );
    }
}
