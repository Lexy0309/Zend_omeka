<?php
namespace DownloadManager\Mvc\Controller\Plugin;

use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Entity\User;
use Omeka\Permissions\Acl;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use Zend\Mvc\Controller\PluginManager;

/**
 * Determine the status of a resource to download by a user.
 */
class IsResourceAvailableForUser extends AbstractPlugin
{
    /**
     * @var Acl
     */
    protected $acl;

    /**
     * @var PluginManager
     */
    protected $plugins;

    /**
     * @param Acl $acl
     * @param PluginManager $plugins
     */
    public function __construct(Acl $acl, PluginManager $plugins)
    {
        $this->acl = $acl;
        $this->plugins = $plugins;
    }

    /**
     * Determine if a resource is available for a user.
     *
     * A resource is available if it is free to download, or if the user has
     * already downloaded it.
     *
     * @param AbstractResourceEntityRepresentation $resource Checked resource (exists, public, with file).
     * @param User $user The current user if not defined.
     * @return bool
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource, User $user = null)
    {
        if (empty($user)) {
            $user = $this->acl->getAuthenticationService()->getIdentity();
        }

        if ($this->acl->userIsAllowed('Omeka\Entity\Resource', 'view-all')) {
            return true;
        }

        $totalAvailablePlugin = $this->plugins->get('totalAvailable');
        $totalAvailable = $totalAvailablePlugin($resource);
        if ($totalAvailable) {
            return true;
        }

        if (empty($user)) {
            return false;
        }

        $getCurrentDownload = $this->plugins->get('getCurrentDownload');
        $download = $getCurrentDownload($resource, $user);
        return $download && $download->isDownloaded();
    }
}
