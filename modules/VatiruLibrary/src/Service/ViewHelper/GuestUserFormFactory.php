<?php
namespace VatiruLibrary\Service\ViewHelper;

use VatiruLibrary\View\Helper\GuestUserForm;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class GuestUserFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $pluginManager = $services->get('ControllerPluginManager');
        $getForm = $pluginManager->get('getForm');
        return new GuestUserForm($getForm);
    }
}
