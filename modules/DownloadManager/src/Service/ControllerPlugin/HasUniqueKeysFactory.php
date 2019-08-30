<?php
namespace DownloadManager\Service\ControllerPlugin;

use DownloadManager\Mvc\Controller\Plugin\HasUniqueKeys;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class HasUniqueKeysFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        $plugins = $services->get('ControllerPluginManager');
        $uniqueUserKey = $plugins->get('uniqueUserKey');
        $uniqueServerKey = $plugins->get('uniqueServerKey');
        $hasUniqueKeys = (bool) $uniqueUserKey() && (bool) $uniqueServerKey();
        return new HasUniqueKeys($hasUniqueKeys);
    }
}
