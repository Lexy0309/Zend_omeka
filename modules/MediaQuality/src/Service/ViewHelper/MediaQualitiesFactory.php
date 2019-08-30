<?php
namespace MediaQuality\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use MediaQuality\View\Helper\MediaQualities;
use Zend\ServiceManager\Factory\FactoryInterface;

class MediaQualitiesFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $store = $services->get('Omeka\File\Store');
        $config = $services->get('Config');
        $processors = $store instanceof \Omeka\File\Store\Local
            ? $config['mediaquality']['processors']
            : [];
        return new MediaQualities(
            $processors,
            $store,
            $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files')
        );
    }
}
