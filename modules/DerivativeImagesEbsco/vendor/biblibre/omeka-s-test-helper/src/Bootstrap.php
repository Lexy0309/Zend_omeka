<?php

namespace OmekaTestHelper;

class Bootstrap
{
    protected static $application;

    public static function bootstrap($moduleDir)
    {
        require_once $moduleDir . '/../../../bootstrap.php';

        //make sure error reporting is on for testing
        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        // Install a fresh database.
        file_put_contents('php://stdout', "Dropping test database schema...\n");
        \Omeka\Test\DbTestCase::dropSchema();
        file_put_contents('php://stdout', "Creating test database schema...\n");
        \Omeka\Test\DbTestCase::installSchema();
    }

    public static function getApplication()
    {
        if (!isset(self::$application)) {
            self::$application = \Omeka\Test\DbTestCase::getApplication();
        }

        return self::$application;
    }

    public static function loginAsAdmin()
    {
        $serviceLocator = self::getApplication()->getServiceManager();
        $auth = $serviceLocator->get('Omeka\AuthenticationService');
        $adapter = $auth->getAdapter();
        $adapter->setIdentity('admin@example.com');
        $adapter->setCredential('root');
        $auth->authenticate();
    }

    public static function enableModule($name)
    {
        $serviceLocator = self::getApplication()->getServiceManager();
        $moduleManager = $serviceLocator->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule($name);
        if ($module->getState() !== \Omeka\Module\Manager::STATE_ACTIVE) {
            $moduleManager->install($module);
        }
    }
}
