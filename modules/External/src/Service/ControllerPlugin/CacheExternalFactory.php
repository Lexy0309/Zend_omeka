<?php
namespace External\Service\ControllerPlugin;

use External\Mvc\Controller\Plugin\CacheExternal;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;
use Zend\Session\Container;

class CacheExternalFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        $sessionContainer = new Container('External');
        $sessionContainer->setExpirationSeconds(86400);
        return new CacheExternal(
            $sessionContainer
        );
    }
}
