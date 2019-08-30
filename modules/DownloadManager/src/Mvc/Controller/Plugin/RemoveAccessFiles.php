<?php
namespace DownloadManager\Mvc\Controller\Plugin;

use Omeka\Entity\User;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use Omeka\Api\Representation\MediaRepresentation;

class RemoveAccessFiles extends AbstractPlugin
{
    /**
     * @var string
     */
    protected $basePath;

    /**
     * @param string $basePath
     */
    public function __construct($basePath)
    {
        $this->basePath = $basePath;
    }

    /**
     * Remove all derivatives files for an access user media.
     *
     * @param User $user
     * @param MediaRepresentation $media
     * @return bool
     */
    public function __invoke(User $user = null, MediaRepresentation $media = null)
    {
        $path = $this->basePath
            . DIRECTORY_SEPARATOR . 'access'
            . DIRECTORY_SEPARATOR . ($user ? $user->getId() : '0')
            . DIRECTORY_SEPARATOR . ($media ? $media->id() : '0');
        return $this->removeDir($path, true);
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
            $result = $this->_rrmdir($path);
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
                $this->_rrmDir($path);
            } else {
                unlink($path);
            }
        }
        return @rmdir($dir);
    }
}
