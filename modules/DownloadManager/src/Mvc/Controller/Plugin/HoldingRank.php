<?php
namespace DownloadManager\Mvc\Controller\Plugin;

use Doctrine\DBAL\Connection;
use DownloadManager\Api\Representation\DownloadRepresentation;
use DownloadManager\Entity\Download;
use PDO;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use Zend\Mvc\Controller\PluginManager;

/**
 * Determine the rank of a holding on an item for a user.
 */
class HoldingRank extends AbstractPlugin
{
    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var PluginManager
     */
    protected $plugins;

    /**
     * @param Connection $connection
     * @param PluginManager $plugins
     */
    public function __construct(Connection $connection, PluginManager $plugins)
    {
        $this->connection = $connection;
        $this->plugins = $plugins;
    }

    /**
     * Determine the holding rank of an item for a user.
     *
     * Note: the resource must be checked before.
     * The rank does not depends on the other holding of a user, that may have
     * reach dowload limit.
     *
     * @param DownloadRepresentation $download
     * @return int -1 means available; 0 means not downloadable; else this is
     * the rank, that starts from 1.
     */
    public function __invoke(DownloadRepresentation $download)
    {
        if ($download->isDownloaded()) {
            return -1;
        }

        // TODO Add a check for not downloadable?

        $resource = $download->resource();
        $totalAvailable = $this->plugins->get('totalAvailable');
        $totalAvailable = $totalAvailable($resource);
        if ($totalAvailable !== 0) {
            return -1;
        }

        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select(['COUNT(*)'])
            ->from('download', 'download')
            ->innerJoin('download', 'download', 'download_b', 'download.id >= download_b.id')
            ->where($qb->expr()->eq('download.owner_id', ':owner'))
            ->setParameter('owner', $download->owner()->id())

            ->andWhere($qb->expr()->eq('download.status', ':status'))
            ->setParameter('status', Download::STATUS_HELD)
            ->andWhere($qb->expr()->eq('download.resource_id', ':resource'))
            ->setParameter('resource', $download->resource()->id())

            ->andWhere($qb->expr()->eq('download_b.status', ':status'))
            ->andWhere($qb->expr()->eq('download_b.resource_id', ':resource'))
        ;
        $stmt = $qb->execute();
        $result = $stmt->fetch(PDO::FETCH_COLUMN);
        return $result;
    }
}
