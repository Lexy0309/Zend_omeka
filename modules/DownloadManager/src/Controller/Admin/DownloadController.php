<?php
namespace DownloadManager\Controller\Admin;

use Doctrine\ORM\EntityManager;
use DownloadManager\Entity\Download;
use DownloadManager\Entity\DownloadLog;
use DownloadManager\Form\QuickSearchForm;
use Omeka\Stdlib\Message;
use Zend\Http\Response;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;

class DownloadController extends AbstractActionController
{
    protected $entityManager;
    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }
    public function browseAction()
    {
        $this->setBrowseDefaults('created');
        $query = $this->params()->fromQuery();

        // Manage AdminSite.
        $site = $this->currentSite();
        if ($site) {
            $query['site_id'] = $site->id();
        }

        // Don't display empty downloads ("ready"), but display past ones.
        if (empty($query['status'])) {
            $query['status'] = [Download::STATUS_HELD, Download::STATUS_DOWNLOADED, Download::STATUS_PAST];
        } else {
            if (!is_array($query['status'])) {
                $query['status'] = array_map('trim', explode(',', $query['status']));
            }
            $query['status'] = array_intersect(
                $query['status'],
                [Download::STATUS_HELD, Download::STATUS_DOWNLOADED, Download::STATUS_PAST]
            );
        }

        // Keep value "0".
        $notNullAndNotEmptyString = function ($v) {
            return $v !== '' && !is_null($v);
        };
        $query = array_filter($query, $notNullAndNotEmptyString);
        $response = $this->api()->search('downloads', $query);
        $this->paginator($response->getTotalResults(), $this->params()->fromQuery('page'));
        $formSearch = $this->getForm(QuickSearchForm::class);
        $formSearch->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'by-download'], true));
        $formSearch->setAttribute('id', 'download-search');
        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $formSearch->setData($data);
        } elseif ($this->getRequest()->isGet()) {
            $data = $this->params()->fromQuery();
            $formSearch->setData($data);
        }
        $view = new ViewModel();
        $downloads = $response->getContent();
        $view->setVariable('resources', $downloads);
        $view->setVariable('downloads', $downloads);
        $view->setVariable('formSearch', $formSearch);
        return $view;
    }
    public function dashboardAction()
    {
        $this->setBrowseDefaults('total');
        $query = $this->params()->fromQuery();
        $notNullAndNotEmptyString = function ($v) {
            return $v !== '' && !is_null($v);
        };
        $query = array_filter($query, $notNullAndNotEmptyString);
        $defaultQuery = [
            'page' => null,
            // TODO Use paginator instead of 20.
            'per_page' => null,
            'per_page' => 20,
            'limit' => null,
            'offset' => null,
            'sort_by' => null,
            'sort_order' => null,
        ];
        $query += $defaultQuery;
        $query['sort_order'] = strtoupper($query['sort_order']) === 'DESC' ? 'DESC' : 'ASC';
        $conn = $this->entityManager->getConnection();
        $qb1 = $conn->createQueryBuilder()
            ->select('user.name as user_name, user.email as user_email, count(download.owner_id) as readed_item')
            ->from('download, user')
            ->where('download.owner_id = user.id')
            ->groupBy('download.owner_id')
            ->orderBy('readed_item','DESC');
        $stmt1 = $conn->executeQuery($qb1);
        $result1 = $stmt1->fetchAll();
        for($number = 0 ; $number < 10; $number++){
                $result[$number]['max_user_name'] = $result1[$number]['user_name'];
                $result[$number]['max_user_email'] = $result1[$number]['user_email'];
                $result[$number]['max_user_total'] = $result1[$number]['readed_item'];
                $result[$number]['id']= $number;     
        }
        $qb2 = $conn->createQueryBuilder()
            ->select('download.resource_id as id, value.value as resource_name, ifnull(count(download.resource_id), 0) as readed_user')
            ->from('download, value')
            ->where('download.resource_id = value.resource_id and value.property_id =1')
            ->groupBy('download.resource_id')
            ->orderBy('readed_user','DESC');
        $number = 0;
        $stmt2 = $conn->executeQuery($qb2);
        $result2 = $stmt2->fetchAll();
        for($number = 0 ; $number < 10; $number++){
            $result[$number]['max_item_name'] = $result2[$number]['resource_name'];
            $result[$number]['max_item_total'] = $result2[$number]['readed_user'];
        }


        $qb3 = $conn->createQueryBuilder()
        ->select('site_permission.site_id as site_id, ifnull(count(DISTINCT download.resource_id), 0) as users')
        ->from('download, site_permission')
        ->where("site_permission.user_id = download.owner_id")
        ->groupBy('site_id')
        ->orderBy('users','DESC');
        $stmt3 = $conn->executeQuery($qb3);
        $result3 = $stmt3->fetchAll();

        for($num = 0 ; $num < 10; $num++){
            $result0[$num] = $this->getSiteStatsTrending($result3[$num]['site_id']);
        }


        $qb4 = $conn->createQueryBuilder()
        ->select('DATE_FORMAT(modified, "%b %Y") AS year_month_date,
        ifnull(count(DISTINCT owner_id), 0) as trending_users, ifnull(count(DISTINCT resource_id), 0) as trending_items')
        ->from('download')
        ->groupBy('year_month_date')
        ->orderBy('download.modified', 'DESC');

        $stmt4 = $conn->executeQuery($qb4);
        $result4 = $stmt4->fetchAll();
        for($number = 0; $number <12; $number++){
            $result[$number]['year_month_date'] = $result4[$number]['year_month_date'];
            $result[$number]['trending_users'] = $result4[$number]['trending_users'];
            $result[$number]['trending_items'] = $result4[$number]['trending_items'];
        }
        
        $data = array ($result, $result0);
        $view = new ViewModel();
        $view->setVariable('result', $data);
        return $view;
    }
    public function getSiteStatsTrending($site_id){
        $conn = $this->entityManager->getConnection();
        $qb3 = $conn->createQueryBuilder()
        ->select('DATE_FORMAT(download.modified, "%b %Y") as year_month_date, ifnull(count(DISTINCT download.resource_id), 0) as site_items, ifnull(count(DISTINCT download.owner_id), 0) as site_users')
        ->from('download, site_permission')
        ->where("site_permission.site_id = $site_id and site_permission.user_id = download.owner_id")
        ->groupBy('year_month_date')
        ->orderBy('download.modified', 'DESC');

        $stmt3 = $conn->executeQuery($qb3);
        $result3 = $stmt3->fetchAll();
        return $result3;
    }

    public function byDownloadAction()
    {
        $params = $this->params()->fromRoute();
        $params['action'] = 'browse';
        return $this->forward()->dispatch(__CLASS__, $params);
    }

    public function byItemAction()
    {
        $params = $this->params()->fromRoute();
        $params['action'] = 'by-resource';
        return $this->forward()->dispatch(__CLASS__, $params);
    }

    public function byUserAction()
    {
        $this->setBrowseDefaults('total');
        $query = $this->params()->fromQuery();
        // Keep value "0".
        $notNullAndNotEmptyString = function ($v) {
            return $v !== '' && !is_null($v);
        };
        $query = array_filter($query, $notNullAndNotEmptyString);

        // Manage AdminSite.
        $site = $this->currentSite();
        if ($site) {
            $query['site_id'] = $site->id();
        }

        // Get the result via a direct query.
        /** @var \DownloadManager\Api\Adapter\DownloadAdapter $adapter */
        $adapterManager = $this->getEvent()->getApplication()->getServiceManager()
            ->get('Omeka\ApiAdapterManager');
        $adapter = $adapterManager->get('downloads');
        // Since the api is not used directly (search), some default are added.
        $defaultQuery = [
            'page' => null,
            'per_page' => null,
            'limit' => null,
            'offset' => null,
            'sort_by' => null,
            'sort_order' => null,
        ];
        $query += $defaultQuery;
        $query['sort_order'] = strtoupper($query['sort_order']) === 'DESC' ? 'DESC' : 'ASC';
        $qb = $this->entityManager->createQueryBuilder();
        $qb
            ->select([
                Download::class . ' AS download',
                'sum(case when ' . Download::class . '.status = :held then 1 else 0 end) AS holding',
                'sum(case when ' . Download::class . '.status = :downloaded then 1 else 0 end) AS reading',
                'sum(case when ' . Download::class . '.status = :past then 1 else 0 end) AS past',
                'count(' . Download::class . '.resource) AS total',
            ])
            ->setParameters([
                'held' => Download::STATUS_HELD,
                'downloaded' => Download::STATUS_DOWNLOADED,
                'past' => Download::STATUS_PAST,
            ])
            ->from(Download::class, Download::class)
            ->groupBy(Download::class . '.owner');
        $qb
            ->andWhere($qb->expr()->neq(Download::class . '.status', ':status'))
            ->setParameter('status', Download::STATUS_READY);
        switch ($query['sort_by']) {
            case 'download':
                $qb
                    ->orderBy(Download::class . '.id', $query['sort_order']);
                break;
            case 'holding':
            case 'reading':
            case 'past':
            case 'total':
                $qb
                    ->orderBy($query['sort_by'], $query['sort_order']);
                break;
            default:
                $adapter->sortQuery($qb, $query);
                break;
        }

        // TODO Trigger filters for downloads like a true api request?
        $adapter->buildQuery($qb, $query);
        $adapter->limitQuery($qb, $query);
        $result = $qb->getQuery()->getResult();
        // Get the representations.
        foreach ($result as &$v) {
            $v['download'] = $adapter->getRepresentation($v['download']);
        }
        unset($v);
        // Get the total rows directly, because it's an advanced query.
        $qb = $this->entityManager->createQueryBuilder();
        $qb
            ->select('count(distinct ' . Download::class . '.owner)')
            ->from(Download::class, Download::class);
        // Don't display empty downloads ("ready").
        $qb
            ->andWhere($qb->expr()->neq(Download::class . '.status', ':status'))
            ->setParameter('status', Download::STATUS_READY);
        $totalResults = $qb->getQuery()->getSingleScalarResult();
        $this->paginator($totalResults, $this->params()->fromQuery('page'));

        $formSearch = $this->getForm(QuickSearchForm::class);
        $formSearch->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'by-user'], true));
        $formSearch->setAttribute('id', 'download-search');
        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $formSearch->setData($data);
        } elseif ($this->getRequest()->isGet()) {
            $data = $this->params()->fromQuery();
            $formSearch->setData($data);
        }

        $view = new ViewModel();
        $view->setVariable('result', $result);
        $view->setVariable('formSearch', $formSearch);
        return $view;
    }

    public function byResourceAction()
    {
        $this->setBrowseDefaults('total');
        $query = $this->params()->fromQuery();
        $notNullAndNotEmptyString = function ($v) {
            return $v !== '' && !is_null($v);
        };
        $query = array_filter($query, $notNullAndNotEmptyString);

        // Manage AdminSite.
        $site = $this->currentSite();
        if ($site) {
            $query['site_id'] = $site->id();
        }

        // Get the result via a direct query.
        /** @var \DownloadManager\Api\Adapter\DownloadAdapter $adapter */
        $adapterManager = $this->getEvent()->getApplication()->getServiceManager()
            ->get('Omeka\ApiAdapterManager');
        $adapter = $adapterManager->get('downloads');
        $defaultQuery = [
            'page' => null,
            'per_page' => null,
            'limit' => null,
            'offset' => null,
            'sort_by' => null,
            'sort_order' => null,
        ];
        $query += $defaultQuery;
        $query['sort_order'] = strtoupper($query['sort_order']) === 'DESC' ? 'DESC' : 'ASC';
        $qb = $this->entityManager->createQueryBuilder();
        $qb
            ->select([
                Download::class . ' AS download',
                'sum(case when ' . Download::class . '.status = :held then 1 else 0 end) AS holding',
                'sum(case when ' . Download::class . '.status = :downloaded then 1 else 0 end) AS reading',
                'sum(case when ' . Download::class . '.status = :past then 1 else 0 end) AS past',
                'count(' . Download::class . '.resource) AS total',
            ])
            ->setParameters([
                'held' => Download::STATUS_HELD,
                'downloaded' => Download::STATUS_DOWNLOADED,
                'past' => Download::STATUS_PAST,
            ])
            ->from(Download::class, Download::class)
            ->groupBy(Download::class . '.resource');
        $qb
            ->andWhere($qb->expr()->neq(Download::class . '.status', ':status'))
            ->setParameter('status', Download::STATUS_READY);
        switch ($query['sort_by']) {
            case 'download':
                $qb
                    ->orderBy(Download::class . '.id', $query['sort_order']);
                break;
            case 'holding':
            case 'reading':
            case 'past':
            case 'total':
                $qb
                    ->orderBy($query['sort_by'], $query['sort_order']);
                break;
            default:
                $adapter->sortQuery($qb, $query);
                break;
        }
        $adapter->buildQuery($qb, $query);
        $adapter->limitQuery($qb, $query);
        $result = $qb->getQuery()->getResult();
        foreach ($result as &$v) {
            $v['download'] = $adapter->getRepresentation($v['download']);
        }
        unset($v);
        $qb = $this->entityManager->createQueryBuilder();
        $qb
            ->select('count(distinct ' . Download::class . '.resource)')
            ->from(Download::class, Download::class);
        $qb
            ->andWhere($qb->expr()->neq(Download::class . '.status', ':status'))
            ->setParameter('status', Download::STATUS_READY);
        $totalResults = $qb->getQuery()->getSingleScalarResult();
        $this->paginator($totalResults, $this->params()->fromQuery('page'));
        $formSearch = $this->getForm(QuickSearchForm::class);
        $formSearch->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'by-resource'], true));
        $formSearch->setAttribute('id', 'download-search');
        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $formSearch->setData($data);
        } elseif ($this->getRequest()->isGet()) {
            $data = $this->params()->fromQuery();
            $formSearch->setData($data);
        }
        $view = new ViewModel();
        $view->setVariable('result', $result);
        $view->setVariable('formSearch', $formSearch);
        return $view;
    }

    public function bySiteAction()
    {
        $this->setBrowseDefaults('total');
        $query = $this->params()->fromQuery();
        $notNullAndNotEmptyString = function ($v) {
            return $v !== '' && !is_null($v);
        };
        $query = array_filter($query, $notNullAndNotEmptyString);
        $defaultQuery = [
            'page' => null,
            // TODO Use paginator instead of 20.
            'per_page' => null,
            'per_page' => 20,
            'limit' => null,
            'offset' => null,
            'sort_by' => null,
            'sort_order' => null,
        ];
        $query += $defaultQuery;
        $query['sort_order'] = strtoupper($query['sort_order']) === 'DESC' ? 'DESC' : 'ASC';
        $conn = $this->entityManager->getConnection();
        $qb1 = $conn->createQueryBuilder()
            ->select('site.title AS title,
                site.id AS id,
                sum( CASE WHEN download.STATUS = "past" THEN 1 ELSE 0 END ) AS past_items,
                sum( CASE WHEN download.STATUS = "held" THEN 1 ELSE 0 END ) AS holding_items,
                sum( CASE WHEN download.STATUS = "downloaded" THEN 1 ELSE 0 END ) AS downloaded_items,
                sum( 1 ) AS total')
            ->from('site, site_permission, download')
            ->where('site.id = site_permission.site_id
                AND site_permission.user_id = download.owner_id')
            ->groupBy('site.title')
            ->orderBy('site.id');
        $stmt1 = $conn->executeQuery($qb1);
        $result1 = $stmt1->fetchAll();
        $qb2 = $conn->createQueryBuilder()
            ->select('site.id AS id,
                sum(CASE WHEN site.id = site_permission.site_id THEN 1 ELSE 0 END ) AS users')
            ->from('site, site_permission')
            ->where('site.id = site_permission.site_id')
            ->groupBy('site_permission.site_id')
            ->orderBy('site.id');
        $stmt2 = $conn->executeQuery($qb2);
        $result2 = $stmt2->fetchAll();

        foreach($result1 as $key => $a){
            foreach($result2 as $b){
                if($a['id'] == $b['id']){
                    $result1[$key]['users'] = $b['users'];
                    break;
                }
                else {
                    $result1[$key]['users'] = null;
                }
            }
        }

       $formSearch = $this->getForm(QuickSearchForm::class);
        $formSearch->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'by-site'], true));
        $formSearch->setAttribute('id', 'download-search');
        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $formSearch->setData($data);
        } elseif ($this->getRequest()->isGet()) {
            $data = $this->params()->fromQuery();
            $formSearch->setData($data);
        }

        switch ($query['sort_by']) {
            case 'title':
                $this->arrayOrderBy($result1, 'title,holding_items,past_items,downloaded_items,total,users', $query['sort_order']);
                break;
            case 'holding_items':
                $this->arrayOrderBy($result1, 'holding_items,title,past_items,downloaded_items,total,users', $query['sort_order']);
                break;
            case 'past_items':
                $this->arrayOrderBy($result1, 'past_items,title,holding_items,downloaded_items,total,users', $query['sort_order']);
                break;
            case 'downloaded_items':
                $this->arrayOrderBy($result1, 'downloaded_items,title,holding_items,past_items,total,users', $query['sort_order']);
                break;
            case 'total':
                $this->arrayOrderBy($result1, 'total,title,holding_items,past_items,downloaded_items,users', $query['sort_order']);
                break;
            case 'users':
                $this->arrayOrderBy($result1, 'users,title,holding_items,past_items,downloaded_items,total', $query['sort_order']);
                break;
            default:
                $this->arrayOrderBy($result1, 'title,holding_items,past_items,downloaded_items,total,users', $query['sort_order']);
                break;
        }
        $page = $query['page']*1 - 1;
        $per_page = $query['per_page']*1;
        $this->paginator(count($result1),$page*1 + 1);
        $result1 = array_slice($result1, ($page * $per_page), $per_page);

        $view = new ViewModel();
        $view->setVariable('result', $result1);
        $view->setVariable('formSearch', $formSearch);
        return $view;
    }

    public function byPropertyAction()
    {
        $this->setBrowseDefaults('total');

        $query = $this->params()->fromQuery();
        $notNullAndNotEmptyString = function ($v) {
            return $v !== '' && !is_null($v);
        };
        $query = array_filter($query, $notNullAndNotEmptyString);

        // Manage AdminSite.
        $site = $this->currentSite();
        if ($site) {
            $query['site_id'] = $site->id();
        }

        $defaultQuery = [
            'page' => null,
            // TODO Use paginator instead of 20.
            'per_page' => null,
            'per_page' => 20,
            'limit' => null,
            'offset' => null,
            'sort_by' => null,
            'sort_order' => null,
            'property' => null,
        ];
        $query += $defaultQuery;
        $query['sort_order'] = strtoupper($query['sort_order']) === 'DESC' ? 'DESC' : 'ASC';

        $property = $this->params('property');
        $property = $this->api()->searchOne('properties', ['term' => $property])->getContent();
        $propertyId = $property ? $property->id() : 3;
        $result1 = $this->getStatsByProperty($propertyId);

        $formSearch = $this->getForm(QuickSearchForm::class);
        $formSearch->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'by-site'], true));
        $formSearch->setAttribute('id', 'download-search');
        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $formSearch->setData($data);
        } elseif ($this->getRequest()->isGet()) {
            $data = $this->params()->fromQuery();
            $formSearch->setData($data);
        }

        switch ($query['sort_by']) {
            case 'title':
                $this->arrayOrderBy($result1, 'title,holding_items,past_items,downloaded_items,total,users', $query['sort_order']);
                break;
            case 'holding_items':
                $this->arrayOrderBy($result1, 'holding_items,title,past_items,downloaded_items,total,users', $query['sort_order']);
                break;
            case 'past_items':
                $this->arrayOrderBy($result1, 'past_items,title,holding_items,downloaded_items,total,users', $query['sort_order']);
                break;
            case 'downloaded_items':
                $this->arrayOrderBy($result1, 'downloaded_items,title,past_items,holding_items,total,users', $query['sort_order']);
                break;
            case 'total':
                $this->arrayOrderBy($result1, 'total,title,past_items,holding_items,downloaded_items,users', $query['sort_order']);
                break;
            case 'users':
                $this->arrayOrderBy($result1, 'users,title,past_items,holding_items,downloaded_items,total', $query['sort_order']);
                break;
            default:
                $this->arrayOrderBy($result1, 'title,past_items,holding_items,downloaded_items,total,users', $query['sort_order']);
                break;
        }
        $page = $query['page']*1 - 1;
        $per_page = $query['per_page']*1;
        $this->paginator(count($result1),$page*1 + 1);
        $result1 = array_slice($result1, ($page * $per_page), $per_page);

        $view = new ViewModel();
        $view->setVariable('result', $result1);
        $view->setVariable('formSearch', $formSearch);
        return $view;
    }

    public function getStatsByProperty($propertyId)
    {
        $conn = $this->entityManager->getConnection();
        $qb1 = $conn->createQueryBuilder()
            ->select('value.value AS title,
                sum(case when download.status = "past" THEN 1 ELSE 0 END ) AS past_items,
                SUM(case when download.status = "held" THEN 1 ELSE 0 END ) AS holding_items,
                sum(case when download.status = "downloaded" THEN 1 ELSE 0 END ) AS downloaded_items,
                sum(1) AS total')
            ->from('value, download')
            ->where("value.property_id = $propertyId AND download.resource_id = value.resource_id")
            ->groupBy('value.value');
        $stmt1 = $conn->executeQuery($qb1);
        $result1 = $stmt1->fetchAll();
        $qb2 = $conn->createQueryBuilder()
            ->select('value.value AS title,
                count(download.owner_id) AS users')
            ->from('value, download')
            ->where("value.property_id = $propertyId AND download.resource_id = value.resource_id")
            ->groupBy('download.owner_id, value.value');
        $stmt2 = $conn->executeQuery($qb2);
        $result2 = $stmt2->fetchAll();
        $number = 0;
        foreach($result1 as $key => $a){
            foreach($result2 as $b){
                if($a['title'] == $b['title']){
                    $number++;
                    $result1[$key]['users'] = $b['users'];
                    $result1[$key]['id']= $number;
                    break;
                }
                else {
                    $result1[$key]['users'] = null;
                }
            }
        }
        $result1[0]['property_id'] = $propertyId;
        return $result1;
    }

    public function byPropertyTermAction()
    {
        $result = [];

        // Property is "dcterms:subject" by default.
        $property = $this->params('property');
        $property = $this->api()->searchOne('properties', ['term' => $property])->getContent();
        $propertyId = $property ? $property->id() : 3;

        $conn = $this->entityManager->getConnection();
        $expr = $conn->getExpressionBuilder();

        $qb1 = $conn->createQueryBuilder()
            ->select('value.value AS title,
                sum(case when download.status = "past" THEN 1 ELSE 0 END ) AS past_items,
                sum(case when download.status = "held" THEN 1 ELSE 0 END ) AS holding_items,
                sum(case when download.status = "downloaded" THEN 1 ELSE 0 END ) AS downloaded_items,
                sum(1) as total')
            ->from('value, download')
            ->where($expr->eq('value.property_id', $propertyId))
            ->andWhere('download.resource_id = value.resource_id')
            ->groupBy('value.value');
        $stmt1 = $conn->executeQuery($qb1);
        $result1 = $stmt1->fetchAll();

        $qb2 = $conn->createQueryBuilder()
            ->select('value.value AS title,
                count(download.owner_id) AS users')
            ->from('value, download')
            ->where($expr->eq('value.property_id', $propertyId))
            ->andWhere('download.resource_id = value.resource_id')
            ->groupBy('download.owner_id, value.value');
        $stmt2 = $conn->executeQuery($qb2);
        $result2 = $stmt2->fetchAll();

        $number = 0;
        foreach($result1 as $key => $a){
            foreach($result2 as $b){
                if($a['title'] == $b['title']){
                    ++$number;
                    $result1[$key]['users'] = $b['users'];
                    $result1[$key]['id']= $number;
                    break;
                }
                else {
                    $result1[$key]['users'] = null;
                    $result1[$key]['id']= null;
                }
            }
        }
        $title = '';
        foreach($result1 as $a){
            if ($a['id'] == $propertyId) {
                $result['title'] = $title = $a['title'];
                $result['past_items'] = $a['past_items'];
                $result['holding_items'] = $a['holding_items'];
                $result['downloaded_items'] = $a['downloaded_items'];
                break;
            }
            else{
                $result['title'] = null;
                $result['past_items'] = null;
                $result['holding_items'] = null;
                $result['downloaded_items'] = null;
            }
        }

        $result['most_readed_item'] = null;
        $result['most_readed_user_name'] = null;
        $result['most_readed_user_email'] = null;

        if ($title) {
            // $title = "Business";
            $qb3 = $conn->createQueryBuilder()
                ->select('value.resource_id AS most_readed_item_id')
                ->from('download, value')
                ->where('value.resource_id = download.resource_id')
                ->andWhere($expr->eq('value.property_id', $propertyId))
                ->andWhere($expr->eq('value.value', $title))
                ->groupBy('download.resource_id')
                ->orderBy('count(download.resource_id)', 'DESC');
            $stmt3 = $conn->executeQuery($qb3);
            $result3 = $stmt3->fetchAll();
            $most_readed_item_id = $result3[0]['most_readed_item_id'];

            $qb4 = $conn->createQueryBuilder()
                ->select('value AS value')
                ->from('value')
                ->where('property_id = 1')
                ->andWhere('resource_id = ' . $most_readed_item_id);
            $stmt4 = $conn->executeQuery($qb4);
            $result4 = $stmt4 ->fetchAll();
            $result['most_readed_item'] = $result4[0]['value'];

            $qb5 = $conn->createQueryBuilder()
                ->select('download.owner_id AS most_readed_user_id')
                ->from('download, value')
                ->where('value.resource_id = download.resource_id')
                ->andWhere($expr->eq('value.property_id', $propertyId))
                ->andWhere($expr->eq('value.value', $title))
                ->groupBy('download.owner_id')
                ->orderBy('count(download.resource_id)', 'DESC');
            $stmt5 = $conn->executeQuery($qb5);
            $result5 = $stmt5->fetchAll();
            $most_readed_user_id = $result5[0]['most_readed_user_id'];

            $qb6 = $conn->createQueryBuilder()
                ->select('name AS user_name, email AS user_email')
                ->from('user')
                ->where($expr->eq('id', $most_readed_user_id));
            $stmt6 = $conn->executeQuery($qb6);
            $result6 = $stmt6 ->fetchAll();
            $result['most_readed_user_name'] = $result6[0]['user_name'];
            $result['most_readed_user_email'] = $result6[0]['user_email'];
        }

        $view = new ViewModel;
        $view->setVariable('result', $result);
        return $view;
    }

    protected function arrayOrderBy(array &$arr, $order = null, $sort_order = 'DESC')
    {
        if (is_null($order)) {
            return $arr;
        }
        $orders = is_array($order) ? $order : array_map('trim', explode(',', $order));
        usort($arr, function($a, $b) use($orders, $sort_order) {
            $result = array();
            foreach ($orders as $field) {
                // list($field, $sort) = array_map('trim', explode(' ', trim($value)));
                if (!(isset($a[$field]) && isset($b[$field]))) {
                    continue;
                }
                if (strcasecmp($sort_order, 'DESC') === 0) {
                    $tmp = $a;
                    $a = $b;
                    $b = $tmp;
                }
                if (is_numeric($a[$field]) && is_numeric($b[$field]) ) {
                    $result[] = $a[$field] - $b[$field];
                } else {
                    $result[] = strcmp($a[$field], $b[$field]);
                }
            }
            return implode('', $result);
        });
        return $arr;
    }

    /**
     * This action is used only for the site section.
     *
     * @todo Move this method inside a SiteAdmin controller.
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function statsAction()
    {
        $site = $this->currentSite();

        $query = $this->params()->fromQuery();
        $query['site_id'] = $site->id();
        $site_id = $query['site_id'];

        $new = [];
        $conn = $this->entityManager->getConnection();
        $expr = $conn->getExpressionBuilder();

        $new['title'] = $site->title();

        $qb5 = $conn->createQueryBuilder()
            ->select('user.name AS owner_name, user.email AS owner_email')
            ->from('user, site')
            ->where($expr->eq('site.id', $site_id))
            ->andWhere('user.id = site.owner_id');
        $stmt5 = $conn->executeQuery($qb5);
        $result5 = $stmt5->fetchAll();
        $new['owner_name'] = $result5[0]['owner_name'];
        $new['owner_email'] = $result5[0]['owner_email'];

        $qb2 = $conn->createQueryBuilder()
            ->select('value.value AS most_readed_item')
            ->from('site, site_permission, download, value')
            ->where('site.id = site_permission.site_id')
            ->andWhere('site_permission.user_id = download.owner_id')
            ->andWhere($expr->eq('site.id', $site_id))
            ->andWhere('value.resource_id = download.resource_id')
            ->andWhere('value.property_id = 1')
            ->groupBy('download.resource_id')
            ->orderBy('count(user_id)', 'DESC');
        $stmt2 = $conn->executeQuery($qb2);
        $result2 = $stmt2->fetchAll();
        $new['most_readed_item'] = $result2[0]['most_readed_item'];

        $qb3 = $conn->createQueryBuilder()
            ->select('site_permission.user_id AS user_id')
            ->from('site, site_permission, download')
            ->where('site.id = site_permission.site_id')
            ->andWhere('site_permission.user_id = download.owner_id')
            ->andWhere($expr->eq('site.id', $site_id))
            ->groupBy('site_permission.user_id')
            ->orderBy('count(download.resource_id)', 'DESC');
        $stmt3 = $conn->executeQuery($qb3);
        $result3 = $stmt3->fetchAll();

        $user_id = $result3[0]['user_id'];

        $qb4 = $conn->createQueryBuilder()
            ->select('name AS most_readed_user_name, email AS most_readed_user_email')
            ->from('user')
            ->where("id = $user_id");
        $stmt4 = $conn->executeQuery($qb4);
        $result4 = $stmt4->fetchAll();
        $new['most_readed_user_name'] = $result4[0]['most_readed_user_name'];
        $new['most_readed_user_email'] = $result4[0]['most_readed_user_email'];

        $qb6 = $conn->createQueryBuilder()
            ->select('site.title AS title,
                site.id AS id,
                sum( CASE WHEN download.STATUS = "past" THEN 1 ELSE 0 END ) AS past_items,
                sum( CASE WHEN download.STATUS = "held" THEN 1 ELSE 0 END ) AS holding_items,
                sum( CASE WHEN download.STATUS = "downloaded" THEN 1 ELSE 0 END ) AS downloaded_items,
                sum( 1 ) AS total')
            ->from('site, site_permission, download')
            ->where('site.id = site_permission.site_id')
            ->andWhere('site_permission.user_id = download.owner_id')
            ->andWhere($expr->eq('site.id', $site_id))
            ->groupBy('site.title')
            ->orderBy('site.id');
        $stmt6= $conn->executeQuery($qb6);
        $result6 = $stmt6->fetchAll();

        $new['past_items'] = $result6[0]['past_items'];;
        $new['holding_items'] = $result6[0]['holding_items'];;
        $new['downloaded_items'] = $result6[0]['downloaded_items'];;

        $view = new ViewModel;
        $view->setVariable('new', $new);
        // $view->setVariable('sites', $response->getContent());
        return $view;
    }

    public function batchIncludeAction()
    {
        $resourceIds = $this->params()->fromPost('resource_ids', []);
        if (empty($resourceIds)) {
            $this->messenger()->addWarning('No resources to add to top picks or trendings.'); // @translate
        } else {
            $key = $this->params()->fromPost('batch_action');
            $keys = [
                'top-pick' => 'downloadmanager_item_set_top_pick',
                'trending' => 'downloadmanager_item_set_trending',
            ];
            if (!isset($keys[$key])) {
                $this->messenger()->addWarning('No item set defined.'); // @translate
            } else {
                $itemSetId = (int) $this->settings()->get($keys[$key]);
                if (empty($itemSetId)) {
                    $this->messenger()->addError('No item set has been set for top picks or trendings.'); // @translate
                } else {
                    $api = $this->api();
                    foreach ($resourceIds as $resourceId) {
                        /** @var \DownloadManager\Api\Representation\DownloadRepresentation $download */
                        $download = $api->searchOne('downloads', ['id' => $resourceId])->getContent();
                        if (!$download) {
                            continue;
                        }
                        $resource = $download->resource();
                        if (empty($resource)) {
                            continue;
                        }
                        $resourceData = json_decode(json_encode($resource->jsonSerialize()), true);
                        $hasItemSet = false;
                        if (empty($resourceData['o:item_set'])) {
                            $resourceData['o:item_set'] = [];
                        }
                        foreach ($resourceData['o:item_set'] as $itemSet) {
                            if ($itemSet['o:id'] === $itemSetId) {
                                $hasItemSet = true;
                                break;
                            }
                        }
                        if (!$hasItemSet) {
                            $resourceData['o:item_set']['o:id'] = $itemSetId;
                        }
                       $api->update('items', $resource->id(), $resourceData);
                    }
                    $this->messenger()->addSuccess(new Message(
                        '%d items successfully added to top picks or trendings.', // @translate
                        count($resourceIds)
                    ));
                }
            }
        }
        return $this->redirect()->toRoute(null, ['action' => 'by-item'], true);
    }

    /**
     * Release a downloaded item.
     *
     * @return \Zend\View\Model\JsonModel
     */
    public function releaseAction()
    {
        $downloadId = $this->params()->fromRoute('id');
        if (empty($downloadId)) {
            return $this->jsonError('Id not set.');
        }

        try {
            /** @var \DownloadManager\Api\Representation\DownloadRepresentation $download */
            $download = $this->api()->read('downloads', $downloadId)->getContent();
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            return $this->jsonError('Download not found.', Response::STATUS_CODE_404);
        }

        if (!$download->isDownloaded()) {
            return $this->jsonError('This resource is not downloaded.');
        }

        $owner = $this->api()
            ->read('users', $download->owner()->id(), [], ['responseContent' => 'resource'])
            ->getContent();
        $resource = $download->resource();

        // TODO Factorize release download (copîed from site).

        // Remove protected files, because the password is no more unique, but
        // the space on the server is limited.
        $media = $resource->primaryMedia();
        $this->removeAccessFiles($owner, $media);

        // Set data as past.
        $data = [];
        $data['o:status'] = Download::STATUS_PAST;
        $log = $download->log() ?: [];
        $log[] = ['action' => 'admin released', 'date' => (new \DateTime('now'))->format('Y-m-d H:i:s')];
        $data['o-module-access:log'] = $log;
        $response = $this->api()
            ->update('downloads', $download->id(), $data, [], ['isPartial' => true]);
        if (!$response) {
            return $this->jsonError(
                'An internal error occurred.', // @translate
                Response::STATUS_CODE_500);
        }

        $result = ['status' => 'released'];
        return new JsonModel($result);
    }

    /**
     * Reset the keys of a user.
     *
     * @return \Zend\View\Model\JsonModel
     */
    public function userKeyResetAction()
    {
        $userEmail = $this->params('email');
        if ($userEmail) {
            $response = $this->api()->searchOne('users', ['email' => $userEmail], ['responseContent' => 'resource']);
            if (empty($response)) {
                return $this->jsonError(new Message(
                    'No user with email "%s".' // @translate
                        , $userEmail),
                    Response::STATUS_CODE_400);
            }
        } else {
            $userId = $this->params('id');
            if (empty($userId)) {
                return $this->jsonError(
                    'No id or email provided for this action.', // @translate
                    Response::STATUS_CODE_400);
            }
            $response = $this->api()->read('users', $userId, [], ['responseContent' => 'resource']);
            if (empty($response)) {
                return $this->jsonError(new Message(
                    'No user with id "%s".' // @translate
                        , $userId),
                    Response::STATUS_CODE_400);
            }
        }
        $user = $response->getContent();

        $entityManager = $this->entityManager;
        $apiKeyRepository = $entityManager->getRepository('Omeka\Entity\ApiKey');
        foreach (['main', 'user_key', 'server_key'] as $label) {
            $apiKeys = $apiKeyRepository->findBy(['label' => $label, 'owner' => $user]);
            foreach ($apiKeys as $apiKey) {
                $entityManager->remove($apiKey);
            }
        }
        $entityManager->flush();
        return new JsonModel(['result' => 'success']);
    }

    /**
     * Reset all the downloads. To be used only in case of emergency!
     *
     * @todo Add a confirm page before truncate the downloads.
     *
     * @return \Zend\View\Model\JsonModel
     */
    public function resetAllDownloadsAction()
    {
        if ($this->truncateTable(Download::class)) {
            return new JsonModel(['result' => 'error']);
        }
        return new JsonModel(['result' => 'success']);
    }

    /**
     * Reset all the downloads logs. To be used only in case of emergency!
     *
     * @todo Add a confirm page before truncate the download logs.
     *
     * @return \Zend\View\Model\JsonModel
     */
    public function resetAllDownloadLogsAction()
    {
        if (!$this->truncateTable(DownloadLog::class)) {
            return new JsonModel(['result' => 'error']);
        }
        return new JsonModel(['result' => 'success']);
    }

    /**
     * Truncate a table.
     *
     * @link https://stackoverflow.com/questions/9686888/how-to-truncate-a-table-using-doctrine-2
     * @param string $entityClass
     * @return bool
     */
    protected function truncateTable($entityClass)
    {
        return false;

        $entityManager = $this->entityManager;
        $connection = $entityManager->getConnection();
        $dbPlatform = $connection->getDatabasePlatform();
        $connection->query('SET FOREIGN_KEY_CHECKS = 0');
        $classMetadata = $entityManager->getClassMetadata($entityClass);
        $query = $dbPlatform->getTruncateTableSql($classMetadata->getTableName());
        $connection->executeUpdate($query);
        $query = 'ALTER TABLE ' . $classMetadata->getTableName() . ' AUTO_INCREMENT = 1;';
        $connection->exec($query);
        $connection->query('SET FOREIGN_KEY_CHECKS = 1');
        return true;
    }

    /**
     * Helper to return a message of error as json.
     *
     * @param string $message
     * @param int $statusCode
     * @return \Zend\View\Model\JsonModel
     */
    protected function jsonError($message, $statusCode)
    {
        $response = $this->getResponse();
        $response->setStatusCode($statusCode);
        return new JsonModel([
            'result' => 'error',
            'message' => $message,
        ]);
    }
}
