<?php
namespace External\Job;

use Omeka\Job\AbstractJob;
use Omeka\Stdlib\Message;

class ImportThumbnail extends AbstractJob
{
    public function perform()
    {
        $services = $this->getServiceLocator();
        $plugins = $services->get('ControllerPluginManager');
        $importThumbnail = $plugins->get('importThumbnail');

        $thumbnailUrl = $this->getArg('thumbnail_url');
        $storageId = $this->getArg('storage_id');

        $hasThumbnails = $importThumbnail($thumbnailUrl, $storageId);

        if (!$hasThumbnails) {
            $mediaId = $this->getMediaIdViaSql();
            if ($mediaId) {
                $connection = $services->get('Omeka\Connection');
                $logger = $services->get('Omeka\Logger');

                $sql = <<<SQL
UPDATE `media` SET `has_thumbnails` = 0 WHERE `id` = '$mediaId';
SQL;
                $connection->exec($sql);

                $logger->err(new Message('Thumbnail not imported for media #%d (url: %s, storage id: %s).', // @translate
                    $mediaId, $thumbnailUrl, $storageId));
            }
        }
    }

    /**
     * Get the with the specified storage id via sql (unavailable via api).
     *
     * @return int|null
     */
    protected function getMediaIdViaSql()
    {
        $storageId = $this->getArg('storage_id');
        $connection = $this->getServiceLocator()->get('Omeka\Connection');
        $sql = <<<SQL
SELECT id FROM media WHERE storage_id = '$storageId' LIMIT 1
SQL;
        return $connection->fetchColumn($sql);
    }
}

