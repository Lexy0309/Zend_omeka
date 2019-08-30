<?php
namespace DownloadManager\Service\Form;

use DownloadManager\Form\QuickSearchForm;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class QuickSearchFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $form = new QuickSearchForm(null, $options);
        $urlHelper = $services->get('ViewHelperManager')->get('url');
        $form->setUrlHelper($urlHelper);
        return $form;
    }
}
