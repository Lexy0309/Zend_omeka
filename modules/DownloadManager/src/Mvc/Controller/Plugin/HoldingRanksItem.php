<?php
namespace DownloadManager\Mvc\Controller\Plugin;

use Doctrine\ORM\EntityManager;
use DownloadManager\Entity\Download;
use Omeka\Api\Representation\ItemRepresentation;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

/**
 * Determine the ranks of a holding on an item for users.
 */
class HoldingRanksItem extends AbstractPlugin
{
    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @param EntityManager $entityManager
     */
    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Determine the holding ranks of an item for users, even if available.
     *
     * @todo Return DownloadRepresentation from HoldingRanksItem.
     *
     * @param ItemRepresentation $item
     * @param int $limit
     * @param int $offset
     * @return Download[]|null Associative array with rank as key and download as
     * value. The rank starts from 1.
     */
    public function __invoke(ItemRepresentation $item, $limit = 0, $offset = 0)
    {
        $downloadRepository = $this->entityManager->getRepository(Download::class);
        $downloads = $downloadRepository->findBy([
            'status' => Download::STATUS_HELD,
            'resource' => $item->id(),
        ], ['created' => 'ASC', 'id' => 'ASC'], $limit, $offset);
        if ($downloads) {
            $downloads = array_combine(range(1, count($downloads)), array_values($downloads));
        }
        return $downloads;
    }
}
