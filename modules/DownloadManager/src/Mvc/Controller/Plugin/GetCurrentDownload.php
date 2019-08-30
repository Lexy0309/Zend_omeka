<?php
namespace DownloadManager\Mvc\Controller\Plugin;

use DownloadManager\Api\Representation\DownloadRepresentation;
use DownloadManager\Entity\Download;
use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Entity\User;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

/**
 * Get the download from a resource and a user.
 */
class GetCurrentDownload extends AbstractPlugin
{
    /**
     * @var ApiManager
     */
    protected $api;

    /**
     * @param ApiManager $api
     */
    public function __construct(ApiManager $api)
    {
        $this->api = $api;
    }

    /**
     * Helper to get an unexpired Download for a resource by a user.
     *
     * A check is done for expiring downloads.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @param User $user
     * @return DownloadRepresentation|null
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource, User $user = null)
    {
        if (empty($resource) || empty($user)) {
            return null;
        }

        $result = $this->api->search('downloads', [
            'resource_id' => $resource->id(),
            'owner_id' => $user->getId(),
            'status' => [Download::STATUS_READY, Download::STATUS_HELD, Download::STATUS_DOWNLOADED],
        ])->getContent();
        if (empty($result)) {
            return;
        }

        $download = reset($result);
        if ($download->isExpiring()) {
            return;
        }

        return $download;
    }
}
