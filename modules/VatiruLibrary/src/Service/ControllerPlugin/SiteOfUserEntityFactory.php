<?php
namespace VatiruLibrary\Service\ControllerPlugin;

use VatiruLibrary\Mvc\Controller\Plugin\SiteOfUserEntity;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class SiteOfUserEntityFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        return new SiteOfUserEntity(
            $services->get('Omeka\EntityManager')
                ->getRepository(\Omeka\Entity\SitePermission::class)
        );
    }
}
