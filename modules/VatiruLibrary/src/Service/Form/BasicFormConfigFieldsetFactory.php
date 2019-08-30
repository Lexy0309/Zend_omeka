<?php
namespace VatiruLibrary\Service\Form;

use VatiruLibrary\Form\BasicFormConfigFieldset;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class BasicFormConfigFieldsetFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $form = new BasicFormConfigFieldset(null, $options);
        return $form;
    }
}
