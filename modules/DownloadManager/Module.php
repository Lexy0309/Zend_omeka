<?php
/**
 * Download Manager
 *
 * Manage the downloads of copyrighted items and the holds placed by users.
 *
 * @copyright Daniel Berthereau, 2017-2018
 */
namespace DownloadManager;

use DownloadManager\Entity\Download;
use DownloadManager\Form\ConfigForm;
// use DownloadManager\Form\DownloadForm;
use Omeka\Api\Exception\NotFoundException;
use Omeka\Api\Exception\ValidationException;
use Omeka\Api\Representation\AbstractResourceRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Module\AbstractModule;
use Omeka\Module\Exception\ModuleCannotInstallException;
use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Permissions\Assertion\OwnsEntityAssertion;
use Omeka\Stdlib\Message;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\ModuleManager\ModuleEvent;
use Zend\ModuleManager\ModuleManager;
use Zend\Mvc\Controller\AbstractController;
use Zend\Mvc\MvcEvent;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Renderer\PhpRenderer;

class Module extends AbstractModule
{
    public function init(ModuleManager $moduleManager)
    {
        require_once __DIR__ . '/vendor/autoload.php';
        $events = $moduleManager->getEventManager();
        $events->attach(ModuleEvent::EVENT_MERGE_CONFIG, [$this, 'onMergeConfig']);
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function onMergeConfig(ModuleEvent $event)
    {
        // The service Omeka\AuthenticationService should be managed by this
        // module, not by GuestUser or Saml.
        $configListener = $event->getConfigListener();
        $config = $configListener->getMergedConfig(false);
        $config['service_manager']['factories']['Omeka\AuthenticationService'] =
            Service\AuthenticationServiceFactory::class;
        $configListener->setMergedConfig($config);
    }

    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);
        $this->addAclRules();
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $connection = $serviceLocator->get('Omeka\Connection');
        $settings = $serviceLocator->get('Omeka\Settings');
        $cli = $serviceLocator->get('Omeka\Cli');
        $config = require __DIR__ . '/config/module.config.php';

        if (!extension_loaded('openssl') && !extension_loaded('mcrypt')) {
            throw new ModuleCannotInstallException(
                'One of the php extension "openssl" or "mcrypt" are required to use this module.'); // @translate
        }

        $requiredModules = [
            'GuestUser',
            'MediaQuality',
        ];
        foreach ($requiredModules as $requiredModule) {
            $this->checkModule($requiredModule, $serviceLocator);
        }

        $this->createMainKey($serviceLocator);

        $vocabulary = [
            'vocabulary' => [
                'o:namespace_uri' => 'http://localhost/ontology/download#',
                'o:prefix' => 'download',
                'o:label' => 'Download Manager module for Omeka S', // @translate
                'o:comment' => 'Add some properties to manage downloads on Omeka S.', // @translate
            ],
            'strategy' => 'file',
            'file' => 'downloadmanager_module.ttl',
            'format' => 'turtle',
        ];
        $this->createVocabulary($vocabulary, $serviceLocator);

        $sql = <<<'SQL'
CREATE TABLE credential (
    api_key_id VARCHAR(32) NOT NULL,
    credential VARCHAR(190) NOT NULL,
    PRIMARY KEY(api_key_id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
CREATE TABLE download_log (
    id INT NOT NULL,
    status VARCHAR(190) NOT NULL,
    resource_id INT NOT NULL,
    owner_id INT NOT NULL,
    expire DATETIME DEFAULT NULL,
    log LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json_array)',
    hash VARCHAR(64) DEFAULT NULL,
    hash_password VARCHAR(64) DEFAULT NULL,
    salt VARCHAR(64) DEFAULT NULL,
    created DATETIME NOT NULL,
    modified DATETIME DEFAULT NULL,
    UNIQUE INDEX UNIQ_A5291D98BF396750 (id),
    INDEX IDX_A5291D9889329D25 (resource_id),
    INDEX IDX_A5291D987E3C61F9 (owner_id),
    INDEX IDX_A5291D982CB14AD4 (expire),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
CREATE TABLE download (
    id INT AUTO_INCREMENT NOT NULL,
    resource_id INT NOT NULL,
    owner_id INT NOT NULL,
    status VARCHAR(190) NOT NULL,
    expire DATETIME DEFAULT NULL,
    log LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json_array)',
    hash VARCHAR(64) DEFAULT NULL,
    hash_password VARCHAR(64) DEFAULT NULL,
    salt VARCHAR(64) DEFAULT NULL,
    created DATETIME NOT NULL,
    modified DATETIME DEFAULT NULL,
    INDEX IDX_781A82707B00651C (status),
    INDEX IDX_781A827089329D25 (resource_id),
    INDEX IDX_781A82707E3C61F9 (owner_id),
    INDEX IDX_781A82702CB14AD4 (expire),
    INDEX IDX_781A8270D1B862B8 (hash),
    INDEX resource_owner (resource_id,
    owner_id),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
ALTER TABLE credential ADD CONSTRAINT FK_57F1D4B8BE312B3 FOREIGN KEY (api_key_id) REFERENCES api_key (id) ON DELETE CASCADE;
ALTER TABLE download ADD CONSTRAINT FK_781A827089329D25 FOREIGN KEY (resource_id) REFERENCES resource (id) ON DELETE CASCADE;
ALTER TABLE download ADD CONSTRAINT FK_781A82707E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id) ON DELETE CASCADE;
SQL;
        $connection = $serviceLocator->get('Omeka\Connection');
        $sqls = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($sqls as $sql) {
            $connection->exec($sql);
        }

        foreach ($config[strtolower(__NAMESPACE__)]['config'] as $name => $value) {
            switch ($name) {
                case 'downloadmanager_credential_key_path':
                    if (strpos($value, DIRECTORY_SEPARATOR) !== 0) {
                        $value = OMEKA_PATH . DIRECTORY_SEPARATOR . $value;
                    }
                    // The realpath may fail on some configuration.
                    // $value = realpath($value);
                    break;
                case 'downloadmanager_signer_path':
                case 'downloadmanager_encrypter_path':
                    if ($value) {
                        $command = strpos('/', $value) === false
                            ? $cli->getCommandPath($value)
                            : $cli->validateCommand($value);
                        if (empty($command)) {
                            $message = new Message('The tool "%s" is not available.', $value); // @translate
                            $messenger = new Messenger;
                            $messenger->addWarning($message);
                            $value = '';
                        }
                    }
                    break;
            }
            $settings->set($name, $value);
        }
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $conn = $serviceLocator->get('Omeka\Connection');
        $settings = $serviceLocator->get('Omeka\Settings');
        $config = require __DIR__ . '/config/module.config.php';

        $sql = <<<'SQL'
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS access_key;
DROP TABLE IF EXISTS download;
DROP TABLE IF EXISTS download_log;
DROP TABLE IF EXISTS credential;
SET FOREIGN_KEY_CHECKS = 1;
SQL;
        $conn->exec($sql);

        if (!empty($_POST['remove-vocabulary'])) {
            $prefix = 'download';
            $this->removeVocabulary($prefix, $serviceLocator);
        }

        $keys = array_keys($config[strtolower(__NAMESPACE__)]['config']);
        foreach ($keys as $name) {
            $settings->delete($name);
        }
    }

