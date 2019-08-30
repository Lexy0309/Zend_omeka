<?php
namespace DownloadManager\View\Helper;

use DownloadManager\Mvc\Controller\Plugin\PrimaryOriginal as PrimaryOriginalPlugin;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Zend\View\Helper\AbstractHelper;

/**
 * Determine if an item has a primary media locally or externally.
 */
class PrimaryOriginal extends AbstractHelper
{
    /**
     * @var PrimaryOriginalPlugin
     */
    protected $primaryOriginal;

    /**
     * @param PrimaryOriginalPlugin $primaryOriginal
     */
    public function __construct(PrimaryOriginalPlugin $primaryOriginal)
    {
        $this->primaryOriginal = $primaryOriginal;
    }

    /**
     * Determine if an item has a primary media with a file, locally or externally.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @param bool $withStoredFile
     * @return MediaRepresentation|null
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource, $withStoredFile = true)
    {
        $primaryOriginal = $this->primaryOriginal;
        return $primaryOriginal($resource, $withStoredFile);
    }
}
