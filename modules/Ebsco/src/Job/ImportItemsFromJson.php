<?php
namespace Ebsco\Job;

use Doctrine\DBAL\Connection;
use External\Mvc\Controller\Plugin\ConvertExternalRecord;
use External\Mvc\Controller\Plugin\FilterExistingExternalRecords;
use Omeka\File\Downloader;
use Omeka\Job\AbstractJob;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Import each json ebsco reference inside Omeka.
 *
 * Note: In fact, 99% of the time is related to the thumbnail process. So the
 * Omeka / Doctrine process is efficient. So the thumbnail process is converted
 * into a job for the ebsco ingester.
 */
class ImportItemsFromJson extends AbstractJob
{
    protected $ownerId;

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var Downloader
     */
    protected $downloader;

    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    /**
     * @var ConvertExternalRecord
     */
    protected $convertExternalRecord;

    /**
     * @var FilterExistingExternalRecords
     */
    protected $filterExistingExternalRecords;

    public function perform()
    {
        $services = $this->getServiceLocator();
        $plugins = $services->get('ControllerPluginManager');
        $logger = $services->get('Omeka\Logger');
        $config = $services->get('Config');
        $this->downloader = $services->get('Omeka\File\Downloader');
        $this->connection = $services->get('Omeka\Connection');

        // Prepare the owner id for quick batch create.
        $authenticationService = $services->get('Omeka\AuthenticationService');
        $user = $authenticationService->getIdentity();
        $this->ownerId = $user->getId();

        $this->api = $services->get('Omeka\ApiManager');
        $this->filterExistingExternalRecords = $plugins->get('filterExistingExternalRecords');
        $this->convertExternalRecord = $plugins->get('convertExternalRecord');

        // TODO Remove the hard coded "/files".
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $folder = $basePath . DIRECTORY_SEPARATOR . 'ebsco';
        if (!file_exists($folder)) {
            $logger->err(
                'No folder "{folder}".', // @translate
                ['folder' => $folder]
            );
            return;
        } elseif (!is_dir($folder) || !is_readable($folder)) {
            $logger->err(
                'Folder "{folder}" is not a readable directory.', // @translate
                ['folder' => $folder]
            );
            return;
        }

        $directoryIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder));
        foreach ($directoryIterator as $name => $pathObject) {
            if (!$pathObject->isReadable()) {
                continue;
            }
            if ($pathObject->isDir()) {
                continue;
            }
            $json = file_get_contents($name);
            $data = $json ? json_decode($json, true) : null;
            if (empty($data)) {
                $logger->err(
                    'File "{file}" has no data.', // @translate
                    ['file' => basename($name)]
                );
                continue;
            }
            $logger->info(
                'Processing file "{file}".', // @translate
                ['file' => basename($name)]
            );
            $this->importRecords($data);
        }
    }

    /**
     * Convert the results from an Ebsco search into Omeka items, if not existing.
     *
     * @see \External\Module::ebscoRecordsToItems().
     *
     * @param array $records
     */
    protected function importRecords(array $records)
    {
        if (empty($records['SearchResult']['Data']['Records'])) {
            return [];
        }

        $filterExistingExternalRecords = $this->filterExistingExternalRecords;
        $records = $filterExistingExternalRecords($records['SearchResult']['Data']['Records']);
        if (empty($records)) {
            return [];
        }

        $convertExternalRecord = $this->convertExternalRecord;
        // TODO RecordFormat = EP Display: no documentation about other formats.
        $result = [];
        foreach ($records as $record) {
            $result[] = $convertExternalRecord('ebsco', $record);
        }

        /*
        $result = $this->api
            ->batchCreate('items', $result, [], ['responseContent' => 'resource'])
            ->getContent();
        */
        $this
            ->quickBatchCreate('items', $result, [], ['responseContent' => 'resource']);
    }

    /**
     * Quick batch create via sql and without event.
     *
     * @see \Omeka\Api\Manager::batchCreate()
     *
     * @param string $resource // Not managed.
     * @param array $data
     * @param array $fileData // Not managed.
     * @param array $options // Not managed.
     */
    protected function quickBatchCreate($resource, array $data = [], $fileData = [],
        array $options = []
    ) {
        foreach ($data as $record) {
            $this->quickCreate($resource, $record, $fileData, $options);
        }
    }

    /**
     * Quick create via sql and without event.
     *
     * @see \Omeka\Api\Manager::batchCreate()
     *
     * @param string $resource // Not managed.
     * @param array $data
     * @param array $fileData // Not managed.
     * @param array $options // Not managed.
     */
    protected function quickCreate($resource, array $data = [], $fileData = [],
        array $options = []
    ) {
        $connection = $this->connection;

        $resourceClassId = empty($data['o:resource_class']['o:id']) ? 'NULL' : $data['o:resource_class']['o:id'];
        $resourceTemplateId = empty($data['o:resource_template']['o:id']) ? 'NULL' : $data['o:resource_template']['o:id'];

        // The LAST_INSERT_ID() is always the first one, so the item id.

        // Create the item.
        $sql = <<<SQL
INSERT INTO `resource` (`owner_id`, `resource_class_id`, `resource_template_id`, `is_public`, `created`, `modified`, `resource_type`)
VALUES ({$this->ownerId}, $resourceClassId, $resourceTemplateId, 1, NOW(), NULL, 'Omeka\\\\Entity\\\\Item');

INSERT INTO `item` (`id`)
VALUES (LAST_INSERT_ID());


SQL;

        if ($data['o:item_set']) {
            $partSqls = [];
            foreach ($data['o:item_set'] as $itemSet) {
                $partSqls[] =  <<<SQL
(LAST_INSERT_ID(), {$itemSet['o:id']})
SQL;
            }
            $partSql = implode(",\n", $partSqls);
            $sql .= <<<SQL
INSERT INTO `item_item_set` (`item_id`, `item_set_id`)
VALUES
$partSql;


SQL;
        }

        $partSqls = [];
        foreach ($data as $value) {
            if (empty($value) || !isset($value[0]['property_id'])) {
                continue;
            }
            foreach ($value as $val) {
                // TODO Manage lang of the value?
                $valueValue = $connection->quote($val['@value']);
                $partSqls[] =  <<<SQL
(LAST_INSERT_ID(), {$val['property_id']}, "{$val['type']}", $valueValue)
SQL;
            }
        }
        if ($partSqls) {
            $partSql = implode(",\n", $partSqls);
            $sql .= <<<SQL
INSERT INTO `value` (`resource_id`, `property_id`, `type`, `value`)
VALUES
$partSql;


SQL;
        }

        $connection->exec($sql);

        // Need the last insert id, since there are multiple queries for media.
        $itemId = $connection->query('SELECT max(id) FROM item')->fetchColumn();
        if (empty($itemId)) {
            $services = $this->getServiceLocator();
            $logger = $services->get('Omeka\Logger');
            $logger->err(
                'Unable to create item. File skipped.' // @translate
            );
            return;
        }

        // Create the media (normally one).
        // Each media is inserted via a specific query.
        // TODO Merge all media queries (useless for now).

        $key = 0;
        foreach ($data['o:media'] as $media) {
            $resourceClassId = empty($media['o:resource_class']['o:id']) ? 'NULL' : $media['o:resource_class']['o:id'];
            $resourceTemplateId = empty($media['o:resource_template']['o:id']) ? 'NULL' : $media['o:resource_template']['o:id'];

            // No check is done: should be good.
            $source = $media['o:source'];
            $ingester = $media['o:ingester'];

            // Ebsco ingester for ebooks (no pdf, online viewer).
            // @see \Ebsco\Media\Ingester\Ebsco
            if ($ingester === 'ebsco') {
                $renderer = 'ebsco';
                $query = [];
                parse_str(parse_url($source, PHP_URL_QUERY), $query);
                // May fix some strange issues.
                $db = isset($query['db']) ? $query['db'] : $query['DB'];
                $an = isset($query['AN']) ? $query['AN'] : $query['an'];
                $mediaData = "'" . json_encode(
                    ['db' => $db, 'AN' => $an],
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                ) . "'";
                $mediaType = 'NULL';
                $storageId = $this->hashStorageName($itemId + $key + 1, $source);
                $extension = 'NULL';
                $sha256 = 'NULL';
                $hasOriginal = 0;
                $hasThumbnails = 1;
                $position = $key + 1;
                $size = 'NULL';
                if (empty($media['thumbnail_url'])) {
                    $thumbnail = null;
                } else {
                    $thumbnail = [
                        'url' => $media['thumbnail_url'],
                        'storageId' => $storageId,
                    ];
                }
                // Get thumbnails.
            }
            // External for articles (pdf loaded only when read).
            // TODO Finish import of external files.
            else {
                $renderer = 'file';
                $mediaData = $ingester === 'external' ? '\'{"is_record_url":true}\'' : 'NULL';
                $mediaType = '"application/pdf"';
                $storageId = $this->hashStorageName($itemId + $key + 1, $source);
                $extension = '"pdf"';
                // Get sha256.
                $sha256 = 'NULL';
                $hasOriginal = 0;
                // No thumbnail before first read.
                $hasThumbnails = 0;
                $position = $key + 1;
                // Get size.
                $size = 'NULL';

                // Get file and create thumbnails? Not for articles.
                $thumbnail = null;
            }

            $sql = <<<SQL
INSERT INTO `resource` (`owner_id`, `resource_class_id`, `resource_template_id`, `is_public`, `created`, `modified`, `resource_type`)
VALUES ({$this->ownerId}, $resourceClassId, $resourceTemplateId, 1, NOW(), NULL, 'Omeka\\\\Entity\\\\Media');

INSERT INTO `media` (`id`, `item_id`, `ingester`, `renderer`, `data`, `source`, `media_type`, `storage_id`, `extension`, `sha256`, `has_original`, `has_thumbnails`, `position`, `lang`, `size`)
VALUES (LAST_INSERT_ID(), $itemId, "{$media['o:ingester']}", "$renderer", $mediaData, "$source", $mediaType, "$storageId", $extension, $sha256, $hasOriginal, $hasThumbnails, $position, NULL, $size);


SQL;

            $partSqls = [];
            foreach ($media as $value) {
                if (empty($value) || !isset($value[0]['property_id'])) {
                    continue;
                }
                foreach ($value as $val) {
                    // TODO Manage lang of the value?
                    $valueValue = $connection->quote($val['@value']);
                    $partSqls[] =  <<<SQL
(LAST_INSERT_ID(), {$val['property_id']}, "{$val['type']}", $valueValue)
SQL;
                }
            }
            if ($partSqls) {
                $partSql = implode(",\n", $partSqls);
                $sql .= <<<SQL
INSERT INTO `value` (`resource_id`, `property_id`, `type`, `value`)
VALUES
$partSql;


SQL;
            }

            $connection->exec($sql);

            if ($thumbnail) {
                $this->importThumbnail($thumbnail);
            }

            ++$key;
        }
    }

    /**
     * Hash a stable single storage name for a specific media.
     *
     * Note: A random name is not used to avoid possible issues when the option
     * changes.
     * @see \Omeka\File\TempFile::getStorageId()
     * @see \ArchiveRepertory\File\FileManager::hashStorageName()
     *
     * @param int $id
     * @param string $source
     * @return string
     */
    protected function hashStorageName($id, $source)
    {
        $storageName = substr(hash('sha256', $id . '/' . $source), 0, 40);
        return $storageName;
    }

    /**
     * Import a thumbnail and create derivatives.
     *
     * @param array $thumbnail
     */
    protected function importThumbnail(array $thumbnail)
    {
        $thumbnailUrl = $thumbnail['url'];
        $storageId = $thumbnail['storageId'];

        $tempFile = $this->downloader->download($thumbnailUrl);
        if ($tempFile) {
            $tempFile->setStorageId($storageId);
            $tempFile->storeThumbnails();
            $tempFile->delete();
        }
    }
}
