<?php
namespace Omeka\Controller\Admin;

use Doctrine\ORM\EntityManager;
use Omeka\Entity\ApiKey;
use Omeka\Form\ConfirmForm;
use Omeka\Form\UserBatchUpdateForm;
use Omeka\Form\UserForm;
use Omeka\Mvc\Exception;
use Omeka\Stdlib\Message;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class SubjectController extends AbstractActionController
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

    public function browseAction()
    {
        $this->setBrowseDefaults('email', 'asc');
        $response = $this->api()->search('users', $this->params()->fromQuery());
        $this->paginator($response->getTotalResults(), $this->params()->fromQuery('page'));

        $formDeleteSelected = $this->getForm(ConfirmForm::class);
        $formDeleteSelected->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'batch-delete'], true));
        $formDeleteSelected->setButtonLabel('Confirm Delete'); // @translate
        $formDeleteSelected->setAttribute('id', 'confirm-delete-selected');

        $formDeleteAll = $this->getForm(ConfirmForm::class);
        $formDeleteAll->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'batch-delete-all'], true));
        $formDeleteAll->setButtonLabel('Confirm Delete'); // @translate
        $formDeleteAll->setAttribute('id', 'confirm-delete-all');
        $formDeleteAll->get('submit')->setAttribute('disabled', true);

        $view = new ViewModel;
        $view->setVariable('users', $response->getContent());
        $view->setVariable('formDeleteSelected', $formDeleteSelected);
        $view->setVariable('formDeleteAll', $formDeleteAll);
        return $view;
    }

    public function showAction()
    {
        $id = $this->params('id');
        $property_type = $_SESSION['property_type'];
        switch ($property_type) {
            case 'subject':
                $property_id = 3;
                break;
            case 'language':
                $property_id = 12;
                break;
            default:
                $property_id = 3;
                break;
        }
        
       
        $conn = $this->entityManager->getConnection();
      
        $qb1 = $conn->createQueryBuilder()
        ->select('value.value as title,
        sum(case when download.status = "held" THEN 1 ELSE 0 END ) as holding_items,
        sum(case when download.status = "past" THEN 1 ELSE 0 END ) as reading_items,
        sum(case when download.status = "downloaded" THEN 1 ELSE 0 END ) as downloaded_items,
        sum(1) as total')
        ->from('value, download')
        ->where("value.property_id = $property_id and download.resource_id = value.resource_id")
        ->groupby('value.value');
        $stmt1 = $conn->executeQuery($qb1);
        $result1 = $stmt1->fetchAll();
        
        $qb2 = $conn->createQueryBuilder()
        ->select('value.value as title, 
        count(download.owner_id) AS users')
        ->from('value, download')
        ->where("value.property_id = $property_id and download.resource_id = value.resource_id")
        ->groupby('download.owner_id, value.value');
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
                    $result1[$key]['id']= null;
                }
            }
        }
        foreach($result1 as $a){
            if ($a['id'] ==  $id) {
                $result['title'] = $title = $a['title'];
                $result['reading_items'] = $a['reading_items'];
                $result['downloaded_items'] = $a['downloaded_items'];
                break;
            }
            else{
                $result['title'] = null;
                $result['reading_items'] = null;
                $result['downloaded_items'] = null;
            }
        }
        $qb3 = $conn->createQueryBuilder()
            ->select('value.resource_id as most_readed_item_id')
            ->from('download, VALUE')
            ->where(" VALUE.resource_id = download.resource_id 
            and value.property_id = $property_id
            and value.value = '$title'")
            ->groupby('download.resource_id')
            ->orderby('count(download.resource_id)', 'DESC');
        $stmt3 = $conn->executeQuery($qb3);
        $result3 = $stmt3->fetchAll();
        $most_readed_item_id = $result3[0]['most_readed_item_id'];

        $qb4 = $conn->createQueryBuilder()
            ->select('value as value')
            ->from('value')
            ->where("property_id = 1 and resource_id = $most_readed_item_id");
        $stmt4 = $conn->executeQuery($qb4);
        $result4 = $stmt4 ->fetchAll();
        $result['most_readed_item'] = $result4[0]['value'];

        $qb5 = $conn->createQueryBuilder()
        ->select('download.owner_id as most_readed_user_id')
        ->from('download, VALUE')
        ->where(" VALUE.resource_id = download.resource_id 
        and value.property_id = $property_id
        and value.value = '$title'")
        ->groupby('download.owner_id')
        ->orderby('count(download.resource_id)', 'DESC');
        $stmt5 = $conn->executeQuery($qb5);
        $result5 = $stmt5->fetchAll();
        $most_readed_user_id = $result5[0]['most_readed_user_id'];

        $qb6 = $conn->createQueryBuilder()
            ->select('name as user_name, email as user_email')
            ->from('user')
            ->where("id = $most_readed_user_id");
        $stmt6 = $conn->executeQuery($qb6);
        $result6 = $stmt6 ->fetchAll();
        $result['most_readed_user_name'] = $result6[0]['user_name'];
        $result['most_readed_user_email'] = $result6[0]['user_email'];
        
        $view = new ViewModel;
        $view->setVariable('result', $result);
        return $view;
    }

   
    public function deleteConfirmAction()
    {
        $resource = $this->api()->read('users', $this->params('id'))->getContent();

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setTemplate('common/delete-confirm-details');
        $view->setVariable('resource', $resource);
        $view->setVariable('resourceLabel', 'user'); // @translate
        $view->setVariable('partialPath', 'omeka/admin/user/show-details');
        return $view;
    }

    public function deleteAction()
    {
        if ($this->getRequest()->isPost()) {
            $form = $this->getForm(ConfirmForm::class);
            $form->setData($this->getRequest()->getPost());
            if ($form->isValid()) {
                $response = $this->api($form)->delete('users', $this->params('id'));
                if ($response) {
                    $this->messenger()->addSuccess('User successfully deleted'); // @translate
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }
        return $this->redirect()->toRoute(
            'admin/default',
            ['action' => 'browse'],
            true
        );
    }

    public function batchDeleteAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $resourceIds = $this->params()->fromPost('resource_ids', []);
        $resourceIds = array_filter(array_unique(array_map('intval', $resourceIds)));
        if (!$resourceIds) {
            $this->messenger()->addError('You must select at least one user to batch delete.'); // @translate
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $userId = $this->identity()->getId();
        $key = array_search($userId, $resourceIds);
        if ($key !== false) {
            $this->messenger()->addError('You can’t delete yourself.'); // @translate
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $form = $this->getForm(ConfirmForm::class);
        $form->setData($this->getRequest()->getPost());
        if ($form->isValid()) {
            $response = $this->api($form)->batchDelete('users', $resourceIds, [], ['continueOnError' => true]);
            if ($response) {
                $this->messenger()->addSuccess('Users successfully deleted'); // @translate
            }
        } else {
            $this->messenger()->addFormErrors($form);
        }
        return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
    }

    public function batchDeleteAllAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        // Derive the query, removing limiting and sorting params.
        $query = json_decode($this->params()->fromPost('query', []), true);
        unset($query['submit'], $query['page'], $query['per_page'], $query['limit'],
            $query['offset'], $query['sort_by'], $query['sort_order']);

        $form = $this->getForm(ConfirmForm::class);
        $form->setData($this->getRequest()->getPost());
        if ($form->isValid()) {
            $job = $this->jobDispatcher()->dispatch('Omeka\Job\BatchDelete', [
                'resource' => 'users',
                'query' => $query,
            ]);
            $this->messenger()->addSuccess('Deleting users. This may take a while.'); // @translate
        } else {
            $this->messenger()->addFormErrors($form);
        }
        return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
    }

    /**
     * Batch update selected users (except current one).
     */
    public function batchEditAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $resourceIds = $this->params()->fromPost('resource_ids', []);
        $resourceIds = array_filter(array_unique(array_map('intval', $resourceIds)));
        if (!$resourceIds) {
            $this->messenger()->addError('You must select at least one user to batch edit.'); // @translate
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $userId = $this->identity()->getId();
        $key = array_search($userId, $resourceIds);
        if ($key !== false) {
            $this->messenger()->addError('For security reasons, you can’t batch edit yourself.'); // @translate
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $resources = [];
        foreach ($resourceIds as $resourceId) {
            $resources[] = $this->api()->read('users', $resourceId)->getContent();
        }

        $form = $this->getForm(UserBatchUpdateForm::class);
        $form->setAttribute('id', 'batch-edit-user');
        if ($this->params()->fromPost('batch_update')) {
            $data = $this->params()->fromPost();
            $form->setData($data);

            if ($form->isValid()) {
                $data = $form->preprocessData();

                foreach ($data as $collectionAction => $properties) {
                    $this->api($form)->batchUpdate('users', $resourceIds, $properties, [
                        'continueOnError' => true,
                        'collectionAction' => $collectionAction,
                    ]);
                }

                $this->messenger()->addSuccess('Users successfully edited'); // @translate
                return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setVariable('form', $form);
        $view->setVariable('resources', $resources);
        $view->setVariable('query', []);
        $view->setVariable('count', null);
        return $view;
    }

    /**
     * Batch update all users (except current one) returned from a query.
     */
    public function batchEditAllAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        // Derive the query, removing limiting and sorting params.
        $query = json_decode($this->params()->fromPost('query', []), true);
        unset($query['submit'], $query['page'], $query['per_page'], $query['limit'],
            $query['offset'], $query['sort_by'], $query['sort_order']);
        // TODO Count without the current user.
        $count = $this->api()->search('users', ['limit' => 0] + $query)->getTotalResults();

        $form = $this->getForm(UserBatchUpdateForm::class);
        $form->setAttribute('id', 'batch-edit-user');
        if ($this->params()->fromPost('batch_update')) {
            $data = $this->params()->fromPost();
            $form->setData($data);

            if ($form->isValid()) {
                $data = $form->preprocessData();

                $job = $this->jobDispatcher()->dispatch('Omeka\Job\BatchUpdate', [
                    'resource' => 'users',
                    'query' => $query,
                    'data' => isset($data['replace']) ? $data['replace'] : [],
                    'data_remove' => isset($data['remove']) ? $data['remove'] : [],
                    'data_append' => isset($data['append']) ? $data['append'] : [],
                ]);

                $this->messenger()->addSuccess('Editing users. This may take a while.'); // @translate
                return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setTemplate('omeka/admin/user/batch-edit.phtml');
        $view->setVariable('form', $form);
        $view->setVariable('resources', []);
        $view->setVariable('query', $query);
        $view->setVariable('count', $count);
        return $view;
    }
}
