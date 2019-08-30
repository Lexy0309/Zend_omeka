<?php
namespace DownloadManager\Mvc\Controller\Plugin;

use DownloadManager\Entity\Download;
use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use Zend\Mvc\Controller\PluginManager;

/**
 * Determine how many resources are available to download.
 */
class TotalAvailable extends AbstractPlugin
{
    /**
     * @var ApiManager
     */
    protected $api;

    /**
     * @var PluginManager
     */
    protected $plugins;

    /**
     * @param ApiManager $api
     * @param PluginManager $plugins
     */
    public function __construct(ApiManager $api, PluginManager $plugins)
    {
        $this->api = $api;
        $this->plugins = $plugins;
    }

    /**
     * Determine the availability of a resource, independantly from the user.
     *
     * The availability isn't related to the fact the resource is public or not.
     * The availability does not depend on the user, who may have downloaded
     * the resource (when this is the case, it is always available for this user
     * until expiration, so check it with isResourceAvailableForUser).
     *
     * Note: the resource must be checked before.
     *
     * @param AbstractResourceEntityRepresentation $resource Checked resource
     * (exists, public, with file).
     * @return int -1 if available without limit, 0 if not, the number of
     * available copies if limited.
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource)
    {
        // Check the resource type to get the rights (the item for the media).
        $resourceType = $resource->getControllerName();
        switch ($resourceType) {
            case 'item-set':
                return -1;
            case 'item':
                break;
            case 'media':
                $resource = $resource->item();
                break;
        }

        $totalExemplars = $this->plugins->get('totalExemplars');
        $totalExemplars = $totalExemplars($resource);
        if ($totalExemplars <= 0) {
            return -1;
        }

        // Check if the number of copies is greater than the number of
        // downloads.
        $totalDownloaded = $this->totalDownloaded($resource);
        if ($totalExemplars <= $totalDownloaded) {
            return 0;
        }

        return $totalExemplars - $totalDownloaded;
    }

    protected function totalDownloaded(AbstractResourceEntityRepresentation $resource)
    {
        $total = $this->api->search('downloads', [
            'resource_id' => $resource->id(),
            'status' => Download::STATUS_DOWNLOADED,
        ])->getTotalResults();
        return $total;
    }
}
