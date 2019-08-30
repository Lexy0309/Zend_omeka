<?php
namespace DerivativeImagesEbsco\Job;

use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\File\Downloader;
use Omeka\Job\AbstractJob;
use Omeka\Stdlib\Message;
use Zend\Uri\Http as HttpUri;

class DerivativeImages extends AbstractJob
{
    /**
     * Limit for the loop to avoid heavy sql requests.
     *
     * @var integer
     */
    const SQL_LIMIT = 20;

    protected $tempFileFactory;
    protected $entityManager;
    protected $logger;
    protected $api;
    protected $importThumbnail;
    protected $retrieveExternal;
    protected $convertExternalRecord;
    protected $downloader;

    protected $basePath;
    protected $skipCheckExistingThumbnails;
    protected $originalExternalMedia;
    protected $thumbnailTypes;
    protected $totalProcessed = 0;
    protected $totalSucceed = 0;
    protected $totalFailed = 0;

    public function perform()
    {
        /**
         * @var array $config
         * @var \Omeka\Mvc\Controller\Plugin\Logger $logger
         * @var \Omeka\Api\Manager $api
         * @var \Omeka\File\TempFileFactory $tempFileFactory
         * @var \Doctrine\ORM\EntityManager $entityManager
         * @var \Doctrine\DBAL\Connection $connection
         */
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $logger = $services->get('Omeka\Logger');
        $api = $services->get('Omeka\ApiManager');
        $tempFileFactory = $services->get('Omeka\File\TempFileFactory');
        // The api cannot update value "has_thumbnails", so use entity manager.
        $entityManager = $services->get('Omeka\EntityManager');
        $connection = $entityManager->getConnection();

        $plugins = $services->get('ControllerPluginManager');
        // $determineAccessPath = $plugins->get('determineAccessPath');
        $this->importThumbnail = $plugins->get('importThumbnail');
        $this->retrieveExternal = $plugins->get('retrieveExternal');
        $this->convertExternalRecord = $plugins->get('convertExternalRecord');

        $this->downloader = $services->get('Omeka\File\Downloader');

        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');

        $this->logger = $logger;
        $this->api = $api;
        $this->tempFileFactory = $tempFileFactory;
        $this->entityManager = $entityManager;
        $this->basePath = $basePath;
        $this->thumbnailTypes = array_keys($config['thumbnails']['types']);

        $mediaRepository = $entityManager->getRepository(\Omeka\Entity\Media::class);

        $sql = 'SELECT COUNT(id) FROM media WHERE 1 = 1';
        $criteria = [];

        $ingesters = $this->getArg('ingesters', []) ?: [];
        if (in_array('', $ingesters) || empty($ingesters)) {
            $ingesters = ['ebsco', 'external'];
        }
        if ($ingesters) {
            $ingesters = array_intersect(['ebsco', 'external'], $ingesters);
            if (empty($ingesters)) {
                $logger->info(new Message(
                    'No ingester to process. You may check your query.' // @translate
                ));
                return;
            }
            $list = array_map([$connection, 'quote'], $ingesters);
            $sql .= ' AND ingester IN (' . implode(',', $list). ')';
            $criteria['ingester'] = $ingesters;
        }

        $renderers = $this->getArg('renderers', []) ?: [];
        if (in_array('', $renderers)) {
            $renderers = [];
        }
        if ($renderers) {
            $list = array_map([$connection, 'quote'], $renderers);
            $sql .= ' AND renderer IN (' . implode(',', $list). ')';
            $criteria['renderer'] = $renderers;
        }

        $mediaTypes = $this->getArg('media_types', []) ?: [];
        if (in_array('', $mediaTypes)) {
            $mediaTypes = [];
        }
        if ($mediaTypes) {
            $list = array_map([$connection, 'quote'], $mediaTypes);
            $sql .= ' AND media_type IN (' . implode(',', $list). ')';
            $criteria['mediaType'] = $mediaTypes;
        }

        $publicationTypes = $this->getArg('publication_types', []) ?: ['book', 'article'];
        if (in_array('', $publicationTypes) || empty($publicationTypes)) {
            $publicationTypes = ['book', 'article'];
        }
        if ($publicationTypes) {
            $publicationTypes = array_intersect(['book', 'article'], $publicationTypes);
            if (empty($publicationTypes)) {
                $logger->info(new Message(
                    'No publication type to process. You may check your query.' // @translate
                ));
                return;
            }
        }

        $skipCheckExistingThumbnails = (bool) $this->getArg('skip_check_existing_thumbnails');
        $this->skipCheckExistingThumbnails = $skipCheckExistingThumbnails;

        $originalExternalMedia = $this->getArg('original_external_media');
        $this->originalExternalMedia = $originalExternalMedia;

        $minimumId = (int) $this->getArg('minimum_id');
        if ($minimumId) {
            $sql .= ' AND id > ' . $minimumId;
            // TODO Use a true criteria.
            // $criteria['id >'] = $minimumId;
        }

        $stmt = $connection->query($sql);
        $totalMedias = $stmt->fetchColumn();

        $fullTotalMedias = $api->search('media', [])
            ->getTotalResults();

        if (empty($totalMedias)) {
            $logger->info(new Message(
                'No media to process for creation of derivative files (on a total of %d medias). You may check your query.', // @translate
                $fullTotalMedias
            ));
            return;
        }

        $logger->info(new Message(
            'Processing creation of derivative files of %d medias (on a total of %d medias).', // @translate
            $totalMedias, $fullTotalMedias
        ));

        $offset = 0;
        $this->totalProcessed = 0;
        $this->totalSucceed= 0;
        $this->totalFailed = 0;
        while (true) {
            // Entity are used, because it's not possible to update the value
            // "has_thumbnails" via api.
            /** @var \Omeka\Entity\Media[] $medias */
            $medias = $mediaRepository->findBy($criteria, ['id' => 'ASC'], self::SQL_LIMIT, $offset);
            if (!count($medias)) {
                break;
            }

            foreach ($medias as $key => $media) {
                // TODO Use a true criteria.
                if ($minimumId && $media->getId() < $minimumId) {
                    continue;
                }

                if ($this->shouldStop()) {
                    $logger->warn(new Message(
                        'The job "Derivative Images" was stopped: %d/%d resources processed.', // @translate
                        $offset + $key, $totalMedias
                    ));
                    break 2;
                }

                $item = $media->getItem();
                $itemRepresentation = $api->read('items', $item->getId())->getContent();

                $isExternal = (bool) $itemRepresentation->value('vatiru:isExternal');
                if (!$isExternal) {
                    continue;
                }

                $publicationType = (string) $itemRepresentation->value('vatiru:publicationType');
                if (!in_array($publicationType, $publicationTypes)) {
                    continue;
                }

                // External books are available only online, so get only thumbnails.
                switch ($publicationType) {
                    case 'book':
                        $this->processEbscoThumbnails($itemRepresentation);
                        break;
                    case 'article':
                        $this->processEbscoFetchAndThumbnails($itemRepresentation);
                        break;
                }

                ++$this->totalProcessed;
            }

            $entityManager->clear();
            $offset += self::SQL_LIMIT;
        }

        $logger->info(new Message(
            'End of the creation of derivative files: %d processed, %d succeed, %d failed.', // @translate
            $this->totalProcessed, $this->totalSucceed, $this->totalFailed
        ));
    }

