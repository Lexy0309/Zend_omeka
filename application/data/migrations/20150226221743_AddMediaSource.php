<?php
namespace Omeka\Db\Migrations;

use Doctrine\DBAL\Connection;
use Omeka\Db\Migration\MigrationInterface;

class AddMediaSource implements MigrationInterface
{
    public function up(Connection $conn)
    {
        $conn->query("ALTER TABLE media ADD source LONGTEXT DEFAULT NULL");
    }
}
