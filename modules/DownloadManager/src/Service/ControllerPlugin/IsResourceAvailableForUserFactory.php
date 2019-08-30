<?php
namespace DownloadManager\Service\ControllerPlugin;

use DownloadManager\Mvc\Controller\Plugin\IsResourceAvailableForUser;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class IsResourceAvailableForUserFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        $acl = $services->get('Omeka\Acl');
        $plugins = $services->get('ControllerPluginManager');
        return new IsResourceAvailableForUser(
            $acl,
            $plugins
        );
    }
}
