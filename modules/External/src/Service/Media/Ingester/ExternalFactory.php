<?php
namespace External\Service\Media\Ingester;

use External\Media\Ingester\External;
use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class ExternalFactory implements FactoryInterface
{
    /**
     * Create the External media ingester service.
     *
     * @return External
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $plugins = $services->get('ControllerPluginManager');
        return new External(
            $services->get('Omeka\File\Downloader'),
            $services->get('Omeka\File\Validator'),
            $plugins->get('retrieveExternal'),
            $plugins->get('convertExternalRecord'),
            $plugins->get('importThumbnail'),
            $services->get('Omeka\Job\Dispatcher')
        );
    }
}
