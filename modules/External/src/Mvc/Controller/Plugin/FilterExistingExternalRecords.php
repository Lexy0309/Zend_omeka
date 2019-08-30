<?php
namespace External\Mvc\Controller\Plugin;

use Doctrine\DBAL\Connection;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

class FilterExistingExternalRecords extends AbstractPlugin
{
    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @param Connection $connection
     * @param ExistingIdentifiers $existingIdentifiers
     */
    public function __construct(Connection $connection, ExistingIdentifiers $existingIdentifiers)
    {
        $this->connection = $connection;
        $this->existingIdentifiers = $existingIdentifiers;
    }

    /**
     * Remove external records that are already cached as Omeka items.
     *
     * @param array $records
     * @param array $identifiers
     * @param array $existingIdentifiers
     * @return array Array of non-existing records, by id.
     */
    public function __invoke(array $records, array $identifiers = null, array $existingIdentifiers = null)
    {
        if (empty($records)) {
            return [];
        }

        if (empty($identifiers)) {
            $identifiers = array_filter(array_map(function ($v) {
                return 'ebsco:'
                    . http_build_query([
                        'dbid' => $v['Header']['DbId'],
                        'an' => $v['Header']['An'],
                    ]);
            }, $records));
            if (empty($identifiers)) {
                return [];
            }
        }

        if (is_null($existingIdentifiers)) {
            $existingIdentifiers = $this->existingIdentifiers;
            $existingIdentifiers = $existingIdentifiers($identifiers);
        }

        $uncachedIdentifiers = array_diff($identifiers, $existingIdentifiers);
        $uncachedRecords = array_intersect_key($records, $uncachedIdentifiers);

        return $uncachedRecords;
    }
}
