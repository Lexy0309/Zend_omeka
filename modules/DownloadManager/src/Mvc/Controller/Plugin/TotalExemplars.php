<?php
namespace DownloadManager\Mvc\Controller\Plugin;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

/**
 * Determine the total of exemplars of a resource.
 */
class TotalExemplars extends AbstractPlugin
{
    /**
     * @var bool
     */
    protected $publicVisibility;

    public function __construct($publicVisibility = true)
    {
        $this->publicVisibility = (bool) $publicVisibility;
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
        $totalExemplars = trim($resource->value('download:totalExemplars', ['type' => 'literal', 'all' => false]));
        $isNotSet = $totalExemplars == '';
        $isPublic = $resource->isPublic();
        if ($isNotSet) {
            return ($this->publicVisibility && $isPublic) ? -1 : 0;
        }
        $totalExemplars = (int) (string) $totalExemplars;
        return $totalExemplars < 0 ? ($isPublic ? -1 : 0) : $totalExemplars;
    }
}
