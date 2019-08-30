<?php
namespace External\Job;

use Omeka\Job\AbstractJob;

/**
 * Fully prepare media from a list of fetched external items.
 */
class PrepareExternalMedia extends AbstractJob
{
    public function perform()
    {
        $services = $this->getServiceLocator();
        $plugins = $services->get('ControllerPluginManager');
        $api = $plugins->get('api');
        $prepareExternal = $plugins->get('prepareExternal');

        $ids = $this->getArg('ids');
        foreach ($ids as $id) {
            if ($this->shouldStop()) {
                break;
            }
            /** @var \Omeka\Api\Representation\ItemRepresentation $item */
            try {
                $item = $api->read('items', $id)->getContent();
            } catch (\Omeka\Api\Exception\NotFoundException $e) {
                continue;
            }
            $media = $item->primaryMedia();
            if (!$media) {
                continue;
            }
            if (!empty($GLOBALS['globalIsTest'])) {
                $executionTime = microtime(true) - $GLOBALS['globalStartTime'];
                $plugins->get('logger')->debug($executionTime . ' ' . __METHOD__ . ' #' . __LINE__ . ' / Media id : ' . $media->id());
            }
            $prepareExternal($media);
        }
    }
}
