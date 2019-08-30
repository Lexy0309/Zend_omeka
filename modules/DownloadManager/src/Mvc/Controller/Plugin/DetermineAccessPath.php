<?php
namespace DownloadManager\Mvc\Controller\Plugin;

use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Entity\User;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

class DetermineAccessPath extends AbstractPlugin
{
    const ACCESS_PATH = 'access';

    /**
     * @var string
     */
    protected $baseHash;

    /**
     * @var UseUniqueKeys
     */
    protected $useUniqueKeys;

    /**
     * @var BaseConvertArbitrary
     */
    protected $baseConvertArbitrary;

    /**
     * @var string
     */
    protected $basePath;

    /**
     * @param string $baseHash
     * @param UseUniqueKeys $useUniqueKeys
     * @param BaseConvertArbitrary $baseConvertArbitrary
     * @param string $basePath
     */
    public function __construct(
        $baseHash,
        UseUniqueKeys $useUniqueKeys,
        BaseConvertArbitrary $baseConvertArbitrary,
        $basePath
    ) {
        $this->baseHash = $baseHash;
        $this->useUniqueKeys = $useUniqueKeys;
        $this->baseConvertArbitrary = $baseConvertArbitrary;
        $this->basePath = $basePath;
    }

    /**
     * Return the relative path to a protected file, without any check.
     *
     * @param MediaRepresentation $media
     * @param string $type
     * @param User $user
     * @param string $pages
     * @param bool $isRawFile
     * @param string $filename
     * @param bool $absolute
     * @return string
     */
    public function __invoke(
        MediaRepresentation $media,
        $type = 'original',
        User $user = null,
        $pages = null,
        $isRawFile = false,
        $filename = null,
        $absolute = false
    ) {
        if (is_null($filename)) {
            $filename = $media->filename();
        }

        $useUniqueKeys = $this->useUniqueKeys;
        $useUniqueKeys = empty($user) || $useUniqueKeys($user);

        // Unique path because the basename may be not unique and avoid big dir.
        $userPath = $isRawFile
            ? '0'
            : ($useUniqueKeys ? 'u' : $user->getId());

        // Check if the resulting file exists to send it early.
        $tempBasePath = self::ACCESS_PATH
            . DIRECTORY_SEPARATOR . $userPath
            . DIRECTORY_SEPARATOR . $media->id()
            . DIRECTORY_SEPARATOR . $type
            . DIRECTORY_SEPARATOR;
        $suffix = $pages ? '-' . $pages : '';
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        // The hash is required to avoid direct access to the file from anybody,
        // in particular when it's uncrypted (it may be used in admin board).
        // The hash should be different for each type too to avoid to get the
        // name of other types.
        $toHash = $this->baseHash
            . '/' . $tempBasePath
            . '/' . pathinfo($filename, PATHINFO_FILENAME)
            . '/' . $type;

        $path = ($absolute ? ($this->basePath . DIRECTORY_SEPARATOR) : '')
            . $tempBasePath
            . $this->convertHexaTo62(sha1($toHash))
            . $suffix
            . ($extension ? '.' . $extension : '');

        return $path;
    }

    protected function convertHexaTo62($number)
    {
        $baseConvertArbitrary = $this->baseConvertArbitrary;
        $result = $baseConvertArbitrary($number, 16, 62);
        return str_pad($result, 27, '0', STR_PAD_LEFT);
    }
}
