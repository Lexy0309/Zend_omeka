<?php
namespace DownloadManager\Service\Form;

use DownloadManager\Form\DownloadForm;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class DownloadFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $form = new DownloadForm(null, $options);
        $viewHelperManager = $services->get('ViewHelperManager');
        $form->setUrlHelper($viewHelperManager->get('Url'));
        $form->setFormElementManager($services->get('FormElementManager'));
        return $form;
    }
}
