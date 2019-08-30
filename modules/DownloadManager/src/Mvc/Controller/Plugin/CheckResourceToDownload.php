<?php
namespace DownloadManager\Mvc\Controller\Plugin;

use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Stdlib\ErrorStore;
use Zend\Http\Response;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use Zend\Mvc\Controller\PluginManager;

/**
 * Determine if a resource is downloadable.
 */
class CheckResourceToDownload extends AbstractPlugin
{
    /**
     * @var ErrorStore
     */
    protected $errorStore;

    /**
     * @var ApiManager
     */
    protected $api;

    /**
     * @var PluginManager
     */
    protected $plugins;

    /**
     * @param PluginManager $plugins
     */
    public function __construct(PluginManager $plugins)
    {
        $this->plugins = $plugins;
    }

    /**
     * Determine if a resource is downloadable by anybody.
     *
     * @param AbstractResourceEntityRepresentation $resource
     * @param ErrorStore $errorStore
     * @return bool|null|array True if downloadable, false if not, else null if
     * there is an error store or an array containing a message and a status
     * code.
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource, ErrorStore $errorStore = null)
    {
        $this->api = $this->plugins->get('api');
        $this->errorStore = $errorStore;

        if (empty($resource)) {
            return $this->returnError(
                'The item to borrow is not defined.', Response::STATUS_CODE_400); // @translate
        }

        // TODO Currently, only items can be held and downloaded.
        $resourceType = $resource->getControllerName();
        if ($resourceType !== 'item') {
            return $this->returnError(
                'The resource to borrow should be an item.', Response::STATUS_CODE_400); // @translate
        }

        // TODO Check the rights more finely (groups). Currently, useless.
        try {
            // This query checks the user automatically.
            $resource = $this->api->read('items', $resource->id())->getContent();
        } catch (\Exception $e) {
            return $this->returnError(
                'The item to borrow is not public.', Response::STATUS_CODE_403); // @translate
        }

        /** @var \DownloadManager\Mvc\Controller\Plugin\PrimaryOriginal $primaryOriginal */
        $primaryOriginal = $this->plugins->get('primaryOriginal');
        $media = $primaryOriginal($resource, false);
        if (!$media) {
            return $this->returnError(
                'The item has no file to download.', Response::STATUS_CODE_400); // @translate
        }

        // TODO Check if pdf? Not really needed.

        return true;
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
