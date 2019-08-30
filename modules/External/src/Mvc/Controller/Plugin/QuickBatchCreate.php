<?php
namespace External\Mvc\Controller\Plugin;

use Doctrine\DBAL\Connection;
use Omeka\Job\Dispatcher as JobDispatcher;
use Zend\EventManager\Event;
use Zend\EventManager\EventManagerAwareTrait;
use Zend\Log\LoggerInterface;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

class QuickBatchCreate extends AbstractPlugin
{
    use EventManagerAwareTrait;

    /**
     * @var bool
     */
    protected $useJobForThumbnails;

    /**
     * @var array
     */
    protected $medias;

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var ImportThumbnail
     */
    protected $importThumbnail;

    /**
     * @var JobDispatcher
     */
    protected $jobDispatcher;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var int|string
     */
    protected $ownerId;

    /**
     * @param Connection $connection
     * @param ImportThumbnail $importThumbnail
     * @param JobDispatcher $jobDispatcher
     * @param LoggerInterface $logger
     * @param int|string $ownerId String "NULL" if empty.
     */
    public function __construct(
        Connection $connection,
        ImportThumbnail $importThumbnail,
        JobDispatcher $jobDispatcher,
        LoggerInterface $logger,
        $ownerId
    ) {
        $this->connection = $connection;
        $this->importThumbnail = $importThumbnail;
        $this->jobDispatcher = $jobDispatcher;
        $this->logger = $logger;
        $this->ownerId = $ownerId;
    }

    /**
     * Import item data via sql instead of api.
     *
     * Replace:
     * <code>
     * $api->batchCreate('items', $data);
     * </code>
     *
     * @param array $itemsData
     * @param bool $useJobForThumbnails Allows to delay creation of thumbnails,
     * that is the slowest process.
     * @return array List of created ids.
     */
    public function __invoke(array $itemsData, $useJobForThumbnails = false)
    {
        $this->useJobForThumbnails = $useJobForThumbnails;

        $ids = [];
        foreach ($itemsData as $data) {
            $id = $this->quickCreate($data);
            if ($id) {
                $ids[] = $id;
            }
        }
        if ($ids) {
            if (!empty($GLOBALS['globalIsTest'])) {
                $executionTime = microtime(true) - $GLOBALS['globalStartTime'];
                $this->logger->debug($executionTime . ' ' . __METHOD__ . ' #' . __LINE__);
            }

            $event = new Event('external.batch_create.post', $this, ['ids' => $ids]);
            $this->getEventManager()->triggerEvent($event);

            if (!empty($GLOBALS['globalIsTest'])) {
                $executionTime = microtime(true) - $GLOBALS['globalStartTime'];
                $this->logger->debug($executionTime . ' ' . __METHOD__ . ' #' . __LINE__);
            }
        }

        return $ids;
    }

    /**
     * @param array $data
     * @return int|null The item id if no error.
     */
    protected function quickCreate(array $data)
    {
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
            $this->logger->err(
                'Unable to create item. File skipped.' // @translate
            );
            return null;
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

        return (int) $itemId;
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
        if ($this->useJobForThumbnails) {
            $jobArgs = [];
            $jobArgs['thumbnail_url'] = $thumbnailUrl;
            $jobArgs['storage_id'] = $storageId;
            $dispatcher = $this->jobDispatcher;
            $dispatcher->dispatch(\External\Job\ImportThumbnail::class, $jobArgs);
        } else {
            $importThumbnail = $this->importThumbnail;
            $importThumbnail($thumbnailUrl, $storageId);
        }
    }
}