    public function warnUninstall(Event $event)
    {
        $view = $event->getTarget();
        $module = $view->vars()->module;
        if ($module->getId() != __NAMESPACE__) {
            return;
        }

        $serviceLocator = $this->getServiceLocator();
        $t = $serviceLocator->get('MvcTranslator');

        $vocabularyLabel = 'Download Manager';

        $html = '<p>';
        $html .= '<strong>';
        $html .= $t->translate('WARNING'); // @translate
        $html .= '</strong>' . ': ';
        $message = $t->translate('If checked, the values of the properties "download:totalExemplars" and "download:samplePages" will be removed.'); // @translate
        $message = ' ' . $t->translate('The list of downloads and the history log will be removed anyway.'); // @translate
        $message = ' ' . $t->translate('The list of credentials keys will be removed too.'); // @translate
        $html .= $message;
        $html .= '</p>';
        $html .= '<label><input name="remove-vocabulary" type="checkbox" form="confirmform">';
        $html .= sprintf($t->translate('Remove the vocabulary "%s"'), $vocabularyLabel); // @translate
        $html .= '</label>';

        echo $html;
    }

    public function upgrade($oldVersion, $newVersion, ServiceLocatorInterface $serviceLocator)
    {
        require_once 'data/scripts/upgrade.php';
    }

