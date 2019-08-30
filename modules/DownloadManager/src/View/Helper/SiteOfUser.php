<?php
namespace DownloadManager\View\Helper;

use DownloadManager\Mvc\Controller\Plugin\SiteOfUser as SiteOfUserPlugin;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\UserRepresentation;
use Zend\View\Helper\AbstractHelper;

/**
 * Determine the site of a user.
 */
class SiteOfUser extends AbstractHelper
{
    /**
     * @var SiteOfUserPlugin
     */
    protected $siteOfUser;

    /**
     * @param SiteOfUserPlugin $siteOfUser
     */
    public function __construct(SiteOfUserPlugin $siteOfUser)
    {
        $this->siteOfUser = $siteOfUser;
    }

    /**
     * Helper to get the site of an user, or the default site, if any.
     *
     * @param UserRepresentation $user
     * @param bool $returnDefaultSite Should be set in main settings of Omeka S.
     * @return SiteRepresentation|null
     */
    public function __invoke(UserRepresentation $user, $returnDefaultSite = true)
    {
        $siteOfUser = $this->siteOfUser;
        return $siteOfUser($user, $returnDefaultSite);
    }
}
