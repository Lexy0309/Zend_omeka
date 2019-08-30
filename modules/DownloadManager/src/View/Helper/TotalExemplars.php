<?php
namespace DownloadManager\View\Helper;

use DownloadManager\Mvc\Controller\Plugin\TotalExemplars as TotalExemplarsPlugin;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Zend\View\Helper\AbstractHelper;

/**
 * Determine the total of exemplars of a resource.
 */
class TotalExemplars extends AbstractHelper
{
    /**
     * @var TotalExemplarsPlugin
     */
    protected $totalExemplars;

    /**
     * @param TotalExemplarsPlugin $totalExemplars
     */
    public function __construct(TotalExemplarsPlugin $totalExemplars)
    {
        $this->totalExemplars = $totalExemplars;
    }

    /**
     * Determine the total of exemplars of a resource (-1 means unlimited).
     *
     * The total of exemplars is set in the field "download:totalExemplars".
     * A total of -1 means no limit for public items, if the option "public_visibility"
     * is set; , and a total of 0 (default) means no limit for identified users.
     *
     * It is recommended to be coherent between the visibility and the value of
     * total exemplars.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @return int
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource)
    {
        $totalExemplars = $this->totalExemplars;
        return $totalExemplars($resource);
    }
}