    protected function processEbscoThumbnails(ItemRepresentation $item)
    {
        // Check if there is a thumbnails to fetch.
        $externalData = $item->value('vatiru:externalData');
        if (!$externalData) {
            return;
        }

        $record = json_decode($externalData->value(), true);
        if (!$record || empty($record['ImageInfo'])) {
            return;
        }

        $url = null;
        foreach ($record['ImageInfo'] as $image) {
            // Warning: xml add a tag "CoverArt" before Target.
            $url = $image['Target'];
            if ($image['Size'] === 'medium') {
                break;
            }
        }
        if (!$url) {
            return;
        }

        $this->fetchThumbnailForItem($item, $url);
    }

    protected function processEbscoFetchAndThumbnails(ItemRepresentation $item)
    {
        $media = $item->primaryMedia();
        if ($media->ingester() !== 'external') {
            $this->logger->info(new Message(
                'Item #%d (media #%d): skipped (ingester: %s).', // @translate
                $item->id(), $media->id(), $media->ingester()
            ));
            return;
        }

        if (in_array($this->originalExternalMedia, [
            'with_original_only',
            'with_original_and_file_only',
            'with_original_and_no_file_only',
        ])) {
            if (!$media->hasOriginal()) {
                $this->logger->info(new Message(
                    'Item #%d (media #%d): skipped (no original).', // @translate
                    $item->id(), $media->id()
                ));
                return;
            }
        } elseif (in_array($this->originalExternalMedia, [
            'without_original_only',
        ])) {
            if ($media->hasOriginal()) {
                $this->logger->info(new Message(
                    'Item #%d (media #%d): skipped (original exists).', // @translate
                    $item->id(), $media->id()
                ));
                return;
            }
        }

        switch ($this->originalExternalMedia) {
            // Only if marked as original.
            case 'with_original_only':
                break;
            // Only if marked as original and with a file (no fetch).
            case 'with_original_and_file_only':
                if (!$this->originalFileExists($media)) {
                    $this->logger->info(new Message(
                        'Item #%d (media #%d): skipped (no file).', // @translate
                        $item->id(), $media->id()
                    ));
                    return;
                }
                break;
            // 'Only if marked as original and without a file.
            case 'with_original_and_no_file_only':
                if ($this->originalFileExists($media)) {
                    $this->logger->info(new Message(
                        'Item #%d (media #%d): skipped (file exists).', // @translate
                        $item->id(), $media->id()
                    ));
                    return;
                }
                break;
            // Only if not marked as original.
            case 'without_original_only':
                break;
        }

        if (in_array($this->originalExternalMedia, [
            'with_original_only',
            'with_original_and_no_file_only',
            'without_original_only',
        ])) {
            $result = $this->fetchOriginalExternalAndCreateThumbnails($item, $media);
            if (!$result) {
                $this->logger->notice(new Message(
                    'Item #%d (media #%d): unable to fetch file.', // @translate
                    $item->id(), $media->id()
                ));
                return;
            }
            $this->logger->info(new Message(
                'Item #%d (media #%d): original fetched and thumbnails created.', // @translate
                $item->id(), $media->id()
            ));
        } else {
            $this->createThumbnailsFromOriginal($media);
        }
    }

