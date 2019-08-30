<?php
namespace External\Mvc\Controller\Plugin;

use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Representation\MediaRepresentation;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use Zend\Mvc\Controller\PluginManager;

/**
 * Fetch external file if needed.
 */
class PrepareExternal extends AbstractPlugin
{
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
     * Fetch external file if needed.
     *
     * @param MediaRepresentation $resource
     * @return bool Success or not.
     */
    public function __invoke(MediaRepresentation $media)
    {
        if ($media->ingester() !== 'external') {
            return true;
        }

        if ($media->hasOriginal()) {
            return true;
        }

        if (!empty($GLOBALS['globalIsTest'])) {
            $executionTime = microtime(true) - $GLOBALS['globalStartTime'];
            $logger = $this->plugins->get('logger');
            $logger->debug($executionTime . ' ' . __METHOD__ . ' #' . __LINE__ . ' / Media id: ' . $media->id());
        }
        // Ingest the media via a normal update.
        $api = $this->plugins->get('api');
        $response = $api->update('media', $media->id(), [], [], ['isPartial' => true]);
        if (!$response) {
            return false;
        }
        if (!empty($GLOBALS['globalIsTest'])) {
            $executionTime = microtime(true) - $GLOBALS['globalStartTime'];
            $logger->debug($executionTime . ' ' . __METHOD__ . ' #' . __LINE__);
        }
        return true;
    }
}
