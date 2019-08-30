<?php
namespace DownloadManager\Mvc\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;

class UniqueServerKey extends AbstractPlugin
{
    /**
     * @var string
     */
    protected $uniqueServerKey;

    /**
     * @param string $uniqueServerKey
     */
    public function __construct($uniqueServerKey = null)
    {
        $this->uniqueServerKey = $uniqueServerKey;
    }

    /**
     * Get the unique server key if any.
     *
     * @return string|null
     */
    public function __invoke()
    {
        return $this->uniqueServerKey;
    }
}
