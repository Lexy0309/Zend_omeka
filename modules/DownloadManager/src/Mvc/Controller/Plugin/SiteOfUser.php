<?php
namespace DownloadManager\Mvc\Controller\Plugin;

use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\UserRepresentation;
use Omeka\Settings\Settings;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

/**
 * Get the site of a user, or the default one.
 */
class SiteOfUser extends AbstractPlugin
{
    /**
     * @var ApiManager
     */
    protected $api;

    /**
     * @var Settings
     */
    protected $settings;

    /**
     * @param ApiManager $api
     * @param Settings $settings
     */
    public function __construct(ApiManager $api, Settings $settings)
    {
        $this->api = $api;
        $this->settings = $settings;
    }

    /**
     * Helper to get the site of an user, or the default site, or the first.
     *
     * @param UserRepresentation $user
     * @param bool $returnDefaultSite Should be set in main settings of Omeka S.
     * @return SiteRepresentation|null
     */
    public function __invoke(UserRepresentation $user, $returnDefaultSite = true)
    {
        $sitePermissions = $user->sitePermissions();
        if (empty($sitePermissions)) {
            if (empty($returnDefaultSite)) {
                return null;
            }

            $siteId = $this->settings->get('default_site');
            if (empty($siteId)) {
                $sites = $this->api->read('sites', ['limit' => 1])->getContent();
                $site = reset($sites);
                return $site;
            }
        } else {
            $sitePermission = reset($sitePermissions);
            $siteId = $sitePermission->site()->id();
        }

        $site = $this->api->read('sites', $siteId)->getContent();
        return $site;
    }
}
