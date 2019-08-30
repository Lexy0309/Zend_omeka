<?php
namespace DerivativeImages\Job;

use Omeka\Job\AbstractJob;
use Omeka\Stdlib\Message;

class DerivativeImages extends AbstractJob
{
    /**
     * Limit for the loop to avoid heavy sql requests.
     *
     * @var integer
     */
    const SQL_LIMIT = 20;

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

        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');

        $types = array_keys($config['thumbnails']['types']);

        $mediaRepository = $entityManager->getRepository(\Omeka\Entity\Media::class);

        $sql = 'SELECT COUNT(id) FROM media WHERE 1 = 1';
        $criteria = [];

        $ingesters = $this->getArg('ingesters', []) ?: [];
        if (in_array('', $ingesters)) {
            $ingesters = [];
        }
        if ($ingesters) {
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
        $totalProcessed = 0;
        $totalSucceed= 0;
        $totalFailed = 0;
        while (true) {
            // Entity are used, because it's not possible to update the value
            // "has_thumbnails" via api.
            /** @var \Omeka\Entity\Media[] $medias */
            $medias = $mediaRepository->findBy($criteria, ['id' => 'ASC'], self::SQL_LIMIT, $offset);
            if (!count($medias)) {
                break;
            }

            foreach ($medias as $key => $media) {
                if ($this->shouldStop()) {
                    $logger->warn(new Message(
                        'The job "Derivative Images" was stopped: %d/%d resources processed.', // @translate
                        $offset + $key, $totalMedias
                    ));
                    break 2;
                }

                // TODO Manage creation of thumbnails for media without original (youtube…).
                $hasOriginal = $media->hasOriginal();
                $hasThumbnails = $media->hasThumbnails();
                if (!$hasOriginal) {
                    continue;
                }

                // Thumbnails are created only if the original file exists.
                $filename = $media->getFilename();
                $sourcePath = $basePath . '/original/' . $filename;

                if (!file_exists($sourcePath)) {
                    $logger->warn(new Message(
                        'Media #%d (%d/%d): the original file "%s" does not exist.', // @translate
                        $media->getId(), $offset + $key + 1, $totalMedias, $filename
                    ));
                    continue;
                }

                if (!is_readable($sourcePath)) {
                    $logger->warn(new Message(
                        'Media #%d (%d/%d): the original file "%s" is not readable.', // @translate
                        $media->getId(), $offset + $key + 1, $totalMedias, $filename
                    ));
                    continue;
                }

                // Check the current files.
                foreach ($types as $type) {
                    $derivativePath = $basePath . '/' . $type . '/' . $filename;
                    if (file_exists($derivativePath) && !is_writeable($derivativePath)) {
                        $logger->warn(new Message(
                            'Media #%d (%d/%d): derivative file "%s" is not writeable (type "%s").', // @translate
                            $media->getId(), $offset + $key + 1, $totalMedias, $filename, $type
                        ));
                        continue 2;
                    }
                }

                ++$totalProcessed;

                $logger->info(new Message(
                    'Media #%d (%d/%d): creating derivative files.', // @translate
                    $media->getId(), $offset + $key + 1, $totalMedias
                ));

                $tempFile = $tempFileFactory->build();
                $tempFile->setTempPath($sourcePath);
                $tempFile->setStorageId($media->getStorageId());

                $result = $tempFile->storeThumbnails();
                if ($hasThumbnails !== $result) {
                    $media->setHasThumbnails($result);
                    $entityManager->persist($media);
                    $entityManager->flush();
                }

                if ($result) {
                    ++$totalSucceed;
                    $logger->info(new Message(
                        'Media #%d (%d/%d): derivative files created.', // @translate
                        $media->getId(), $offset + $key + 1, $totalMedias
                    ));
                } else {
                    ++$totalFailed;
                    $logger->notice(new Message(
                        'Media #%d (%d/%d): derivative files not created.', // @translate
                        $media->getId(), $offset + $key + 1, $totalMedias
                    ));
                }
            }

            $entityManager->clear();
            $offset += self::SQL_LIMIT;
        }

        $logger->info(new Message(
            'End of the creation of derivative files: %d/%d processed, %d skipped, %d succeed, %d failed.', // @translate
            $totalProcessed, $totalMedias, $totalMedias - $totalProcessed, $totalSucceed, $totalFailed
        ));
    }
}
