<?php
namespace VatiruLibrary;

use VatiruLibrary\Form\ConfigForm;
use Group\Api\Adapter\GroupAdapter;
use Group\Entity\Group;
use Group\Entity\GroupUser;
use Group\Form\Element\GroupSelect;
use Omeka\Api\Exception\NotFoundException;
use Omeka\Api\Exception\ValidationException;
use Omeka\Entity\Site;
use Omeka\Entity\User;
use Omeka\Module\AbstractModule;
use Omeka\Module\Exception\ModuleCannotInstallException;
use Omeka\Mvc\Controller\Plugin\Messenger;
use Omeka\Stdlib\Message;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Form\Fieldset;
use Zend\Mvc\Controller\AbstractController;
use Zend\Mvc\MvcEvent;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Renderer\PhpRenderer;

/**
 * VatiruLibrary
 *
 * Manages all specific points of the web application vatiru.com.
 */
class Module extends AbstractModule
{
    const VATIRU_HTTP_ACCEPT = 'application/vnd.vatiru.api+json';

    /**
     * Store the fact that a search has already been processed.
     *
     * @var bool
     */
    protected $isProcessed = false;

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);
        if (!$this->checkDependencies($event)) {
            $this->siteUnderMaintenance($event);
            return;
        }
        $this->addAclRules();
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $vocabulary = [
            'vocabulary' => [
                'o:namespace_uri' => 'https://vatiru.com/ns/vatiru/',
                'o:prefix' => 'vatiru',
                'o:label' => 'Vatiru', // @translate
                'o:comment' => 'Specific metadata for Vatiru.', // @translate
            ],
            'strategy' => 'file',
            'file' => 'vatiru.ttl',
            'format' => 'turtle',
        ];
        $this->createVocabulary($vocabulary, $serviceLocator);

        $this->manageSettings($serviceLocator->get('Omeka\Settings'), 'install');
        $this->manageSiteSettings($serviceLocator, 'install');
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        // Vatiru vocabulary is not removed.

        $this->manageSettings($serviceLocator->get('Omeka\Settings'), 'uninstall');
        $this->manageSiteSettings($serviceLocator, 'uninstall');
    }

    public function upgrade($oldVersion, $newVersion, ServiceLocatorInterface $serviceLocator)
    {
        require_once 'data/scripts/upgrade.php';
    }

    protected function manageSettings($settings, $process, $key = 'config')
    {
        $config = require __DIR__ . '/config/module.config.php';
        $defaultSettings = $config[strtolower(__NAMESPACE__)][$key];
        foreach ($defaultSettings as $name => $value) {
            switch ($process) {
                case 'install':
                    $settings->set($name, $value);
                    break;
                case 'uninstall':
                    $settings->delete($name);
                    break;
            }
        }
    }

    protected function manageSiteSettings(ServiceLocatorInterface $serviceLocator, $process)
    {
        $siteSettings = $serviceLocator->get('Omeka\Settings\Site');
        $api = $serviceLocator->get('Omeka\ApiManager');
        $sites = $api->search('sites')->getContent();
        foreach ($sites as $site) {
            $siteSettings->setTargetId($site->id());
            $this->manageSettings($siteSettings, $process, 'site_settings');
        }
    }

    /**
     * Check if all dependencies are enabled.
     *
     * @param MvcEvent $event
     * @param string $dependencyType
     * @return bool
     */
    protected function checkDependencies(MvcEvent $event, $dependencyType = 'required')
    {
        $services = $event->getApplication()->getServiceManager();
        $moduleManager = $services->get('Omeka\ModuleManager');
        $config = require __DIR__ . '/config/module.config.php';
        $dependencies = $config[strtolower(__NAMESPACE__)]['dependencies'][$dependencyType];
        foreach ($dependencies as $moduleClass) {
            $module = $moduleManager->getModule($moduleClass);
            if (empty($module) || $module->getState() !== \Omeka\Module\Manager::STATE_ACTIVE) {
                return false;
            }
        }
        return true;
    }

    /**
     * Redirect to maintenance for public pages and warn on admin pages.
     *
     * @param MvcEvent $event
     */
    protected function siteUnderMaintenance(MvcEvent $event)
    {
        $services = $event->getApplication()->getServiceManager();
        $services->get('Omeka\Settings')->set('maintenance_status', true);
    }

    /**
     * Add ACL rules for this module.
     */
    protected function addAclRules()
    {
        $services = $this->getServiceLocator();
        $acl = $services->get('Omeka\Acl');

        $acl->allow(
            null,
            [\VatiruLibrary\Controller\Site\VatiruController::class]
        );

        // Assign groups from saml or public during creation (so without user).
        // TODO These rights to assign groups should not be set.
        $groupEntityRights = ['read', 'create'];
        $adapterRights = ['search', 'read', 'create'];
        $acl->allow(null, GroupUser::class, $groupEntityRights);
        $acl->allow(null, GroupAdapter::class, $adapterRights);
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        // Add args into Omeka args, so the event should be triggered before
        // external search.
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.search.pre',
            [$this, 'handleItemApiSearchPreBefore'],
            +100
        );

        // Manage the search post to convert args into Omeka args, so the event
        // should be triggered after external search (that may manage arguments
        // differently).
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.search.pre',
            [$this, 'handleItemApiSearchPreAfter'],
            -100
        );

        $sharedEventManager->attach(
            \Search\Controller\IndexController::class,
            'search.query.pre',
            [$this, 'handleSearchQueryPre']
        );

        // Some resource data are hidden for display after modules processes.
        $sharedEventManager->attach(
            \Omeka\Api\Representation\ItemRepresentation::class,
            'rep.resource.json',
            [$this, 'filterResourceJsonLd'],
            -100
        );
        $sharedEventManager->attach(
            \Omeka\Api\Representation\MediaRepresentation::class,
            'rep.resource.json',
            [$this, 'filterResourceJsonLd'],
            -100
        );
        $sharedEventManager->attach(
            \Omeka\Api\Representation\ItemSetRepresentation::class,
            'rep.resource.json',
            [$this, 'filterResourceJsonLd'],
            -100
        );

        $sharedEventManager->attach(
            \External\Mvc\Controller\Plugin\QuickBatchCreate::class,
            'external.batch_create.post',
            [$this, 'handleExternalBatchCreatePost']
        );

        $sharedEventManager->attach(
            \Omeka\Api\Adapter\UserAdapter::class,
            'api.create.post',
            [$this, 'handleUserCreatePost']
        );

        $sharedEventManager->attach(
            \Omeka\Api\Adapter\SiteAdapter::class,
            'api.create.post',
            [$this, 'handleSiteCreatePost']
        );

        $sharedEventManager->attach(
            \Omeka\Api\Adapter\SiteAdapter::class,
            'api.update.post',
            [$this, 'handleSiteUpdatePost']
        );

        $sharedEventManager->attach(
            \Omeka\Api\Adapter\SiteAdapter::class,
            'api.delete.post',
            [$this, 'handleSiteDeletePost']
        );

        $sharedEventManager->attach(
            \Omeka\Form\SiteSettingsForm::class,
            'form.add_elements',
            [$this, 'addFormElementsSiteSettings']
        );

        $sharedEventManager->attach(
            \Omeka\Form\SiteSettingsForm::class,
            'form.add_input_filters',
            [$this, 'addSiteSettingsFilters']
        );

        $sharedEventManager->attach(
            \Intercom\Module::class,
            'intercom.settings',
            [$this, 'intercomSettings']
        );

        // Module Csv Import.
        $sharedEventManager->attach(
            \CSVImport\Form\MappingForm::class,
            'form.add_elements',
            [$this, 'addCsvImportFormElements']
        );
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $settings = $services->get('Omeka\Settings');
        $form = $services->get('FormElementManager')->get(ConfigForm::class);

        $data = [];
        $defaultSettings = $config[strtolower(__NAMESPACE__)]['config'];
        foreach ($defaultSettings as $name => $value) {
            $data[$name] = $settings->get($name, $value);
        }

        $form->init();
        $form->setData($data);
        $html = $renderer->formCollection($form);
        return $html;
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $settings = $services->get('Omeka\Settings');
        $form = $services->get('FormElementManager')->get(ConfigForm::class);

        $params = $controller->getRequest()->getPost();

        $form->init();
        $form->setData($params);
        if (!$form->isValid()) {
            $controller->messenger()->addErrors($form->getMessages());
            return false;
        }

        $params = $form->getData();
        $defaultSettings = $config[strtolower(__NAMESPACE__)]['config'];
        $params = array_intersect_key($params, $defaultSettings);
        foreach ($params as $name => $value) {
            $settings->set($name, $value);
        }
    }

    public function addFormElementsSiteSettings(Event $event)
    {
        $services = $this->getServiceLocator();
        $siteSettings = $services->get('Omeka\Settings\Site');
        $config = $services->get('Config');
        $form = $event->getTarget();

        $defaultSiteSettings = $config[strtolower(__NAMESPACE__)]['site_settings'];

        $fieldset = new Fieldset('vatiru_library');
        $fieldset->setLabel('Vatiru Library specific settings');

        $fieldset->add([
            'name' => 'vatiru_current_site_group',
            'type' => \Zend\Form\Element\Checkbox::class,
            'options' => [
                'label' => 'Add new user to the current site group', // @translate
                'info' => 'Automatically add the user to the current site group during first registration.', // @translate
            ],
            'attributes' => [
                'value' => $siteSettings->get(
                    'vatiru_current_site_group',
                    $defaultSiteSettings['vatiru_current_site_group']
                ),
            ],
        ]);

        // TODO Find why GroupSelect does not load via form->add().
        // GroupSelect does not load automatically via form->add(), so use the
        // form element manager.
        $formElementManager = $services->get('FormElementManager');
        $element = $formElementManager
            ->get(GroupSelect::class)
            ->setName('vatiru_site_groups')
            ->setOptions([
                'label' => 'Add new user to groups', // @translate
                'info' => 'Automatically add the user to the specified groups during first registration.', // @translate
                'chosen' => true,
            ])
            ->setAttributes([
                'multiple' => true,
                'value' => $siteSettings->get(
                    'vatiru_site_groups',
                    $defaultSiteSettings['vatiru_site_groups']
                ),
            ]);
        $fieldset->add($element);

        $form->add($fieldset);
    }

    public function addSiteSettingsFilters(Event $event)
    {
        $inputFilter = $event->getParam('inputFilter');
        $inputFilter->get('vatiru_library')->add([
            'name' => 'vatiru_site_groups',
            'required' => false,
        ]);
    }

    public function addCsvImportFormElements(Event $event)
    {
        /** @var \CSVImport\Form\MappingForm $form */
        $form = $event->getTarget();
        $resourceType = $form->getOption('resource_type');
        if ($resourceType !== 'sites') {
            return;
        }

        $services = $this->getServiceLocator();
        $acl = $services->get('Omeka\Acl');

        if (!$acl->userIsAllowed(Site::class, 'create')) {
            return;
        }

        if ($acl->userIsAllowed(\Omeka\Entity\Site::class, 'change-owner')) {
            $form->addOwnerElement();
        }
        $form->addProcessElements();
        $form->addAdvancedElements();
    }

    /**
     * Handle arguments of an api search of items.
     *
     * @param Event $event
     */
    public function handleItemApiSearchPreBefore(Event $event)
    {
        // Avoid processing multiple times (to get results and total of results).
        // TODO Check if the check of multiple processing of the handle is still needed.
        // static $isProcessed = false;
        // if ($isProcessed) {
        //     return;
        // }
        // $isProcessed = true;

        $services = $this->getServiceLocator();

        // TODO Find a better way to limit query to public request only.
        /** @var \Zend\Router\Http\RouteMatch $routeMatch */
        $routeMatch = $services->get('Application')->getMvcEvent()
            ->getRouteMatch();
        // TODO Find why there may be no route match (SolrIndexer.php (#133) searches items via api).
        if (empty($routeMatch)) {
            return;
        }
        $route = $routeMatch->getMatchedRouteName();
        if (strpos($route, 'admin/') === 0) {
            return;
        }

        if (!empty($GLOBALS['globalIsTest'])) {
            $executionTime = microtime(true) - $GLOBALS['globalStartTime'];
            $services->get('Omeka\Logger')->debug($executionTime . ' ' . __METHOD__ . ' #' . __LINE__);
        }

        /** @var \Omeka\Api\Request $request */
        $request = $event->getParam('request');
        $params = $request->getContent();

        $settings = $services->get('Omeka\Settings');
        if ($settings ->get('external_ebsco_disable')) {
            $property = [
                'joiner' => 'and',
                'property' => 'dcterms:type',
                'type' => 'neq',
                'text' => 'ebsco: eBook',
            ];
            $params['property'][] = $property;
        }

        // Enable the quick search via modules Search/Solr when available.
        if (!array_key_exists('index', $params)) {
            $params['index'] = '1';
        }

        // TODO Check if the site id is added via the public site.
        // If the site is already set, the limit is already working.
        // In all cases, the right of the user (group) are managed.
        $siteSlug = $routeMatch->getParam('site-slug');
        if ($siteSlug) {
            $controllerPlugins = $services->get('ControllerPluginManager');
            $currentSite = $controllerPlugins->get('currentSite');
            $site = $currentSite();
            $params['site_id'] = $site->id();
            $request->setContent($params);
            return;
        }

        // TODO The site id should be managed above, via a higher global event.
        // No site on api, so add it. Or when the user goes on another site.
        $controllerPlugins = $services->get('ControllerPluginManager');
        $siteOfCurrentUser = $controllerPlugins->get('siteOfCurrentUser');
        $site = $siteOfCurrentUser();
        $params['site_id'] = $site->getId();
        $request->setContent($params);
    }

    /**
     * Handle arguments of a search query of items.
     *
     * @param Event $event
     */
    public function handleSearchQueryPre(Event $event)
    {
        // Avoid processing multiple times (to get results and total of results).
        // TODO Check if the check of multiple processing of the handle is still needed.
        // static $isProcessed = false;
        // if ($isProcessed) {
        //     return;
        // }
        // $isProcessed = true;

        $services = $this->getServiceLocator();

        // TODO Find a better way to limit query to public request only.
        /** @var \Zend\Router\Http\RouteMatch $routeMatch */
        $routeMatch = $services->get('Application')->getMvcEvent()
            ->getRouteMatch();
        // TODO Find why there may be no route match (SolrIndexer.php (#133) searches items via api).
        if (empty($routeMatch)) {
            return;
        }
        $route = $routeMatch->getMatchedRouteName();
        if (strpos($route, 'admin/') === 0) {
            return;
        }

        if (!empty($GLOBALS['globalIsTest'])) {
            $executionTime = microtime(true) - $GLOBALS['globalStartTime'];
            $services->get('Omeka\Logger')->debug($executionTime . ' ' . __METHOD__ . ' #' . __LINE__);
        }

        $settings = $services->get('Omeka\Settings');
        if (!$settings ->get('external_ebsco_disable')) {
            return;
        }

        /** @var \Search\Api\Request $query */
        $query = $event->getParam('query');
        $query->addFilterQuery(
            'dcterms_type_t',
            'ebsco: eBook',
            'neq',
            'and'
        );

        // // Enable the quick search via modules Search/Solr when available.
        // if (!array_key_exists('index', $params)) {
        //     $params['index'] = '1';
        // }

        // // TODO Check if the site id is added via the public site.
        // // If the site is already set, the limit is already working.
        // // In all cases, the right of the user (group) are managed.
        // $siteSlug = $routeMatch->getParam('site-slug');
        // if ($siteSlug) {
        //     $controllerPlugins = $services->get('ControllerPluginManager');
        //     $currentSite = $controllerPlugins->get('currentSite');
        //     $site = $currentSite();
        //     $params['site_id'] = $site->id();
        //     $request->setContent($params);
        //     return;
        // }
    }

    /**
     * Handle arguments of a public item search in order to use Omeka args.
     *
     * The params are the one used in the polaris theme (to be normalized).
     *
     * @param Event $event
     */
    public function handleItemApiSearchPreAfter(Event $event)
    {
        // Avoid processing multiple times (to get results and total of results).
        if ($this->isProcessed) {
            return;
        }

        $this->isProcessed = true;

        // The "pubtype" is already autmatically managed via ApiController
        // (overridden) for external app.

        $services = $this->getServiceLocator();

        // TODO Find a way to limit query to public request only.
        $routeMatch = $services->get('Application')->getMvcEvent()
            ->getRouteMatch();
        // TODO Find why there may be no route match (SolrIndexer.php (#133) searches items via api).
        if (empty($routeMatch)) {
            return;
        }
        $route = $routeMatch->getMatchedRouteName();
        if (strpos($route, 'admin/') === 0) {
            return;
        }

        // Check if the external query should be processed.
        /** @var \Omeka\Api\Request $request */
        $request = $event->getParam('request');

        $params = $request->getContent();

        if (!empty($GLOBALS['globalIsTest'])) {
            $executionTime = microtime(true) - $GLOBALS['globalStartTime'];
            $services->get('Omeka\Logger')->debug($executionTime . ' ' . __METHOD__ . ' #' . __LINE__);
            $services->get('Omeka\Logger')->debug(json_encode($params, 320));
        }

        // Search by site should be enabled, because there may be site.
        // In Vatiru, there is no search by site.
        // unset($params['site_id']);
        // $request->setContent($params);

        if (!empty($params['pubtype'])) {
            switch ($params['pubtype']) {
                case 'book':
                    /*
                     $types = [
                         'bibo:Book',
                         'bibo:ReferenceSource',
                         'bibo:Chapter',
                         'bibo:BookSection',
                         'bibo:MultiVolumeBook',
                     ];
                     */
                    $property = [
                        'joiner' => 'and',
                        'property' => 'vatiru:publicationType',
                        'type' => 'eq',
                        'text' => 'book',
                    ];
                    $params['property'][] = $property;
                    // No break;
                case '':
                case 'all':
                default:
                    // Sort local book first.
                    // Because of controller plugin SetBrowseDefaults(), check Get
                    // instead of the params. See view helper PaginationDefaultOrder() too.
                    // if (!empty($params['sort_by'])) {
                    if (empty($_GET['sort_by'])) {
                        $params['sort_by'] = 'vatiru:resourcePriority';
                        $params['sort_order'] = 'asc';
                    }
                    break;

                case 'article':
                // TODO Remove "articles", that was a mistake in app version 32.
                case 'articles':
                    /*
                    $types = [
                        'bibo:Journal',
                        'bibo:AcademicArticle',
                        'bibo:Report',
                        'bibo:Proceedings',
                        'bibo:Conference',
                        'bibo:Thesis',
                        'bibo:Manuscript',
                        'bibo:Periodical',
                        'bibo:Newspaper',
                        'bibo:Article',
                        'bibo:Interview',
                        'bibo:Magazine',
                    ];
                    */
                    $property = [
                        'joiner' => 'and',
                        'property' => 'vatiru:publicationType',
                        'type' => 'eq',
                        'text' => 'article',
                    ];
                    $params['property'][] = $property;
                    break;

                case 'academic':
                    $types = [
                        'bibo:Journal',
                        'bibo:AcademicArticle',
                        'bibo:Report',
                        'bibo:Proceedings',
                        'bibo:Conference',
                        'bibo:Thesis',
                        'bibo:Manuscript',
                    ];
                    foreach ($types as $type) {
                        $property = [
                            'joiner' => 'or',
                            'property' => 'dcterms:type',
                            'type' => 'eq',
                            'text' => $type,
                        ];
                        $params['property'][] = $property;
                    }
                    break;

                case 'news':
                    $types = [
                        'bibo:Periodical',
                        'bibo:Newspaper',
                        'bibo:Article',
                        'bibo:Interview',
                        'bibo:Magazine',
                    ];
                    foreach ($types as $type) {
                        $property = [
                            'joiner' => 'or',
                            'property' => 'dcterms:type',
                            'type' => 'eq',
                            'text' => $type,
                        ];
                        $params['property'][] = $property;
                    }
                    break;
            }
        }

        $request->setContent($params);
    }

    /**
     * Filter the JSON-LD resources.
     *
     * @param Event $event
     */
    public function filterResourceJsonLd(Event $event)
    {
        $jsonLd = $event->getParam('jsonLd');
        unset($jsonLd['vatiru:sourceRepository']);
        unset($jsonLd['vatiru:externalData']);
        unset($jsonLd['vatiru:resourcePriority']);
        unset($jsonLd['vatiru:userQuery']);
        // For media.
        unset($jsonLd['o:source']);
        $event->setParam('jsonLd', $jsonLd);
    }

    public function handleExternalBatchCreatePost(Event $event)
    {
        $ids = $event->getParam('ids', []);
        if (empty($ids)) {
            return;
        }

        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');

        if (!empty($GLOBALS['globalIsTest'])) {
            $executionTime = microtime(true) - $GLOBALS['globalStartTime'];
            $services->get('Omeka\Logger')->debug($executionTime . ' ' . __METHOD__ . ' #' . __LINE__);
        }

        /** @var \Search\Api\Representation\SearchIndexRepresentation[] $searchIndexes */
        $searchIndexes = $api->search('search_indexes')->getContent();
        if (empty($searchIndexes)) {
            return;
        }

        /** @var \Doctrine\ORM\EntityManager $entityManager */
        $entityManager = $services->get('Omeka\EntityManager');
        $qb = $entityManager
            ->createQueryBuilder()
            ->select(\Omeka\Entity\Item::class)
            ->from(\Omeka\Entity\Item::class, \Omeka\Entity\Item::class)
            ->where(\Omeka\Entity\Item::class . '.id IN (:ids)')
            ->setParameter('ids', $ids);

        $resources = $qb->getQuery()->getResult();
        if (!count($resources)) {
            return;
        }

        $requestResource = 'items';
        foreach ($searchIndexes as $searchIndex) {
            $searchIndexSettings = $searchIndex->settings();
            if (in_array($requestResource, $searchIndexSettings['resources'])) {
                $indexer = $searchIndex->indexer();
                $indexer->indexResources($resources);
            }
        }
    }

    /**
     * Handle hydration for user data after hydration of an entity.
     *
     * Attach the guest user to all groups s/he belongs to (via site slugs).
     *
     * @param Event $event
     */
    public function handleUserCreatePost(Event $event)
    {
        $response = $event->getParam('response');
        $user = $response->getContent();
        if ($user->getRole() !== \GuestUser\Permissions\Acl::ROLE_GUEST) {
            return;
        }

        $services = $this->getServiceLocator();
        $entityManager = $services->get('Omeka\EntityManager');
        $controllerPlugins = $services->get('ControllerPluginManager');
        $siteSettings = $controllerPlugins->get('siteSettings');
        $config = $services->get('Config');

        // Avoid an issue when created from the admin board.
        try {

        $addCurrentSiteGroup = $siteSettings()->get(
            'vatiru_current_site_group',
            $config[strtolower(__NAMESPACE__)]['site_settings']['vatiru_current_site_group']
        );

        } catch (\Exception $e) {
            $addCurrentSiteGroup = [];
        }

        // Add the default groups.
        $settings = $services->get('Omeka\Settings');
        $defaultGroups = $settings->get('vatiru_default_groups', []);

        try {

        // Add default groups of the site.
        $defaultSiteGroups = $siteSettings()->get(
            'vatiru_site_groups',
            $config[strtolower(__NAMESPACE__)]['site_settings']['vatiru_site_groups']
        );

        } catch (\Exception $e) {
            $defaultSiteGroups = [];
        }

        try {

        // Add the current site group.
        $currentSiteGroups = $addCurrentSiteGroup ? $this->getCurrentSiteGroup() : [];

        } catch (\Exception $e) {
            $currentSiteGroups = [];
        }

        // Add groups of each site of the user.
        $siteGroups = $this->getSiteGroupsForUser($user);

        // Keep the specific groups of the user when set.
        $userGroups = $entityManager->getRepository(GroupUser::class)
            ->findBy(['user' => $user]);
        $userGroups = array_map(function ($v) {
            return $v->getGroup()->getName();
        }, $userGroups);

        // Assign the user to groups.
        $toAssign = array_unique(array_merge(
            $defaultGroups, $defaultSiteGroups, $currentSiteGroups, $siteGroups, $userGroups
        ));

        // Because this module is registered before Group (alphabetic), the
        // request is simply modified. It may avoid an issue with a double
        // application of groups too.
        $request = $response->getRequest();
        $data = $request->getContent();
        $data['o-module-group:group'] = $toAssign;
        $request->setContent($data);
    }

    /**
     * Handle hydration for site data after hydration of an entity.
     *
     * Add the group for the site.
     *
     * @param Event $event
     */
    public function handleSiteCreatePost(Event $event)
    {
        $services = $this->getServiceLocator();
        $controllerPlugins = $services->get('ControllerPluginManager');
        $entityManager = $services->get('Omeka\EntityManager');
        $acl = $services->get('Omeka\Acl');
        $api = $controllerPlugins->get('api');
        $settings = $services->get('Omeka\Settings');
        $siteSettings = $services->get('Omeka\Settings\Site');

        $response = $event->getParam('response');
        /** @var \Omeka\Entity\Site $site */
        $site = $response->getContent();

        // Copy main page, main theme settings and some other settings.
        $mainSiteId = $settings->get('default_site');
        // Api doesn't allow to search site by slug.
        try {
            /** @var \Omeka\Api\Representation\SiteRepresentation $mainSite */
            $mainSite = $api->read('sites', $mainSiteId)->getContent();
        } catch (NotFoundException $e) {
            $mainSite = null;
        }

        if ($mainSite) {
            // Api doesn't allow to search pages by slug.
            $mainPages = $mainSite->linkedPages();
            if ($mainPages) {
                /** @var \Omeka\Api\Representation\SitePageRepresentation $mainPage */
                $mainPage = reset($mainPages);
                if ($mainPage) {
                    // Delete default pages of the site.
                    $pages = $site->getPages();
                    if ($pages) {
                        foreach ($pages as $page) {
                            $api->delete('site_pages', $page->getId());
                        }
                    }

                    // Process copy.
                    $data = ['o:site' => ['o:id' => $site->getId()]];
                    $data['o:title'] = $mainPage->title();
                    $data['o:slug'] = $mainPage->slug();
                    foreach ($mainPage->blocks() as $block) {
                        $blockData = [];
                        $blockData['o:layout'] = $block->layout();
                        $blockData['o:data'] = $block->data();
                        $data['o:block'][] = $blockData;
                    }
                    $page = $api->create('site_pages', $data)->getContent();

                    // Initialize the navigation.
                    // TODO Copy the navigation (currently set by default).
                    $mainNavigation = $mainSite->navigation();
                    $navigation = $site->getNavigation();
                    $navigationPage = [
                        'type' => 'page',
                        'links' => [],
                        'data' => [
                            'id' => $page->id(),
                            'label' => $mainNavigation[0]['data']['label'],
                        ],
                    ];
                    $navigation = array_merge([$navigationPage], $navigation);
                    ;
                    $api->update('sites',
                         $site->getId(),
                         ['o:navigation' => $navigation],
                         [],
                         ['isPartial' => true, 'finalize' => true]
                     );
                }
            }

            $mainSettings = $entityManager->getRepository(\Omeka\Entity\SiteSetting::class)
                ->findBy(['site' => $mainSite->id()]);
            $mainThemeSettings = 'theme_settings_' . $mainSite->theme();

            /** @var \Omeka\Settings\SiteSettings $siteSettings */
            $siteSettings->setTargetId($site->getId());
            foreach ($mainSettings as $mainSiteSetting) {
                $key = $mainSiteSetting->getId();
                // Remove old theme settings, but keep current one and derivates of it.
                if (strpos($key, 'theme_settings_') === 0 && strpos($key, $mainThemeSettings) !== 0) {
                    continue;
                }
                // Remove Saml settings, that are very specific to each site.
                if (strpos($key, 'saml_') === 0) {
                    continue;
                }
                $siteSettings->set($key, $mainSiteSetting->getValue());
            }

            // Launch a reindexation of Solr, without clearing current indexes.
            $moduleManager = $services->get('Omeka\ModuleManager');
            $module = $moduleManager->getModule('Solr');
            if ($module && $module->getState() === \Omeka\Module\Manager::STATE_ACTIVE) {
                $siteSettings->setTargetId($mainSite->id());
                $mainSearchPage = (int) $siteSettings->get('search_main_page');
                if ($mainSearchPage) {
                    $searchPage = $api
                        ->searchOne('search_pages', ['id' => $mainSearchPage])
                        ->getContent();
                    if ($searchPage) {
                        $searchIndex = $searchPage->index();
                        $jobArgs = [];
                        $jobArgs['search_index_id'] = $searchIndex->id();
                        $jobArgs['start_resource_id'] = 1;
                        $jobArgs['resource_names'] = [];
                        $jobArgs['force'] = false;
                        $jobDispatcher = $services->get('Omeka\Job\Dispatcher');
                        $job = $jobDispatcher->dispatch(\Search\Job\Indexing::class, $jobArgs);

                        $viewHelpers = $controllerPlugins->get('viewHelpers');
                        $urlHelper = $viewHelpers()->get('url');
                        $jobUrl = $urlHelper('admin/id', [
                            'controller' => 'job',
                            'action' => 'show',
                            'id' => $job->getId(),
                        ]);

                        $message = new Message(
                            'Indexing of "%s" started in %sjob %s%s', // @translate
                            $searchIndex->name(),
                            sprintf('<a href="%s">', htmlspecialchars($jobUrl)),
                            $job->getId(),
                            '</a>'
                        );

                        $message->setEscapeHtml(false);
                        $messenger = new Messenger();
                        $messenger->addSuccess($message);
                    }
                }
            }
        }

        $this->updateApiSitesJson($site);

        if (!$acl->userIsAllowed(Group::class, 'create')) {
            return;
        }

        $siteGroup = $entityManager->getRepository(Group::class)
            ->findOneBy(['name' => $site->getSlug()]);
        if (empty($siteGroup)) {
            $groupname = $site->getSlug();
            if ($groupname) {
                $api = $services->get('Omeka\ApiManager');
                $response = $api->create('groups', [
                    'o:name' => $site->getSlug(),
                    'o:comment' => $site->getTitle(),
                ]);
            }
        }
    }

    /**
     * Handle update for site.
     *
     * @param Event $event
     */
    public function handleSiteUpdatePost(Event $event)
    {
        $site = $event->getParam('response')->getContent();
        $this->updateApiSitesJson($site);
    }

    /**
     * Handle deletion for site.
     *
     * Delete the group for the site.
     *
     * @param Event $event
     */
    public function handleSiteDeletePost(Event $event)
    {
        $services = $this->getServiceLocator();

        $acl = $services->get('Omeka\Acl');
        if (!$acl->userIsAllowed(Group::class, 'delete')) {
            return;
        }

        $api = $services->get('ControllerPluginManager')->get('api');
        $response = $event->getParam('response');
        /** @var \Omeka\Entity\Site $site */
        $site = $response->getContent();

        $this->updateApiSitesJson($site, true);

        $entityManager = $services->get('Omeka\EntityManager');
        $siteGroup = $entityManager->getRepository(Group::class)
            ->findOneBy(['name' => $site->getSlug()]);
        if ($siteGroup) {
            $api = $services->get('Omeka\ApiManager');
            $response = $api->delete('groups', $siteGroup->getId());
        }
    }

    public function intercomSettings(Event $event)
    {
        /** @var \ArrayObject $args */
        $args = $event->getParams();
        /** @var \Omeka\Api\Representation\UserRepresentation $user */
        $user = $args['user'];
        if (empty($user)) {
            return;
        }

        $sitePermissions = $user->sitePermissions();
        $role = $user->role();
        $hasSite = $sitePermissions && $role === \GuestUser\Permissions\Acl::ROLE_GUEST;
        if (!$hasSite) {
            return;
        }

        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $sitePermission = reset($sitePermissions);
        /** \Omeka\Api\Representation\SiteRepresentation $site */
        $site = $api->read('sites', $sitePermission->site()->id())->getContent();
        /** @var \ArrayObject $values */
        $values = $args['values'];
        $values['site'] = $site->slug();
    }

    /**
     * Get the current site group of the user (the one from the site he logged).
     *
     * @return array List of group names.
     */
    protected function getCurrentSiteGroup()
    {
        $services = $this->getServiceLocator();
        $controllerPlugins = $services->get('ControllerPluginManager');
        $currentSitePlugin = $controllerPlugins->get('currentSite');
        $currentSite = $currentSitePlugin();
        if (empty($currentSite)) {
            return [];
        }

        $siteSlug = $currentSite->slug();

        $entityManager = $services->get('Omeka\EntityManager');
        $siteGroup = $entityManager->getRepository(Group::class)
            ->findOneBy(['name' => $siteSlug]);
        if (empty($siteGroup)) {
            // $response = $api->create('groups', ['name' => $siteSlug]);
            $logger = $services->get('Omeka\Logger');
            $logger->warn(new Message('Missing site group: %s', $siteSlug));
        }

        return $siteGroup ? [$siteSlug] : [];
    }

    /**
     * Update the file api_sites.json.
     *
     * @param Site $site
     * @param bool $delete
     */
    protected function updateApiSitesJson(Site $site, $delete = false)
    {
        $apiSitesFile = dirname(dirname(__DIR__)) . '/api_sites.json';
        if (!file_exists($apiSitesFile) || !is_writeable($apiSitesFile)) {
            return;
        }

        $apiSites = json_decode(file_get_contents($apiSitesFile), true);
        if ($delete) {
            foreach ($apiSites as $key => $apiSite) {
                if ($apiSite['o:slug'] === $site->getSlug()) {
                    unset($apiSites[$key]);
                    break;
                }
            }
        } else {
            $services = $this->getServiceLocator();
            $apiAdapterManager = $services->get('Omeka\ApiAdapterManager');
            $siteAdapter = $apiAdapterManager->get('sites');
            $siteRepresentation = $siteAdapter->getRepresentation($site);
            $apiSite = $siteRepresentation->jsonSerialize();
            $apiSite['o:site_permission'] = [];
            $apiSites[] = $apiSite;
        }
        file_put_contents($apiSitesFile, json_encode($apiSites, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Get the site groups of the user (from site permission).
     *
     * @param User $user
     * @return array List of group names.
     */
    protected function getSiteGroupsForUser(User $user)
    {
        $services = $this->getServiceLocator();
        $entityManager = $services->get('Omeka\EntityManager');

        $sitePermissions = $entityManager->getRepository(\Omeka\Entity\SitePermission::class)
            ->findBy(['user' => $user->getId()]);

        $siteSlugs = [];
        foreach ($sitePermissions as $sitePermission) {
            $slug = $sitePermission->getSite()->getSlug();
            $siteSlugs[$slug] = $slug;
        }

        $existingGroups = $entityManager->getRepository(Group::class)
            ->findBy(['name' => $siteSlugs]);
        $missingGroups = $siteSlugs;
        foreach ($existingGroups as $key => $group) {
            $existingGroups[$key] = $group->getName();
            unset($missingGroups[$group->getName()]);
        }

        if ($missingGroups) {
            // $response = $api->batchCreate('groups', $missingGroups);
            $logger = $services->get('Omeka\Logger');
            $logger->warn(new Message('Missing site groups: %s', implode(', ', $missingGroups)));
        }

        return $existingGroups;
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
}
