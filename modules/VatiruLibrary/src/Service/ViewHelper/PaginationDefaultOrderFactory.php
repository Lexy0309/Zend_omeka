<?php

namespace VatiruLibrary\Service\ViewHelper;

use VatiruLibrary\View\Helper\PaginationDefaultOrder;
use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

/**
 * Service factory for the pagination view helper.
 */
class PaginationDefaultOrderFactory implements FactoryInterface
{
    /**
     * Create and return the pagination view helper
     *
     * @return PaginationDefaultOrder
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new PaginationDefaultOrder($services->get('Omeka\Paginator'));
    }
}
