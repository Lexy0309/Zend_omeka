<?php
namespace External\Service\ControllerPlugin;

use External\Mvc\Controller\Plugin\FilterExistingExternalRecords;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class FilterExistingExternalRecordsFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        $plugins = $services->get('ControllerPluginManager');
        return new FilterExistingExternalRecords(
            $services->get('Omeka\Connection'),
            $plugins->get('existingIdentifiers')
        );
    }
}

