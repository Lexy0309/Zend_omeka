<?php
namespace DownloadManager\Mvc\Controller\Plugin;

use DownloadManager\Entity\Download;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Entity\User;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

/**
 * Get the status of a resource for a user.
 */
class CheckDownloadStatus extends AbstractPlugin
{
    protected $checkResourceToDownload;
    protected $checkRightToDownload;
    protected $getCurrentDownload;
    protected $isResourceAvailableForUser;
    protected $totalAvailablePlugin;
    // protected $createMediaHash;
    protected $api;
    protected $urlHelper;

    public function __construct(
        $checkResourceToDownload,
        $checkRightToDownload,
        $getCurrentDownload,
        $isResourceAvailableForUser,
        $totalAvailablePlugin,
        // $createMediaHash,
        $api,
        $urlHelper
    ) {
        $this->checkResourceToDownload = $checkResourceToDownload;
        $this->checkRightToDownload = $checkRightToDownload;
        $this->getCurrentDownload = $getCurrentDownload;
        $this->isResourceAvailableForUser = $isResourceAvailableForUser;
        $this->totalAvailablePlugin = $totalAvailablePlugin;
        // $this->createMediaHash = $createMediaHash;
        $this->api = $api;
        $this->urlHelper = $urlHelper;
    }

    /**
     * Get the status of a resource for a user.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @param User $user
     * @return array Associative array with availability, status and url, or
     * error.
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource, User $user)
    {
        $checkResourceToDownload = $this->checkResourceToDownload;
        $result = $checkResourceToDownload($resource);
        if (is_array($result)) {
            unset($result['result']);
            return ['available' => false, 'status' => 'error'] + $result;
        }

        $checkRightToDownload = $this->checkRightToDownload;
        $result = $checkRightToDownload($resource, $user);
        if (is_array($result)) {
            unset($result['result']);
            return ['available' => false, 'status' => 'error'] + $result;
        }

        $getCurrentDownload = $this->getCurrentDownload;
        $download = $getCurrentDownload($resource, $user);

        // Don't use the plugin url but the view helper, since the plugin
        // requires a route that may be not available in background process.
        // DomainException: Url plugin requires a controller that implements InjectApplicationEventInterface
        // $url = $this->plugins->get('url');
        $url = $this->urlHelper;

        if ($download && $download->isDownloaded()) {
            $url = $url('download', ['action' => 'item', 'hash' => $download->hash()], ['force_canonical' => true]);
            return [
                'available' => true,
                'status' => 'downloaded',
                'url' => $url,
                'expire' => $download->expire(),
            ];
        }

        $isResourceAvailableForUser = $this->isResourceAvailableForUser;
        $totalAvailablePlugin = $this->totalAvailablePlugin;
        $available = $isResourceAvailableForUser($resource);
        $totalAvailable = $available || $totalAvailablePlugin($resource);
        if ($available || $totalAvailable) {
            $url = $this->urlHelper;
            if (empty($download)) {
                // Since 3.4.3, Downloads are set only when item is displayed.
                /*
                $data = [];
                $data['o:status'] = Download::STATUS_READY;
                $data['o:resource']['o:id'] = $resource->id();
                $data['o:owner']['o:id'] = $user->getId();
                $response = $this->api
                    ->create('downloads', $data);
                if (!$response) {
                    throw new \Exception(sprintf('Unable to create download for resource #%d.', $resource->id()));
                }
                $download = $response->getContent();
                */
                // Nevertheless, the url is needed by the app.
                // TODO Remove the url when item is downloadable.
                // $primaryMedia = $resource->primaryMedia();
                // $createMediaHash = $this->createMediaHash;
                // $url = $url('download', ['action' => 'item', 'hash' => $createMediaHash($primaryMedia)], ['force_canonical' => true]);
                $url = $url('download', ['action' => 'item', 'hash' => $resource->id()], ['force_canonical' => true]);
                return [
                    'available' => true,
                    'status' => 'downloadable',
                    'url' => $url,
                    'expire' => null,
                ];
            }
            $url = $url('download', ['action' => 'item', 'hash' => $download->hash()], ['force_canonical' => true]);
            return [
                'available' => true,
                'status' => 'downloadable',
                'url' => $url,
                // Expire is useless (always null here) but required by the app.
                'expire' => $download->expire(),
            ];
        }

        $status = $download && $download->status() !== Download::STATUS_READY
            ? $download->status()
            : 'unavailable';
        return [
            // Here, the status can be "held" or "unavailable" only.
            'available' => false,
            'status' => $status,
        ];
    }
}
