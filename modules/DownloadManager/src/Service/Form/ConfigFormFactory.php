<?php
namespace DownloadManager\Service\Form;

use DownloadManager\Form\ConfigForm;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ConfigFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $form = new ConfigForm(null, $options);
        $form->setUrlHelper($services->get('ViewHelperManager')->get('Url'));
        return $form;
    }
}
