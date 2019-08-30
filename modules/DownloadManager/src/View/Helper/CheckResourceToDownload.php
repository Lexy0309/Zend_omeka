<?php
namespace DownloadManager\View\Helper;

use DownloadManager\Mvc\Controller\Plugin\CheckResourceToDownload as CheckResourceToDownloadPlugin;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Zend\View\Helper\AbstractHelper;

/**
 * Determine if a resource is downloadable.
 */
class CheckResourceToDownload extends AbstractHelper
{
    /**
     * @var CheckResourceToDownloadPlugin
     */
    protected $checkResourceToDownload;

    /**
     * @param CheckResourceToDownloadPlugin $checkResourceToDownload
     */
    public function __construct(CheckResourceToDownloadPlugin $checkResourceToDownload)
    {
        $this->checkResourceToDownload = $checkResourceToDownload;
    }

    /**
     * Determine if a resource is downloadable by anybody.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @return bool|null|array True if downloadable, false if not, else an array
     * containing a message and a status code.
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource)
    {
        $checkResourceToDownload = $this->checkResourceToDownload;
        return $checkResourceToDownload($resource);
    }
}
