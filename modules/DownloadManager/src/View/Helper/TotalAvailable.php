<?php
namespace DownloadManager\View\Helper;

use DownloadManager\Mvc\Controller\Plugin\TotalAvailable as TotalAvailablePlugin;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Zend\View\Helper\AbstractHelper;

/**
 * Determine how many resources are available to download.
 */
class TotalAvailable extends AbstractHelper
{
    /**
     * @var TotalAvailablePlugin
     */
    protected $totalAvailable;

    /**
     * @param TotalAvailablePlugin $totalAvailable
     */
    public function __construct(TotalAvailablePlugin $totalAvailable)
    {
        $this->totalAvailable = $totalAvailable;
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
     * @param AbstractResourceEntityRepresentation $resource Checked resource (exists,
     * public, with file).
     * @return int -1 if available without limit, 0 if not, the number of
     * available copies if limited.
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource)
    {
        $totalAvailable = $this->totalAvailable;
        return $totalAvailable($resource);
    }
}
