<?php
namespace Next\View\Helper;

use Omeka\Api\Representation\AbstractResourceRepresentation;
use Zend\View\Helper\AbstractHelper;

/**
 * View helper to return the url to the public default site page of a resource.
 */
class SaveUrl extends AbstractHelper
{
    /**
     * @var string
     */

    /**
     * Return the url to the public default site page or a resource.
     *
     * @uses AbstractResourceRepresentation::siteUrl()
     *
     * @param AbstractResourceRepresentation $resource
     * @param bool $canonical Whether to return an absolute URL
     * @return string
     */
    public function __invoke()
    {
        return $_SESSION['current_url'];
    }
}