    /**
     * Fetch an external thumbnail to create directly the one of an item, even
     * without original thumbnail.
     *
     * @param ItemRepresentation $item
     * @param string $url
     */
    protected function fetchThumbnailForItem(ItemRepresentation $item, $url)
    {
        $media = $item->primaryMedia();

        if ($this->checkExistingThumbnails($media)) {
            $this->logger->info(new Message(
                'Item #%d (media #%d): derivative files exist.', // @translate
                $item->id(), $media->id()
            ));
            return;
        }

        $importThumbnail = $this->importThumbnail;
        $result = $importThumbnail($url, $media->storageId());

        $hasThumbnails = $media->hasThumbnails();
        if ($hasThumbnails !== $result) {
            $mediaEntity = $this->api->read('media', $media->id(), [], ['responseContent' => 'resource'])->getContent();
            $mediaEntity->setHasThumbnails($result);
            $this->entityManager->persist($mediaEntity);
            $this->entityManager->flush();
        }

        if ($result) {
            ++$this->totalSucceed;
            $this->logger->info(new Message(
                'Item #%d (media #%d): derivative files created.', // @translate
                $item->id(), $media->id()
            ));
        } else {
            ++$this->totalFailed;
            $this->logger->notice(new Message(
                'Item #%d (media #%d): derivative files not created.', // @translate
                $item->id(), $media->id()
            ));
        }
    }

    protected function originalFileExists(MediaRepresentation $media)
    {
        $filename = $media->filename();
        $sourcePath = $this->basePath . '/original/' . $filename;
        return file_exists($sourcePath) && filesize($sourcePath);
    }

    protected function fetchOriginalExternalAndCreateThumbnails(ItemRepresentation $item, MediaRepresentation $media)
    {
        // Ebsco record url:
        // https://eds-api.ebscohost.com/edsapi/rest/retrieve?dbid=asn&an
        // Ebsco file url:
        // https://content.ebscohost.com/ContentServer.asp?EbscoContent=
        $url = $media->source();
        $isFileUrl = strpos($url, 'https://content.ebscohost.com/ContentServer.asp') === 0;

        // The token is probably out, so it should be updated from the item data.
        if ($isFileUrl) {
            $externalData = $item->value('vatiru:externalData');
            if (!$externalData) {
                $this->logger->info(new Message(
                    'Item #%d (media #%d): no external data in item, so no url to fetch data.', // @translate
                    $item->id(), $media->id()
                ));
                return;
            }

            $record = json_decode($externalData->value(), true);
            if (!$record) {
                $this->logger->info(new Message(
                    'Item #%d (media #%d): no external data in item, so no url to fetch data.', // @translate
                    $item->id(), $media->id()
                ));
                return;
            }
            $url = $this->retrieveUrlEbsco($record);
        }

        $this->logger->debug(new Message(
            'Item #%d (media #%d): extracting file url.', // @translate
            $item->id(), $media->id()
        ));

        $url = $this->extractFileUrl($url);
        if (empty($url)) {
            $this->logger->info(new Message(
                'Item #%d (media #%d): undetermined url.', // @translate
                $item->id(), $media->id()
            ));
            return;
        }

        $uri = new HttpUri($url);
        if (!($uri->isValid() && $uri->isAbsolute())) {
            $this->logger->info(new Message(
                'Item #%d (media #%d): invalid file url.', // @translate
                $item->id(), $media->id()
            ));
            return;
        }

        $tempFile = $this->downloader->download($uri);
        if (!$tempFile) {
            $this->logger->info(new Message(
                'Item #%d (media #%d): unable to download.', // @translate
                $item->id(), $media->id()
            ));
            return;
        }

        $this->logger->debug(new Message(
            'Item #%d (media #%d): storing file.', // @translate
            $item->id(), $media->id()
        ));

        $tempFile->setStorageId($media->storageId());
        $tempFile->setSourceName($uri->getPath());
        $media = $this->api->read('media', $media->id(), [], ['responseContent' => 'resource'])->getContent();
        $media->setData(null);
        $media->setSource($uri);
        $media->setExtension($tempFile->getExtension());
        $media->setMediaType($tempFile->getMediaType());
        $media->setSha256($tempFile->getSha256());
        $media->setSize($tempFile->getSize());
        $hasThumbnails = $tempFile->storeThumbnails();
        $media->setHasThumbnails($hasThumbnails);
        $tempFile->storeOriginal();
        // There is an original, but it may be not stored.
        $media->setHasOriginal(true);
        $tempFile->delete();

        $this->entityManager->persist($media);
        $this->entityManager->flush();

        return true;
    }

