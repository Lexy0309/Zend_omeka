<?php
namespace MediaQuality;

use Omeka\Entity\Media;
use Omeka\Module\AbstractModule;
use Omeka\Module\Exception\ModuleCannotInstallException;
use Omeka\Stdlib\Message;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Form\Element\Checkbox;
use Zend\Form\Fieldset;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Renderer\PhpRenderer;

class Module extends AbstractModule
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $store = $this->getLocalStore($serviceLocator);
        if (empty($store)) {
            throw new ModuleCannotInstallException(
                new Message('This module requires a local store.')); // @translate
        }

        $basePath = $this->getBasePath($serviceLocator);
        if (!is_dir($basePath) || !is_writeable($basePath)) {
            throw new ModuleCannotInstallException(
                new Message('The directory "%1$s" should be writeable to install this module.', // @translate
                    $basePath));
        }

        $this->manageSiteSettings($serviceLocator, 'install');
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $this->manageSiteSettings($serviceLocator, 'uninstall');
    }

    protected function manageSiteSettings(ServiceLocatorInterface $serviceLocator, $process)
    {
        $siteSettings = $serviceLocator->get('Omeka\Settings\Site');
        $api = $serviceLocator->get('Omeka\ApiManager');
        $sites = $api->search('sites')->getContent();
        $config = require __DIR__ . '/config/module.config.php';
        $defaultSettings = $config[strtolower(__NAMESPACE__)]['site_settings'];
        foreach ($sites as $site) {
            $siteSettings->setTargetId($site->id());
            foreach ($defaultSettings as $name => $value) {
                switch ($process) {
                    case 'install':
                        $siteSettings->set($name, $value);
                        break;
                    case 'uninstall':
                        $siteSettings->delete($name);
                        break;
                }
            }
        }
    }

    public function warnUninstall(Event $event)
    {
        $view = $event->getTarget();
        $module = $view->vars()->module;
        if ($module->getId() != __NAMESPACE__) {
            return;
        }

        $services = $this->getServiceLocator();
        $basePath = $this->getBasePath($services);

        // TODO Add a checkbox to let the choice to remove or not.
        $html = '<ul class="messages"><li class="warning">';
        $html .= '<strong>';
        $html .= new Message('WARNING'); // @translate
        $html .= '</strong>' . ' ';
        $html .= new Message(
            'The processed files in %1$s won’t be removed: you have to delete them manually.', // @translate
            $basePath);
        ;
        $html .= '</li></ul>';
        echo $html;
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        return $renderer->escapeHtml($renderer->translate('For security reasons, the config of this module is available only in the config file "config/module.config.php".')); // @translate
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        // Note: When an item is saved manually, no event is triggered for media.
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.create.post',
            [$this, 'afterSaveItem']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.update.post',
            [$this, 'afterSaveItem']
        );

        // TODO "api.create.post" seems never to occur for media. Remove event?
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\MediaAdapter::class,
            'api.create.post',
            [$this, 'afterSaveMedia']
        );

        $sharedEventManager->attach(
            \Omeka\Entity\Media::class,
            'entity.remove.post',
            [$this, 'afterDeleteMedia'],
            // Before the deletion of the media via the core method.
            10
        );

        $sharedEventManager->attach(
            'Omeka\Controller\Site\Item',
            'view.show.after',
            [$this, 'viewShowAfterItem']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Site\Media',
            'view.show.after',
            [$this, 'viewShowAfterMedia']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Media',
            'view.details',
            [$this, 'viewDetailsMedia']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Media',
            'view.show.sidebar',
            [$this, 'viewDetailsMedia']
        );

        $sharedEventManager->attach(
            \Omeka\Form\SiteSettingsForm::class,
            'form.add_elements',
            [$this, 'addFormElementsSiteSettings']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Module',
            'view.details',
            [$this, 'warnUninstall']
        );
    }

    /**
     * Manages folders for attached files of items.
     */
    public function afterSaveItem(Event $event)
    {
        $services = $this->getServiceLocator();
        $store = $this->getLocalStore($services);
        if (empty($store)) {
            $this->logNoStore();
            return;
        }

        $item = $event->getParam('response')->getContent();
        $mediaIds = [];
        foreach ($item->getMedia() as $media) {
            if (!$this->isManaged($media)) {
                continue;
            }
            if ($this->hasAllDerivatives($media)) {
                continue;
            }
            $mediaIds[] = $media->getId();
        }
        $this->prepareJob($mediaIds);
    }

    /**
     * Convert media if configured for.
     *
     * @param Event $event
     */
    public function afterSaveMedia(Event $event)
    {
        $services = $this->getServiceLocator();
        $store = $this->getLocalStore($services);
        if (empty($store)) {
            $this->logNoStore();
            return;
        }

        $media = $event->getParam('response')->getContent();
        if (!$this->isManaged($media)) {
            return;
        }
        if ($this->hasAllDerivatives($media)) {
            return;
        }
        $this->prepareJob([$media->getId()]);
    }

    /**
     * Remove derivative media.
     *
     * @param Event $event
     */
    public function afterDeleteMedia(Event $event)
    {
        $services = $this->getServiceLocator();
        $store = $this->getLocalStore($services);
        if (empty($store)) {
            $this->logNoStore();
            return;
        }

        $media = $event->getTarget();
        if (!$this->isManaged($media)) {
            return;
        }

        $basePath = $this->getBasePath($services);

        $derivativePaths = $this->getAllDerivativePaths($media);
        foreach ($derivativePaths as $derivative) {
            if (file_exists($derivative['destination'])) {
                $store->delete($derivative['storagePath']);
            }

            // Remove empty dir if any (Archive Repertory or use of subdir).
            $dirBase = $basePath . DIRECTORY_SEPARATOR . $derivative['dir'];
            $this->removeEmptySubdirInsideDir($dirBase, dirname($derivative['destination']));
        }
    }

    public function viewShowAfterItem(Event $event)
    {
        $view = $event->getTarget();
        $services = $this->getServiceLocator();
        $siteSettings = $services->get('Omeka\Settings\Site');
        $siteSetting = 'mediaquality_append_item_show';
        if ($siteSettings->get($siteSetting,
            $services->get('Config')['mediaquality']['site_settings'][$siteSetting])
        ) {
            echo $view->partial('common/media-qualities-list', ['resource' => $view->resource]);
        }
    }

    public function viewShowAfterMedia(Event $event)
    {
        $view = $event->getTarget();
        $services = $this->getServiceLocator();
        $siteSettings = $services->get('Omeka\Settings\Site');
        $siteSetting = 'mediaquality_append_media_show';
        if ($siteSettings->get($siteSetting,
            $services->get('Config')['mediaquality']['site_settings'][$siteSetting])
        ) {
            echo $view->partial('common/media-qualities', ['resource' => $view->resource]);
        }
    }

    public function viewDetailsMedia(Event $event)
    {
        $view = $event->getTarget();
        $services = $this->getServiceLocator();
        echo $view->partial('common/media-qualities', ['resource' => $view->resource]);
    }

    public function addFormElementsSiteSettings(Event $event)
    {
        $services = $this->getServiceLocator();
        $siteSettings = $services->get('Omeka\Settings\Site');
        $config = $services->get('Config');
        $urlHelper = $services->get('ViewHelperManager')->get('url');
        $form = $event->getTarget();

        $defaultSiteSettings = $config['mediaquality']['site_settings'];

        $fieldset = new Fieldset('media_quality');
        $fieldset->setLabel('Media Quality');

        $fieldset->add([
            'name' => 'mediaquality_append_item_show',
            'type' => Checkbox::class,
            'options' => [
                'label' => 'Append automatically to "Item show"', // @translate
                'info' => 'If unchecked, the list can be added via the helper in the theme in any page.', // @translate
            ],
            'attributes' => [
                'value' => $siteSettings->get(
                    'mediaquality_append_item_show',
                    $defaultSiteSettings['mediaquality_append_item_show']
                ),
            ],
        ]);

        $fieldset->add([
            'name' => 'mediaquality_append_media_show',
            'type' => Checkbox::class,
            'options' => [
                'label' => 'Append automatically to "Media show"', // @translate
                'info' => 'If unchecked, the list can be added via the helper in the theme in any page.', // @translate
            ],
            'attributes' => [
                'value' => $siteSettings->get(
                    'mediaquality_append_media_show',
                    $defaultSiteSettings['mediaquality_append_media_show']
                ),
            ],
        ]);

        $form->add($fieldset);
    }

    /**
     * Prepare the jog
     *
     * @param array $mediaIds
     */
    protected function prepareJob(array $mediaIds)
    {
        if (empty($mediaIds)) {
            return;
        }

        $args = [];
        $args['medias'] = $mediaIds;
        $dispatcher = $this->getServiceLocator()->get('ControllerPluginManager')
            ->get('jobDispatcher');
        $job = $dispatcher()->dispatch(\MediaQuality\Job\Processor::class, $args);
    }

    /**
     * Remove an empty directory inside a directory.
     *
     * @param string $dirBase
     * @param string $subdir
     */
    protected function removeEmptySubdirInsideDir($dirBase, $subdir)
    {
        $subdir = realpath($subdir);
        while (strpos($subdir, $dirBase) === 0
            && $subdir !== $dirBase
            && file_exists($subdir)
            && is_readable($subdir)
            && is_writeable($subdir)
            // Check if the dir is empty.
            && !(new \FilesystemIterator($subdir))->valid()
            // Process the deletion.
            && rmdir($subdir)
        ) {
            $subdir = dirname($subdir);
        }
    }

    /**
     * Check if a media is managed by the module.
     *
     * @param Media $media
     * @return bool
     */
    protected function isManaged(Media $media)
    {
        if (!$media->hasOriginal()) {
            return false;
        }
        if ($media->getRenderer() !== 'file') {
            return false;
        }
        $processors = $this->getServiceLocator()->get('Config')['mediaquality']['processors'];
        return !empty($processors[$media->getMediaType()]);
    }

    /**
     * Check if a media is already processed.
     *
     * The check isManaged() should be done before.
     *
     * @param Media $media
     * @return bool
     */
    protected function hasAllDerivatives(Media $media)
    {
        $derivativePaths = $this->getAllDerivativePaths($media);
        foreach ($derivativePaths as $derivative) {
            if (!file_exists($derivative['destination'])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get all derivative paths of a media, existing or not, from the store.
     *
     * The check isManaged() should be done before.
     *
     * @param Media $media
     * @return array
     */
    protected function getAllDerivativePaths(Media $media)
    {
        $services = $this->getServiceLocator();
        $basePath = $this->getBasePath($services);
        $store = $this->getLocalStore($services);
        $processors = $services->get('Config')['mediaquality']['processors'];

        $derivativePaths = [];
        $storageId = $media->getStorageId();
        foreach ($processors[$media->getMediaType()] as $quality => $mediaProcessor) {
            $dir = $mediaProcessor['dir'];
            $storagePath = $dir
                . DIRECTORY_SEPARATOR . $storageId
                . '.' . $mediaProcessor['extension'];
            $destination = $basePath . DIRECTORY_SEPARATOR . $storagePath;
            $derivativePaths[] = [
                'dir' => $dir,
                'storagePath' => $storagePath,
                'destination' => $destination,
            ];
        }
        return $derivativePaths;
    }

    /**
     * Get the local dir, that is not available in the default config.
     *
     * @param ServiceLocatorInterface $services
     * @return \Omeka\File\Store\Local|null
     */
    protected function getLocalStore(ServiceLocatorInterface $services)
    {
        $store = $services->get('Omeka\File\Store');
        return $store instanceof \Omeka\File\Store\Local ? $store : null;
    }

    /**
     * Get the local dir, that is not available in the default config.
     *
     * @param ServiceLocatorInterface $services
     * @return string
     */
    protected function getBasePath(ServiceLocatorInterface $services)
    {
        $config = $services->get('Config');
        $basePath = $config['file_store']['local']['base_path'];
        if (null === $basePath) {
            $basePath = OMEKA_PATH . '/files';
        }
        return $basePath;
    }

    protected function logNoStore()
    {
        $services = $this->getServiceLocator();
        $logger = $services->get('Omeka\Logger');
        $logger->err(new Message('[MediaQuality] This module requires a local store.')); // @translate
    }
}
