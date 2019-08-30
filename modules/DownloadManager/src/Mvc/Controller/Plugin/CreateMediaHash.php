<?php
namespace DownloadManager\Mvc\Controller\Plugin;

use DateTime;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Entity\User;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

class CreateMediaHash extends AbstractPlugin
{
    /**
     * Helper to get the hash of a media and a user, if any.
     *
     * @todo Convert the hash to a short alphanumeric key. See determineAccessPath.
     *
     * @param MediaRepresentation $media
     * @param User $owner
     * @return string
     */
    public function __invoke(MediaRepresentation $media, User $owner = null)
    {
        $string = $media->id()
            // Prevent missing file hash.
            . ' ' . ($media->sha256() ?: hash('sha256', $media->source() . ' ' . $media->storageId()));

        if ($owner) {
            $string .= ' ' . $owner->getEmail()
                . ' ' . $owner->getId()
                . (new DateTime('now'))->format('Y-m-d H:i:s');
        }

        $hash = hash('sha256', $string);
        return $hash;
    }
}
