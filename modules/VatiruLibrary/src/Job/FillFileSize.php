<?php
namespace VatiruLibrary\Job;

use Omeka\Job\AbstractJob;

/**
 * Fill all file sizes for the new column of table media.
 *
 * This is a copy of \Omeka\Db\Migrations\FillFileSize to allow to upgrade in
 * the background.
 *
 * @see \Omeka\Db\Migrations\FillFileSize
 */
class FillFileSize extends AbstractJob
{
    public function perform()
    {
        $services = $this->getServiceLocator();
        $conn = $services->get('Omeka\Connection');

        $basePath = $services->get('Config')['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        /** @var \Omeka\Mvc\Controller\Plugin\Logger $logger */
        $logger = $services->get('Omeka\Logger');
        /** @var \Doctrine\ORM\EntityManager $entityManager */
        $entityManager = $services->get('Omeka\EntityManager');
        $mediaRepository = $entityManager->getRepository(\Omeka\Entity\Media::class);

        // Get all media files without size in one array (not a heavy one).
        $stmt = $conn->query('SELECT id FROM media WHERE renderer = "file" AND size IS NULL');
        $mediaIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        // Update filesize.
        $stmt = $conn->prepare('UPDATE media SET size = ? WHERE id = ?');
        foreach ($mediaIds as $id) {
            /** @var \Omeka\Entity\Media $media */
            $media = $mediaRepository->find($id);
            $filepath = $basePath . '/original/' . $media->getFilename();
            if (!file_exists($filepath)) {
                // $logger->err(sprintf('Media #%d: File cannot be found: "%s"', $id, $media->getFilename()));
                continue;
            }
            $filesize = filesize($filepath);
            $stmt->bindValue(1, $filesize);
            $stmt->bindValue(2, $id);
            $stmt->execute();
        }
    }
}
