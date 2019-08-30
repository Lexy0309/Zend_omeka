<?php
namespace DownloadManager\Service\ControllerPlugin;

use DownloadManager\Mvc\Controller\Plugin\UniqueServerKey;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class UniqueServerKeyFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        $config = $services->get('Config');
        $uniqueKey = empty($config['downloadmanager']['config']['downloadmanager_unique_server_key'])
            ? null
            : $config['downloadmanager']['config']['downloadmanager_unique_server_key'];
        return new UniqueServerKey($uniqueKey);
    }
}
