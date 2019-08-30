<?php
/*
 * External
 *
 * Manage items from external libraries.
 *
 * Copyright Daniel Berthereau, 2018
 *
 * This software is governed by the CeCILL license under French law and abiding
 * by the rules of distribution of free software.  You can use, modify and/ or
 * redistribute the software under the terms of the CeCILL license as circulated
 * by CEA, CNRS and INRIA at the following URL "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and rights to copy, modify
 * and redistribute granted by the license, users are provided only with a
 * limited warranty and the software's author, the holder of the economic
 * rights, and the successive licensors have only limited liability.
 *
 * In this respect, the user's attention is drawn to the risks associated with
 * loading, using, modifying and/or developing or reproducing the software by
 * the user in light of its specific status of free software, that may mean that
 * it is complicated to manipulate, and that also therefore means that it is
 * reserved for developers and experienced professionals having in-depth
 * computer knowledge. Users are therefore encouraged to load and test the
 * software's suitability as regards their requirements in conditions enabling
 * the security of their systems and/or data to be ensured and, more generally,
 * to use and operate it in the same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL license and that you accept its terms.
 */

namespace External;

use External\Form\ConfigForm;
use Omeka\Api\Request;
use Omeka\Module\AbstractModule;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Form\Fieldset;
use Zend\Mvc\Controller\AbstractController;
use Zend\Mvc\MvcEvent;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Renderer\PhpRenderer;

class Module extends AbstractModule
{
    /**
     * Store the fact that a search has already been processed.
     *
     * @var bool
     */
    protected $isProcessed = false;

