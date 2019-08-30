<?php
namespace VatiruLibrary\Mvc\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;

/**
 * Get the current vatiru api version.
 */
class VatiruApiVersion extends AbstractPlugin
{
    /**
     * @var string
     */
    protected $apiVersion;

    public function __construct($apiVersion)
    {
        $this->apiVersion = $apiVersion;
    }

    /**
     * Get the current vatiru api version.
     *
     * @return string
     */
    public function __invoke()
    {
        return $this->apiVersion;
    }
}
