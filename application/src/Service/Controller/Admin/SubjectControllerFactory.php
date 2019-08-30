<?php
namespace Omeka\Service\Controller\Admin;

use Interop\Container\ContainerInterface;
use Omeka\Controller\Admin\SubjectController;
use Zend\ServiceManager\Factory\FactoryInterface;

class SubjectControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new SubjectController(
            $services->get('Omeka\EntityManager')
        );
    }
}
