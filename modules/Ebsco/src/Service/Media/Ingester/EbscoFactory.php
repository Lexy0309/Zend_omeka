<?php
namespace Ebsco\Service\Media\Ingester;

use Ebsco\Media\Ingester\Ebsco;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class EbscoFactory implements FactoryInterface
{
    /**
     * Create the Ebsco media ingester service.
     *
     * @return Ebsco
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $plugins = $services->get('ControllerPluginManager');
        return new Ebsco(
            $services->get('Omeka\File\Downloader'),
            $plugins->get('importThumbnail'),
            $services->get('Omeka\Job\Dispatcher')
        );
    }
}
