<?php
namespace DownloadManager\Service\ControllerPlugin;

use DownloadManager\Mvc\Controller\Plugin\UserApiKeys;
use Interop\Container\ContainerInterface;
use Omeka\Mvc\Exception\RuntimeException;
use Zend\ServiceManager\Factory\FactoryInterface;

class UserApiKeysFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        $entityManager = $services->get('Omeka\EntityManager');
        $credentialMainKey = $this->readCredentialMainKey($services);
        $controllerPlugins = $services->get('ControllerPluginManager');
        $uniqueUserKey = $controllerPlugins->get('uniqueUserKey');
        $uniqueServerKey = $controllerPlugins->get('uniqueServerKey');
        return new UserApiKeys(
            $entityManager,
            $credentialMainKey,
            $uniqueUserKey(),
            $uniqueServerKey()
        );
    }

    protected function readCredentialMainKey(ContainerInterface $services)
    {
        $settings = $services->get('Omeka\Settings');
        $filepath = $settings->get('downloadmanager_credential_key_path');
        $filesize = 1040;
        if (!file_exists($filepath)) {
            throw new RuntimeException('The credential main key does not exist.'); // @translate
        } elseif (filesize($filepath) != $filesize) {
            throw new RuntimeException('The credential main key is empty.'); // @translate
        } elseif (!is_readable($filepath)) {
            throw new RuntimeException('The credential main key is not readable.'); // @translate
        }
        $credentialMainKey = require $filepath;
        return $credentialMainKey;
    }
}
