<?php
namespace DownloadManager\Service\ControllerPlugin;

use DownloadManager\Mvc\Controller\Plugin\IdentityBySessionOrKey;
use Omeka\Authentication\Adapter\KeyAdapter;
use Omeka\Authentication\Storage\DoctrineWrapper;
use Interop\Container\ContainerInterface;
use Zend\Authentication\AuthenticationService;
use Zend\Authentication\Storage\NonPersistent;
use Zend\ServiceManager\Factory\FactoryInterface;

class IdentityBySessionOrKeyFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedNamed, array $options = null)
    {
        $entityManager = $services->get('Omeka\EntityManager');
        $acl = $services->get('Omeka\Acl');
        $userRepository = $entityManager->getRepository('Omeka\Entity\User');
        $keyRepository = $entityManager->getRepository('Omeka\Entity\ApiKey');
        $storage = new DoctrineWrapper(new NonPersistent, $userRepository);
        $adapter = new KeyAdapter($keyRepository, $entityManager);
        $authenticationServiceByKey = new AuthenticationService($storage, $adapter);
        $params = $services->get('ControllerPluginManager')->get('params');
        return new IdentityBySessionOrKey(
            $acl,
            $authenticationServiceByKey,
            $params
        );
    }
}
