<?php
namespace DownloadManager\Service\ControllerPlugin;

use DownloadManager\Mvc\Controller\Plugin\UserApiKeys;
use DownloadManager\Mvc\Controller\Plugin\UseUniqueKeys;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class UseUniqueKeysFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        $plugins = $services->get('ControllerPluginManager');
        $uniqueUserKey = $plugins->get('uniqueUserKey');
        $uniqueServerKey = $plugins->get('uniqueServerKey');
        $uniqueUserKey = $uniqueUserKey();
        $uniqueServerKey = $uniqueServerKey();
        $userApiKeys = $plugins->get('userApiKeys');

        $hasUniqueKeys = (bool) $uniqueUserKey && (bool) $uniqueServerKey;
        $uniqueKeys = $hasUniqueKeys
            ? [
                UserApiKeys::LABEL_USER_KEY => $uniqueUserKey,
                UserApiKeys::LABEL_SERVER_KEY => $uniqueServerKey,
            ]
            : [];
        return new UseUniqueKeys($uniqueKeys, $userApiKeys);
    }
}
