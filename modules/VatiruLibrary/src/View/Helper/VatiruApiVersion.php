<?php
namespace VatiruLibrary\View\Helper;

use Zend\View\Helper\AbstractHelper;

/**
 * Get the current vatiru api version.
 */
class VatiruApiVersion extends AbstractHelper
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