    /**
     * Add ACL rules for this module.
     */
    protected function addAclRules()
    {
        $services = $this->getServiceLocator();
        $acl = $services->get('Omeka\Acl');

        // TODO Set visitors rights via config in the case there are no free resources.
        // $controllerRights = ['files'];
        // $acl->allow(null, 'DownloadManager\Controller\Files', $controllerRights);

        // Only identified users can download (there are no free resources).
        // TODO Clean rights (remove entity and adapter rights for visitors?).
        // The visitors can access the status of the downloads.
        $acl->allow(null,
            [
                \DownloadManager\Entity\Download::class,
                \DownloadManager\Entity\DownloadLog::class,
            ],
            [
                'read',
            ]
        );
        $acl->allow(null,
            [
                \DownloadManager\Api\Adapter\DownloadAdapter::class,
                \DownloadManager\Api\Adapter\DownloadLogAdapter::class,
            ],
            [
                'search',
                'read',
            ]
        );

        // The visitors can access to the Download controller via the keys
        // identity/credential, so some actions need to be available by default.
        $controllerRights = [
            'user-key', 'server-key', 'sites',
            'show', 'browse', 'files', 'item',
            'status', 'hold', 'release', 'extend',
            'read-terms', 'accept-terms',
            'version',
        ];
        $acl->allow(
            null,
            [
                \DownloadManager\Controller\Site\DownloadController::class,
            ],
            $controllerRights
        );

        $roles = $acl->getRoles();
        $adminRoles = [
            \Omeka\Permissions\Acl::ROLE_GLOBAL_ADMIN,
            \Omeka\Permissions\Acl::ROLE_SITE_ADMIN,
        ];
        $standardRoles = array_diff($roles, $adminRoles);

        // Guest and standard users can access only own resources.
        // Nevertheless, they should be able to update other ones, because some
        // operation may impact other downloads, for example when a download is
        // released, the other downloaders are notified and this is logged.
        $entityRights = ['create', 'update'];
        $entityRightsOwn = ['read', 'delete'];
        $adapterRights = ['create', 'search', 'read', 'update', 'delete'];
        $adapterLogRights = ['search', 'read', 'create'];
        $controllerRights = [
            'user-key', 'server-key', 'sites',
            'show', 'browse', 'files', 'item',
            'status', 'hold', 'release', 'extend',
            'read-terms', 'accept-terms',
            'version',
        ];
        $acl->allow(
            $standardRoles,
            [
                \DownloadManager\Entity\Download::class,
                \DownloadManager\Entity\DownloadLog::class,
            ],
            $entityRights
        );
        $acl->allow(
            $standardRoles,
            [
                \DownloadManager\Entity\Download::class,
                \DownloadManager\Entity\DownloadLog::class,
            ],
            $entityRightsOwn,
            new OwnsEntityAssertion
        );
        $acl->allow(
            $standardRoles,
            [
                \DownloadManager\Api\Adapter\DownloadAdapter::class,
            ],
            $adapterRights
        );
        $acl->allow(
            $standardRoles,
            [
                \DownloadManager\Api\Adapter\DownloadLogAdapter::class,
            ],
            $adapterLogRights
        );
        $acl->allow(
            $standardRoles,
            [
                \DownloadManager\Controller\Site\DownloadController::class,
            ],
            $controllerRights
        );

        // Admins access to anything.
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $serviceLocator = $this->getServiceLocator();
        $settings = $serviceLocator->get('Omeka\Settings');

        // Add the Download Manager term definition.
        $sharedEventManager->attach(
            '*',
            'api.context',
            function (Event $event) {
                $context = $event->getParam('context');
                $context['o-module-access'] = 'http://omeka.org/s/vocabs/module/download-manager#';
                $event->setParam('context', $context);
            }
        );

        // Forbid visitor to read media (protect media, not thumbnails).
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\MediaAdapter::class,
            'api.read.pre',
            [$this, 'filterMediaForVisitors']
        );

        // Add the download part to the resource representation.
        $sharedEventManager->attach(
            \Omeka\Api\Representation\ItemRepresentation::class,
            'rep.resource.json',
            [$this, 'filterResourceJsonLd']
        );
        // $sharedEventManager->attach(
        //     \Omeka\Api\Representation\ItemSetRepresentation::class,
        //     'rep.resource.json',
        //     [$this, 'filterResourceJsonLd']
        // );
        // $sharedEventManager->attach(
        //     \Omeka\Api\Representation\MediaRepresentation::class,
        //     'rep.resource.json',
        //     [$this, 'filterResourceJsonLd']
        // );

        // TODO Remove this useless handle. Or limit it to delete.
        // // Handle hydration after hydration of resource.
        // $sharedEventManager->attach(
        //     \Omeka\Api\Adapter\ItemAdapter::class,
        //     'api.hydrate.post',
        //     [$this, 'handleDownload']
        // );

        // Simplify the search of top picks (a collection).
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.search.pre',
            [$this, 'apiSearchPre']
        );

        // Prepare downloads when a user or a media is created.
        // TODO Remove when all users will have a unique password.
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\UserAdapter::class,
            'api.create.post',
            [$this, 'handleUserCreatePost']
        );
        // Disabled: Since 3.4.3, Downloads are set only when item is displayed.
        /*
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.create.post',
            [$this, 'handleItemSavePost']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.update.post',
            [$this, 'handleItemSavePost']
        );
        */

        // Display a warn before uninstalling.
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Module',
            'view.details',
            [$this, 'warnUninstall']
        );

        // Add the show downloads and holdings to the user show admin pages.
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\User',
            'view.details',
            [$this, 'viewUserDetails']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\User',
            'view.show.after',
            [$this, 'viewUserShowAfter']
        );