    protected function createThumbnailsFromOriginal(MediaRepresentation $media)
    {
        $mediaRepresentation = $media;
        if ($this->checkExistingThumbnails($mediaRepresentation)) {
            return;
        }

        $media = $this->api->read('media', $media->id(), [], ['responseContent' => 'resource'])->getContent();

        // Thumbnails are created only if the original file exists.
        $filename = $media->getFilename();
        $sourcePath = $this->basePath . '/original/' . $filename;

        $tempFileFactory = $this->tempFileFactory;
        $tempFile = $tempFileFactory->build();
        $tempFile->setTempPath($sourcePath);
        $tempFile->setStorageId($media->getStorageId());

        $result = $tempFile->storeThumbnails();
        if ($media->hasThumbnails() !== $result) {
            $media->setHasThumbnails($result);
            $entityManager->persist($media);
            $entityManager->flush();
        }
    }

    /**
     * Check if the thumbnails of a media exists, and, if not, it they are
     * writeable.
     *
     * @todo Clarify this method, but avoid multiple read on the multiple system..
     *
     * @param MediaRepresentation $media
     * @return bool Return true if all thumbnails are ready or if they are not
     * writeable, else false when a thumbnail is missing or when check is
     * skipped.
     */
    protected function checkExistingThumbnails(MediaRepresentation $media)
    {
        if ($this->skipCheckExistingThumbnails) {
            return false;
        }

        $filename = $media->filename();
        // $sourcePath = $this->basePath . '/original/' . $filename;

        // Check the current files to avoid a refetch.
        $hasAll = true;
        foreach ($this->thumbnailTypes as $type) {
            $derivativePath = $this->basePath . '/' . $type . '/' . $filename;
            $fileExists = file_exists($derivativePath);
            if (!$fileExists || !filesize($derivativePath)) {
                $hasAll = false;
            }
            // Check if all files are writeable to avoid an issue.
            if ($fileExists && !is_writeable($derivativePath)) {
                $this->logger->notice(new Message(
                    'Item #%d (media #%d): derivative files not created: file unwriteable for type %s.', // @translate
                    $media->item->id(), $media->id(), $type
                ));
                // It is not a true, but an error.
                return true;
            }
        }

        return $hasAll;
    }

    /**
     * Get the url to retrieve full data of a record.
     *
     * @param array $record
     * @return string
     */
    protected function retrieveUrlEbsco(array $record)
    {
        return 'https://eds-api.ebscohost.com/edsapi/rest/retrieve?'
            . http_build_query([
                'dbid' => $record['Header']['DbId'],
                'an' => $record['Header']['An'],
                // Default: ebook-epub.
                // 'ebookpreferredformat' => 'ebook-epub',
                'ebookpreferredformat' => 'ebook-pdf',
        ]);
    }

    /**
     * Retrieve the file url from external record data.
     *
     * @todo Find where the files are when the parsing mode is needed.
     * Copy from \External\Media\Ingester\External::extractFileUrl().
     *
     * @param string $url
     * @return string
     */
    protected function extractFileUrl($url)
    {
        $retrieveExternal = $this->retrieveExternal;
        $result = $retrieveExternal('ebsco', ['url' => $url]);
        if (empty($result)) {
            return;
        }

        $convertExternalRecord = $this->convertExternalRecord;
        // TODO Save the full metadata of the item.
        $item = $convertExternalRecord('ebsco', $result['Record']);
        if (empty($item['o:media'][0]['ingest_url'])) {
            return;
        }
        return $item['o:media'][0]['ingest_url'];
    }
}
