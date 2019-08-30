<?php
namespace DownloadManager\View\Helper;

use Omeka\Module\Manager as ModuleManager;
use Zend\View\Helper\AbstractHelper;

/**
 * Helper to check if a module is installed.
 */
class IsModuleActive extends AbstractHelper
{
    /**
     * @var ModuleManager
     */
    protected $moduleManager;

    /**
     * @param ModuleManager $moduleManager
     */
    public function __construct(ModuleManager $moduleManager)
    {
        $this->moduleManager = $moduleManager;
    }

    /**
     * Check if a module is installed
     *
     * @param string $moduleName
     * @return bool
     */
    public function __invoke($moduleName)
    {
        $module = $this->moduleManager->getModule($moduleName);
        return $module && $module->getState() === ModuleManager::STATE_ACTIVE;
    }
}