    protected $cacheIdentifiers;
    protected $existingIdentifiers = [];

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);

        $acl = $this->getServiceLocator()->get('Omeka\Acl');
        $acl->allow(
            null,
            [
                \Omeka\Api\Adapter\ItemAdapter::class,
                \Omeka\Api\Adapter\MediaAdapter::class,
            ],
            ['create', 'batch_create', 'update']
        );
        $acl->allow(
            null,
            [
                \Omeka\Entity\Item::class,
                \Omeka\Entity\Media::class,
            ],
            ['create', 'update']
        );
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $settings = $serviceLocator->get('Omeka\Settings');
        $this->manageSettings($settings, 'install');
        $this->manageSiteSettings($serviceLocator, 'install');
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
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

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        // Process the external search via the api or the public frontend.
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.search.pre',
            [$this, 'handleItemApiSearchPre']
        );
        // Process the external search via the module search.
        $sharedEventManager->attach(
            \Search\Controller\IndexController::class,
            'search.query.pre',
            [$this, 'handleSearchQueryPre']
        );
        // // Update the media when loaded.
        // $sharedEventManager->attach(
        //     \Omeka\Api\Adapter\MediaAdapter::class,
        //     'api.read.post',
        //     [$this, 'readPostExternal']
        // );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\MediaAdapter::class,
            'api.find.post',
            [$this, 'findPostExternal']
        );

        $sharedEventManager->attach(
            \Omeka\Form\SiteSettingsForm::class,
            'form.add_elements',
            [$this, 'addFormElementsSiteSettings']
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

        $fieldset = new Fieldset('external');
        $fieldset->setLabel('External');

        $fieldset->add([
            'name' => 'external_access_resources',
            'type' => \Zend\Form\Element\Checkbox::class,
            'options' => [
                'label' => 'Access to external resources', // @translate
            ],
            'attributes' => [
                'value' => $siteSettings->get(
                    'external_access_resources',
                    $defaultSiteSettings['external_access_resources']
                ),
            ],
        ]);

        $fieldset->add([
            'name' => 'external_access_books',
            'type' => \Zend\Form\Element\Checkbox::class,
            'options' => [
                'label' => 'Access to external books', // @translate
            ],
            'attributes' => [
                'value' => $siteSettings->get(
                    'external_access_books',
                    $defaultSiteSettings['external_access_books']
                ),
            ],
        ]);

        $fieldset->add([
            'name' => 'external_access_articles',
            'type' => \Zend\Form\Element\Checkbox::class,
            'options' => [
                'label' => 'Access to external articles', // @translate
            ],
            'attributes' => [
                'value' => $siteSettings->get(
                    'external_access_articles',
                    $defaultSiteSettings['external_access_articles']
                ),
            ],
        ]);

        $form->add($fieldset);
    }

    /**
     * Get search results on external databases (for public pages and web api).
     *
     * The external search is done one time only and statically cached.
     * The total results are cached too.
     *
     * @param Event $event
     */
    public function handleItemApiSearchPre(Event $event)
    {
        if (!$this->checkSearchPre()) {
            return;
        }

        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');

        /** @var \Omeka\Api\Request $request */
        $request = $event->getParam('request');
        $params = $request->getContent();

        // TODO Clarify modules External, Vatiru and Ebsco.

        // Update the params for the next internal search, according to query.

        // Global level (option used mainly for dev).
        $externalEbscoEbooks = $settings ->get('external_ebsco_ebook');
        $pubtype = empty($params['pubtype']) ? null : $params['pubtype'];
        if (!$externalEbscoEbooks && $pubtype === 'book') {
            $this->appendNoExternalSearchToRequest($request, $params);
            return;
        }

        // Don't do external search on some sites.
        // The site id may be added by a handle with a higher priority.
        // Once saved as internal, the limitation is related via the resources of the site.
        if (!empty($params['site_id'])) {
            $defaultSiteSettings = $services->get('Config')[strtolower(__NAMESPACE__)]['site_settings'];
            $siteSettings = $services->get('Omeka\Settings\Site');
            $siteSettings->setTargetId($params['site_id']);

            $access = $siteSettings->get('external_access_resources',
                $defaultSiteSettings['external_access_resources']);
            if (!$access) {
                $this->appendNoExternalSearchToRequest($request, $params);
                return;
            }

            switch ($pubtype) {
                case 'book':
                    $access = $siteSettings->get('external_access_books',
                    $defaultSiteSettings['external_access_books']);
                    if (!$access) {
                        $this->appendNoExternalSearchToRequest($request, $params);
                        return;
                    }
                    break;

                case 'article':
                    $access = $siteSettings->get('external_access_articles',
                    $defaultSiteSettings['external_access_articles']);
                    if (!$access) {
                        $this->appendNoExternalSearchToRequest($request, $params);
                        return;
                    }
                    break;
            }
        }

        if (!empty($GLOBALS['globalIsTest'])) {
            $executionTime = microtime(true) - $GLOBALS['globalStartTime'];
            $services->get('Omeka\Logger')->debug($executionTime . ' ' . __METHOD__ . ' #' . __LINE__);
        }

        $this->executeSearchPre($params);
    }

    /**
     * Get search results on external databases (for search with module Search).
     *
     * The external search is done one time only and statically cached.
     * The total results are cached too.
     *
     * @param Event $event
     */
    public function handleSearchQueryPre(Event $event)
    {
        if (!$this->checkSearchPre()) {
            return;
        }

        /** @var \Search\Api\Request $query */
        $query = $event->getParam('query');

        if (!in_array('items', $query->getResources())) {
            return;
        }

        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');

        $params = $event->getParam('request');

        // TODO Manage all search forms to search external resources (here, Vatiru form).
        // The update of these params is just for next method.
        $params['search'] = $query->getQuery();
        $params['page'] = (int) ($query->getOffset() / $query->getLimit()) + 1;
        $event->setParam('request', $params);

        // TODO Clarify modules External, Vatiru and Ebsco.

        // Update the params for the next internal search, according to query.
        $searchPage = $event->getParam('search_page');

        // Global level (option used mainly for dev).
        $externalEbscoEbooks = $settings ->get('external_ebsco_ebook');
        $pubtype = empty($params['pubtype']) ? null : $params['pubtype'];
        if (!$externalEbscoEbooks && $pubtype === 'book') {
            $this->appendNoExternalSearchToSearchQuery($query, $params, $searchPage);
            return;
        }

        // Don't do external search on some sites.
        // The site id may be added by a handle with a higher priority.
        // Once saved as internal, the limitation is related via the resources of the site.
        if (!empty($params['site_id'])) {
            $defaultSiteSettings = $services->get('Config')[strtolower(__NAMESPACE__)]['site_settings'];
            $siteSettings = $services->get('Omeka\Settings\Site');
            $siteSettings->setTargetId($params['site_id']);

            $access = $siteSettings->get('external_access_resources',
                $defaultSiteSettings['external_access_resources']);
            if (!$access) {
                $this->appendNoExternalSearchToSearchQuery($query, $params, $searchPage);
                return;
            }

            switch ($pubtype) {
                case 'book':
                    $access = $siteSettings->get('external_access_books',
                        $defaultSiteSettings['external_access_books']);
                    if (!$access) {
                        $this->appendNoExternalSearchToSearchQuery($query, $params, $searchPage);
                        return;
                    }
                    break;

                case 'article':
                    $access = $siteSettings->get('external_access_articles',
                        $defaultSiteSettings['external_access_articles']);
                    if (!$access) {
                        $this->appendNoExternalSearchToSearchQuery($query, $params, $searchPage);
                        return;
                    }
                    break;
            }
        }

        if (!empty($GLOBALS['globalIsTest'])) {
            $executionTime = microtime(true) - $GLOBALS['globalStartTime'];
            $services->get('Omeka\Logger')->debug($executionTime . ' ' . __METHOD__ . ' #' . __LINE__);
        }

        $this->executeSearchPre($params);
    }

    /**
     * Check if the external search should be done.
     *
     * @return bool
     */
    protected function checkSearchPre()
    {
        // Avoid multiple searches, in particular for the module Search, that
        // does one search by site.
        if ($this->isProcessed) {
            return false;
        }

        $this->isProcessed = true;

        // Check if this is a background query.
        $services = $this->getServiceLocator();
        $routeMatch = $services->get('Application')->getMvcEvent()->getRouteMatch();
        if (empty($routeMatch)) {
            return false;
        }

        // Check if the external query should be processed.

        $route = $routeMatch->getMatchedRouteName();
        if (strpos($route, 'admin/') === 0) {
            return false;
        }

        $settings = $services->get('Omeka\Settings');
        if ($settings ->get('external_ebsco_disable')) {
            return false;
        }

        if (!empty($GLOBALS['globalIsTest'])) {
            $executionTime = microtime(true) - $GLOBALS['globalStartTime'];
            $services->get('Omeka\Logger')->debug($executionTime . ' ' . __METHOD__ . ' #' . __LINE__);
        }

        return true;
    }

    protected function appendNoExternalSearchToRequest(Request $request, array $params)
    {
        $property = [
            'joiner' => 'and',
            'property' => 'vatiru:isExternal',
            'type' => 'neq',
            'text' => '1',
        ];
        $params['property'][] = $property;
        $request->setContent($params);
    }

    protected function appendNoExternalSearchToSearchQuery(
        \Search\Query $query,
        array $params,
       \Search\Api\Representation\SearchPageRepresentation $searchPage
    ) {
        $settings = $searchPage->settings();
        if (!empty($settings['form']['vatiru:publicationType'])) {
            $query->addFilterQuery($settings['form']['vatiru:publicationType'], '1', 'neq', 'and');
        }
    }

    /**
     * Execute an external search query.
     *
     * @param array $params The params of a query.
     */
    protected function executeSearchPre(array $params)
    {
        // Avoid to do a search without query (item/browse view).
        if (!isset($params['search']) || !strlen($params['search'])) {
            return;
        }

        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $plugins = $services->get('ControllerPluginManager');
        $searchExternal = $plugins->get('searchExternal');

        if (!empty($GLOBALS['globalIsTest'])) {
            $executionTime = microtime(true) - $GLOBALS['globalStartTime'];
            $services->get('Omeka\Logger')->debug($executionTime . ' ' . __METHOD__ . ' #' . __LINE__);
        }

        $result = $searchExternal($params);
        if (empty($result)) {
            // TODO Remove the log when the external query doesn't return anything.
            $logger = $services->get('Omeka\Logger');
            $logger->warn('No response for external search.'); // @translate
            return;
        }

        if (!empty($GLOBALS['globalIsTest'])) {
            $executionTime = microtime(true) - $GLOBALS['globalStartTime'];
            $services->get('Omeka\Logger')->debug($executionTime . ' ' . __METHOD__ . ' #' . __LINE__);
        }

        $result = $this->ebscoRecordsToItemData($result);

        if (!empty($GLOBALS['globalIsTest'])) {
            $executionTime = microtime(true) - $GLOBALS['globalStartTime'];
            $services->get('Omeka\Logger')->debug($executionTime . ' ' . __METHOD__ . ' #' . __LINE__);
            $services->get('Omeka\Logger')->debug('total result external: ' . count($result));
        }

        // Save the query of the user so all items will be findable.
        $result = $this->includeQueryAsValue($result, $params);

        if (!empty($GLOBALS['globalIsTest'])) {
            $executionTime = microtime(true) - $GLOBALS['globalStartTime'];
            $services->get('Omeka\Logger')->debug($executionTime . ' ' . __METHOD__ . ' #' . __LINE__);
        }

        // Save the query for already imported values.
        $this->saveQueryAsValue($this->existingIdentifiers, $params);

        if (!empty($GLOBALS['globalIsTest'])) {
            $executionTime = microtime(true) - $GLOBALS['globalStartTime'];
            $services->get('Omeka\Logger')->debug($executionTime . ' ' . __METHOD__ . ' #' . __LINE__);
        }

        // Use jobs to create thumbnails for quicker process.
        $quickBatchCreate = $plugins->get('quickBatchCreate');
        $ids = $quickBatchCreate($result, true);

        if (!empty($GLOBALS['globalIsTest'])) {
            $executionTime = microtime(true) - $GLOBALS['globalStartTime'];
            $services->get('Omeka\Logger')->debug($executionTime . ' ' . __METHOD__ . ' #' . __LINE__);
            $services->get('Omeka\Logger')->debug('Created : ' . count($ids));
        }

        $processExisting = $this->existingIdentifiers && $settings->get('external_ebsco_process_existing');
        if (!$processExisting && !count($ids)) {
            return;
        }

        // Check if the fetch of media can be done.
        $maxFetchItems = $settings->get('external_ebsco_max_fetch_items');
        if (!empty($GLOBALS['globalIsTest'])) {
            $executionTime = microtime(true) - $GLOBALS['globalStartTime'];
            $services->get('Omeka\Logger')->debug($executionTime . ' ' . __METHOD__ . ' #' . __LINE__ . ' / Max fetch items: ' . $maxFetchItems);
        }
        if ($maxFetchItems < 1) {
            return;
        }
        $maxFetchJobs = $settings->get('external_ebsco_max_fetch_jobs');
        if (!empty($GLOBALS['globalIsTest'])) {
            $executionTime = microtime(true) - $GLOBALS['globalStartTime'];
            $services->get('Omeka\Logger')->debug($executionTime . ' ' . __METHOD__ . ' #' . __LINE__ . ' / Max fetch jobs: ' . $maxFetchJobs);
        }
        if ($maxFetchJobs < 1) {
            return;
        }
        // TODO Move this check inside Omeka Core.
        /** @var \Doctrine\ORM\EntityManager $entityManager */
        $entityManager = $services->get('Omeka\EntityManager');
        $qb = $entityManager->createQueryBuilder();
        $qb
            ->select('COUNT(' . \Omeka\Entity\Job::class . '.id)')
            ->from(\Omeka\Entity\Job::class, \Omeka\Entity\Job::class)
            ->where($qb->expr()->eq(\Omeka\Entity\Job::class . '.class', ':job_class'))
            ->setParameter('job_class', \External\Job\PrepareExternalMedia::class)
            ->andWhere(\Omeka\Entity\Job::class . '.status IN (:status)')
            // TODO Add a query multiple argument "status" to job in order to search it via api.
            ->setParameter('status', [
                \Omeka\Entity\Job::STATUS_STARTING,
                \Omeka\Entity\Job::STATUS_IN_PROGRESS,
            ]);
        $totalJobs = $qb->getQuery()->getSingleScalarResult();
        if (!empty($GLOBALS['globalIsTest'])) {
            $executionTime = microtime(true) - $GLOBALS['globalStartTime'];
            $services->get('Omeka\Logger')->debug($executionTime . ' ' . __METHOD__ . ' #' . __LINE__ . '  / Total current jobs: ' . $totalJobs);
        }
        if ($totalJobs >= $maxFetchJobs) {
            return;
        }

        if ($processExisting) {
            $sliceExistingIdentifiers = array_slice(array_keys($this->existingIdentifiers), 0, $maxFetchItems);
            $services->get('Omeka\Job\Dispatcher')
                ->dispatch(\External\Job\PrepareExternalMedia::class, ['ids' => $sliceExistingIdentifiers]);
        }

        if ($ids) {
            $sliceIds = array_slice($ids, 0, $maxFetchItems);
            $services->get('Omeka\Job\Dispatcher')
                ->dispatch(\External\Job\PrepareExternalMedia::class, ['ids' => $sliceIds]);
        }

        if (!empty($GLOBALS['globalIsTest'])) {
            $executionTime = microtime(true) - $GLOBALS['globalStartTime'];
            $services->get('Omeka\Logger')->debug($executionTime . ' ' . __METHOD__ . ' #' . __LINE__);
        }
    }

    /**
     * Update an external media when needed.
     *
     * @param Event $event
     */
    public function readPostExternal(Event $event)
    {
        $services = $this->getServiceLocator();
        $controllerPlugins = $services->get('ControllerPluginManager');
        $prepareExternal = $controllerPlugins->get('prepareExternal');
        /** @var MediaRepresentation $media */
        $media = $event->getParam('response')->getContent();
        if (!$media) {
            return;
        }
        /** @var MediaAdapter $adapter */
        $adapter = $event->getTarget();
        $mediaRepresentation = $adapter->getRepresentation($media);
        // Prepare the media if needed.
        $prepareExternal($mediaRepresentation);
    }

    /**
     * Update an external media when needed.
     *
     * @param Event $event
     */
    public function findPostExternal(Event $event)
    {
        $services = $this->getServiceLocator();
        $controllerPlugins = $services->get('ControllerPluginManager');
        $prepareExternal = $controllerPlugins->get('prepareExternal');
        /** @var MediaRepresentation $media */
        $media = $event->getParam('entity');
        /** @var MediaAdapter $adapter */
        $adapter = $event->getTarget();
        $mediaRepresentation = $adapter->getRepresentation($media);
        // Prepare the media if needed.
        $prepareExternal($mediaRepresentation);
    }

    /**
     * Convert the results from an Ebsco search into item data, if not cached.
     *
     * Existing items are not saved again.
     *
     * @param array $records
     * @return array
     */
    protected function ebscoRecordsToItemData(array $records)
    {
        if (empty($records['SearchResult']['Data']['Records'])) {
            return [];
        }

        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $controllerPlugins = $services->get('ControllerPluginManager');
        $existingIdentifiers = $controllerPlugins->get('existingIdentifiers');
        $filterExistingExternalRecords = $controllerPlugins->get('filterExistingExternalRecords');

        $identifiers = array_filter(array_map(function ($v) {
            return 'ebsco:'
                . http_build_query([
                    'dbid' => $v['Header']['DbId'],
                    'an' => $v['Header']['An'],
                ]);
        }, $records['SearchResult']['Data']['Records']));
        $this->cacheIdentifiers = $identifiers;

        $this->existingIdentifiers = $existingIdentifiers($identifiers);

        $records = $filterExistingExternalRecords(
            $records['SearchResult']['Data']['Records'],
            $identifiers,
            $this->existingIdentifiers
        );
        if (empty($records)) {
            return [];
        }

        $normalized = $this->convertExternalRecords($records);
        if (empty($normalized)) {
            return [];
        }

        $createItem = $settings->get('external_create_item');
        if ($createItem === 'file_url') {
            $normalized = array_filter($normalized, function($v) {
                return !empty($v['o:media']);
            });
        } elseif ($createItem === 'never') {
            return [];
        }

        return $normalized;
    }

    /**
     * Add the search params as values to items.
     *
     * @param array $itemData
     * @param array $params Search params.
     * @param array Updated item data.
     */
    protected function includeQueryAsValue($itemData, $params)
    {
        $services = $this->getServiceLocator();
        $plugins = $services->get('ControllerPluginManager');
        $api = $plugins->get('api');

        $userQueryId = $api
            ->searchOne('properties', ['term' => 'vatiru:userQuery'], ['returnScalar' => 'id'])
            ->getContent();
        $value = json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // No check is needed, since data are new items.
        foreach ($itemData as &$data) {
            $data['vatiru:userQuery'][] = [
                'property_id' => $userQueryId,
                'type' => 'literal',
                '@value' => $value,
            ];
        }
        return $itemData;
    }

    /**
     * Save the query as value.
     *
     * This process is mainly used for existing records.
     *
     * @param array $identifiers
     * @param array $params
     */
    protected function saveQueryAsValue(array $identifiers, array $params)
    {
        if (empty($identifiers)) {
            return;
        }

        $services = $this->getServiceLocator();
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $services->get('Omeka\Connection');
        $plugins = $services->get('ControllerPluginManager');
        $api = $plugins->get('api');

        $sqlIdentifiers = implode(',', array_map([$connection, 'quote'], $identifiers));

        // Use a direct query for quick check.
        // TODO Improve the query to check cached items (use entity saved query or dql).
        // Property 10 = dcterms:identifier.
        // The authentication is not checked.
        $sql = <<<SQL
SELECT value.resource_id
FROM value
WHERE value.property_id = 10
AND value.type = "literal"
AND value.value IN ($sqlIdentifiers)
;
SQL;

        $userQueryId = $api
            ->searchOne('properties', ['term' => 'vatiru:userQuery'], ['returnScalar' => 'id'])
            ->getContent();

        $stmt = $connection->query($sql);
        $result = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        if (empty($result)) {
            return;
        }

        $ids = implode(',', $result);

        // Remove all ids that have the params as value (avoid duplicate).
        $value = json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $quotedValue = $connection->quote($value);

        $sql = <<<SQL
SELECT DISTINCT value.resource_id
FROM value
WHERE value.resource_id NOT IN
(
    SELECT DISTINCT value.resource_id
    FROM value
    WHERE value.property_id = $userQueryId
    AND value.type = "literal"
    AND value.resource_id IN ($ids)
    AND value.value = $quotedValue
)
AND value.resource_id IN ($ids)
;
SQL;

        $stmt = $connection->query($sql);
        $result = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        if (empty($result)) {
            return;
        }

        // Insert the params to the remaining ids.
        $q = ", $userQueryId, 'literal', null, $quotedValue),
(";
        $values = '(' . implode($q,  $result) . ", $userQueryId, 'literal', null, $quotedValue);";
        $sql = 'INSERT INTO value (resource_id, property_id, type, lang, value) VALUES ' . $values;
        $connection->exec($sql);
    }

    /**
     * Convert the results from an Ebsco search into Omeka items, as data item.
     *
     * @param array $records
     * @return array
     */
    protected function convertExternalRecords(array $records)
    {
        $services = $this->getServiceLocator();
        $controllerPlugins = $services->get('ControllerPluginManager');
        $convertExternalRecord = $controllerPlugins->get('convertExternalRecord');
        $result = [];
        // TODO RecordFormat = EP Display: no documentation about other formats.
        foreach ($records as $record) {
            $result[] = $convertExternalRecord('ebsco', $record, true);
        }
        return $result;
    }
}
