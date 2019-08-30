<?php
namespace DownloadManager\Service\ControllerPlugin;

use DownloadManager\Mvc\Controller\Plugin\SiteOfUser;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class SiteOfUserFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        return new SiteOfUser(
            $services->get('Omeka\ApiManager'),
            $services->get('Omeka\Settings')
        );
    }
}
