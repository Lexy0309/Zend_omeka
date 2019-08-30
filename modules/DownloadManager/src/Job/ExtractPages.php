<?php
namespace DownloadManager\Job;

use DownloadManager\Mvc\Controller\Plugin\DetermineAccessPath;
use FilesystemIterator;
use mikehaertl\pdftk\Pdf;
use Omeka\Job\AbstractJob;

class ExtractPages extends AbstractJob
{
    const SQL_LIMIT = 20;

    public function perform()
    {
        // $group = 'www-data';

        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $logger = $services->get('Omeka\Logger');
        $api = $services->get('Omeka\ApiManager');

        $accessFolder = DIRECTORY_SEPARATOR . DetermineAccessPath::ACCESS_PATH;
        $pagesFolder = DIRECTORY_SEPARATOR . 'p';

        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        // Process only original pages currently.
        $types = ['original' /*, 'median', 'low' */];

        // Check if the base path is writeable to avoid useless process.
        $accessPath = $basePath . $accessFolder;
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
            // The default api doesn't allow to search by media type.
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
                if ($media->mediaType() !== 'application/pdf') {
                    continue;
                }

                $filename = $media->filename();
                $filebase = basename($media->storageId());
                $extension = $media->extension();
                $dotExtension = strlen($extension) ? '.' . $extension : '';
                foreach ($types as $type) {
                    $baseDir = $basePath . '/' . $type;
                    $sourcePath = $baseDir . '/' . $filename;
                    if (!file_exists($sourcePath) || empty(filesize($sourcePath))) {
                        continue;
                    }

                    $numberOfPages = $this->numberOfPages($sourcePath);
                    if (empty($numberOfPages)) {
                        continue;
                    }

                    // Get the burst directory.
                    $dirpath = $accessPath . $pagesFolder
                        . DIRECTORY_SEPARATOR . $media->id()
                        . DIRECTORY_SEPARATOR . $type;
                    if (!file_exists($dirpath)) {
                        $result = mkdir($dirpath, 0775, true);
                        if (!$result) {
                            $logger->err(
                                'Error when preparing the pages folder for media #{media_id} and type "{type}".', // @translate
                                ['media_id' => $media->id(), 'type' => $type]
                            );
                            continue;
                        }
                    } elseif (!is_writeable($dirpath)) {
                        $logger->err(
                            'The pages folder for media #{media_id} and type "{type}" is not writeable.', // @translate
                            ['media_id' => $media->id(), 'type' => $type]
                        );
                        continue;
                    }
                    // Check if the number of pages equals the non-empty files.
                    else {
                        $totalFiles = $this->countFilesInDir($dirpath);
                        if (!empty($totalFiles) && $numberOfPages !== $totalFiles) {
                            $result = $this->removeDir($dirpath, true);
                            if (!$result) {
                                $logger->err(
                                    'Error when removing the pages folder for media #{media_id} and type "{type}".', // @translate
                                    ['media_id' => $media->id(), 'type' => $type]
                                );
                                continue;
                            }
                            $result = mkdir($dirpath, 0775, true);
                            if (!$result) {
                                $logger->err(
                                    'Error when preparing the pages folder for media #{media_id} and type "{type}".', // @translate
                                    ['media_id' => $media->id(), 'type' => $type]
                                );
                                continue;
                            }
                        }
                    }

                    $pdf = new Pdf($sourcePath);
                    $result = $pdf->burst($dirpath . DIRECTORY_SEPARATOR . $filebase . '-%d' . $dotExtension);
                    if (!$result) {
                        $result = $this->removeDir($dirpath, true);
                        $logger->err(
                            'Error when bursting pages for media #{media_id} and type "{type}".', // @translate
                            ['media_id' => $media->id(), 'type' => $type]
                        );
                        continue;
                    }
                }

                $logger->info(
                    'Pages extracted for media #{media_id}.', // @translate
                    ['media_id' => $media->id()]
                );
            }

            $offset += self::SQL_LIMIT;
        }
    }

    /**
     * Get the number of pages of a pdf.
     *
     * @param string $filepath
     * @return int|null
     */
    protected function numberOfPages($filepath)
    {
        $pdf = new Pdf($filepath);
        $data = (string) $pdf->getData();
        if ($data) {
            $regex = '~^NumberOfPages: (\d+)$~m';
            $matches = [];
            preg_match($regex, $data, $matches);
            if ($matches[1]) {
                return $matches[1];
            }
        }
    }

    /**
     * Determines the number of files of a directory.
     *
     * @link https://stackoverflow.com/questions/12801370/count-how-many-files-in-directory-php
     *
     * @param string $dir
     * @return int|null
     */
    protected function countFilesInDir($dir)
    {
        if (empty($dir) || !file_exists($dir) || !is_dir($dir) || !is_readable($dir)) {
            return null;
        }
        $fi = new FilesystemIterator($dir, FilesystemIterator::SKIP_DOTS);
        $total = iterator_count($fi);
        return $total;
    }

    /**
     * Checks and removes a folder, empty or not.
     *
     * @param string $path Full path of the folder to remove.
     * @param bool $evenNonEmpty Remove non empty folder.
     * This parameter can be used with non standard folders.
     * @return boolean.
     */
    protected function removeDir($path, $evenNonEmpty = false)
    {
        $path = realpath($path);
        if (!strlen($path) || $path == '/' || !file_exists($path)) {
            return true;
        }
        if (is_dir($path)
            && is_readable($path)
            && is_writable($path)
            && ($evenNonEmpty || count(array_diff(@scandir($path), ['.', '..'])) == 0)
        ) {
            $result = self::_rrmdir($path);
            return is_null($result) || $result;
        }
        return false;
    }

    /**
     * Removes directories recursively.
     *
     * @param string $dir Directory name.
     * @return bool
     */
    protected function _rrmdir($dir)
    {
        if (!file_exists($dir)
            || !is_dir($dir)
            || !is_readable($dir)
        ) {
            return;
        }
        $scandir = scandir($dir);
        if (!is_array($scandir)) {
            return;
        }
        $files = array_diff($scandir, ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                self::_rrmDir($path);
            } else {
                unlink($path);
            }
        }
        return @rmdir($dir);
    }
}
