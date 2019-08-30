<?php
namespace Maintenance;

use Omeka\Form\Element\CkeditorInline;
use Omeka\Module\AbstractModule;
use Omeka\Stdlib\Message;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Form\Element\Checkbox;
use Zend\Form\Fieldset;
use Zend\Mvc\MvcEvent;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\EventManager\Event;

/**
 * Maintenance
 *
 * Add a setting to set the site under maintenance for the public.
 *
 * @copyright Daniel Berthereau, 2017-2018
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */
class Module extends AbstractModule
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);
        if ($this->checkMaintenanceStatus($event)) {
            $this->siteUnderMaintenance($event);
        }
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $settings = $serviceLocator->get('Omeka\Settings');
        $t = $serviceLocator->get('MvcTranslator');
        $config = require __DIR__ . '/config/module.config.php';
        $defaultSettings = $config[strtolower(__NAMESPACE__)]['config'];
        foreach ($defaultSettings as $name => $value) {
            if ($name === 'maintenance_text') {
                $value = $t->translate($value);
            }
            $settings->set($name, $value);
        }
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $this->uninstallSettings($serviceLocator->get('Omeka\Settings'));
    }

    protected function uninstallSettings($settings)
    {
        $config = require __DIR__ . '/config/module.config.php';
        $defaultSettings = $config[strtolower(__NAMESPACE__)]['config'];
        foreach ($defaultSettings as $name => $value) {
            $settings->delete($name);
        }
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $sharedEventManager->attach(
            \Omeka\Form\SettingForm::class,
            'form.add_elements',
            [$this, 'addSettingFormElements']
        );
    }

    public function addSettingFormElements(Event $event)
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $config = $services->get('Config');
        $viewHelpers = $services->get('ViewHelperManager');
        $urlHelper = $viewHelpers->get('url');
        $form = $event->getTarget();

        $defaultSettings = $config[strtolower(__NAMESPACE__)]['config'];

        $fieldset = new Fieldset('maintenance');
        $fieldset->setLabel('Maintenance'); // @translate

        $fieldset->add([
            'name' => 'maintenance_status',
            'type' => Checkbox::class,
            'options' => [
                'label' => 'Set the public site under maintenance', // @translate
            ],
            'attributes' => [
                'value' => $settings->get(
                    'maintenance_status',
                    $defaultSettings['maintenance_status']
                ),
            ],
        ]);

        $formElementManager = $services->get('FormElementManager');
        $textarea = $formElementManager->get(CkeditorInline::class);
        $textarea
            ->setName('maintenance_text')
            ->setOptions([
                'label' => 'Text to display', // @translate
            ])
            ->setAttributes([
                'id' => 'maintenance-text',
                'rows' => 12,
                'placeholder' => 'This site is down for maintenance. Please contact the site administrator for more information.', // @translate
                'value' => $settings->get(
                    'maintenance_text',
                    $defaultSettings['maintenance_text']
                ),
            ]);

        $ckEditorHelper = $viewHelpers->get('ckEditor');
        $ckEditorHelper();

        $fieldset->add($textarea);

        $form->add($fieldset);
    }

    /**
     * Check if the maintenance is set on or off.
     *
     * @param MvcEvent $event
     * @return bool
     */
    protected function checkMaintenanceStatus(MvcEvent $event)
    {
        return $event->getApplication()
            ->getServiceManager()
            ->get('Omeka\Settings')
            ->get('maintenance_status', false);
    }

    /**
     * Redirect to maintenance for public pages and warn on admin pages.
     *
     * @param MvcEvent $event
     */
    protected function siteUnderMaintenance(MvcEvent $event)
    {
        static $done;

        $services = $event->getApplication()->getServiceManager();
        if ($this->isAdminRequest($event)) {
            if ($done) {
                return;
            }
            $done = true;
            $basePath = $services->get('ViewHelperManager')->get('basePath');
            $url = $basePath() . '/admin/setting';
            $messenger = $services->get('ControllerPluginManager')->get('messenger');
            $message = new Message(
                'Site is under %smaintenance%s.', // @translate
                sprintf('<a href="%s">', htmlspecialchars($url)),
                '</a>'
            );
            $message->setEscapeHtml(false);
            $messenger->addWarning($message); // @translate
            return;
        }

        $urlHelper = $services->get('ViewHelperManager')->get('url');
        $request = $event->getRequest();
        $requestUri = $request->getRequestUri();
        $maintenanceUri = $urlHelper('maintenance');
        $loginUri = $urlHelper('login');
        $logoutUri = $urlHelper('logout');
        $migrateUri = $urlHelper('migrate');
        if (in_array($requestUri, [$maintenanceUri, $loginUri, $logoutUri, $migrateUri])) {
            return;
        }

        $response = $event->getResponse();
        $response->getHeaders()->addHeaderLine('Location', $maintenanceUri);
        $response->setStatusCode(302);
        $response->sendHeaders();
        exit;
    }

    /**
     * Check if a request is public.
     *
     * @param MvcEvent $event
     * @return bool
     */
    protected function isAdminRequest(MvcEvent $event)
    {
        $request = $event->getRequest();
        return strpos($request->getRequestUri(), $request->getBasePath() . '/admin') === 0;
    }
}
