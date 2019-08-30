<?php
namespace Log\Controller\Admin;

use Log\Form\SearchForm;
use Omeka\Form\ConfirmForm;
use Omeka\Stdlib\Message;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class LogController extends AbstractActionController
{
    public function browseAction()
    {
        $this->setBrowseDefaults('created');
        $response = $this->api()->search('logs', $this->params()->fromQuery());
        $this->paginator($response->getTotalResults(), $this->params()->fromQuery('page'));

        $formSearch = $this->getForm(SearchForm::class);
        $formSearch->setAttribute('action', $this->url()->fromRoute(null, ['action' => 'browse'], true));
        $formSearch->setAttribute('id', 'log-search');
        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $formSearch->setData($data);
        } elseif ($this->getRequest()->isGet()) {
            $data = $this->params()->fromQuery();
            $formSearch->setData($data);
        }

        $formDeleteSelected = $this->getForm(ConfirmForm::class);
        $formDeleteSelected->setAttribute('action', $this->url()->fromRoute('admin/log/default', ['action' => 'batch-delete'], true));
        $formDeleteSelected->setButtonLabel('Confirm delete'); // @translate
        $formDeleteSelected->setAttribute('id', 'confirm-delete-selected');

        $formDeleteAll = $this->getForm(ConfirmForm::class);
        $formDeleteAll->setAttribute('action', $this->url()->fromRoute('admin/log/default', ['action' => 'batch-delete-all'], true));
        $formDeleteAll->setButtonLabel('Confirm delete'); // @translate
        $formDeleteAll->setAttribute('id', 'confirm-delete-all');
        $formDeleteAll->get('submit')->setAttribute('disabled', true);

        $view = new ViewModel;
        $logs = $response->getContent();
        $view->setVariable('logs', $logs);
        $view->setVariable('resources', $logs);
        $view->setVariable('formSearch', $formSearch);
        $view->setVariable('formDeleteSelected', $formDeleteSelected);
        $view->setVariable('formDeleteAll', $formDeleteAll);
        return $view;
    }

    public function showDetailsAction()
    {
        $linkTitle = false;
        $response = $this->api()->read('logs', $this->params('id'));
        $log = $response->getContent();

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setVariable('linkTitle', $linkTitle);
        $view->setVariable('resource', $log);
        $view->setVariable('log', $log);
        return $view;
    }

    public function deleteConfirmAction()
    {
        $linkTitle = false;
        $response = $this->api()->read('logs', $this->params('id'));
        $log = $response->getContent();

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setTemplate('common/delete-confirm-details');
        $view->setVariable('resource', $log);
        $view->setVariable('resourceLabel', 'log'); // @translate
        $view->setVariable('partialPath', 'log/admin/log/show-details');
        $view->setVariable('linkTitle', $linkTitle);
        $view->setVariable('log', $log);
        return $view;
    }

    public function deleteAction()
    {
        if ($this->getRequest()->isPost()) {
            $form = $this->getForm(ConfirmForm::class);
            $form->setData($this->getRequest()->getPost());
            if ($form->isValid()) {
                $response = $this->api($form)->delete('logs', $this->params('id'));
                if ($response) {
                    $this->messenger()->addSuccess('Log successfully deleted'); // @translate
                } else {
                    $this->messenger()->addError('An error occured during deletion of logs'); // @translate
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }
        return $this->redirect()->toRoute(
            'admin/log',
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
        if (!$resourceIds) {
            $this->messenger()->addError('You must select at least one log to batch delete.'); // @translate
            return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
        }

        $form = $this->getForm(ConfirmForm::class);
        $form->setData($this->getRequest()->getPost());
        if ($form->isValid()) {
            $response = $this->api($form)->batchDelete('logs', $resourceIds, [], ['continueOnError' => true]);
            if ($response) {
                $this->messenger()->addSuccess('Logs successfully deleted'); // @translate
            } else {
                $this->messenger()->addError('An error occured during deletion of logs'); // @translate
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
                'resource' => 'logs',
                'query' => $query,
            ]);
            $message = new Message(
                'Deleting logs in background (%sjob #%d%s). This may take a while', // @translate
                sprintf(
                    '<a href="%s">',
                    htmlspecialchars($this->url()->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()]))
                ),
                $job->getId(),
                '</a>'
            );
            $message->setEscapeHtml(false);
            $this->messenger()->addSuccess($message);
        } else {
            $this->messenger()->addFormErrors($form);
        }
        return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
    }
}
