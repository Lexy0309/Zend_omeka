<?php
namespace DownloadManager\Mvc\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;

class UniqueUserKey extends AbstractPlugin
{
    /**
     * @var string
     */
    protected $uniqueUserKey;

    /**
     * @param string $uniqueUserKey
     */
    public function __construct($uniqueUserKey = null)
    {
        $this->uniqueUserKey = $uniqueUserKey;
    }

    /**
     * Get the unique user key if any.
     *
     * @return string|null
     */
    public function __invoke()
    {
        return $this->uniqueUserKey;
    }
}
