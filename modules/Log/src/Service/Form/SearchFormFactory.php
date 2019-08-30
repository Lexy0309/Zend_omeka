<?php
namespace Log\Service\Form;

use Interop\Container\ContainerInterface;
use Log\Form\SearchForm;
use Zend\ServiceManager\Factory\FactoryInterface;

class SearchFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $form = new SearchForm(null, $options);
        $urlHelper = $services->get('ViewHelperManager')->get('url');
        $form->setUrlHelper($urlHelper);
        return $form;
    }
}
