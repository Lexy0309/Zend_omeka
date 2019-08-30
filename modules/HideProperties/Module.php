<?php
namespace HideProperties;

use Omeka\Module\AbstractModule;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Mvc\Controller\AbstractController;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Renderer\PhpRenderer;

class Module extends AbstractModule
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $settings = $serviceLocator->get('Omeka\Settings');
        $settings->delete('hidden_properties_properties');
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $sharedEventManager->attach(
            '*',
            'rep.resource.display_values',
            [$this, 'filterDisplayValues']
        );
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $hiddenProperties = $settings->get('hidden_properties_properties', []);
        return $renderer->render('hide-properties/config-form', ['hiddenProperties' => $hiddenProperties]);
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $hiddenProperties = $controller->params()->fromPost('hidden-properties', []);
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $settings->set('hidden_properties_properties', $hiddenProperties);
    }

    public function filterDisplayValues(Event $event)
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $hiddenProperties = $settings->get('hidden_properties_properties', []);
        $values = $event->getParams()['values'];
        foreach ($hiddenProperties as $property) {
            unset($values[$property]);
        }
        $event->setParam('values', $values);
    }
}

