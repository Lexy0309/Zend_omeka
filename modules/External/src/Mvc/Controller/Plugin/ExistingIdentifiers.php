<?php
namespace External\Mvc\Controller\Plugin;

use Doctrine\DBAL\Connection;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

class ExistingIdentifiers extends AbstractPlugin
{
    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Get existing identifiers from a list of identifiers.
     *
     * @param array $identifiers
     * @return array Array of existing identifiers, by id.
     */
    public function __invoke(array $identifiers)
    {
        if (empty($identifiers)) {
            return [];
        }

        $connection = $this->connection;

        // TODO Set the "IN" values as sql parameters to improve sql queries.
        $sqlIdentifiers = implode(',', array_map([$connection, 'quote'], $identifiers));

        // Use a direct query for quick check.
        // TODO Improve the query to check cached items (use entity saved query or dql).
        // Property 10 = dcterms:identifier.
        // The authentication is not checked.
        $sql = <<<SQL
SELECT value.resource_id, value.value
FROM value
WHERE value.property_id = 10
AND value.type = "literal"
AND value.value IN ($sqlIdentifiers)
;
SQL;

        $stmt = $connection->query($sql);
        $result = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        return $result ?: [];
    }
}