/*
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Subject',
            'view.show.after',
            [$this, 'viewSubjectShowAfter']
        );
*/

        // Add the show downloads and holdings to the user show admin pages.
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.details',
            [$this, 'viewResourceDetails']
        );
        // $sharedEventManager->attach(
        //     'Omeka\Controller\Admin\ItemSet',
        //     'view.details',
        //     [$this, 'viewResourceDetails']
        // );
        // $sharedEventManager->attach(
        //     'Omeka\Controller\Admin\Media',
        //     'view.details',
        //     [$this, 'viewResourceDetails']
        // );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.show.section_nav',
            [$this, 'viewResourceSectionNav']
        );
        // $sharedEventManager->attach(
        //     'Omeka\Controller\Admin\ItemSet',
        //     'view.show.section_nav',
        //     [$this, 'viewResourceShowAfter']
        // );
        // $sharedEventManager->attach(
        //     'Omeka\Controller\Admin\Media',
        //     'view.show.section_nav',
        //     [$this, 'viewResourceShowAfter']
        // );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.show.after',
            [$this, 'viewResourceShowAfter']
        );
        // $sharedEventManager->attach(
        //     'Omeka\Controller\Admin\ItemSet',
        //     'view.show.after',
        //     [$this, 'viewResourceShowAfter']
        // );
        // $sharedEventManager->attach(
        //     'Omeka\Controller\Admin\Media',
        //     'view.show.after',
        //     [$this, 'viewResourceShowAfter']
        // );

        if ($settings->get('downloadmanager_show_availability')) {
            // Add the download form to the resource show public pages.
            $sharedEventManager->attach(
                'Omeka\Controller\Site\Item',
                'view.show.after',
                [$this, 'displayDownloadForm']
            );
        }

        $sharedEventManager->attach(
            \GuestUser\Controller\Site\GuestUserController::class,
            'guestuser.widgets',
            [$this, 'guestUserWidgets']
        );

        $sharedEventManager->attach(
            '*',
            'user.login',
            [$this, 'handleUserLogin']
        );
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $config = $services->get('Config');
        $formElementManager = $services->get('FormElementManager');

        $data = [];
        foreach ($config[strtolower(__NAMESPACE__)]['config'] as $name => $value) {
            $data[$name] = $settings->get($name, $value);
        }

        $form = $formElementManager->get(ConfigForm::class);
        $form->init();
        $form->setData($data);

        return $renderer->formCollection($form, false);
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $config = $services->get('Config');
        $formElementManager = $services->get('FormElementManager');
        $params = $controller->params()->fromPost();

        $form = $formElementManager->get(ConfigForm::class);
        $form->init();
        $form->setData($params);
        if (!$form->isValid()) {
            $controller->messenger()->addErrors($form->getMessages());
            return false;
        }

        $messenger = new Messenger;
        $params = $form->getData();

        $space = strtolower(__NAMESPACE__);
        $defaultSettings = $config[$space]['config'];
        $params = array_intersect_key($params, $defaultSettings);

        // Add a specific url to be able to manage url via console.
        $urlHelper = $services->get('ViewHelperManager')->get('Url');
        $serverUrl = $urlHelper('top', [], ['force_canonical' => true]);
        $params['downloadmanager_server_url'] = rtrim($serverUrl, '/');

        foreach ($params as $name => $value) {
            // TODO Move these checks in validation of the form.
            switch ($name) {
                case 'downloadmanager_credential_key_path':
                    $existingPath = $settings->get($name);
                    $newPath = strpos($value, DIRECTORY_SEPARATOR) === 0
                        ? $value
                        : OMEKA_PATH . DIRECTORY_SEPARATOR . $value;
                    // The realpath may fail on some configuration.
                    // $value = $newPath = realpath($newPath);
                    $value = $newPath;
                    // Move the file if possible.
                    if ($existingPath !== $value) {
                        if (file_exists($newPath)) {
                            $value = $existingPath;
                            $message = new Message(
                                'There is a file in the provided path (%s), so the current file can’t be moved.', $newPath); // @translate
                            $messenger->addWarning($message);
                        }
                        // Move the file.
                        else {
                            if (!is_writeable(dirname($newPath))) {
                                $value = $existingPath;
                                $message = new Message(
                                    'Unable to move the credential main key in the directory %s.', $newPath); // @translate
                                $messenger->addWarning($message);
                            } elseif (!touch($newPath)) {
                                $value = $existingPath;
                                $message = new Message(
                                    'Unable to move the file "%s" for the credential main key.', $newPath); // @translate
                                $messenger->addWarning($message);
                            } else {
                                $result = copy($existingPath, $newPath);
                                if (empty($result)) {
                                    $value = $existingPath;
                                    $message = new Message(
                                        'The credential main key (%s) cannot be moved.', $newPath); // @translate
                                    $messenger->addWarning($message);
                                } else {
                                    @chmod($existingPath, 0660);
                                    $result = unlink($existingPath);
                                    if (empty($result)) {
                                        $message = new Message(
                                            'The credential main key was copied to the new path, but cannot be removed from the old one, so remove it yourself (%s).', // @translate
                                            $existingPath);
                                        $messenger->addWarning($message);
                                    }
                                    @chmod($newPath, 0400);
                                }
                            }
                        }
                    }
                    break;

                case 'downloadmanager_signer_path':
                case 'downloadmanager_encrypter_path':
                    if ($value) {
                        $cli = $this->getServiceLocator()->get('Omeka\Cli');
                        $command = strpos('/', $value) === false
                            ? $cli->getCommandPath($value)
                            : $cli->validateCommand($value);
                        if (empty($command)) {
                            $message = new Message(
                                'The tool "%s" is not available.', $value); // @translate
                            $messenger->addWarning($message);
                            $value = '';
                        }
                    }
                    break;

                case 'downloadmanager_sign_image_path':
                    if (!file_exists($value) || !is_readable($value)) {
                        $value = '';
                    }
                    break;

                case 'downloadmanager_pdf_permissions':
                    $value = implode(',', array_filter(array_map('trim', explode(' ', str_replace(',', ' ', $value)))));
                    break;
            }
            $settings->set($name, $value);
        }
    }

    /**
     * Add the download data to the resource JSON-LD.
     *
     * @param Event $event
     */
    public function filterResourceJsonLd(Event $event)
    {
        $services = $this->getServiceLocator();
        $controllerPlugins = $services->get('ControllerPluginManager');

        /** @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource */
        $resource = $event->getTarget();
        $primaryOriginal = $controllerPlugins->get('primaryOriginal');
        /** @var \Omeka\Api\Representation\MediaRepresentation $media */
        $media = $primaryOriginal($resource, false);
        $hasMedia = !empty($media);
        $jsonLd = $event->getParam('jsonLd');
        if ($hasMedia) {
            $urlHelper = $services->get('ViewHelperManager')->get('url');
            $totalAvailable = $controllerPlugins->get('totalAvailable');
            $totalExemplars = $controllerPlugins->get('totalExemplars');
            $isAdminRequest = $this->isAdminRequest();
            $isExternalWithoutFile = ($media->ingester() === 'external' && !$media->hasOriginal())
                || $media->renderer() === 'ebsco';

            // TODO If needed, add the download only for admins and current user.
            $data = [];
            $data['available'] = $totalAvailable($resource);
            if ($isAdminRequest) {
                $data['exemplars'] = $totalExemplars($resource);
                $data['downloaded'] = $this->totalDownloaded($resource);
                $data['holdings'] = $this->totalHoldings($resource);
                $data['past-downloaded'] = $this->totalPastDownloaded($resource);
            }
            $jsonLd['o-module-access:stats'] = $data;

            $data = [];
            if ($media->hasThumbnails()) {
                $data['thumbnail_urls'] = $media->thumbnailUrls();
            } else {
                // The current external app (v28) should have the data as
                // object, not empty array.
                $data['thumbnail_urls'] = (object) [];
            }
            // // TODO To be removed in some days.
            // $data['o:thumbnail_urls'] = $data['thumbnail_urls'];
            $jsonLd['o-module-access:links'] = $data;

            // The authentication service is different for api and web pages.
            $urlItem = null;
            $authenticationService = $services->get('Omeka\AuthenticationService');
            $hasIdentity = $authenticationService->hasIdentity();
            if ($hasIdentity) {
                $user = $authenticationService->getIdentity();
                $checkDownloadStatus = $controllerPlugins->get('checkDownloadStatus');
                $downloadStatus = $checkDownloadStatus($resource, $user);
                $jsonLd['o-module-access:download'] = $downloadStatus;
                if (!empty($downloadStatus['url'])) {
                    $urlItem = $jsonLd['o-module-access:download']['url'];
                }
            }

            // Add the file size of media of the primary media.
            $viewHelpers = $services->get('ViewHelperManager');
            $mediaQualitiesHelper = $viewHelpers->get('mediaQualities');
            $mediaQualities = $mediaQualitiesHelper($media);
            if ($mediaQualities) {
                foreach ($mediaQualities as $mediaQuality) {
                    $quality = $mediaQuality['quality'];
                    $jsonLd['o-module-access:media'][$quality]['filesize'] = $mediaQuality['filesize'];
                    if ($hasIdentity && $urlItem) {
                        $jsonLd['o-module-access:media'][$quality]['url'] = $quality === 'original'
                            ? $urlItem
                            : ($urlItem . '?quality=' . $quality);
                    } else {
                        $jsonLd['o-module-access:media'][$quality]['url'] = $mediaQuality['url'];
                    }
                }
            } else {
                if ($isExternalWithoutFile) {
                    $jsonLd['o-module-access:media']['original']['filesize'] = 0;
                    $jsonLd['o-module-access:media']['original']['url'] = '';
                } else {
                    $mediaQuality = $mediaQualitiesHelper($media, 'original');
                    if (empty($mediaQuality['filesize'])) {
                        $jsonLd['o-module-access:media']['original']['filesize'] = 0;
                        $jsonLd['o-module-access:media']['original']['url'] = '';
                    } else {
                        $jsonLd['o-module-access:media']['original']['filesize'] = $mediaQuality['filesize'];
                        $jsonLd['o-module-access:media']['original']['url'] = $mediaQuality['url'];
                    }
                }
            }
            // // TODO To be removed in some days.
            // if (!empty($jsonLd['o-module-access:media']['original']['filesize'])) {
            //     $jsonLd['o-module-access:media']['main']['filesize'] = $jsonLd['o-module-access:media']['original']['filesize'];
            // }

            $samplePages = $this->getSamplePages($resource);
            if ($samplePages && !empty($jsonLd['o-module-access:media'])) {
                $qualities = array_keys($jsonLd['o-module-access:media']);
                foreach ($qualities as $quality) {
                    // TODO To be removed in some days.
                    if ($quality === 'main') {
                        continue;
                    }
                    if ($hasIdentity && $urlItem) {
                        $jsonLd['o-module-access:links']['samples'][$quality] = $urlItem
                            . '?quality=' . $quality . '&sample=1';
                    } else {
                        $jsonLd['o-module-access:links']['samples'][$quality] = $urlHelper(
                            'download-file',
                            ['type' => $quality, 'filename' => $media->filename()],
                            ['query' => ['sample' => 1], 'force_canonical' => true]
                        );
                    }
                }
            }
        } else {
            $jsonLd['o-module-access:download'] = [
                'available' => false,
                'status' => 'error',
                'message' => 'The item has no file to download.', // @translate
            ];
        }

        $event->setParam('jsonLd', $jsonLd);
    }

    /**
     * Helper to filter search queries before process.
     *
     * @param Event $event
     */
    public function apiSearchPre(Event $event)
    {
        $request = $event->getParam('request');
        $query = $request->getContent();
        if (!empty($query['top-pick'])) {
            $query = $this->apiSearchPreQuery($query, 'downloadmanager_item_set_top_pick');
        }
        if (!empty($query['trending'])) {
            $query = $this->apiSearchPreQuery($query, 'downloadmanager_item_set_trending');
        }
        $request->setContent($query);
    }

    protected function apiSearchPreQuery(array $query, $itemSetKey)
    {
        $itemSetId = $this->getServiceLocator()->get('Omeka\Settings')
            ->get($itemSetKey);
        if ($itemSetId) {
            if (empty($query['item_set_id'])) {
                $query['item_set_id'] = [];
            } elseif (!is_array($query['item_set_id'])) {
                $query['item_set_id'] = [$query['item_set_id']];
            }
            $query['item_set_id'][] = $itemSetId;
        }
        return $query;
    }

    public function filterMediaForVisitors(Event $event)
    {
        $services = $this->getServiceLocator();
        if (!$services->get('Omeka\AuthenticationService')->hasIdentity()) {
            // throw new \Omeka\Api\Exception\PermissionDeniedException('Unauthorized access. Please login.'); // @translate
            $this->redirectToLogin();
        }
    }

    public function viewResourceSectionNav(Event $event)
    {
        $sectionNav = $event->getParam('section_nav');
        $sectionNav['download-manager'] = 'Downloads'; // @translate
        $event->setParam('section_nav', $sectionNav);
    }

    public function viewUserDetails(Event $event)
    {
        $view = $event->getTarget();
        $user = $view->resource;
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $args = [
            'owner_id' => $user->id(),
            'status' => [Download::STATUS_HELD, Download::STATUS_DOWNLOADED],
            'sort_by' => 'expire',
            'sort_order' => 'desc',
        ];
        $downloads = $api->search('downloads', $args)->getContent();
        $partial = 'common/admin/downloads-user';
        echo $view->partial(
            $partial,
            [
                'user' => $user,
                'downloads' => $downloads,
            ]
        );
    }

    public function viewUserShowAfter(Event $event)
    {
        $view = $event->getTarget();
        $user = $view->vars()->user;
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');

        $args = [
            'owner_id' => $user->id(),
            'status' => [Download::STATUS_HELD, Download::STATUS_DOWNLOADED],
            'sort_by' => 'expire',
            'sort_order' => 'desc',
        ];

        $downloads = $api->search('downloads', $args)->getContent();
        $partial = 'common/admin/downloads-user-list';
        echo $view->partial(
            $partial,
            [
                'user' => $user,
                'downloads' => $downloads,
                'label' => $view->translate('Holdings and downloads'), // @translate
            ]
        );

        $args['status'] = [Download::STATUS_PAST];
        $downloads = $api->search('downloads', $args)->getContent();
        echo $view->partial(
            $partial,
            [
                'user' => $user,
                'downloads' => $downloads,
                'label' => $view->translate('Past downloads'), // @translate
            ]
        );
    }

    public function viewSubjectShowAfter(Event $event)
    {
        $view = $event->getTarget();
        // $values = $view->vars()->values;

    }

    public function viewResourceDetails(Event $event)
    {
        $resource = $event->getTarget()->resource;
        $this->displayViewResourceDownloads($event, $resource);
    }

    public function viewResourceShowAfter(Event $event)
    {
        $resource = $event->getTarget()->vars()->resource;
        $this->displayViewResourceDownloads($event, $resource);
    }

    protected function displayViewResourceDownloads(Event $event, AbstractResourceRepresentation $resource)
    {
        $json = $resource->jsonSerialize();
        // Check if there is a media.
        if (!isset($json['o-module-access:stats'])) {
            return;
        }
        $stats = $json['o-module-access:stats'];
        $holdingRanks = $this->getServiceLocator()->get('ControllerPluginManager')->get('holdingRanksItem');
        $holdingRanks = $holdingRanks($resource, 25);
        $isViewDetails = $event->getName() == 'view.details';
        $partial = $isViewDetails
            ? 'common/admin/downloads-resource'
            : 'common/admin/downloads-resource-list';
        echo $event->getTarget()->partial(
            $partial,
            [
                'resource' => $resource,
                'stats' => $stats,
                'holdingRanks' => $holdingRanks,
            ]
        );
    }

    /**
     * Display the download form on public site.
     *
     * @param Event $event
     */
    public function displayDownloadForm(Event $event)
    {
        $view = $event->getTarget();
        $resource = $event->getTarget()->resource;
        echo $view->showAvailability($resource);
    }

    public function guestUserWidgets(Event $event)
    {
        $widgets = $event->getParam('widgets');
        $viewHelpers = $this->getServiceLocator()->get('ViewHelperManager');
        $translate = $viewHelpers->get('translate');
        $partial = $viewHelpers->get('partial');

        $widget = [];
        $widget['label'] = $translate('Holdings'); // @translate
        $widget['content'] = $partial('common/guest-user-holdings');
        $widgets['access'] = $widget;

        $event->setParam('widgets', $widgets);
    }

    /**
     * Handle hydration for user data after hydration of an entity.
     *
     * Prepare the downloads for a user.
     * @todo Remove the preparation when all users will have the same keys.
     *
     * @param Event $event
     */
    public function handleUserCreatePost(Event $event)
    {
        $response = $event->getParam('response');
        $user = $response->getContent();
        $services = $this->getServiceLocator();
        // Prepare api keys.
        $plugins = $services->get('ControllerPluginManager');
        $userApiKeys = $plugins->get('userApiKeys');
        $userApiKeys($user);
        // Prepare downloads.
        /*
        // Since 3.4.3, Downloads are set only when item is displayed.
        $jobDispatcher = $services->get('Omeka\Job\Dispatcher');
        $jobArgs = ['user_id' => $user->getId()];
        $job = $jobDispatcher->dispatch(Job\PrepareUserDownloads::class, $jobArgs);
        */
    }

    /**
     * Handle hydration for item data after hydration of an entity.
     *
     * Prepare the downloads for all users.
     *
     * @todo Remove the preparation when all users will have the same keys.
     * Disabled: Since 3.4.3, Downloads are set only when item is displayed.
     *
     * @param Event $event
     */
    public function handleItemSavePost(Event $event)
    {
        $item = $event->getParam('response')->getContent();
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $itemRepresentation = $api->read('items', $item->getId())->getContent();
        $media = $itemRepresentation->primaryMedia();
        if ($media) {
            $this->afterSaveMedia($media);
        }
    }

    /**
     * Check if the user has keys.
     *
     * @param Event $event
     */
    public function handleUserLogin(Event $event)
    {
        $user = $event->getTarget();
        $services = $this->getServiceLocator();
        // Check api keys.
        $userApiKeys = $services->get('ControllerPluginManager')->get('userApiKeys');
        $userApiKeys($user);
    }

    /**
     * Handle hydration for item data after hydration of an entity.
     *
     * Prepare the downloads for all users.
     *
     * @todo Remove the preparation when all users will have the same keys.
     * Disabled: Since 3.4.3, Downloads are set only when item is displayed.
     *
     * @param MediaRepresentation $media
     */
    protected function afterSaveMedia(MediaRepresentation $media)
    {
        // $services = $this->getServiceLocator();
        // $jobDispatcher = $services->get('Omeka\Job\Dispatcher');
        // $jobArgs = ['media_id' => $media->id()];
        // TODO Fix creation of for large upload.
        // $job = $jobDispatcher->dispatch(Job\PrepareMediaDownloads::class, $jobArgs);
    }

    // TODO Create a view helper or a controller plugin for next methods (see controller).

    protected function totalHoldings($resource)
    {
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $total = $api->search('downloads', [
            'resource_id' => $resource->id(),
            'status' => Download::STATUS_HELD,
        ])->getTotalResults();
        return $total;
    }

    protected function totalDownloaded($resource)
    {
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $total = $api->search('downloads', [
            'resource_id' => $resource->id(),
            'status' => Download::STATUS_DOWNLOADED,
        ])->getTotalResults();
        return $total;
    }

    protected function totalPastDownloaded($resource)
    {
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $total = $api->search('downloads', [
            'resource_id' => $resource->id(),
            'status' => [Download::STATUS_PAST],
        ])->getTotalResults();
        $total += $api->search('download_logs', [
            'resource_id' => $resource->id(),
            'status' => [Download::STATUS_DOWNLOADED, Download::STATUS_PAST],
        ])->getTotalResults();
        return $total;
    }

    protected function getSamplePages(AbstractResourceRepresentation $resource)
    {
        $result = trim($resource->value('download:samplePages', ['type' => 'literal', 'all' => false]));
        return $result;
    }

    protected function createMainKey(ServiceLocatorInterface $services)
    {
        $settings = $services->get('Omeka\Settings');
        $config = require __DIR__ . '/config/module.config.php';

        $filepath = $settings->get('downloadmanager_credential_key_path',
            $config[strtolower(__NAMESPACE__)]['config']['downloadmanager_credential_key_path']);
        if (strpos($filepath, DIRECTORY_SEPARATOR) !== 0) {
            $filepath = OMEKA_PATH . DIRECTORY_SEPARATOR . $filepath;
        }
        // The realpath may fail on some configuration.
        // $filepath = realpath($filepath);

        $keySize = 1024;
        $charlist = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
        $key = \Zend\Math\Rand::getString($keySize, $charlist, true);
        $keyContent = "<?php return '$key';";
        $filesize = strlen($keyContent);

        if (!file_exists($filepath)) {
            if (!is_writeable(dirname($filepath))) {
                throw new ModuleCannotInstallException(
                    'Unable to create the credential main key in the files directory.'); // @translate
            }
            if (!touch($filepath)) {
                throw new ModuleCannotInstallException(
                    'Unable to create the credential main key.'); // @translate
            }
            $result = file_put_contents($filepath, $keyContent);
            if ($result !== $filesize) {
                throw new ModuleCannotInstallException(new Message(
                    'The credential main key (%s) cannot be created.', $filepath)); // @translate
            }
            @chmod($filepath, 0400);
        } elseif (filesize($filepath) == 0) {
            throw new ModuleCannotInstallException(new Message(
                'The credential main key (%s) is empty.', $filepath)); // @translate
        } elseif (filesize($filepath) !== $filesize) {
            throw new ModuleCannotInstallException(new Message(
                'The credential main key (%s) has not a standard size.', $filepath)); // @translate
        } elseif (!is_readable($filepath)) {
            throw new ModuleCannotInstallException(new Message(
                'The credential main key (%s) is not readable.', $filepath)); // @translate
        }
    }

    /**
     * Check if a module is enabled.
     *
     * @param string $requiredModule
     * @param ServiceLocatorInterface $serviceLocator
     * @throws ModuleCannotInstallException
     * @return bool
     */
    protected function checkModule($requiredModule, ServiceLocatorInterface $serviceLocator)
    {
        $moduleManager = $serviceLocator->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule($requiredModule);
        if (!$module || $module->getState() !== \Omeka\Module\Manager::STATE_ACTIVE) {
            throw new ModuleCannotInstallException(
                new Message('The module "%s" is required.', $requiredModule)); // @translate
        }
        return true;
    }

    /**
     * Create a vocabulary, with a check of its existence before.
     *
     * @param array $vocabulary
     * @param ServiceLocatorInterface $serviceLocator
     * @throws ModuleCannotInstallException
     * @return bool True if the vocabulary has been created, false if it
     * exists already, so it is not created twice.
     */
    protected function createVocabulary(array $vocabulary, ServiceLocatorInterface $serviceLocator)
    {
        $api = $serviceLocator->get('Omeka\ApiManager');

        // Check if the vocabulary have been already imported.
        $prefix = $vocabulary['vocabulary']['o:prefix'];

        try {
            /** @var \Omeka\Api\Representation\VocabularyRepresentation $vocabularyRepresentation */
            $vocabularyRepresentation = $api
                ->read('vocabularies', ['prefix' => $prefix])->getContent();
        } catch (NotFoundException $e) {
            $vocabularyRepresentation = null;
        }

        if ($vocabularyRepresentation) {
            // Check if it is the same vocabulary.
            if ($vocabularyRepresentation->namespaceUri() === $vocabulary['vocabulary']['o:namespace_uri']) {
                $message = new Message('The vocabulary "%s" was already installed and was kept.', // @translate
                    $vocabulary['vocabulary']['o:label']);
                $messenger = new Messenger();
                $messenger->addWarning($message);
                return false;
            }

            // It is another vocabulary with the same prefix.
            throw new ModuleCannotInstallException(
                sprintf(
                    'An error occured when adding the prefix "%s": another vocabulary exists. Resolve the conflict before installing this module.', // @translate
                    $vocabulary['vocabulary']['o:prefix']
                ));
        }

        $rdfImporter = $serviceLocator->get('Omeka\RdfImporter');

        try {
            $rdfImporter->import(
                $vocabulary['strategy'],
                $vocabulary['vocabulary'],
                [
                    'file' => __DIR__ . '/data/vocabularies/' . $vocabulary['file'],
                    'format' => $vocabulary['format'],
                ]
            );
        } catch (ValidationException $e) {
            throw new ModuleCannotInstallException(
                sprintf(
                    'An error occured when adding the prefix "%s" and the associated properties: %s', // @translate
                    $vocabulary['vocabulary']['o:prefix'], $e->getMessage()
                ));
        }

        return true;
    }

    /**
     * Remove a vocabulary by its prefix.
     *
     * @param string $prefix
     * @param ServiceLocatorInterface $serviceLocator
     */
    protected function removeVocabulary($prefix, ServiceLocatorInterface $serviceLocator)
    {
        $api = $serviceLocator->get('Omeka\ApiManager');
        // The vocabulary may have been removed manually before.
        try {
            $api->delete('vocabularies', ['prefix' => $prefix])->getContent();
        } catch (NotFoundException $e) {
        }
    }

    /**
     * Check if a request is admin.
     *
     * @return bool
     */
    protected function isAdminRequest()
    {
        $routeMatch = $this->getServiceLocator()->get('Application')
            ->getMvcEvent()->getRouteMatch();
        return $routeMatch && $routeMatch->getParam('__ADMIN__');
    }

    /**
     * Redirect to login via Guest user or normal route.
     *
     * @param string $action
     * @return \Zend\Http\Response
     */
    protected function redirectToLogin()
    {
        $services = $this->getServiceLocator();
        $isModuleActive = $services->get('ViewHelperManager')->get('isModuleActive');
        $controllerPlugins = $services->get('ControllerPluginManager');
        $redirect = $controllerPlugins->get('redirect');
        if ($isModuleActive('GuestUser')) {
            $currentSite = $controllerPlugins->get('currentSite');
            $site = $currentSite();
            if ($site) {
                return $redirect->toRoute('site/guest-user',
                    ['site-slug' => $site->slug(), 'action' => 'login']
                );
            }
        }
        return $redirect->toRoute('login');
    }
}
