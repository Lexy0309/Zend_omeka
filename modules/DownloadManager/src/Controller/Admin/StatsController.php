<?php
namespace DownloadManager\Controller\Admin;

use Doctrine\ORM\EntityManager;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class StatsController extends AbstractActionController
{
    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @param EntityManager $entityManager
     */
    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function searchAction()
    {
        $view = new ViewModel;
        $view->setVariable('query', $this->params()->fromQuery());
        return $view;
    }

    public function showAction()
    {
        $params = explode('_', $this->params('id'));
        $site_id = $params[0];
        $holding_items = $params[1];
        $downloaded_items = $params[2];

        $response = $this->api()->read('sites', $site_id);

        $conn = $this->entityManager->getConnection();

        $qb1 = $conn->createQueryBuilder()
            ->select('site.title as title')
            ->from('site')
            ->where("site.id = $site_id");
        $stmt1 = $conn->executeQuery($qb1);
        $result1 = $stmt1->fetchAll();
        $new['title'] = $result1[0]['title'];

        $qb5 = $conn->createQueryBuilder()
        ->select('user.name as owner_name, user.email as owner_email')
        ->from('user, site')
        ->where("site.id = $site_id and user.id = site.owner_id");
        $stmt5 = $conn->executeQuery($qb5);
        $result5 = $stmt5->fetchAll();
        $new['owner_name'] = $result5[0]['owner_name'];
        $new['owner_email'] = $result5[0]['owner_email'];

        $qb2 = $conn->createQueryBuilder()
            ->select('value.value as most_readed_item')
            ->from('site,
            site_permission,
            download,
                value')
            ->where("site.id = site_permission.site_id
            AND site_permission.user_id = download.owner_id
            AND site.id = $site_id
            AND value.resource_id = download.resource_id
            AND value.property_id = 1 ")
            ->groupby('download.resource_id')
            ->orderby('count(user_id)', 'DESC');
        $stmt2 = $conn->executeQuery($qb2);
        $result2 = $stmt2->fetchAll();
        $new['most_readed_item'] = $result2[0]['most_readed_item'];

        $qb3 = $conn->createQueryBuilder()
            ->select('site_permission.user_id as user_id')
            ->from('site, site_permission, download')
            ->where("site.id = site_permission.site_id and site_permission.user_id = download.owner_id and site.id = $site_id")
            ->groupby('site_permission.user_id')
            ->orderby('count(download.resource_id)', 'DESC');
        $stmt3 = $conn->executeQuery($qb3);
        $result3 = $stmt3->fetchAll();

        $user_id = $result3[0]['user_id'];

        $qb4 = $conn->createQueryBuilder()
            ->select('name as most_readed_user_name, email as most_readed_user_email')
            ->from('user')
            ->where("id = $user_id");
        $stmt4 = $conn->executeQuery($qb4);
        $result4 = $stmt4->fetchAll();
        $new['most_readed_user_name'] = $result4[0]['most_readed_user_name'];
        $new['most_readed_user_email'] = $result4[0]['most_readed_user_email'];
        $new['holding_items'] = $holding_items;
        $new['reading_items'] = $downloaded_items;
        $view = new ViewModel;
        $view->setVariable('new', $new);
        // $view->setVariable('sites', $response->getContent());
        return $view;
    }

    public function statsAction() {

        $params = explode('_', $this->params('id'));
        $site_id = $params[0];
        $holding_items = $params[1];
        $downloaded_items = $params[2];

        $response = $this->api()->read('sites', $site_id);

        $conn = $this->entityManager->getConnection();

        $qb1 = $conn->createQueryBuilder()
            ->select('site.title as title')
            ->from('site')
            ->where("site.id = $site_id");
        $stmt1 = $conn->executeQuery($qb1);
        $result1 = $stmt1->fetchAll();
        $new['title'] = $result1[0]['title'];

        $qb5 = $conn->createQueryBuilder()
        ->select('user.name as owner_name, user.email as owner_email')
        ->from('user, site')
        ->where("site.id = $site_id and user.id = site.owner_id");
        $stmt5 = $conn->executeQuery($qb5);
        $result5 = $stmt5->fetchAll();
        $new['owner_name'] = $result5[0]['owner_name'];
        $new['owner_email'] = $result5[0]['owner_email'];

        $qb2 = $conn->createQueryBuilder()
            ->select('value.value as most_readed_item')
            ->from('site,
            site_permission,
            download,
                value')
            ->where("site.id = site_permission.site_id
            AND site_permission.user_id = download.owner_id
            AND site.id = $site_id
            AND value.resource_id = download.resource_id
            AND value.property_id = 1 ")
            ->groupby('download.resource_id')
            ->orderby('count(user_id)', 'DESC');
        $stmt2 = $conn->executeQuery($qb2);
        $result2 = $stmt2->fetchAll();
        $new['most_readed_item'] = $result2[0]['most_readed_item'];

        $qb3 = $conn->createQueryBuilder()
            ->select('site_permission.user_id as user_id')
            ->from('site, site_permission, download')
            ->where("site.id = site_permission.site_id and site_permission.user_id = download.owner_id and site.id = $site_id")
            ->groupby('site_permission.user_id')
            ->orderby('count(download.resource_id)', 'DESC');
        $stmt3 = $conn->executeQuery($qb3);
        $result3 = $stmt3->fetchAll();

        $user_id = $result3[0]['user_id'];

        $qb4 = $conn->createQueryBuilder()
            ->select('name as most_readed_user_name, email as most_readed_user_email')
            ->from('user')
            ->where("id = $user_id");
        $stmt4 = $conn->executeQuery($qb4);
        $result4 = $stmt4->fetchAll();
        $new['most_readed_user_name'] = $result4[0]['most_readed_user_name'];
        $new['most_readed_user_email'] = $result4[0]['most_readed_user_email'];
        $new['holding_items'] = $holding_items;
        $new['reading_items'] = $downloaded_items;
        $view = new ViewModel;
        $view->setVariable('new', $new);
        // $view->setVariable('sites', $response->getContent());
        return $view;
    }

}
