<?php
namespace DownloadManager\View\Helper;

use DownloadManager\Mvc\Controller\Plugin\CheckRightToDownload as CheckRightToDownloadPlugin;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Entity\User;
use Zend\View\Helper\AbstractHelper;

/**
 * Determine the status of a resource to download by a user.
 */
class CheckRightToDownload extends AbstractHelper
{
    /**
     * @var CheckRightToDownloadPlugin
     */
    protected $checkRightToDownload;

    /**
     * @param CheckRightToDownloadPlugin $checkRightToDownload
     */
    public function __construct(CheckRightToDownloadPlugin $checkRightToDownload)
    {
        $this->checkRightToDownload = $checkRightToDownload;
    }

    /**
     * Determine the right of a user to download a resource.
     *
     * Note: The resource must be checked.
     *
     * @param AbstractResourceEntityRepresentation $resource Checked resource
     * (exists, public, with file).
     * @param User $user The current user if not defined.
     * @return bool|null|array True if downloadable, false if not, else an array
     * containing a message and a status code.
     */
    public function __invoke(
        AbstractResourceEntityRepresentation $resource,
        User $user = null
    ) {
        $checkRightToDownload = $this->checkRightToDownload;
        return $checkRightToDownload($resource, $user);
    }
}
