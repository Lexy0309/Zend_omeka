<?php

namespace OmekaTestHelper\Controller;

use Omeka\Test\AbstractHttpControllerTestCase;
use Zend\Http\Request as HttpRequest;

abstract class OmekaControllerTestCase extends AbstractHttpControllerTestCase
{
    protected function postDispatch($url, $data)
    {
        return $this->dispatch($url, HttpRequest::METHOD_POST, $data);
    }

    protected function resetApplication()
    {
        $this->application = null;
    }

    protected function getServiceLocator()
    {
        return $this->getApplication()->getServiceManager();
    }

    protected function api()
    {
        return $this->getServiceLocator()->get('Omeka\ApiManager');
    }

    protected function settings()
    {
        return $this->getServiceLocator()->get('Omeka\Settings');
    }

    protected function getEntityManager()
    {
        return $this->getServiceLocator()->get('Omeka\EntityManager');
    }

    protected function login($email, $password)
    {
        $serviceLocator = $this->getServiceLocator();
        $auth = $serviceLocator->get('Omeka\AuthenticationService');
        $adapter = $auth->getAdapter();
        $adapter->setIdentity($email);
        $adapter->setCredential($password);
        return $auth->authenticate();
    }

    protected function loginAsAdmin()
    {
        $this->login('admin@example.com', 'root');
    }

    protected function logout()
    {
        $serviceLocator = $this->getServiceLocator();
        $auth = $serviceLocator->get('Omeka\AuthenticationService');
        $auth->clearIdentity();
    }

    protected function urlFromRoute($name = null, $params = [], $options = [], $reuseMatchedParams = false)
    {
        $url = $this->getServiceLocator()->get('ViewHelperManager')->get('url');

        return $url($name, $params, $options, $reuseMatchedParams);
    }

    protected function moduleConfigureUrl($id)
    {
        $params = [
            'controller' => 'module',
            'action' => 'configure',
        ];
        $options = [
            'query' => ['id' => $id],
        ];

        return $this->urlFromRoute('admin/default', $params, $options);
    }

    protected function persistAndSave($entity)
    {
        $em = $this->getServiceLocator()->get('Omeka\EntityManager');
        $em->persist($entity);
        $em->flush();
    }

    protected function cleanTable($table_name)
    {
        $connection = $this->getServiceLocator()->get('Omeka\Connection');
        $connection->exec('DELETE FROM ' . $table_name);
    }

    protected function setSettings($id, $value)
    {
        $this->settings()->set($id, $value);
    }
}
