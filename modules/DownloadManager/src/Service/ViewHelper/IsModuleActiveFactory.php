<?php
namespace DownloadManager\Service\ViewHelper;

use DownloadManager\View\Helper\IsModuleActive;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class IsModuleActiveFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $moduleManager = $services->get('Omeka\ModuleManager');
        return new IsModuleActive($moduleManager);
    }
}
