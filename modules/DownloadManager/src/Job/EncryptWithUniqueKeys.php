<?php
namespace DownloadManager\Job;

use Omeka\Job\AbstractJob;

class EncryptWithUniqueKeys extends AbstractJob
{
    const SQL_LIMIT = 20;

    public function perform()
    {
        $group = 'www-data';

        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $plugins = $services->get('ControllerPluginManager');
        $logger = $services->get('Omeka\Logger');
        $api = $services->get('Omeka\ApiManager');

        $hasUniqueKeys = $plugins->get('hasUniqueKeys');
        if (!$hasUniqueKeys()) {
            return;
        }

        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $types = ['original', 'median', 'low'];
        $determineAccessPath = $plugins->get('determineAccessPath');
        /** @var \DownloadManager\Mvc\Controller\Plugin\ProtectFile $protectFile */
        $protectFile = $plugins->get('protectFile');

        // Check if the base path is writeable to avoid useless process.
        $accessPath = $basePath
            . DIRECTORY_SEPARATOR . \DownloadManager\Mvc\Controller\Plugin\DetermineAccessPath::ACCESS_PATH;
        if (!is_writeable($accessPath)) {
            $logger->err(
                'The access base path "{access_path}" is not writeable.', // @translate
                ['access_path' => $accessPath]
            );
            return;
        }

        $offset = 0;
        while (true) {
            /** @var \Omeka\Api\Representation\MediaRepresentation[] $medias */
            $medias = $api->search('media', [
                'limit' => self::SQL_LIMIT,
                'offset' => $offset,
            ])->getContent();
            if (empty($medias)) {
                break;
            }

            foreach ($medias as $media) {
                if (!$media->hasOriginal()) {
                    continue;
                }
                $filename = $media->filename();
                foreach ($types as $type) {
                    $baseDir = $basePath . '/' . $type;
                    $sourcePath = $baseDir . '/' . $filename;
                    if (!file_exists($sourcePath) || empty(filesize($sourcePath))) {
                        continue;
                    }

                    $destinationPath = $determineAccessPath($media, $type, null, null, false, $media->filename(), true);
                    if (file_exists($destinationPath)) {
                        if (filesize($destinationPath)) {
                            continue;
                        }
                        unlink($destinationPath);
                    }

                    $result = $protectFile($sourcePath, $media);
                    // An error occurred and  is already logged.
                    if (!is_string($result)) {
                        continue;
                    }

                    $tempPath = $result;

                    $dirPath = dirname($destinationPath);
                    if (!file_exists($dirPath)) {
                        $result = mkdir($dirPath, 0775, true);
                        if (!$result) {
                            $logger->err(
                                'Error when preparing folder "{folder}" for media #{media_id} and type "{type}".', // @translate
                                ['folder' => $dirPath, 'media_id' => $media->id(), 'type' => $type]
                            );
                            continue;
                        }
                    } elseif (!is_writeable($dirPath)) {
                        $logger->err(
                            'The folder "{folder}" for media #{media_id} and type "{type}" is not writeable.', // @translate
                            ['folder' => $dirPath, 'media_id' => $media->id(), 'type' => $type]
                        );
                        continue;
                    }

                    $result = rename($tempPath, $destinationPath);
                    if ($result) {
                        $logger->info(
                            'File {filename} (media #{media_id} is encrypted for type {type}.',
                            ['filename' => basename($filename), 'media_id' => $media->id(), 'type' => $type]
                        );
                    } else {
                        $logger->err(
                            'File {filename} (media #{media_id} was not encrypted for type {type}.',
                            ['filename' => basename($filename), 'media_id' => $media->id(), 'type' => $type]
                        );
                    }

                    @chmod($destinationPath, 0664);
                    @chgrp($destinationPath, $group);
                }
            }

            $offset += self::SQL_LIMIT;
        }
    }
}
