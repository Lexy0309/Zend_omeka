<?php
namespace MediaQuality\Job;

use Omeka\Api\Exception\NotFoundException;
use Omeka\File\Exception\RuntimeException;
use Omeka\Job\AbstractJob;
use Omeka\Stdlib\Message;

/**
 * Process commands on a list of medias.
 */
class Processor extends AbstractJob
{
    public function perform()
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $logger = $services->get('Omeka\Logger');
        $cli = $services->get('Omeka\Cli');
        $processors = $services->get('Config')['mediaquality']['processors'];
        $basePath = $this->config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $tempFileFactory = $services->get('Omeka\File\TempFileFactory');

        $store = $this->getLocalStore();
        if (empty($store)) {
            $logger->err(new Message('[MediaQuality] This module requires a local store.')); // @translate
            return;
        }

        $mediaIds = $this->getArg('medias');
        if (empty($mediaIds)) {
            $logger->warn(new Message('[MediaQuality] No media to process.')); // @translate
            return;
        }

        // Check the media and prepare all the commands.
        $mediaCommands = [];
        foreach ($mediaIds as $mediaId) {
            if ($this->shouldStop()) {
                $logger->warn(new Message(
                    '[MediaQuality] The job was stopped before processing.', // @translate
                    $key, count($csvEntities)
                ));
                return;
            }

            try {
                $media = $api->read('media', $mediaId, [], ['responseContent' => 'resource'])->getContent();
            } catch (NotFoundException $e) {
                $logger->err(new Message('[MediaQuality] The media #%d is not found and cannot be processed.', // @translate
                    $mediaId));
                continue;
            }

            if (!$media->hasOriginal()) {
                $logger->warn(new Message('[MediaQuality] The media #%d has no original and cannot be processed.', // @translate
                    $mediaId));
                continue;
            }

            if ($media->getRenderer() !== 'file') {
                $logger->warn(new Message('[MediaQuality] The media #%d is not a  file and cannot be processed.', // @translate
                    $mediaId));
                continue;
            }

            $filename = $media->getFilename();
            $storageId = $media->getStorageId();

            $source = $basePath
                . DIRECTORY_SEPARATOR . 'original'
                . DIRECTORY_SEPARATOR . $filename;
            if (!file_exists($source)) {
                $logger->warn(new Message('[MediaQuality] The file %1$s of the media #%2$d to convert does not exist.', // @translate
                    'original' . DIRECTORY_SEPARATOR . $filename, $media->getId()));
                continue;
            }

            $mediaType = $media->getMediaType();
            if (empty($processors[$mediaType])) {
                $logger->warn(new Message('[MediaQuality] Nothiing to process for media type %1$s (media #%2$d).', // @translate
                    $mediaType, $media->getId()));
                continue;
            }

            foreach ($processors[$mediaType] as $quality => $mediaProcessor) {
                // Note: the filename may contain a "/" (Archive Repertory).
                $dir = $mediaProcessor['dir'];
                $extension = $mediaProcessor['extension'];
                $command = $mediaProcessor['command'];
                $storagePath = $dir
                    . DIRECTORY_SEPARATOR . $storageId
                    . '.' . $extension;
                $destination = $basePath . DIRECTORY_SEPARATOR . $storagePath;
                if (file_exists($destination)) {
                    $logger->warn(new Message('[MediaQuality] The file %1$s is already processed (media #%2$d).', // @translate
                        $storagePath, $media->getId()));
                    continue;
                }

                $tempFile = $tempFileFactory->build();
                $tempPath = sprintf('%s.%s', $tempFile->getTempPath(), $extension);
                $tempFile->delete();

                $mediaCommands[] = [
                    'command' => sprintf($command, escapeshellarg($source), escapeshellarg($tempPath)),
                    'tempPath' => $tempPath,
                    'storagePath' => $storagePath,
                ];
            }
        }

        // Process the commands.
        foreach ($mediaCommands as $key => $mediaCommand) {
            if ($this->shouldStop()) {
                $logger->warn(new Message(
                    '[MediaQuality] The job was stopped: %d/%d commands processed.', // @translate
                    $key, count($mediaCommands)
                ));
                break;
            }

            $command = $mediaCommand['command'];
            $output = $cli->execute($command);
            if ($output === false) {
                $logger->err(new Message('[MediaQuality] The file %1$s of media #%2$d cannot be processed.', // @translate
                    $mediaCommand['storagePath'], $media->getId()));
                continue;
            }

            try {
                $store->put($mediaCommand['tempPath'], $mediaCommand['storagePath']);
            } catch (RuntimeException $e) {
                $logger->err(new Message('[MediaQuality] The file %1$s of media #%2$d cannot be processed: %3$s', // @translate
                    $mediaCommand['storagePath'], $media->getId(), $e->getMessage()));
                continue;
            }
        }
    }

    /**
     * Get the local dir, that is not available in the default config.
     *
     * @return \Omeka\File\Store\Local|null
     */
    protected function getLocalStore()
    {
        $services = $this->getServiceLocator();
        $store = $services->get('Omeka\File\Store');
        return $store instanceof \Omeka\File\Store\Local ? $store : null;
    }
}
