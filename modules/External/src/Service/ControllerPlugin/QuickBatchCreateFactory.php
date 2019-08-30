<?php
namespace External\Service\ControllerPlugin;

use External\Mvc\Controller\Plugin\QuickBatchCreate;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class QuickBatchCreateFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        $plugins = $services->get('ControllerPluginManager');
        $owner = $services->get('Omeka\AuthenticationService')->getIdentity();
        // Owner id is used to create sql, so the string "NULL" may be used.
        $ownerId = $owner ? $owner->getId() : 'NULL';
        $quickBatchCreate = new QuickBatchCreate(
            $services->get('Omeka\Connection'),
            $plugins->get('importThumbnail'),
            $services->get('Omeka\Job\Dispatcher'),
            $services->get('Omeka\Logger'),
            $ownerId
        );
        $quickBatchCreate->setEventManager($services->get('EventManager'));
        return $quickBatchCreate;
    }
}
