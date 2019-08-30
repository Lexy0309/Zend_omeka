<?php
namespace DownloadManager\Mvc\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;

class HasUniqueKeys extends AbstractPlugin
{
    /**
     * @var bool
     */
    protected $uniqueKeys;

    /**
     * @param bool $hasUniqueKeys
     */
    public function __construct($hasUniqueKeys)
    {
        $this->uniqueKeys = $hasUniqueKeys;
    }

    /**
     * Check if the unique keys are enabled (user and server).
     *
     * @return bool
     */
    public function __invoke()
    {
        return $this->uniqueKeys;
    }
}
