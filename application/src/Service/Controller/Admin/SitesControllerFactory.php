<?php
namespace Omeka\Service\Controller\Admin;

use Interop\Container\ContainerInterface;
use Omeka\Controller\Admin\StatsController;
use Zend\ServiceManager\Factory\FactoryInterface;

class StatsControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new StatsController(
            $services->get('Omeka\EntityManager')
        );
    }
}
