<?php
namespace DownloadManager\Service\ControllerPlugin;

use DownloadManager\Mvc\Controller\Plugin\UniqueUserKey;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class UniqueUserKeyFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        $config = $services->get('Config');
        $uniqueKey = empty($config['downloadmanager']['config']['downloadmanager_unique_user_key'])
            ? null
            : $config['downloadmanager']['config']['downloadmanager_unique_user_key'];
        return new UniqueUserKey($uniqueKey);
    }
}
