<?php
namespace Omeka\Service\Controller;

use Interop\Container\ContainerInterface;
use Omeka\Controller\ApiController;

class ApiControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new ApiController(
            $services->get('Omeka\Paginator'),
            $services->get('Omeka\ApiManager'),
        );
    }
}
