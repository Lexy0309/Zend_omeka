<?php
namespace DownloadManager\Mvc\Controller\Plugin;

use DownloadManager\Entity\Download;
use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Entity\User;
use Omeka\Permissions\Acl;
use Omeka\Settings\Settings;
use Omeka\Stdlib\ErrorStore;
use Zend\Http\Response;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use Zend\Mvc\Controller\PluginManager;

/**
 * Determine the status of a resource to download by a user.
 */
class CheckRightToDownload extends AbstractPlugin
{
    /**
     * Allow admins and librarians (default roles, not guests) to manage files.
     *
     * @todo Factorize with DownloadController::itemAction() and FilesController.
     * @todo Convert to a selectable list via main config.
     * @todo Convert to a regular acl rule/filter.
     * @todo Sign files for librarians, without encryption)?
     *
     * @var array
     */
    protected $bypassRoles = [
        Acl::ROLE_GLOBAL_ADMIN,
        Acl::ROLE_SITE_ADMIN,
        Acl::ROLE_EDITOR,
        Acl::ROLE_REVIEWER,
        Acl::ROLE_AUTHOR,
        Acl::ROLE_RESEARCHER,
    ];

    /**
     * @var ErrorStore
     */
    protected $errorStore;

    /**
     * @var Acl
     */
    protected $acl;

    /**
     * @var Settings
     */
    protected $settings;

    /**
     * @var ApiManager
     */
    protected $api;

    /**
     * @var PluginManager
     */
    protected $plugins;

    /**
     * @param Acl $acl
     * @param Settings $settings
     * @param ApiManager $api
     * @param PluginManager $plugins
     */
    public function __construct(Acl $acl, Settings $settings, ApiManager $api, PluginManager $plugins)
    {
        $this->acl = $acl;
        $this->settings = $settings;
        $this->api = $api;
        $this->plugins = $plugins;
    }

    /**
     * Determine the right of a user to download a resource.
     *
     * Note: The resource must be checked.
     *
     * @param AbstractResourceEntityRepresentation $resource Checked resource
     * (exists, public, with file).
     * @param User $user The current user if not defined.
     * @param ErrorStore $errorStore
     * @return bool|null|array True if downloadable, false if not, else null if
     * there is an error store or an array containing a message and a status
     * code.
     */
    public function __invoke(
        AbstractResourceEntityRepresentation $resource,
        User $user = null,
        ErrorStore $errorStore = null
    ) {
        if ($resource->isPublic()) {
            return true;
        }

        $this->errorStore = $errorStore;

        if (empty($user)) {
            $user = $this->acl->getAuthenticationService()->getIdentity();
            if (empty($user)) {
                return $this->returnError(
                    'The item is not available: the user is not identified.', Response::STATUS_CODE_403); // @translate
            }
        }

        if (!$this->acl->userIsAllowed(Download::class, 'create')) {
            return $this->returnError(
                'The item is not available: the user is not authorized.', Response::STATUS_CODE_403); // @translate
        }

        $result = $this->checkRights($resource, $user);
        if (!$result) {
            return $this->returnError(
                'The user has no rights to access the item.', Response::STATUS_CODE_403); // @translate
        }

        // Admins have no limit of download.
        $isAdmin = in_array($user->getRole(), $this->bypassRoles);
        if ($isAdmin) {
            return true;
        }

        // Check the limit of copies by user.
        $userCopies = $this->totalCopiesForUser($user);
        $maxCopies = $this->settings->get('downloadmanager_max_copies_by_user');
        if ($maxCopies && $userCopies >= $maxCopies) {
            return $this->returnError(
                'The user has reached the maximum number of copies.', Response::STATUS_CODE_400); // @translate
        }

        // Check the limit of simultaneous copies by user.
        $userCurrentCopies = $this->totalSimultaneousCopiesForUser($user);
        $maxSimultaneousCopies = $this->settings->get('downloadmanager_max_simultaneous_copies_by_user');
        if ($maxSimultaneousCopies && $userCurrentCopies >= $maxSimultaneousCopies) {
            return $this->returnError(
                'The user has reached the maximum number of simultaneous copies.', Response::STATUS_CODE_400); // @translate
        }

        return true;
    }

    /**
     * Check rights of a user on a resource.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @param User $user
     * @return bool
     */
    protected function checkRights(AbstractResourceEntityRepresentation $resource, User $user)
    {
        $totalExemplars = $this->plugins->get('totalExemplars');
        $totalExemplars = $totalExemplars($resource);

        // -1 means freely downloadable by public.
        if ($totalExemplars < 0) {
            return true;
        }

        // 0 (default) means freely downloadable by authenticated users.
        if (empty($totalExemplars)) {
            return !empty($user);
        }

        // TODO Other checks (group, institution, etc.).

        return true;
    }

    protected function totalCopiesForUser(User $user)
    {
        $args = [
            'status' => [Download::STATUS_DOWNLOADED, Download::STATUS_PAST],
            'owner_id' => $user->getId(),
        ];
        $total = $this->api->search('downloads', $args)->getTotalResults();
        $total += $this->api->search('download_logs', $args)->getTotalResults();
        return $total;
    }

    protected function totalSimultaneousCopiesForUser(User $user)
    {
        $total = $this->api->search('downloads', [
            'status' => Download::STATUS_DOWNLOADED,
            'owner_id' => $user->getId(),
        ])->getTotalResults();
        return $total;
    }

    protected function returnError($message, $statusCode = null)
    {
        if ($this->errorStore) {
            $this->errorStore->addError('o:resource', $message);
            return null;
        } else {
            return [
                'result' => false,
                'message' => $message,
                'statusCode' => $statusCode,
            ];
        }
    }
}
