<?php
namespace External\Service\ControllerPlugin;

use External\Mvc\Controller\Plugin\ExternalSearchPage;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ExternalSearchPageFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        $plugins = $services->get('ControllerPluginManager');
        return new ExternalSearchPage(
            $plugins
        );
    }
}
