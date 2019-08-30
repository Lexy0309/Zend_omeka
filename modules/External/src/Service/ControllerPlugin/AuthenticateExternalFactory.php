<?php
namespace External\Service\ControllerPlugin;

use External\Mvc\Controller\Plugin\AuthenticateExternal;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class AuthenticateExternalFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        $httpClient = $services->get('Omeka\HttpClient');
        $settings = $services->get('Omeka\Settings');
        $hasIdentity = $services->get('Omeka\AuthenticationService')->hasIdentity();
        return new AuthenticateExternal(
            $httpClient,
            $settings,
            $hasIdentity
        );
    }
}
